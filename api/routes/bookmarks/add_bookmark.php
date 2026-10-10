<?php
// Ensure only POST method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use POST method."]);
    exit();
}

// Read input data (JSON payload or $_POST)
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);
if (!$data) {
    $data = $_POST;
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid or empty request data!"]);
    exit();
}

// Authenticate user: prefer Bearer token, fallback to 'username' from body for testing
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

// Validate trip_id
if (empty($data['trip_id']) || !is_numeric($data['trip_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid 'trip_id' is required!"]);
    exit();
}

$trip_id = intval($data['trip_id']);
$escaped_username = mysqli_real_escape_string($conn, $username);

// Check if trip exists
$trip_check = mysqli_query($conn, "SELECT id, title FROM trip_info WHERE id = $trip_id LIMIT 1");
if (!$trip_check || mysqli_num_rows($trip_check) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Trip with ID $trip_id not found!"]);
    exit();
}
$trip = mysqli_fetch_assoc($trip_check);

// Check if user already bookmarked this trip
$check_query = "SELECT id FROM bookmark WHERE username = '$escaped_username' AND trip_id = $trip_id LIMIT 1";
$check_result = mysqli_query($conn, $check_query);

if ($check_result && mysqli_num_rows($check_result) > 0) {
    http_response_code(409);
    echo json_encode([
        "status" => "error", 
        "message" => "You have already bookmarked this trip!"
    ]);
    exit();
}

// Insert into bookmark table
$insert_query = "INSERT INTO bookmark (username, trip_id) VALUES ('$escaped_username', $trip_id)";

if (mysqli_query($conn, $insert_query)) {
    $bookmark_id = mysqli_insert_id($conn);
    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Trip bookmarked successfully!",
        "data" => [
            "id" => $bookmark_id,
            "username" => $username,
            "trip_id" => $trip_id,
            "trip_title" => $trip['title'],
            "created_at" => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to bookmark trip: " . mysqli_error($conn)]);
}
?>
