<?php
// db_connect.php
// Purpose: hold a reusable MySQL connection for your app
$host = 'localhost'; // XAMPP runs MySQL on your machine
$user = 'root'; // Default username in XAMPP
$pass = ''; // Default password is blank
$db = 'mypos'; // The database we created in phpMyAdmin
// Turn on mysqli error reporting to get clear error messages in dev
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
 // Create a new connection object
 $conn = new mysqli($host, $user, $pass, $db);
 // Always set a safe, modern charset
 $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
 // If connection fails, stop execution and show a readable message
 http_response_code(500);
 exit('Database connection error: ' . $e->getMessage());
}
?>

