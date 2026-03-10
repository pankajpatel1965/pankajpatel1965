<?php
include 'db_connect.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'];
$sql = "DELETE FROM salaries WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: salary.php");
    exit();
} else {
    // Optionally handle the error (e.g., display an error message)
    header("Location: salary.php?error=Unable to delete salary record");
    exit();
}

$stmt->close();
?>