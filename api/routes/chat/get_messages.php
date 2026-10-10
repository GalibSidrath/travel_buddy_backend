<?php
// Ensure only GET method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use GET method."]);
    exit();
}

// Authenticate user: prefer Bearer token, fallback to 'username' from query param
$username = '';
$token = getBearerToken();

if ($token) {
    $payload = verifyJWT($token);
    if ($payload && !empty($payload['username'])) {
        $username = $payload['username'];
    }
}

if (empty($username) && !empty($_GET['username'])) {
    $username = trim($_GET['username']);
}

if (empty($username)) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized: Please provide Authorization Bearer token or 'username' query parameter."
    ]);
    exit();
}

// Validate room_id
if (empty($_GET['room_id']) || !is_numeric($_GET['room_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid 'room_id' is required!"]);
    exit();
}

$room_id = intval($_GET['room_id']);
$escaped_username = mysqli_real_escape_string($conn, $username);

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

$room_info = mysqli_fetch_assoc($room_result);

// Automatic Seen Update: Mark all unread messages sent by the OTHER participant as SEEN (1)
$mark_seen_query = "UPDATE message 
                    SET seen_status = 1 
                    WHERE room_id = $room_id 
                      AND LOWER(sender_username) != LOWER('$escaped_username') 
                      AND seen_status = 0";
mysqli_query($conn, $mark_seen_query);

// Check if smart polling filter (last_id or after_id) is provided
$filter_after = "";
if (!empty($_GET['last_id']) && is_numeric($_GET['last_id'])) {
    $last_id = intval($_GET['last_id']);
    $filter_after = " AND m.id > $last_id";
} elseif (!empty($_GET['after_id']) && is_numeric($_GET['after_id'])) {
    $after_id = intval($_GET['after_id']);
    $filter_after = " AND m.id > $after_id";
}

// Fetch messages ordered chronologically (id ASC)
$query = "SELECT 
            m.id, m.room_id, m.sender_username, m.message_text, m.seen_status, m.created_at,
            u.full_name AS sender_name, u.profile_pic AS sender_profile_pic
          FROM message m
          LEFT JOIN user_info u ON m.sender_username = u.username
          WHERE m.room_id = $room_id $filter_after
          ORDER BY m.id ASC";

$result = mysqli_query($conn, $query);

if (!$result) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to fetch messages: " . mysqli_error($conn)]);
    exit();
}

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = [
        "id" => intval($row['id']),
        "room_id" => intval($row['room_id']),
        "sender_username" => $row['sender_username'],
        "sender_name" => $row['sender_name'] ?? $row['sender_username'],
        "sender_profile_pic_url" => !empty($row['sender_profile_pic']) ? "uploads/" . $row['sender_profile_pic'] : null,
        "message_text" => $row['message_text'],
        "seen_status" => intval($row['seen_status']),
        "is_my_message" => (strtolower($row['sender_username']) === strtolower($username)),
        "created_at" => $row['created_at']
    ];
}

http_response_code(200);
echo json_encode([
    "status" => "success",
    "room_id" => $room_id,
    "count" => count($messages),
    "data" => $messages
]);
?>
