<?php
// Ensure only POST or PUT method is allowed (POST is required for multipart file uploads in PHP)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use POST or PUT method."]);
    exit();
}

// Read input data (supports multipart/form-data, application/json, and urlencoded)
if (!empty($_POST) || !empty($_FILES)) {
    $data = $_POST;
} else {
    $raw_input = file_get_contents("php://input");
    $data = json_decode($raw_input, true) ?? [];
    if (empty($data)) {
        $data = $_GET;
    }
}

// Authenticate user: prefer Bearer token, fallback to 'host_username' or 'username' from body/params
$auth_username = '';
$token = getBearerToken();

if ($token) {
    $payload = verifyJWT($token);
    if (!$payload || empty($payload['username'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized: Invalid or expired token!"]);
        exit();
    }
    $auth_username = $payload['username'];
} elseif (!empty($data['host_username'])) {
    $auth_username = trim($data['host_username']);
} elseif (!empty($data['username'])) {
    $auth_username = trim($data['username']);
} else {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized: Please provide Authorization Bearer token or 'host_username'."
    ]);
    exit();
}

// Validate trip identification (support 'trip_id' or 'id')
$trip_id = null;
if (!empty($data['trip_id']) && is_numeric($data['trip_id'])) {
    $trip_id = intval($data['trip_id']);
} elseif (!empty($data['id']) && is_numeric($data['id'])) {
    $trip_id = intval($data['id']);
} elseif (!empty($_GET['trip_id']) && is_numeric($_GET['trip_id'])) {
    $trip_id = intval($_GET['trip_id']);
} elseif (!empty($_GET['id']) && is_numeric($_GET['id'])) {
    $trip_id = intval($_GET['id']);
}

if (!$trip_id || $trip_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Valid 'trip_id' or 'id' is required to update a trip!"
    ]);
    exit();
}

// Fetch existing trip record from database
$fetch_query = "SELECT * FROM trip_info WHERE id = $trip_id LIMIT 1";
$fetch_result = mysqli_query($conn, $fetch_query);

if (!$fetch_result || mysqli_num_rows($fetch_result) === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Trip with ID $trip_id not found!"
    ]);
    exit();
}

$trip = mysqli_fetch_assoc($fetch_result);
$old_cover_picture = $trip['cover_picture'];

// Authorization: only the trip host can update the trip
if (strtolower($trip['host_username']) !== strtolower($auth_username)) {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Forbidden: You are not authorized to update this trip. Only the trip host ('" . $trip['host_username'] . "') can update it!"
    ]);
    exit();
}

// Helper to save uploaded multipart file
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

// Helper to save base64 image
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
$new_cover_picture = null;
if (!empty($_FILES['cover_picture']['tmp_name'])) {
    $new_cover_picture = saveCoverFile($_FILES['cover_picture'], $trip['host_username']);
} elseif (!empty($data['cover_picture'])) {
    $new_cover_picture = saveCoverBase64($data['cover_picture'], $trip['host_username']);
}

// Prepare update fields
$updates = [];

// 1. title
if (isset($data['title'])) {
    $title_val = trim($data['title']);
    if ($title_val === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Title cannot be empty!"]);
        exit();
    }
    $escaped_title = mysqli_real_escape_string($conn, $title_val);
    $updates[] = "title = '$escaped_title'";
}

// 2. destination
if (isset($data['destination'])) {
    $dest_val = trim($data['destination']);
    if ($dest_val === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Destination cannot be empty!"]);
        exit();
    }
    $escaped_dest = mysqli_real_escape_string($conn, $dest_val);
    $updates[] = "destination = '$escaped_dest'";
}

// 3. start_day & end_day
$start_day_val = isset($data['start_day']) ? trim($data['start_day']) : $trip['start_day'];
$end_day_val = isset($data['end_day']) ? trim($data['end_day']) : $trip['end_day'];

if (isset($data['start_day']) || isset($data['end_day'])) {
    $start_time = strtotime($start_day_val);
    $end_time = strtotime($end_day_val);

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

    if (isset($data['start_day'])) {
        $formatted_start = date('Y-m-d', $start_time);
        $updates[] = "start_day = '$formatted_start'";
    }

    if (isset($data['end_day'])) {
        $formatted_end = date('Y-m-d', $end_time);
        $updates[] = "end_day = '$formatted_end'";
    }
}

// 4. budget
if (isset($data['budget'])) {
    if (!is_numeric($data['budget'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Budget must be a valid numeric amount!"]);
        exit();
    }
    $budget_val = floatval($data['budget']);
    if ($budget_val < 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Budget must be a positive number!"]);
        exit();
    }
    $updates[] = "budget = $budget_val";
}

// 5. category
if (isset($data['category'])) {
    $raw_cat = trim($data['category']);
    if ($raw_cat === '') {
        $updates[] = "category = NULL";
    } else {
        $clean_cat = strtolower($raw_cat);
        $valid_categories = ['adventure', 'advanture', 'beach', 'cultural', 'curtural', 'family', 'romantic', 'wildlife'];
        if (!in_array($clean_cat, $valid_categories)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Invalid category '$raw_cat'! Allowed: adventure, beach, cultural, family, romantic, wildlife",
                "available_categories" => ["adventure", "beach", "cultural", "family", "romantic", "wildlife"]
            ]);
            exit();
        }
        $escaped_cat = mysqli_real_escape_string($conn, $clean_cat);
        $updates[] = "category = '$escaped_cat'";
    }
}

// 6. transport_method
if (isset($data['transport_method'])) {
    $transport_val = trim($data['transport_method']);
    if ($transport_val === '') {
        $updates[] = "transport_method = NULL";
    } else {
        $escaped_transport = mysqli_real_escape_string($conn, $transport_val);
        $updates[] = "transport_method = '$escaped_transport'";
    }
}

// 7. description
if (isset($data['description'])) {
    $desc_val = trim($data['description']);
    if ($desc_val === '') {
        $updates[] = "description = NULL";
    } else {
        $escaped_desc = mysqli_real_escape_string($conn, $desc_val);
        $updates[] = "description = '$escaped_desc'";
    }
}

// 8. total_seats & available_seats
$curr_total = intval($trip['total_seats']);
$curr_avail = intval($trip['available_seats']);

// Query number of confirmed members for this trip
$confirmed_res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM confirm_trip WHERE trip_id = $trip_id");
$confirmed_count = 0;
if ($confirmed_res && $row = mysqli_fetch_assoc($confirmed_res)) {
    $confirmed_count = intval($row['cnt']);
}

$new_total = null;
if (isset($data['total_seats'])) {
    if (!is_numeric($data['total_seats'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "total_seats must be an integer!"]);
        exit();
    }
    $new_total = intval($data['total_seats']);
    if ($new_total < 1) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "total_seats must be at least 1!"]);
        exit();
    }
    if ($new_total < $confirmed_count) {
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "Cannot reduce total_seats to $new_total because $confirmed_count members are already confirmed for this trip!"
        ]);
        exit();
    }
    $updates[] = "total_seats = $new_total";
}

$target_total = ($new_total !== null) ? $new_total : $curr_total;

if (isset($data['available_seats'])) {
    if (!is_numeric($data['available_seats'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "available_seats must be an integer!"]);
        exit();
    }
    $new_avail = intval($data['available_seats']);
    $max_possible_avail = $target_total - $confirmed_count;
    if ($new_avail < 0 || $new_avail > $max_possible_avail) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "available_seats must be between 0 and $max_possible_avail (total capacity: $target_total, confirmed members: $confirmed_count)!"
        ]);
        exit();
    }
    $updates[] = "available_seats = $new_avail";
} elseif ($new_total !== null) {
    // Automatically adjust available_seats based on the change in total_seats
    $seat_diff = $new_total - $curr_total;
    $adjusted_avail = max(0, min($target_total - $confirmed_count, $curr_avail + $seat_diff));
    $updates[] = "available_seats = $adjusted_avail";
}

// 9. cover_picture
if ($new_cover_picture !== null) {
    $updates[] = "cover_picture = '$new_cover_picture'";
}

// If no fields to update
if (empty($updates)) {
    http_response_code(400);
    echo json_encode([
        "status" => "warning",
        "message" => "No valid fields provided to update! Updatable fields: title, destination, start_day, end_day, budget, category, transport_method, description, total_seats, available_seats, cover_picture."
    ]);
    exit();
}

// Execute database update
$update_sql = "UPDATE trip_info SET " . implode(", ", $updates) . " WHERE id = $trip_id";

if (mysqli_query($conn, $update_sql)) {
    // If a new cover picture was successfully saved, delete the old cover picture file from disk
    if ($new_cover_picture !== null && !empty($old_cover_picture)) {
        $old_path = __DIR__ . '/../../uploads/' . $old_cover_picture;
        if (file_exists($old_path) && is_file($old_path)) {
            unlink($old_path);
        }
    }

    // Fetch refreshed trip record
    $refreshed_result = mysqli_query($conn, "SELECT * FROM trip_info WHERE id = $trip_id LIMIT 1");
    $updated_trip = mysqli_fetch_assoc($refreshed_result);
    $updated_trip['cover_picture_url'] = !empty($updated_trip['cover_picture']) ? "uploads/" . $updated_trip['cover_picture'] : null;

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Trip updated successfully!",
        "data" => $updated_trip
    ]);
} else {
    // If update query failed, cleanup any newly uploaded file
    if ($new_cover_picture !== null) {
        $temp_path = __DIR__ . '/../../uploads/' . $new_cover_picture;
        if (file_exists($temp_path)) {
            unlink($temp_path);
        }
    }

    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update trip: " . mysqli_error($conn)
    ]);
}
?>
