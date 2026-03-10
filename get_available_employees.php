<?php
// Start output buffering to prevent stray output
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Log errors to a file for debugging (remove in production)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Include database connection
require_once 'db_connect.php';

// Check for month_year parameter
if (!isset($_GET['month_year']) || empty($_GET['month_year'])) {
    ob_end_clean();
    echo json_encode(['error' => 'Month and year are required']);
    exit;
}

$month_year = $_GET['month_year'];

// Log received month_year for debugging
file_put_contents(__DIR__ . '/debug.log', "Received month_year: $month_year\n", FILE_APPEND);

// Prepare SQL query
$sql = "SELECT e.id, e.name, e.salary, e.payment_type, e.bank_name, e.micr_code, e.ifsc_code, e.account_number
        FROM employees e
        LEFT JOIN salaries s ON e.id = s.employee_id AND s.month_year = ?
        WHERE s.id IS NULL
        ORDER BY 
            CASE 
                WHEN e.payment_type = 'cash' THEN 1
                WHEN e.payment_type = 'bank' AND e.bank_name = 'HDFC' THEN 2
                WHEN e.payment_type = 'bank' AND e.bank_name = 'Apprentice' THEN 3
                WHEN e.payment_type = 'bank' AND e.bank_name = 'Other' THEN 4
                ELSE 5
            END, 
            e.name ASC";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("s", $month_year);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'salary' => (float)$row['salary'],
            'payment_type' => $row['payment_type'],
            'bank_name' => $row['bank_name'] ?: null,
            'micr_code' => $row['micr_code'] ?: null,
            'ifsc_code' => $row['ifsc_code'] ?: null,
            'account_number' => $row['account_number'] ?: null
        ];
    }

    $stmt->close();
    $conn->close();

    // Log number of employees returned
    file_put_contents(__DIR__ . '/debug.log', "Employees returned: " . count($employees) . "\n", FILE_APPEND);

    // Send response
    ob_end_clean();
    echo json_encode(['employees' => $employees]);

} catch (Exception $e) {
    $conn->close();
    ob_end_clean();
    file_put_contents(__DIR__ . '/debug.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
?>