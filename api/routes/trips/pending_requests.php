<?php
// Ensure only GET method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use GET method."]);
    exit();
}

// Authenticate host user: prefer Bearer token, fallback to 'username' from query param
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

// Optional filter: for a specific trip_id
$trip_filter = "";
if (!empty($_GET['trip_id']) && is_numeric($_GET['trip_id'])) {
    $t_id = intval($_GET['trip_id']);
    $trip_filter = " AND r.trip_id = $t_id";
}

// Fetch all pending requests for trips hosted by this user
$query = "SELECT 
            r.id AS request_id, r.trip_id, r.username AS requester_username, 
            r.status, r.created_at AS requested_at,
            t.title AS trip_title, t.destination, t.available_seats,
            u.full_name AS requester_full_name, u.profile_pic AS requester_profile_pic
          FROM trip_request r
          INNER JOIN trip_info t ON r.trip_id = t.id
          LEFT JOIN user_info u ON r.username = u.username
          WHERE LOWER(t.host_username) = LOWER('$escaped_username') 
            AND r.status = 'pending' $trip_filter
          ORDER BY r.id DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to fetch pending requests: " . mysqli_error($conn)]);
    exit();
}

$requests = [];
while ($row = mysqli_fetch_assoc($result)) {
    $requests[] = [
        "request_id" => intval($row['request_id']),
        "trip_id" => intval($row['trip_id']),
        "trip_title" => $row['trip_title'],
        "destination" => $row['destination'],
        "available_seats" => intval($row['available_seats']),
        "requester_username" => $row['requester_username'],
        "requester_full_name" => $row['requester_full_name'] ?? $row['requester_username'],
        "requester_profile_pic_url" => !empty($row['requester_profile_pic']) ? "uploads/" . $row['requester_profile_pic'] : null,
        "status" => $row['status'],
        "requested_at" => $row['requested_at']
    ];
}

http_response_code(200);
echo json_encode([
    "status" => "success",
    "host_username" => $username,
    "count" => count($requests),
    "data" => $requests
]);
?>
