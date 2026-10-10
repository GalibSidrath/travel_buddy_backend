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

$escaped_username = mysqli_real_escape_string($conn, $username);

// Query all rooms where the user is either the traveler (username) or the host (host_username)
$query = "SELECT 
            r.id AS room_id, r.trip_id, r.username, r.host_username, r.created_at AS room_created_at,
            t.title AS trip_title, t.destination AS trip_destination, t.cover_picture AS trip_cover_picture
          FROM chat_room r
          INNER JOIN trip_info t ON r.trip_id = t.id
          WHERE LOWER(r.username) = LOWER('$escaped_username') 
             OR LOWER(r.host_username) = LOWER('$escaped_username')";

$result = mysqli_query($conn, $query);

if (!$result) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to fetch chat rooms: " . mysqli_error($conn)]);
    exit();
}

$rooms = [];
while ($row = mysqli_fetch_assoc($result)) {
    $room_id = intval($row['room_id']);

    // Identify the chat partner (the other person)
    $partner_username = (strtolower($row['username']) === strtolower($username)) ? $row['host_username'] : $row['username'];
    $escaped_partner = mysqli_real_escape_string($conn, $partner_username);

    // Fetch partner details from user_info
    $partner_q = mysqli_query($conn, "SELECT full_name, profile_pic FROM user_info WHERE username = '$escaped_partner' LIMIT 1");
    $partner_data = $partner_q ? mysqli_fetch_assoc($partner_q) : null;

    // Fetch latest message in this room
    $latest_msg_q = mysqli_query($conn, "SELECT id, sender_username, message_text, seen_status, created_at 
                                          FROM message 
                                          WHERE room_id = $room_id 
                                          ORDER BY id DESC 
                                          LIMIT 1");
    $latest_msg = ($latest_msg_q && mysqli_num_rows($latest_msg_q) > 0) ? mysqli_fetch_assoc($latest_msg_q) : null;

    // Count unseen messages in this room sent by the partner
    $unseen_q = mysqli_query($conn, "SELECT COUNT(*) AS unseen_count 
                                     FROM message 
                                     WHERE room_id = $room_id 
                                       AND LOWER(sender_username) = LOWER('$escaped_partner') 
                                       AND seen_status = 0");
    $unseen_count = $unseen_q ? intval(mysqli_fetch_assoc($unseen_q)['unseen_count']) : 0;

    $rooms[] = [
        "room_id" => $room_id,
        "trip_id" => intval($row['trip_id']),
        "trip_title" => $row['trip_title'],
        "trip_destination" => $row['trip_destination'],
        "trip_cover_picture_url" => !empty($row['trip_cover_picture']) ? "uploads/" . $row['trip_cover_picture'] : null,
        "partner" => [
            "username" => $partner_username,
            "full_name" => $partner_data['full_name'] ?? $partner_username,
            "profile_pic" => $partner_data['profile_pic'] ?? null,
            "profile_pic_url" => !empty($partner_data['profile_pic']) ? "uploads/" . $partner_data['profile_pic'] : null
        ],
        "latest_message" => $latest_msg ? [
            "id" => intval($latest_msg['id']),
            "message_text" => $latest_msg['message_text'],
            "sender_username" => $latest_msg['sender_username'],
            "is_my_message" => (strtolower($latest_msg['sender_username']) === strtolower($username)),
            "seen_status" => intval($latest_msg['seen_status']),
            "created_at" => $latest_msg['created_at']
        ] : null,
        "unseen_count" => $unseen_count,
        "has_unseen" => ($unseen_count > 0),
        "last_activity" => $latest_msg ? $latest_msg['created_at'] : $row['room_created_at']
    ];
}

// Sort rooms by last_activity DESC (latest conversation at the very top)
usort($rooms, function($a, $b) {
    return strtotime($b['last_activity']) - strtotime($a['last_activity']);
});

http_response_code(200);
echo json_encode([
    "status" => "success",
    "username" => $username,
    "total_rooms" => count($rooms),
    "data" => $rooms
]);
?>
