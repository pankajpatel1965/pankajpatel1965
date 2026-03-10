<?php
include 'db_connect.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'];
mysqli_query($conn, "DELETE FROM salaries WHERE employee_id = '$id'");
mysqli_query($conn, "DELETE FROM employees WHERE id = '$id'");
header("Location: index.php");
exit();
?>