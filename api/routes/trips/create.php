<?php
// Ensure only POST method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use POST method."]);
    exit();
}

// Read input data (supports both multipart/form-data and application/json)
if (!empty($_POST) || !empty($_FILES)) {
    $data = $_POST;
} else {
    $raw_input = file_get_contents("php://input");
    $data = json_decode($raw_input, true) ?? [];
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid or empty request data!"]);
    exit();
}

// Authenticate user: prefer Bearer token, fallback to host_username if provided
$host_username = '';
$token = getBearerToken();

if ($token) {
    $payload = verifyJWT($token);
    if (!$payload || empty($payload['username'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized: Invalid or expired token!"]);
        exit();
    }
    $host_username = $payload['username'];
} elseif (!empty($data['host_username'])) {
    $host_username = trim($data['host_username']);
} else {
    http_response_code(401);
    echo json_encode([
        "status" => "error", 
        "message" => "Unauthorized: Please provide Authorization Bearer token or 'host_username'."
    ]);
    exit();
}

// Sanitize host_username and verify user existence in user_info
$escaped_host = mysqli_real_escape_string($conn, $host_username);
$host_check = mysqli_query($conn, "SELECT username FROM user_info WHERE username = '$escaped_host' LIMIT 1");
if (!$host_check || mysqli_num_rows($host_check) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Host user '$host_username' not found in database!"]);
    exit();
}

// Validate mandatory fields
$required_fields = ['title', 'destination', 'start_day', 'end_day', 'budget', 'total_seats'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || trim(strval($data[$field])) === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Field '$field' is required!"]);
        exit();
    }
}

$title = trim($data['title']);
$destination = trim($data['destination']);
$start_day = trim($data['start_day']);
$end_day = trim($data['end_day']);
$budget = floatval($data['budget']);
$total_seats = intval($data['total_seats']);

// Validate date formats
$start_time = strtotime($start_day);
$end_time = strtotime($end_day);

if (!$start_time || !$end_time) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid date format for start_day or end_day. Use YYYY-MM-DD."]);
    exit();
}

if ($end_time < $start_time) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "end_day cannot be earlier than start_day!"]);
    exit();
}

// Format dates strictly to YYYY-MM-DD
$start_day_formatted = date('Y-m-d', $start_time);
$end_day_formatted = date('Y-m-d', $end_time);

// Validate budget and seats
if ($budget < 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Budget must be a positive number!"]);
    exit();
}

if ($total_seats < 1) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "total_seats must be at least 1!"]);
    exit();
}

// Available seats: defaults to total_seats unless specified
$available_seats = $total_seats;
if (isset($data['available_seats']) && trim(strval($data['available_seats'])) !== '') {
    $parsed_avail = intval($data['available_seats']);
    if ($parsed_avail < 0 || $parsed_avail > $total_seats) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "available_seats must be between 0 and total_seats ($total_seats)!"]);
        exit();
    }
    $available_seats = $parsed_avail;
}

// Optional fields
$category = !empty($data['category']) ? trim($data['category']) : null;
$transport_method = !empty($data['transport_method']) ? trim($data['transport_method']) : null;
$description = !empty($data['description']) ? trim($data['description']) : null;

// File upload helpers
function saveCoverFile($file, $username) {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    
    $upload_dir = __DIR__ . '/../../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = !empty($ext) ? strtolower($ext) : 'jpg';
    
    $filename = "trip_" . $username . "_" . time() . "_" . uniqid() . "." . $ext;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    return null;
}

function saveCoverBase64($base64String, $username) {
    if (empty($base64String)) return null;
    
    $upload_dir = __DIR__ . '/../../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $extension = 'jpg';
    if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
        $ext = strtolower($type[1]);
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $extension = ($ext === 'jpeg') ? 'jpg' : $ext;
        }
        $base64String = substr($base64String, strpos($base64String, ',') + 1);
    }
    
    $image_base64 = base64_decode(str_replace(' ', '+', $base64String));
    if ($image_base64 === false) {
        return null;
    }
    
    $filename = "trip_" . $username . "_" . time() . "_" . uniqid() . "." . $extension;
    $filepath = $upload_dir . $filename;
    
    if (file_put_contents($filepath, $image_base64)) {
        return $filename;
    }
    return null;
}

// Handle cover picture upload
$cover_picture = null;
if (!empty($_FILES['cover_picture']['tmp_name'])) {
    $cover_picture = saveCoverFile($_FILES['cover_picture'], $escaped_host);
} elseif (!empty($data['cover_picture'])) {
    $cover_picture = saveCoverBase64($data['cover_picture'], $escaped_host);
}

// Escape strings for SQL insertion
$title_sql = mysqli_real_escape_string($conn, $title);
$destination_sql = mysqli_real_escape_string($conn, $destination);
$category_sql = ($category !== null) ? "'" . mysqli_real_escape_string($conn, $category) . "'" : "NULL";
$transport_sql = ($transport_method !== null) ? "'" . mysqli_real_escape_string($conn, $transport_method) . "'" : "NULL";
$description_sql = ($description !== null) ? "'" . mysqli_real_escape_string($conn, $description) . "'" : "NULL";
$cover_sql = ($cover_picture !== null) ? "'" . mysqli_real_escape_string($conn, $cover_picture) . "'" : "NULL";

// Insert into trip_info
$query = "INSERT INTO trip_info (
            title, host_username, destination, start_day, end_day, 
            budget, category, transport_method, description, 
            total_seats, available_seats, cover_picture
          ) VALUES (
            '$title_sql', '$escaped_host', '$destination_sql', '$start_day_formatted', '$end_day_formatted', 
            $budget, $category_sql, $transport_sql, $description_sql, 
            $total_seats, $available_seats, $cover_sql
          )";

if (mysqli_query($conn, $query)) {
    $trip_id = mysqli_insert_id($conn);
    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Trip created successfully!",
        "data" => [
            "id" => $trip_id,
            "title" => $title,
            "host_username" => $host_username,
            "destination" => $destination,
            "start_day" => $start_day_formatted,
            "end_day" => $end_day_formatted,
            "budget" => $budget,
            "category" => $category,
            "transport_method" => $transport_method,
            "description" => $description,
            "total_seats" => $total_seats,
            "available_seats" => $available_seats,
            "cover_picture" => $cover_picture,
            "cover_picture_url" => $cover_picture ? "uploads/" . $cover_picture : null
        ]
    ]);
} else {
    // If insertion failed, cleanup any uploaded cover image
    if ($cover_picture && file_exists(__DIR__ . '/../../uploads/' . $cover_picture)) {
        unlink(__DIR__ . '/../../uploads/' . $cover_picture);
    }
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to create trip: " . mysqli_error($conn)
    ]);
}
?>
