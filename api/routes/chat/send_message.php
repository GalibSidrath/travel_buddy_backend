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

// Authenticate user: prefer Bearer token, fallback to 'username' from body
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

// Validate room_id
if (empty($data['room_id']) || !is_numeric($data['room_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid 'room_id' is required!"]);
    exit();
}

// Validate message_text
$message_text = trim($data['message_text'] ?? $data['message'] ?? '');
if (empty($message_text)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Message text cannot be empty!"]);
    exit();
}

$room_id = intval($data['room_id']);
$escaped_username = mysqli_real_escape_string($conn, $username);
$escaped_text = mysqli_real_escape_string($conn, $message_text);

// Verify that the user is a participant of this chat room
$room_check = "SELECT id, trip_id, username, host_username 
               FROM chat_room 
               WHERE id = $room_id 
                 AND (LOWER(username) = LOWER('$escaped_username') OR LOWER(host_username) = LOWER('$escaped_username')) 
               LIMIT 1";

$room_result = mysqli_query($conn, $room_check);

if (!$room_result || mysqli_num_rows($room_result) === 0) {
    http_response_code(403);
    echo json_encode([
        "status" => "error", 
        "message" => "Forbidden: You are not a participant of this chat room!"
    ]);
    exit();
}

// Insert message with seen_status = 0 (Unseen by default)
$insert_query = "INSERT INTO message (room_id, sender_username, message_text, seen_status) 
                 VALUES ($room_id, '$escaped_username', '$escaped_text', 0)";

if (mysqli_query($conn, $insert_query)) {
    $message_id = mysqli_insert_id($conn);

    // Fetch sender profile pic
    $user_q = mysqli_query($conn, "SELECT full_name, profile_pic FROM user_info WHERE username = '$escaped_username' LIMIT 1");
    $u_data = $user_q ? mysqli_fetch_assoc($user_q) : null;

    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Message sent successfully!",
        "data" => [
            "id" => $message_id,
            "room_id" => $room_id,
            "sender_username" => $username,
            "sender_name" => $u_data['full_name'] ?? $username,
            "sender_profile_pic_url" => !empty($u_data['profile_pic']) ? "uploads/" . $u_data['profile_pic'] : null,
            "message_text" => $message_text,
            "seen_status" => 0,
            "created_at" => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to send message: " . mysqli_error($conn)]);
}
?>
