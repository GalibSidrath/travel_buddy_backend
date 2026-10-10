<?php
// Ensure only GET method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use GET method."]);
    exit();
}

// Determine target username: from query parameter, or from authenticated JWT token
$username = '';

if (!empty($_GET['username'])) {
    $username = trim($_GET['username']);
} else {
    $token = getBearerToken();
    if ($token) {
        $payload = verifyJWT($token);
        if ($payload && !empty($payload['username'])) {
            $username = $payload['username'];
        }
    }
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

// Fetch user profile info
$user_query = "SELECT username, full_name, email, gender, country, dob, profile_pic, nid_pic 
               FROM user_info 
               WHERE username = '$escaped_username' 
               LIMIT 1";

$user_result = mysqli_query($conn, $user_query);

if (!$user_result || mysqli_num_rows($user_result) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "User '$username' not found!"]);
    exit();
}

$user = mysqli_fetch_assoc($user_result);

// Count total trips hosted by this user
$hosted_query = "SELECT COUNT(*) AS total_hosted FROM trip_info WHERE host_username = '$escaped_username'";
$hosted_result = mysqli_query($conn, $hosted_query);
$total_hosted_trips = $hosted_result ? intval(mysqli_fetch_assoc($hosted_result)['total_hosted']) : 0;

// Count total trips joined/confirmed by this user
$joined_query = "SELECT COUNT(*) AS total_joined FROM confirm_trip WHERE username = '$escaped_username'";
$joined_result = mysqli_query($conn, $joined_query);
$total_joined_trips = $joined_result ? intval(mysqli_fetch_assoc($joined_result)['total_joined']) : 0;

// Count pending requests for trips hosted by this user (for the badge/chip)
$pending_count_query = "SELECT COUNT(*) AS pending_count 
                        FROM trip_request r 
                        INNER JOIN trip_info t ON r.trip_id = t.id 
                        WHERE LOWER(t.host_username) = LOWER('$escaped_username') 
                          AND r.status = 'pending'";
$pending_count_res = mysqli_query($conn, $pending_count_query);
$pending_requests_count = $pending_count_res ? intval(mysqli_fetch_assoc($pending_count_res)['pending_count']) : 0;

// Send profile response
http_response_code(200);
echo json_encode([
    "status" => "success",
    "data" => [
        "username" => $user['username'],
        "full_name" => $user['full_name'],
        "email" => $user['email'],
        "gender" => $user['gender'],
        "country" => $user['country'],
        "dob" => $user['dob'],
        "profile_pic" => $user['profile_pic'],
        "profile_pic_url" => !empty($user['profile_pic']) ? "uploads/" . $user['profile_pic'] : null,
        "nid_pic" => $user['nid_pic'],
        "nid_pic_url" => !empty($user['nid_pic']) ? "uploads/" . $user['nid_pic'] : null,
        "total_hosted_trips" => $total_hosted_trips,
        "total_joined_trips" => $total_joined_trips,
        "pending_requests_count" => $pending_requests_count
    ]
]);
?>
