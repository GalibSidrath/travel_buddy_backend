<?php
// Ensure only GET method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use GET method."]);
    exit();
}

// Read category from query parameters
$raw_category = isset($_GET['category']) ? trim($_GET['category']) : '';

if (empty($raw_category)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Category is required! Available categories: adventure, beach, cultural, family, romantic, wildlife",
        "available_categories" => ["adventure", "beach", "cultural", "family", "romantic", "wildlife"]
    ]);
    exit();
}

$clean_category = strtolower($raw_category);

// Allowed categories (supports both standard and common alternate spellings)
$valid_categories = [
    'adventure' => ['adventure', 'advanture'],
    'advanture' => ['adventure', 'advanture'],
    'beach'     => ['beach'],
    'cultural'  => ['cultural', 'curtural'],
    'curtural'  => ['cultural', 'curtural'],
    'family'    => ['family'],
    'romantic'  => ['romantic'],
    'wildlife'  => ['wildlife']
];

if (!array_key_exists($clean_category, $valid_categories)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid category '$raw_category'! Allowed categories: adventure, beach, cultural, family, romantic, wildlife",
        "available_categories" => ["adventure", "beach", "cultural", "family", "romantic", "wildlife"]
    ]);
    exit();
}

// Prepare values for SQL IN clause
$search_values = $valid_categories[$clean_category];
$escaped_values = array_map(function($val) use ($conn) {
    return "'" . mysqli_real_escape_string($conn, $val) . "'";
}, $search_values);

$in_clause = implode(", ", $escaped_values);

// Fetch trips matching the selected category
$query = "SELECT * FROM trip_info WHERE LOWER(category) IN ($in_clause) ORDER BY id DESC";
$result = mysqli_query($conn, $query);

if (!$result) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to fetch trips: " . mysqli_error($conn)]);
    exit();
}

$trips = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['cover_picture_url'] = !empty($row['cover_picture']) ? "uploads/" . $row['cover_picture'] : null;
    $trips[] = $row;
}

http_response_code(200);
echo json_encode([
    "status" => "success",
    "category" => $clean_category,
    "count" => count($trips),
    "data" => $trips
]);
?>
