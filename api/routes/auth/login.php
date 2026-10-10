<?php
// Ensure only POST method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use POST method."]);
    exit();
}

// Read input JSON payload
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid or empty JSON body!"]);
    exit();
}

// Validate required fields (supports login with username or email)
$identity = trim($data['username'] ?? $data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($identity) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Username/Email and password are required!"]);
    exit();
}

$escaped_identity = mysqli_real_escape_string($conn, $identity);

// Query user credentials and profile
$query = "SELECT ul.username, ul.password, ui.full_name, ui.email, ui.gender, ui.country, ui.dob, ui.profile_pic, ui.nid_pic
          FROM user_login ul
          INNER JOIN user_info ui ON ul.username = ui.username
          WHERE ul.username = '$escaped_identity' OR ui.email = '$escaped_identity'
          LIMIT 1";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid credentials! User not found."]);
    exit();
}

$user = mysqli_fetch_assoc($result);

// Verify password
if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid credentials! Wrong password."]);
    exit();
}

// Generate JWT token (valid for 7 days)
$token_payload = [
    "username" => $user['username'],
    "email" => $user['email']
];

$token = generateJWT($token_payload, 604800); // 7 days in seconds

// Send success response
http_response_code(200);
echo json_encode([
    "status" => "success",
    "message" => "Login successful!",
    "token" => $token,
    "user" => [
        "username" => $user['username'],
        "full_name" => $user['full_name'],
        "email" => $user['email'],
        "gender" => $user['gender'],
        "country" => $user['country'],
        "dob" => $user['dob'],
        "profile_pic" => $user['profile_pic'],
        "profile_pic_url" => $user['profile_pic'] ? "uploads/" . $user['profile_pic'] : null,
        "nid_pic" => $user['nid_pic'],
        "nid_pic_url" => $user['nid_pic'] ? "uploads/" . $user['nid_pic'] : null
    ]
]);
?>
