<?php
// Ensure only GET method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use GET method."]);
    exit();
}

// Validate trip_id
if (empty($_GET['trip_id']) || !is_numeric($_GET['trip_id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid 'trip_id' parameter is required!"]);
    exit();
}

$trip_id = intval($_GET['trip_id']);

// Fetch all comments and replies for this trip joined with author details
$query = "SELECT 
            c.id, c.trip_id, c.username, c.parent_id, c.comment_text, c.created_at,
            u.full_name, u.profile_pic
          FROM trip_comment c
          LEFT JOIN user_info u ON c.username = u.username
          WHERE c.trip_id = $trip_id
          ORDER BY c.created_at ASC";

$result = mysqli_query($conn, $query);

if (!$result) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to fetch comments: " . mysqli_error($conn)]);
    exit();
}

// Group into top-level comments and replies
$comments_by_id = [];
$top_level_comments = [];
$replies = [];

while ($row = mysqli_fetch_assoc($result)) {
    $item = [
        "id" => intval($row['id']),
        "trip_id" => intval($row['trip_id']),
        "username" => $row['username'],
        "full_name" => $row['full_name'],
        "profile_pic" => $row['profile_pic'],
        "profile_pic_url" => !empty($row['profile_pic']) ? "uploads/" . $row['profile_pic'] : null,
        "parent_id" => $row['parent_id'] !== null ? intval($row['parent_id']) : null,
        "comment_text" => $row['comment_text'],
        "created_at" => $row['created_at'],
        "replies" => []
    ];

    if ($row['parent_id'] === null) {
        $top_level_comments[$item['id']] = $item;
    } else {
        $replies[] = $item;
    }
}

// Attach replies to their parent comments
foreach ($replies as $reply) {
    $p_id = $reply['parent_id'];
    if (isset($top_level_comments[$p_id])) {
        $top_level_comments[$p_id]['replies'][] = $reply;
    }
}

$formatted_comments = array_values($top_level_comments);

http_response_code(200);
echo json_encode([
    "status" => "success",
    "trip_id" => $trip_id,
    "total_top_level_comments" => count($formatted_comments),
    "total_replies" => count($replies),
    "data" => $formatted_comments
]);
?>
