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

    case 'logout':
    case 'signout':
    case 'auth/logout':
    case 'auth/signout':
        require_once 'routes/auth/logout.php';
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

    case 'change_password':
    case 'changepassword':
    case 'auth/change_password':
    case 'auth/changepassword':
        require_once 'routes/auth/change_password.php';
        break;

    // --- Trip Routes ---
    case 'create_trip':
    case 'createtrip':
    case 'trip/create':
    case 'trips/create':
    case 'trip/insert':
    case 'trips/insert':
        require_once 'routes/trips/create.php';
        break;

    case 'update_trip':
    case 'updatetrip':
    case 'trip/update':
    case 'trips/update':
    case 'edit_trip':
    case 'edittrip':
    case 'trip/edit':
    case 'trips/edit':
        require_once 'routes/trips/update.php';
        break;

    case 'delete_trip':
    case 'deletetrip':
    case 'trip/delete':
    case 'trips/delete':
    case 'remove_trip':
    case 'removetrip':
    case 'trip/remove':
    case 'trips/remove':
        require_once 'routes/trips/delete.php';
        break;

    case 'all_trips':
    case 'alltrips':
    case 'trips':
    case 'get_trips':
        require_once 'routes/trips/all_trips.php';
        break;

    case 'category_trips':
    case 'categorytrips':
    case 'trips_by_category':
    case 'category':
    case 'trip/category':
    case 'trips/category':
        require_once 'routes/trips/category_trips.php';
        break;

    case 'join_trip':
    case 'jointrip':
    case 'trip/join':
    case 'trips/join':
    case 'trip_request':
    case 'request_trip':
        require_once 'routes/trips/join_trip.php';
        break;

    case 'accept_request':
    case 'acceptrequest':
    case 'trip/accept':
    case 'trips/accept':
    case 'reject_request':
    case 'rejectrequest':
    case 'trip/reject':
    case 'trips/reject':
    case 'respond_request':
    case 'handle_request':
        require_once 'routes/trips/respond_request.php';
        break;

    // --- User Profile Routes ---
    case 'profile':
    case 'user_profile':
    case 'user/profile':
    case 'get_profile':
        require_once 'routes/user/profile.php';
        break;

    case 'update_profile':
    case 'updateprofile':
    case 'edit_profile':
    case 'editprofile':
    case 'profile/update':
    case 'user/update_profile':
        require_once 'routes/user/update_profile.php';
        break;

    // --- Comment & Reply Routes ---
    case 'add_comment':
    case 'addcomment':
    case 'comment/add':
    case 'trip/comment':
        require_once 'routes/comments/add_comment.php';
        break;

    case 'reply_comment':
    case 'replycomment':
    case 'comment/reply':
    case 'add_reply':
    case 'reply':
        require_once 'routes/comments/reply_comment.php';
        break;

    case 'get_comments':
    case 'getcomments':
    case 'comments':
    case 'comment/get':
    case 'trip/comments':
        require_once 'routes/comments/get_comments.php';
        break;

    // --- Bookmark Routes ---
    case 'add_bookmark':
    case 'addbookmark':
    case 'bookmark/add':
    case 'create_bookmark':
        require_once 'routes/bookmarks/add_bookmark.php';
        break;

    case 'my_bookmarks':
    case 'mybookmarks':
    case 'bookmarks':
    case 'bookmark/list':
    case 'user/bookmarks':
        require_once 'routes/bookmarks/my_bookmarks.php';
        break;

    case 'remove_bookmark':
    case 'removebookmark':
    case 'delete_bookmark':
    case 'bookmark/remove':
    case 'unbookmark':
        require_once 'routes/bookmarks/remove_bookmark.php';
        break;

    // --- Chat & Messenger Routes ---
    case 'start_chat':
    case 'startchat':
    case 'chat/start':
    case 'create_chat_room':
        require_once 'routes/chat/start_chat.php';
        break;

    case 'send_message':
    case 'sendmessage':
    case 'chat/send':
    case 'message/send':
        require_once 'routes/chat/send_message.php';
        break;

    case 'get_messages':
    case 'getmessages':
    case 'messages':
    case 'chat/messages':
        require_once 'routes/chat/get_messages.php';
        break;

    case 'chat_rooms':
    case 'chatrooms':
    case 'inbox':
    case 'my_chats':
    case 'chat/rooms':
        require_once 'routes/chat/chat_rooms.php';
        break;

    // --- Trip Join Requests Routes ---
    case 'pending_requests':
    case 'pendingrequests':
    case 'trip_requests':
    case 'triprequests':
    case 'my_trip_requests':
        require_once 'routes/trips/pending_requests.php';
        break;

    default:
        http_response_code(404);
        echo json_encode([
            "status" => "error", 
            "message" => "404 - Route '$raw_route' not found! Available routes: registration, login, logout, forgot_password, verify_otp, reset_password, change_password, create_trip, update_trip, delete_trip, all_trips, category_trips, join_trip, accept_request, reject_request, pending_requests, profile, update_profile, add_comment, reply_comment, get_comments, add_bookmark, my_bookmarks, remove_bookmark, start_chat, send_message, get_messages, chat_rooms"
        ]);
        break;
}
?>