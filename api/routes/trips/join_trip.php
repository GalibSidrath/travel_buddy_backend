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

// Authenticate user: prefer Bearer token, fallback to 'username' from body for manual testing
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

// Verify user exists in user_info
$user_check = mysqli_query($conn, "SELECT username FROM user_info WHERE username = '$escaped_username' LIMIT 1");
if (!$user_check || mysqli_num_rows($user_check) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "User '$username' not found in database!"]);
    exit();
}

// Verify trip exists in trip_info
$trip_query = mysqli_query($conn, "SELECT id, title, host_username, available_seats FROM trip_info WHERE id = $trip_id LIMIT 1");
if (!$trip_query || mysqli_num_rows($trip_query) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Trip with ID $trip_id not found!"]);
    exit();
}

$trip = mysqli_fetch_assoc($trip_query);

// Check 1: Host cannot join their own trip
if (strtolower($trip['host_username']) === strtolower($username)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "You cannot send a join request to your own trip!"]);
    exit();
}

// Check 2: Check available seats
if (intval($trip['available_seats']) <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Sorry, no available seats left for this trip!"]);
    exit();
}

// Check 3: Check if already confirmed in confirm_trip table
$confirmed_check = mysqli_query($conn, "SELECT id FROM confirm_trip WHERE trip_id = $trip_id AND username = '$escaped_username' LIMIT 1");
if ($confirmed_check && mysqli_num_rows($confirmed_check) > 0) {
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "You are already a confirmed member of this trip!"]);
    exit();
}

// Check 4: Check if request already exists in trip_request
$req_check = mysqli_query($conn, "SELECT id, status FROM trip_request WHERE trip_id = $trip_id AND username = '$escaped_username' LIMIT 1");
if ($req_check && mysqli_num_rows($req_check) > 0) {
    $existing = mysqli_fetch_assoc($req_check);
    $status = $existing['status'];

    if ($status === 'pending') {
        http_response_code(409);
        echo json_encode([
            "status" => "error", 
            "message" => "You have already sent a join request for this trip. Please wait for host approval."
        ]);
        exit();
    } elseif ($status === 'accepted') {
        http_response_code(409);
        echo json_encode([
            "status" => "error", 
            "message" => "Your join request has already been accepted!"
        ]);
        exit();
    } elseif ($status === 'rejected') {
        // Allow re-submitting request if previously rejected
        $update_query = "UPDATE trip_request SET status = 'pending', created_at = NOW() WHERE id = " . $existing['id'];
        if (mysqli_query($conn, $update_query)) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Your join request has been re-submitted to the host!",
                "data" => [
                    "id" => intval($existing['id']),
                    "trip_id" => $trip_id,
                    "trip_title" => $trip['title'],
                    "username" => $username,
                    "status" => "pending"
                ]
            ]);
            exit();
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to update request: " . mysqli_error($conn)]);
            exit();
        }
    }
}

// Insert new request
$insert_query = "INSERT INTO trip_request (trip_id, username, status) VALUES ($trip_id, '$escaped_username', 'pending')";

if (mysqli_query($conn, $insert_query)) {
    $request_id = mysqli_insert_id($conn);
    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Join request sent successfully! Waiting for host confirmation.",
        "data" => [
            "id" => $request_id,
            "trip_id" => $trip_id,
            "trip_title" => $trip['title'],
            "username" => $username,
            "status" => "pending"
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to submit join request: " . mysqli_error($conn)]);
}
?>
