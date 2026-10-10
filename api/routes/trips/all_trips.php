<?php
// Ensure only GET method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use GET method."]);
    exit();
}

// Fetch all trips
$query = "SELECT * FROM trip_info ORDER BY id DESC";
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
    "data" => $trips
]);
?>
