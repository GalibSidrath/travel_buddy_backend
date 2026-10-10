<?php

header("Access-Control-Allow-Origin: *"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


require_once 'db.php';
require_once 'jwt.php';


$raw_route = isset($_GET['route']) ? $_GET['route'] : '';
$route = strtolower(trim($raw_route));
$route_normalized = str_replace('-', '_', $route);

if (empty($route_normalized)) {
    echo json_encode(["status" => "error", "message" => "No route specified!"]);
    exit();
}

switch ($route_normalized) {

    // --- Authentication Routes ---
    case 'registration':
    case 'register':
    case 'auth/registration':
    case 'auth/register':
        require_once 'routes/auth/registration.php';
        break;

    case 'login':
    case 'signin':
    case 'auth/login':
    case 'auth/signin':
        require_once 'routes/auth/login.php';
        break;

    case 'forgot_password':
    case 'forgotpassword':
    case 'forgot':
    case 'auth/forgot_password':
    case 'auth/forgotpassword':
        require_once 'routes/auth/forgot_password.php';
        break;

    case 'verify_otp':
    case 'verifyotp':
    case 'verify':
    case 'auth/verify_otp':
    case 'auth/verifyotp':
        require_once 'routes/auth/verify_otp.php';
        break;

    case 'reset_password':
    case 'resetpassword':
    case 'reset':
    case 'auth/reset_password':
    case 'auth/resetpassword':
        require_once 'routes/auth/reset_password.php';
        break;

    default:
        http_response_code(404);
        echo json_encode([
            "status" => "error", 
            "message" => "404 - Route '$raw_route' not found! Available routes: registration, login, forgot_password, verify_otp, reset_password"
        ]);
        break;
}
?>