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

// Read password fields
$current_password = $data['current_password'] ?? $data['old_password'] ?? '';
$new_password     = $data['new_password'] ?? '';
$confirm_password = $data['confirm_password'] ?? null;

// Validate mandatory fields
if (empty($current_password) || empty($new_password)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Both current_password and new_password are required!"
    ]);
    exit();
}

// Validate new password length
if (strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "New password must be at least 6 characters long!"
    ]);
    exit();
}

// Check confirm password if provided
if ($confirm_password !== null && $new_password !== $confirm_password) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "New password and confirm password do not match!"
    ]);
    exit();
}

// Check if new password is identical to old password
if ($current_password === $new_password) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "New password cannot be the same as your current password!"
    ]);
    exit();
}

$escaped_username = mysqli_real_escape_string($conn, $username);

// Fetch current hashed password from user_login table
$login_query = "SELECT password FROM user_login WHERE username = '$escaped_username' LIMIT 1";
$login_result = mysqli_query($conn, $login_query);

if (!$login_result || mysqli_num_rows($login_result) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "User credentials not found!"]);
    exit();
}

$user = mysqli_fetch_assoc($login_result);
$hashed_password = $user['password'];

// Verify existing password
if (!password_verify($current_password, $hashed_password)) {
    http_response_code(401);
    echo json_encode([
        "status" => "error", 
        "message" => "Current password is incorrect! Please enter your correct current password."
    ]);
    exit();
}

// Hash the new password securely
$new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update user_login with new hashed password
$update_query = "UPDATE user_login SET password = '$new_hashed_password' WHERE username = '$escaped_username'";

if (mysqli_query($conn, $update_query)) {
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Password changed successfully! Please use your new password next time you log in."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Failed to update password: " . mysqli_error($conn)
    ]);
}
?>
