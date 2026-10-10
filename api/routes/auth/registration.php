<?php
// ১. শুধু POST রিকোয়েস্ট হ্যান্ডেল করা
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use POST method."]);
    exit();
}

// ২. ইনপুট ডেটা রিড করা (JSON অথবা Multipart Form-Data উভয়ই সাপোর্ট করবে)
if (!empty($_POST) || !empty($_FILES)) {
    // ফ্লাটার থেকে multipart/form-data আসলে
    $data = $_POST;
} else {
    // ফ্লাটার থেকে application/json আসলে
    $raw_input = file_get_contents("php://input");
    $data = json_decode($raw_input, true) ?? [];
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid or empty request data!"]);
    exit();
}

// ৩. ম্যান্ডেটরি ফিল্ডগুলো চেক করা
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

// ৪. ডাটা স্যানিটাইজ করা (SQL Injection ঠেকানোর জন্য)
$username = mysqli_real_escape_string($conn, trim($data['username']));
$full_name = mysqli_real_escape_string($conn, trim($data['full_name']));
$email = mysqli_real_escape_string($conn, trim($data['email']));
$password = $data['password'];

// ইমেইল ফরম্যাট চেক করা
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email format!"]);
    exit();
}

// জেন্ডার ভ্যালিডেশন (Enum: 'Male', 'Female', 'Other')
$gender = null;
if (!empty($data['gender'])) {
    $clean_gender = ucfirst(strtolower(trim($data['gender'])));
    if (in_array($clean_gender, ['Male', 'Female', 'Other'])) {
        $gender = $clean_gender;
    }
}

$country = !empty($data['country']) ? mysqli_real_escape_string($conn, trim($data['country'])) : null;
$dob = !empty($data['dob']) ? mysqli_real_escape_string($conn, trim($data['dob'])) : null;

// পাসওয়ার্ড হ্যাশ করা (সিকিউরিটির জন্য)
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// ৫. ইউজারনেম বা ইমেইল আগে থেকেই আছে কি না চেক করা
$check_query = "SELECT username, email FROM user_info WHERE username = '$username' OR email = '$email' LIMIT 1";
$check_result = mysqli_query($conn, $check_query);
if ($check_result && mysqli_num_rows($check_result) > 0) {
    $row = mysqli_fetch_assoc($check_result);
    $duplicate_field = ($row['username'] === $username) ? "Username" : "Email";
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "$duplicate_field already exists!"]);
    exit();
}

// ৬.১ Multipart ফাইল আপলোড হ্যান্ডেল করার ফাংশন
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

// ৬.২ Base64 ইমেজ হ্যান্ডেল করার ফাংশন
function saveBase64Image($base64String, $username, $imageType) {
    if (empty($base64String)) return null;
    
    // uploads ফোল্ডার না থাকলে তৈরি করা (api/uploads/)
    $upload_dir = __DIR__ . '/../../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // ফাইল এক্সটেনশন ডিটেক্ট করা (ডিফল্ট: jpg)
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

// প্রোফাইল ছবি সেভ করা (Multipart অথবা Base64 যেকোনোভাবে পাঠানো যাবে)
$profile_pic = null;
if (!empty($_FILES['profile_pic']['tmp_name'])) {
    $profile_pic = saveUploadedFile($_FILES['profile_pic'], $username, "profile");
} elseif (!empty($data['profile_pic'])) {
    $profile_pic = saveBase64Image($data['profile_pic'], $username, "profile");
}

// এনআইডি ছবি সেভ করা (Multipart অথবা Base64 যেকোনোভাবে পাঠানো যাবে)
$nid_pic = null;
if (!empty($_FILES['nid_pic']['tmp_name'])) {
    $nid_pic = saveUploadedFile($_FILES['nid_pic'], $username, "nid");
} elseif (!empty($data['nid_pic'])) {
    $nid_pic = saveBase64Image($data['nid_pic'], $username, "nid");
}

// ৭. ডাটাবেসের জন্য NULL হ্যান্ডেলিং (NULL হলে কোটেশন ছাড়া যাবে, স্ট্রিং হলে কোটেশনে যাবে)
$gender_sql  = ($gender !== null)      ? "'$gender'"      : "NULL";
$country_sql = ($country !== null)     ? "'$country'"     : "NULL";
$dob_sql     = ($dob !== null)         ? "'$dob'"         : "NULL";
$profile_sql = ($profile_pic !== null) ? "'$profile_pic'" : "NULL";
$nid_sql     = ($nid_pic !== null)     ? "'$nid_pic'"     : "NULL";

// ৮. ডাটাবেস ট্রানজেকশন শুরু
mysqli_begin_transaction($conn);

try {
    // User Info টেবিলে ইনসার্ট
    $query1 = "INSERT INTO user_info (username, full_name, email, gender, country, dob, profile_pic, nid_pic) 
               VALUES ('$username', '$full_name', '$email', $gender_sql, $country_sql, $dob_sql, $profile_sql, $nid_sql)";
    
    if (!mysqli_query($conn, $query1)) {
        throw new Exception("Failed to insert into user_info: " . mysqli_error($conn));
    }

    // User Login টেবিলে ইনসার্ট
    $query2 = "INSERT INTO user_login (username, password) VALUES ('$username', '$hashed_password')";
    
    if (!mysqli_query($conn, $query2)) {
        throw new Exception("Failed to insert into user_login: " . mysqli_error($conn));
    }

    // সবকিছু ঠিক থাকলে ট্রানজেকশন কমিট করা
    mysqli_commit($conn);
    
    // টোকেন তৈরি করা (রেজিস্ট্রেশনের সাথে সাথেই লগইন সেশন নিশ্চিত করতে)
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
    // কোনো একটা ইনসার্ট ফেইল করলে ডাটাবেস রোলব্যাক হবে
    mysqli_rollback($conn);
    
    // সেভ হওয়া ফাইল ডিলিট করে দেওয়া
    $upload_dir = __DIR__ . '/../../uploads/';
    if ($profile_pic && file_exists($upload_dir . $profile_pic)) unlink($upload_dir . $profile_pic);
    if ($nid_pic && file_exists($upload_dir . $nid_pic)) unlink($upload_dir . $nid_pic);

    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Registration failed: " . $e->getMessage()]);
}
?>
