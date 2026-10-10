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

if (!$data || empty(trim($data['email'] ?? ''))) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email is required!"]);
    exit();
}

$email = mysqli_real_escape_string($conn, trim($data['email']));

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email format!"]);
    exit();
}

// Verify that user exists in user_info table
$user_check = mysqli_query($conn, "SELECT username, full_name FROM user_info WHERE email = '$email' LIMIT 1");
if (!$user_check || mysqli_num_rows($user_check) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "No account found with this email address!"]);
    exit();
}

$user = mysqli_fetch_assoc($user_check);
$full_name = $user['full_name'];

// Remove any expired OTPs or previous pending OTPs for this email
mysqli_query($conn, "DELETE FROM password_reset WHERE expires_at < NOW() OR email = '$email'");

// Generate a secure 6-digit OTP
$otp = str_pad(strval(random_int(100000, 999999)), 6, '0', STR_PAD_LEFT);

// Save OTP in database with 5 minutes expiration
$insert_query = "INSERT INTO password_reset (email, otp, expires_at) 
                 VALUES ('$email', '$otp', DATE_ADD(NOW(), INTERVAL 5 MINUTE))";

if (!mysqli_query($conn, $insert_query)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to generate OTP: " . mysqli_error($conn)]);
    exit();
}

// Send OTP email using SMTP mailer
require_once __DIR__ . '/../../mailer.php';
$mailError = '';
$isSent = sendOTPEmail($email, $full_name, $otp, $mailError);

// Send response
if ($isSent) {
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "A 6-digit OTP has been sent to your email inbox! It will expire in 5 minutes.",
        "email" => $email
    ]);
} else {
    // Return diagnostic info if SMTP sending fails
    http_response_code(200);
    echo json_encode([
        "status" => "warning",
        "message" => "OTP generated in database, but email sending failed.",
        "email" => $email,
        "smtp_error" => $mailError,
        "otp" => $otp,
        "note" => "Please update your Gmail and App Password in api/mail_config.php to send actual emails."
    ]);
}
?>
