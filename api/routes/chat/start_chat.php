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

// Validate trip_id
if (empty($data['trip_id']) || !is_numeric($data['trip_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid 'trip_id' is required!"]);
    exit();
}

$trip_id = intval($data['trip_id']);
$escaped_username = mysqli_real_escape_string($conn, $username);

// Fetch trip details to determine the host
$trip_query = "SELECT id, title, host_username FROM trip_info WHERE id = $trip_id LIMIT 1";
$trip_res = mysqli_query($conn, $trip_query);

if (!$trip_res || mysqli_num_rows($trip_res) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Trip with ID $trip_id not found!"]);
    exit();
}

$trip = mysqli_fetch_assoc($trip_res);
$host_username = $trip['host_username'];

// Determine participants
$participant_user = $username;
$target_host = $host_username;

if (strtolower($participant_user) === strtolower($target_host)) {
    // If the logged in user is the host, they must specify which traveler they want to chat with
    if (empty($data['partner_username']) && empty($data['traveler_username'])) {
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "As the host of this trip, please provide 'partner_username' to start a chat with a specific traveler."
        ]);
        exit();
    }
    $participant_user = trim($data['partner_username'] ?? $data['traveler_username']);
}

$escaped_participant = mysqli_real_escape_string($conn, $participant_user);
$escaped_target_host = mysqli_real_escape_string($conn, $target_host);

if (strtolower($escaped_participant) === strtolower($escaped_target_host)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "You cannot start a chat room with yourself!"]);
    exit();
}

// Check if a chat room already exists for this trip and pair
$check_room = "SELECT id, trip_id, username, host_username, created_at 
               FROM chat_room 
               WHERE trip_id = $trip_id 
                 AND ((username = '$escaped_participant' AND host_username = '$escaped_target_host') 
                   OR (username = '$escaped_target_host' AND host_username = '$escaped_participant')) 
               LIMIT 1";

$room_res = mysqli_query($conn, $check_room);

if ($room_res && mysqli_num_rows($room_res) > 0) {
    $existing_room = mysqli_fetch_assoc($room_res);
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Chat room already exists.",
        "is_new" => false,
        "data" => [
            "room_id" => intval($existing_room['id']),
            "trip_id" => intval($existing_room['trip_id']),
            "trip_title" => $trip['title'],
            "username" => $existing_room['username'],
            "host_username" => $existing_room['host_username'],
            "created_at" => $existing_room['created_at']
        ]
    ]);
    exit();
}

// Create new chat room
$insert_query = "INSERT INTO chat_room (trip_id, username, host_username) 
                 VALUES ($trip_id, '$escaped_participant', '$escaped_target_host')";

if (mysqli_query($conn, $insert_query)) {
    $new_room_id = mysqli_insert_id($conn);
    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Chat room created successfully!",
        "is_new" => true,
        "data" => [
            "room_id" => $new_room_id,
            "trip_id" => $trip_id,
            "trip_title" => $trip['title'],
            "username" => $participant_user,
            "host_username" => $target_host,
            "created_at" => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to create chat room: " . mysqli_error($conn)]);
}
?>
