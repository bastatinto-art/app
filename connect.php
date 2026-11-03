<?php
// Same host/user/database as before; exception mode, utf8mb4, and Philippine time for PHP + MySQL.
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "homerent_db";

date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+08:00'");
} catch (mysqli_sql_exception $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Please make sure MySQL is running.');
}
