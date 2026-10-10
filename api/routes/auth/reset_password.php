<?php
// Ensure only POST method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use POST method."]);
    exit();
}

// Read input payload
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid or empty JSON body!"]);
    exit();
}

$new_password = $data['new_password'] ?? '';
if (empty($new_password) || strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "New password must be at least 6 characters long!"]);
    exit();
}

$email = null;

// Method 1: Verify using reset_token (issued by verify_otp)
if (!empty($data['reset_token'])) {
    $payload = verifyJWT($data['reset_token']);
    if (!$payload || ($payload['purpose'] ?? '') !== 'password_reset') {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid or expired reset token! Please request a new OTP."]);
        exit();
    }
    $email = mysqli_real_escape_string($conn, $payload['email']);
}
// Method 2: Reset directly using email and OTP
elseif (!empty($data['email']) && !empty($data['otp'])) {
    $email = mysqli_real_escape_string($conn, trim($data['email']));
    $otp = mysqli_real_escape_string($conn, trim($data['otp']));

    // Check OTP in database
    $check_query = "SELECT * FROM password_reset WHERE email = '$email' AND otp = '$otp' AND expires_at >= NOW() LIMIT 1";
    $otp_result = mysqli_query($conn, $check_query);

    if (!$otp_result || mysqli_num_rows($otp_result) === 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid or expired OTP!"]);
        exit();
    }

    // Delete OTP immediately after verification
    mysqli_query($conn, "DELETE FROM password_reset WHERE email = '$email'");
} 
else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing reset_token or (email and otp)!"]);
    exit();
}

// Look up user username from user_info table
$user_query = mysqli_query($conn, "SELECT username FROM user_info WHERE email = '$email' LIMIT 1");
if (!$user_query || mysqli_num_rows($user_query) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "User account not found!"]);
    exit();
}

$user_row = mysqli_fetch_assoc($user_query);
$username = $user_row['username'];

// Hash new password and update user_login table
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$update_query = "UPDATE user_login SET password = '$hashed_password' WHERE username = '$username'";

if (mysqli_query($conn, $update_query)) {
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Password has been successfully updated! You can now log in with your new password."
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to update password: " . mysqli_error($conn)]);
}
?>
