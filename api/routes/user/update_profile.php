<?php
// Ensure only POST method is allowed (POST is required for multipart file uploads in PHP)
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

$escaped_username = mysqli_real_escape_string($conn, $username);

// Fetch current user details from database
$fetch_query = "SELECT * FROM user_info WHERE username = '$escaped_username' LIMIT 1";
$fetch_result = mysqli_query($conn, $fetch_query);

if (!$fetch_result || mysqli_num_rows($fetch_result) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "User '$username' not found!"]);
    exit();
}

$current_user = mysqli_fetch_assoc($fetch_result);
$old_profile_pic = $current_user['profile_pic'];

// Helper to save uploaded multipart file
function saveProfileFile($file, $username) {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    
    $upload_dir = __DIR__ . '/../../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = !empty($ext) ? strtolower($ext) : 'jpg';
    
    $filename = $username . "_profile_" . time() . "_" . uniqid() . "." . $ext;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    return null;
}

// Helper to save base64 image
function saveProfileBase64($base64String, $username) {
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
    
    $filename = $username . "_profile_" . time() . "_" . uniqid() . "." . $extension;
    $filepath = $upload_dir . $filename;
    
    if (file_put_contents($filepath, $image_base64)) {
        return $filename;
    }
    return null;
}

// Handle profile picture update
$new_profile_pic = null;
if (!empty($_FILES['profile_pic']['tmp_name'])) {
    $new_profile_pic = saveProfileFile($_FILES['profile_pic'], $username);
} elseif (!empty($data['profile_pic'])) {
    $new_profile_pic = saveProfileBase64($data['profile_pic'], $username);
}

// Prepare update fields (immutable fields: username, email, nid_pic are intentionally ignored)
$updates = [];

// 1. full_name
if (isset($data['full_name'])) {
    $trimmed_name = trim($data['full_name']);
    if (!empty($trimmed_name)) {
        $escaped_name = mysqli_real_escape_string($conn, $trimmed_name);
        $updates[] = "full_name = '$escaped_name'";
    }
}

// 2. gender ('Male', 'Female', 'Other')
if (isset($data['gender'])) {
    $clean_gender = ucfirst(strtolower(trim($data['gender'])));
    if (in_array($clean_gender, ['Male', 'Female', 'Other'])) {
        $updates[] = "gender = '$clean_gender'";
    }
}

// 3. country
if (isset($data['country'])) {
    $country_val = trim($data['country']);
    if (!empty($country_val)) {
        $escaped_country = mysqli_real_escape_string($conn, $country_val);
        $updates[] = "country = '$escaped_country'";
    }
}

// 4. dob (date of birth YYYY-MM-DD)
if (isset($data['dob'])) {
    $dob_val = trim($data['dob']);
    $time_val = strtotime($dob_val);
    if ($time_val) {
        $formatted_dob = date('Y-m-d', $time_val);
        $updates[] = "dob = '$formatted_dob'";
    }
}

// 5. profile_pic (if new image was provided)
if ($new_profile_pic !== null) {
    $updates[] = "profile_pic = '$new_profile_pic'";
}

if (empty($updates)) {
    http_response_code(400);
    echo json_encode([
        "status" => "warning", 
        "message" => "No valid fields provided to update! Updatable fields: full_name, gender, country, dob, profile_pic."
    ]);
    exit();
}

// Execute database update
$update_sql = "UPDATE user_info SET " . implode(", ", $updates) . " WHERE username = '$escaped_username'";

if (mysqli_query($conn, $update_sql)) {
    // If a new profile picture was successfully saved, delete the old profile picture from disk
    if ($new_profile_pic !== null && !empty($old_profile_pic)) {
        $old_path = __DIR__ . '/../../uploads/' . $old_profile_pic;
        if (file_exists($old_path) && is_file($old_path)) {
            unlink($old_path);
        }
    }

    // Fetch refreshed user record
    $refreshed_result = mysqli_query($conn, "SELECT * FROM user_info WHERE username = '$escaped_username' LIMIT 1");
    $updated_user = mysqli_fetch_assoc($refreshed_result);

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Profile updated successfully!",
        "data" => [
            "username" => $updated_user['username'],
            "full_name" => $updated_user['full_name'],
            "email" => $updated_user['email'],
            "gender" => $updated_user['gender'],
            "country" => $updated_user['country'],
            "dob" => $updated_user['dob'],
            "profile_pic" => $updated_user['profile_pic'],
            "profile_pic_url" => !empty($updated_user['profile_pic']) ? "uploads/" . $updated_user['profile_pic'] : null,
            "nid_pic" => $updated_user['nid_pic'],
            "nid_pic_url" => !empty($updated_user['nid_pic']) ? "uploads/" . $updated_user['nid_pic'] : null
        ]
    ]);
} else {
    // If update failed, cleanup newly uploaded image
    if ($new_profile_pic !== null) {
        $temp_path = __DIR__ . '/../../uploads/' . $new_profile_pic;
        if (file_exists($temp_path)) unlink($temp_path);
    }

    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to update profile: " . mysqli_error($conn)]);
}
?>
