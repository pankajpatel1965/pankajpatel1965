<?php
include 'db_connect.php';

// Get the selected month_year from the query parameter
$month_year = isset($_GET['month_year']) ? $_GET['month_year'] : '';

// Fetch employees who don't have a salary entry for the selected month
$query = "SELECT e.id, e.name 
          FROM employees e 
          WHERE e.id NOT IN (
              SELECT s.employee_id 
              FROM salaries s 
              WHERE s.month_year = ?
          ) 
          ORDER BY e.name";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $month_year);
$stmt->execute();
$result = $stmt->get_result();

$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

$stmt->close();

// Return the employees as JSON
header('Content-Type: application/json');
echo json_encode($employees);
exit();