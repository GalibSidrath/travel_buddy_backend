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

if (!$data || empty(trim($data['email'] ?? '')) || empty(trim($data['otp'] ?? ''))) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and 6-digit OTP are required!"]);
    exit();
}

$email = mysqli_real_escape_string($conn, trim($data['email']));
$otp = mysqli_real_escape_string($conn, trim($data['otp']));

// Clean up expired OTP records
mysqli_query($conn, "DELETE FROM password_reset WHERE expires_at < NOW()");

// Verify OTP and check expiration
$query = "SELECT * FROM password_reset WHERE email = '$email' AND otp = '$otp' AND expires_at >= NOW() LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid or expired OTP code!"]);
    exit();
}

// Delete OTP immediately after verification to prevent reuse
mysqli_query($conn, "DELETE FROM password_reset WHERE email = '$email'");

// Issue a temporary password reset token (valid for 10 minutes)
$reset_token = generateJWT([
    "email" => $email,
    "purpose" => "password_reset"
], 600);

// Send success response
http_response_code(200);
echo json_encode([
    "status" => "success",
    "message" => "OTP verified successfully! You can now proceed to reset your password.",
    "email" => $email,
    "reset_token" => $reset_token
]);
?>
