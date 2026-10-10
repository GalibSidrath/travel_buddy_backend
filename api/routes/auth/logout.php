<?php
// Ensure only POST method is allowed (preferred standard for session termination)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use POST method."]);
    exit();
}

// Extract Bearer token if provided
$token = getBearerToken();
$username = null;

if ($token) {
    $payload = verifyJWT($token);
    if ($payload && !empty($payload['username'])) {
        $username = $payload['username'];
    }
}

// Clear any active PHP sessions/cookies if present
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

// Send success response
http_response_code(200);
echo json_encode([
    "status" => "success",
    "message" => "Logged out successfully! Please clear the token from client storage (localStorage/sessionStorage).",
    "user" => $username
]);
?>
