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

// Validate parent comment ID (either 'parent_id' or 'comment_id')
$parent_id = null;
if (!empty($data['parent_id']) && is_numeric($data['parent_id'])) {
    $parent_id = intval($data['parent_id']);
} elseif (!empty($data['comment_id']) && is_numeric($data['comment_id'])) {
    $parent_id = intval($data['comment_id']);
}

if (!$parent_id) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Valid 'parent_id' or 'comment_id' is required to post a reply!"
    ]);
    exit();
}

// Validate reply text
$comment_text = trim($data['comment_text'] ?? $data['comment'] ?? $data['reply_text'] ?? $data['reply'] ?? '');
if (empty($comment_text)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Reply text cannot be empty!"]);
    exit();
}

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

// Verify parent comment exists in trip_comment
$parent_query = "SELECT id, trip_id, username AS parent_author FROM trip_comment WHERE id = $parent_id LIMIT 1";
$parent_result = mysqli_query($conn, $parent_query);

if (!$parent_result || mysqli_num_rows($parent_result) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Parent comment with ID $parent_id not found!"]);
    exit();
}

$parent = mysqli_fetch_assoc($parent_result);
$trip_id = intval($parent['trip_id']);

// Insert reply into trip_comment with parent_id set to parent comment's id
$insert_query = "INSERT INTO trip_comment (trip_id, username, parent_id, comment_text) 
                 VALUES ($trip_id, '$escaped_username', $parent_id, '$escaped_comment')";

if (mysqli_query($conn, $insert_query)) {
    $reply_id = mysqli_insert_id($conn);
    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Reply posted successfully!",
        "data" => [
            "id" => $reply_id,
            "trip_id" => $trip_id,
            "parent_id" => $parent_id,
            "parent_author" => $parent['parent_author'],
            "username" => $username,
            "full_name" => $user['full_name'],
            "profile_pic" => $user['profile_pic'],
            "profile_pic_url" => !empty($user['profile_pic']) ? "uploads/" . $user['profile_pic'] : null,
            "comment_text" => $comment_text,
            "created_at" => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to post reply: " . mysqli_error($conn)]);
}
?>
