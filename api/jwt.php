<?php
// Secret Key for Signing JWT (Keep this secret and secure)
define('JWT_SECRET_KEY', 'travel_buddy_super_secret_key_2026_x!9qZ');

/**
 * Base64Url Encode
 */
function base64UrlEncode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

/**
 * Base64Url Decode
 */
function base64UrlDecode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
}

/**
 * Generate a JWT token
 * 
 * @param array $payload Data to encode in token (e.g. ['username' => 'galib'])
 * @param int $expirySeconds Token validity duration in seconds (Default: 7 days)
 * @return string JWT token
 */
function generateJWT($payload, $expirySeconds = 604800) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

    $issuedAt = time();
    $payload['iat'] = $issuedAt;
    $payload['exp'] = $issuedAt + $expirySeconds;

    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET_KEY, true);
    $base64UrlSignature = base64UrlEncode($signature);

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Verify and decode a JWT token
 * 
 * @param string $token
 * @return array|false Returns payload array if valid, false if expired or invalid
 */
function verifyJWT($token) {
    if (empty($token)) return false;

    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;

    // Verify signature
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET_KEY, true);
    $expectedSignature = base64UrlEncode($signature);

    if (!hash_equals($expectedSignature, $base64UrlSignature)) {
        return false; // Signature mismatch
    }

    $payload = json_decode(base64UrlDecode($base64UrlPayload), true);
    if (!$payload) return false;

    // Check expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false; // Token expired
    }

    return $payload;
}

/**
 * Extract Bearer token from Request Headers
 * 
 * @return string|null
 */
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        } elseif (isset($requestHeaders['authorization'])) {
            $headers = trim($requestHeaders['authorization']);
        }
    }

    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Middleware/Guard function to protect authenticated routes
 * 
 * @return array Decoded token payload with user data
 */
function authenticateUser() {
    $token = getBearerToken();
    if (!$token) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized: Token not provided!"]);
        exit();
    }

    $payload = verifyJWT($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized: Invalid or expired token!"]);
        exit();
    }

    return $payload;
}
?>
