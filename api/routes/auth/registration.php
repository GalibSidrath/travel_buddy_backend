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

// Validate mandatory fields
if (
    empty(trim($data['username'] ?? '')) || 
    empty(trim($data['full_name'] ?? '')) || 
    empty(trim($data['email'] ?? '')) || 
    empty($data['password'] ?? '')
) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Required fields (username, full_name, email, password) are missing!"]);
    exit();
}

// Sanitize inputs
$username = mysqli_real_escape_string($conn, trim($data['username']));
$full_name = mysqli_real_escape_string($conn, trim($data['full_name']));
$email = mysqli_real_escape_string($conn, trim($data['email']));
$password = $data['password'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email format!"]);
    exit();
}

// Validate gender ENUM ('Male', 'Female', 'Other')
$gender = null;
if (!empty($data['gender'])) {
    $clean_gender = ucfirst(strtolower(trim($data['gender'])));
    if (in_array($clean_gender, ['Male', 'Female', 'Other'])) {
        $gender = $clean_gender;
    }
}

$country = !empty($data['country']) ? mysqli_real_escape_string($conn, trim($data['country'])) : null;
$dob = !empty($data['dob']) ? mysqli_real_escape_string($conn, trim($data['dob'])) : null;

// Secure password hashing
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check for existing username or email
$check_query = "SELECT username, email FROM user_info WHERE username = '$username' OR email = '$email' LIMIT 1";
$check_result = mysqli_query($conn, $check_query);
if ($check_result && mysqli_num_rows($check_result) > 0) {
    $row = mysqli_fetch_assoc($check_result);
    $duplicate_field = ($row['username'] === $username) ? "Username" : "Email";
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "$duplicate_field already exists!"]);
    exit();
}

// Handle multipart file upload
function saveUploadedFile($file, $username, $imageType) {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    
    $upload_dir = __DIR__ . '/../../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = !empty($ext) ? strtolower($ext) : 'jpg';
    
    $filename = $username . "_" . $imageType . "_" . time() . "_" . uniqid() . "." . $ext;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    return null;
}

// Handle Base64 image payload
function saveBase64Image($base64String, $username, $imageType) {
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
    
    $filename = $username . "_" . $imageType . "_" . time() . "_" . uniqid() . "." . $extension;
    $filepath = $upload_dir . $filename;
    
    if (file_put_contents($filepath, $image_base64)) {
        return $filename;
    }
    return null;
}

// Save profile picture (supports multipart upload or base64)
$profile_pic = null;
if (!empty($_FILES['profile_pic']['tmp_name'])) {
    $profile_pic = saveUploadedFile($_FILES['profile_pic'], $username, "profile");
} elseif (!empty($data['profile_pic'])) {
    $profile_pic = saveBase64Image($data['profile_pic'], $username, "profile");
}

// Save NID picture (supports multipart upload or base64)
$nid_pic = null;
if (!empty($_FILES['nid_pic']['tmp_name'])) {
    $nid_pic = saveUploadedFile($_FILES['nid_pic'], $username, "nid");
} elseif (!empty($data['nid_pic'])) {
    $nid_pic = saveBase64Image($data['nid_pic'], $username, "nid");
}

// Format NULL values properly for SQL insertion
$gender_sql  = ($gender !== null)      ? "'$gender'"      : "NULL";
$country_sql = ($country !== null)     ? "'$country'"     : "NULL";
$dob_sql     = ($dob !== null)         ? "'$dob'"         : "NULL";
$profile_sql = ($profile_pic !== null) ? "'$profile_pic'" : "NULL";
$nid_sql     = ($nid_pic !== null)     ? "'$nid_pic'"     : "NULL";

// Begin database transaction
mysqli_begin_transaction($conn);

try {
    // Insert record into user_info
    $query1 = "INSERT INTO user_info (username, full_name, email, gender, country, dob, profile_pic, nid_pic) 
               VALUES ('$username', '$full_name', '$email', $gender_sql, $country_sql, $dob_sql, $profile_sql, $nid_sql)";
    
    if (!mysqli_query($conn, $query1)) {
        throw new Exception("Failed to insert into user_info: " . mysqli_error($conn));
    }

    // Insert record into user_login
    $query2 = "INSERT INTO user_login (username, password) VALUES ('$username', '$hashed_password')";
    
    if (!mysqli_query($conn, $query2)) {
        throw new Exception("Failed to insert into user_login: " . mysqli_error($conn));
    }

    // Commit transaction
    mysqli_commit($conn);
    
    // Generate JWT token for immediate authenticated session
    $token = generateJWT([
        "username" => $username,
        "email" => $email
    ], 604800);

    http_response_code(201);
    echo json_encode([
        "status" => "success", 
        "message" => "Registration successful!",
        "token" => $token,
        "data" => [
            "username" => $username,
            "full_name" => $full_name,
            "email" => $email,
            "gender" => $gender,
            "country" => $country,
            "dob" => $dob,
            "profile_pic" => $profile_pic,
            "profile_pic_url" => $profile_pic ? "uploads/" . $profile_pic : null,
            "nid_pic" => $nid_pic,
            "nid_pic_url" => $nid_pic ? "uploads/" . $nid_pic : null
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on failure
    mysqli_rollback($conn);
    
    // Remove uploaded files if database insertion failed
    $upload_dir = __DIR__ . '/../../uploads/';
    if ($profile_pic && file_exists($upload_dir . $profile_pic)) unlink($upload_dir . $profile_pic);
    if ($nid_pic && file_exists($upload_dir . $nid_pic)) unlink($upload_dir . $nid_pic);

    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Registration failed: " . $e->getMessage()]);
}
?>
