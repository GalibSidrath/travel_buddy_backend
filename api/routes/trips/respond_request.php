<?php
// Ensure only POST method is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed. Please use POST method."]);
    exit();
}

// Read input data (JSON payload or $_POST)
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);
if (!$data) {
    $data = $_POST;
}

// Determine action ('accept' or 'reject')
$action = '';
if (isset($route_normalized) && (strpos($route_normalized, 'accept') !== false)) {
    $action = 'accept';
} elseif (isset($route_normalized) && (strpos($route_normalized, 'reject') !== false)) {
    $action = 'reject';
} elseif (!empty($data['action'])) {
    $action = strtolower(trim($data['action']));
}

if (!in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Action must be either 'accept' or 'reject'!"]);
    exit();
}

// Authenticate Host user: prefer Bearer token, fallback to body 'host_username'
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

// Validate request identifier: either 'request_id' OR ('trip_id' and 'requester_username')
$request_id = null;
if (!empty($data['request_id']) && is_numeric($data['request_id'])) {
    $request_id = intval($data['request_id']);
} elseif (!empty($data['trip_id']) && !empty($data['requester_username'])) {
    $t_id = intval($data['trip_id']);
    $r_user = mysqli_real_escape_string($conn, trim($data['requester_username']));
    $find_req = mysqli_query($conn, "SELECT id FROM trip_request WHERE trip_id = $t_id AND username = '$r_user' LIMIT 1");
    if ($find_req && mysqli_num_rows($find_req) > 0) {
        $request_id = intval(mysqli_fetch_assoc($find_req)['id']);
    }
}

if (!$request_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Valid 'request_id' is required!"]);
    exit();
}

// Fetch request and related trip information
$query = "SELECT 
            r.id, r.trip_id, r.username AS requester_username, r.status AS current_status,
            t.host_username, t.available_seats, t.total_seats, t.title AS trip_title
          FROM trip_request r
          INNER JOIN trip_info t ON r.trip_id = t.id
          WHERE r.id = $request_id
          LIMIT 1";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Trip request with ID $request_id not found!"]);
    exit();
}

$req_info = mysqli_fetch_assoc($result);
$trip_id = intval($req_info['trip_id']);
$requester = $req_info['requester_username'];
$actual_host = $req_info['host_username'];
$available_seats = intval($req_info['available_seats']);
$current_status = $req_info['current_status'];

// Authorization: only the trip host can accept or reject requests
if (strtolower($host_username) !== strtolower($actual_host)) {
    http_response_code(403);
    echo json_encode([
        "status" => "error", 
        "message" => "Forbidden: Only the trip host ('$actual_host') can $action this request!"
    ]);
    exit();
}

// Check redundant actions
if ($action === 'accept' && $current_status === 'accepted') {
    http_response_code(400);
    echo json_encode(["status" => "warning", "message" => "This request is already accepted!"]);
    exit();
}

if ($action === 'reject' && $current_status === 'rejected') {
    http_response_code(400);
    echo json_encode(["status" => "warning", "message" => "This request is already rejected!"]);
    exit();
}

// ---------------------------------------------------------
// ACTION: ACCEPT
// ---------------------------------------------------------
if ($action === 'accept') {
    if ($available_seats <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Cannot accept request: No seats available for this trip!"]);
        exit();
    }

    mysqli_begin_transaction($conn);

    try {
        // 1. Update trip_request status to 'accepted'
        $u1 = mysqli_query($conn, "UPDATE trip_request SET status = 'accepted' WHERE id = $request_id");
        if (!$u1) {
            throw new Exception("Failed to update trip_request status: " . mysqli_error($conn));
        }

        // 2. Add requester into confirm_trip table
        $escaped_requester = mysqli_real_escape_string($conn, $requester);
        $check_confirmed = mysqli_query($conn, "SELECT id FROM confirm_trip WHERE trip_id = $trip_id AND username = '$escaped_requester' LIMIT 1");
        if (mysqli_num_rows($check_confirmed) === 0) {
            $u2 = mysqli_query($conn, "INSERT INTO confirm_trip (trip_id, username) VALUES ($trip_id, '$escaped_requester')");
            if (!$u2) {
                throw new Exception("Failed to insert into confirm_trip: " . mysqli_error($conn));
            }
        }

        // 3. Decrement available_seats by 1 in trip_info
        $u3 = mysqli_query($conn, "UPDATE trip_info SET available_seats = available_seats - 1 WHERE id = $trip_id AND available_seats > 0");
        if (!$u3 || mysqli_affected_rows($conn) === 0) {
            throw new Exception("Failed to decrement available seats: " . mysqli_error($conn));
        }

        mysqli_commit($conn);

        // Fetch updated seats
        $updated_seats_res = mysqli_query($conn, "SELECT available_seats FROM trip_info WHERE id = $trip_id LIMIT 1");
        $new_seats = mysqli_fetch_assoc($updated_seats_res)['available_seats'];

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Trip request accepted! Member added to confirmed list and available seats updated.",
            "data" => [
                "request_id" => $request_id,
                "trip_id" => $trip_id,
                "trip_title" => $req_info['trip_title'],
                "requester_username" => $requester,
                "status" => "accepted",
                "available_seats" => intval($new_seats)
            ]
        ]);
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        exit();
    }
}

// ---------------------------------------------------------
// ACTION: REJECT
// ---------------------------------------------------------
if ($action === 'reject') {
    mysqli_begin_transaction($conn);

    try {
        // 1. Update trip_request status to 'rejected'
        $u1 = mysqli_query($conn, "UPDATE trip_request SET status = 'rejected' WHERE id = $request_id");
        if (!$u1) {
            throw new Exception("Failed to update trip_request status: " . mysqli_error($conn));
        }

        // If previously accepted, rollback seat and remove from confirm_trip
        if ($current_status === 'accepted') {
            $escaped_requester = mysqli_real_escape_string($conn, $requester);
            mysqli_query($conn, "DELETE FROM confirm_trip WHERE trip_id = $trip_id AND username = '$escaped_requester'");
            mysqli_query($conn, "UPDATE trip_info SET available_seats = LEAST(total_seats, available_seats + 1) WHERE id = $trip_id");
        }

        mysqli_commit($conn);

        // Fetch current seats
        $updated_seats_res = mysqli_query($conn, "SELECT available_seats FROM trip_info WHERE id = $trip_id LIMIT 1");
        $curr_seats = mysqli_fetch_assoc($updated_seats_res)['available_seats'];

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Trip request rejected successfully.",
            "data" => [
                "request_id" => $request_id,
                "trip_id" => $trip_id,
                "trip_title" => $req_info['trip_title'],
                "requester_username" => $requester,
                "status" => "rejected",
                "available_seats" => intval($curr_seats)
            ]
        ]);
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        exit();
    }
}
?>
