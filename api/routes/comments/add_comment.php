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

// Validate comment text
$comment_text = trim($data['comment_text'] ?? $data['comment'] ?? '');
if (empty($comment_text)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Comment text cannot be empty!"]);
    exit();
}

$trip_id = intval($data['trip_id']);
$escaped_username = mysqli_real_escape_string($conn, $username);
$escaped_comment = mysqli_real_escape_string($conn, $comment_text);

// Verify user exists in user_info
$user_check = mysqli_query($conn, "SELECT username, full_name, profile_pic FROM user_info WHERE username = '$escaped_username' LIMIT 1");
if (!$user_check || mysqli_num_rows($user_check) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "User '$username' not found!"]);
    exit();
}
$user = mysqli_fetch_assoc($user_check);

// Verify trip exists in trip_info
$trip_check = mysqli_query($conn, "SELECT id, title FROM trip_info WHERE id = $trip_id LIMIT 1");
if (!$trip_check || mysqli_num_rows($trip_check) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Trip with ID $trip_id not found!"]);
    exit();
}

// Top-level comments always have parent_id = NULL
$insert_query = "INSERT INTO trip_comment (trip_id, username, parent_id, comment_text) 
                 VALUES ($trip_id, '$escaped_username', NULL, '$escaped_comment')";

if (mysqli_query($conn, $insert_query)) {
    $comment_id = mysqli_insert_id($conn);
    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Comment posted successfully!",
        "data" => [
            "id" => $comment_id,
            "trip_id" => $trip_id,
            "username" => $username,
            "full_name" => $user['full_name'],
            "profile_pic" => $user['profile_pic'],
            "profile_pic_url" => !empty($user['profile_pic']) ? "uploads/" . $user['profile_pic'] : null,
            "parent_id" => null,
            "comment_text" => $comment_text,
            "created_at" => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to post comment: " . mysqli_error($conn)]);
}
?>
