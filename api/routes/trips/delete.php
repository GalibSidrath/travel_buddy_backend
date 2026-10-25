<?php
// Ensure only POST or DELETE method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use POST or DELETE method."]);
    exit();
}

// Read input data (JSON payload, query params, or $_POST)
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);
if (!$data) {
    $data = !empty($_POST) ? $_POST : $_GET;
}

// Authenticate user: prefer Bearer token, fallback to 'host_username' or 'username' from body/params
$auth_username = '';
$token = getBearerToken();

if ($token) {
    $payload = verifyJWT($token);
    if (!$payload || empty($payload['username'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized: Invalid or expired token!"]);
        exit();
    }
    $auth_username = $payload['username'];
} elseif (!empty($data['host_username'])) {
    $auth_username = trim($data['host_username']);
} elseif (!empty($data['username'])) {
    $auth_username = trim($data['username']);
} else {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized: Please provide Authorization Bearer token or 'host_username'."
    ]);
    exit();
}

// Validate trip identification (support 'trip_id' or 'id')
$trip_id = null;
if (!empty($data['trip_id']) && is_numeric($data['trip_id'])) {
    $trip_id = intval($data['trip_id']);
} elseif (!empty($data['id']) && is_numeric($data['id'])) {
    $trip_id = intval($data['id']);
} elseif (!empty($_GET['trip_id']) && is_numeric($_GET['trip_id'])) {
    $trip_id = intval($_GET['trip_id']);
} elseif (!empty($_GET['id']) && is_numeric($_GET['id'])) {
    $trip_id = intval($_GET['id']);
}

if (!$trip_id || $trip_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Valid 'trip_id' or 'id' is required to delete a trip!"
    ]);
    exit();
}

// Fetch trip from database
$fetch_query = "SELECT id, title, host_username, cover_picture FROM trip_info WHERE id = $trip_id LIMIT 1";
$fetch_result = mysqli_query($conn, $fetch_query);

if (!$fetch_result || mysqli_num_rows($fetch_result) === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Trip with ID $trip_id not found or already deleted!"
    ]);
    exit();
}

$trip = mysqli_fetch_assoc($fetch_result);
$host_username = $trip['host_username'];
$cover_picture = $trip['cover_picture'];

// Authorization: only the trip host can delete their trip
if (strtolower($host_username) !== strtolower($auth_username)) {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Forbidden: You are not authorized to delete this trip. Only the trip host ('$host_username') can delete it!"
    ]);
    exit();
}

// Perform deletion with database transaction
mysqli_begin_transaction($conn);

try {
    // Explicitly delete dependent records to ensure complete cleanup across MySQL storage engines
    mysqli_query($conn, "DELETE FROM bookmark WHERE trip_id = $trip_id");
    mysqli_query($conn, "DELETE FROM trip_request WHERE trip_id = $trip_id");
    mysqli_query($conn, "DELETE FROM confirm_trip WHERE trip_id = $trip_id");
    mysqli_query($conn, "DELETE FROM trip_comment WHERE trip_id = $trip_id");
    mysqli_query($conn, "DELETE FROM message WHERE room_id IN (SELECT id FROM chat_room WHERE trip_id = $trip_id)");
    mysqli_query($conn, "DELETE FROM chat_room WHERE trip_id = $trip_id");

    // Delete trip from trip_info
    $del_query = "DELETE FROM trip_info WHERE id = $trip_id";
    if (!mysqli_query($conn, $del_query)) {
        throw new Exception("Failed to delete trip record: " . mysqli_error($conn));
    }

    mysqli_commit($conn);

    // Delete cover photo file from disk if present
    if (!empty($cover_picture)) {
        $cover_path = __DIR__ . '/../../uploads/' . $cover_picture;
        if (file_exists($cover_path) && is_file($cover_path)) {
            unlink($cover_path);
        }
    }

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Trip deleted successfully!",
        "data" => [
            "trip_id" => $trip_id,
            "title" => $trip['title'],
            "host_username" => $host_username
        ]
    ]);
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete trip: " . $e->getMessage()
    ]);
    exit();
}
?>
