<?php
// Ensure POST or DELETE method is used
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

// Authenticate user: prefer Bearer token, fallback to 'username' from body/params
$username = '';
$token = getBearerToken();

if ($token) {
    $payload = verifyJWT($token);
    if (!$payload || empty($payload['username'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized: Invalid or expired token!"]);
        exit();
    }
    $username = $payload['username'];
} elseif (!empty($data['username'])) {
    $username = trim($data['username']);
} else {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized: Please provide Authorization Bearer token or 'username'."
    ]);
    exit();
}

// Support unbookmarking via 'trip_id' OR 'bookmark_id'
$trip_id = !empty($data['trip_id']) && is_numeric($data['trip_id']) ? intval($data['trip_id']) : null;
$bookmark_id = !empty($data['bookmark_id']) && is_numeric($data['bookmark_id']) ? intval($data['bookmark_id']) : null;

if (!$trip_id && !$bookmark_id) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Either 'trip_id' or 'bookmark_id' is required to remove bookmark!"
    ]);
    exit();
}

$escaped_username = mysqli_real_escape_string($conn, $username);

// Build deletion condition
if ($bookmark_id) {
    $where = "id = $bookmark_id AND username = '$escaped_username'";
} else {
    $where = "trip_id = $trip_id AND username = '$escaped_username'";
}

// Check if bookmark exists before deleting
$check_query = "SELECT id, trip_id FROM bookmark WHERE $where LIMIT 1";
$check_result = mysqli_query($conn, $check_query);

if (!$check_result || mysqli_num_rows($check_result) === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Bookmark not found or already removed!"
    ]);
    exit();
}

$existing = mysqli_fetch_assoc($check_result);
$del_trip_id = intval($existing['trip_id']);
$del_id = intval($existing['id']);

// Delete bookmark
$delete_query = "DELETE FROM bookmark WHERE id = $del_id";

if (mysqli_query($conn, $delete_query)) {
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Bookmark removed successfully!",
        "data" => [
            "bookmark_id" => $del_id,
            "trip_id" => $del_trip_id,
            "username" => $username
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to remove bookmark: " . mysqli_error($conn)]);
}
?>
