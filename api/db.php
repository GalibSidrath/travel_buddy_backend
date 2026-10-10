<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "travel_buddy";

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . mysqli_connect_error()]));
}

mysqli_set_charset($conn, "utf8mb4");
?>