<?php
// Start session only if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Replace with your localhost database credentials
// $host = "localhost";
// $username = "root";
// $password = ""; // Empty by default in XAMPP
// $database = "employee_db";


// Replace with your Hostinger database credentials
$host = "127.0.0.1:3306"; // e.g., mysql.hostinger.com
$username = "u701287743_salary";
$password = "Het@2210";
$database = "u701287743_salary";
$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>


