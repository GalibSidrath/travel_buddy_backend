<?php
// Ensure only GET method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use GET method."]);
    exit();
}

// Authenticate user: prefer Bearer token, fallback to 'username' query param for testing
$username = '';
$token = getBearerToken();

if ($token) {
    $payload = verifyJWT($token);
    if ($payload && !empty($payload['username'])) {
        $username = $payload['username'];
    }
}

if (empty($username) && !empty($_GET['username'])) {
    $username = trim($_GET['username']);
}

if (empty($username)) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized: Please provide Authorization Bearer token or 'username' query parameter."
    ]);
    exit();
}

$escaped_username = mysqli_real_escape_string($conn, $username);

// Query all bookmarks of this user joined with trip_info and host user_info
$query = "SELECT 
            b.id AS bookmark_id, b.created_at AS bookmarked_at,
            t.id AS trip_id, t.title, t.host_username, t.destination, 
            t.start_day, t.end_day, t.budget, t.category, 
            t.transport_method, t.description, t.total_seats, 
            t.available_seats, t.cover_picture,
            u.full_name AS host_full_name,
            u.email AS host_email,
            u.profile_pic AS host_profile_pic
          FROM bookmark b
          INNER JOIN trip_info t ON b.trip_id = t.id
          LEFT JOIN user_info u ON t.host_username = u.username
          WHERE b.username = '$escaped_username'
          ORDER BY b.id DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to fetch bookmarks: " . mysqli_error($conn)]);
    exit();
}

$bookmarks = [];
while ($row = mysqli_fetch_assoc($result)) {
    $bookmarks[] = [
        "bookmark_id" => intval($row['bookmark_id']),
        "bookmarked_at" => $row['bookmarked_at'],
        "trip" => [
            "id" => intval($row['trip_id']),
            "title" => $row['title'],
            "destination" => $row['destination'],
            "start_day" => $row['start_day'],
            "end_day" => $row['end_day'],
            "budget" => floatval($row['budget']),
            "category" => $row['category'],
            "transport_method" => $row['transport_method'],
            "description" => $row['description'],
            "total_seats" => intval($row['total_seats']),
            "available_seats" => intval($row['available_seats']),
            "cover_picture" => $row['cover_picture'],
            "cover_picture_url" => !empty($row['cover_picture']) ? "uploads/" . $row['cover_picture'] : null,
            "host" => [
                "username" => $row['host_username'],
                "full_name" => $row['host_full_name'],
                "email" => $row['host_email'],
                "profile_pic" => $row['host_profile_pic'],
                "profile_pic_url" => !empty($row['host_profile_pic']) ? "uploads/" . $row['host_profile_pic'] : null
            ]
        ]
    ];
}

http_response_code(200);
echo json_encode([
    "status" => "success",
    "username" => $username,
    "count" => count($bookmarks),
    "data" => $bookmarks
]);
?>
