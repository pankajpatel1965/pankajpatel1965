<?php
include 'db_connect.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM company_details LIMIT 1"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company ? $company['company_name'] : 'Employee Management System'; ?> - <?php echo $page_title ?? 'Dashboard'; ?></title>
    <!-- Corrected Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f7fa;
            margin: 0;
            overflow-x: hidden;
        }
        .header {
            background-color: #2c3e50;
            color: #ecf0f1;
            text-align: center;
            padding: 15px 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header .hamburger {
            display: none;
            font-size: 24px;
            cursor: pointer;
            padding-left: 15px;
        }
        .sidebar {
            background-color: #34495e;
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 60px;
            left: 0;
            padding-top: 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            z-index: 999;
        }
        .sidebar a {
            color: #ecf0f1;
            padding: 15px;
            display: block;
            text-decoration: none;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .sidebar a:hover {
            background-color: #3498db;
            color: #fff;
        }
        .sidebar a.active {
            background-color: #3498db;
            color: #fff;
            font-weight: 700;
        }
        .content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }
        .sidebar.active + .sidebar-overlay {
            display: block;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #218838;
        }
        .btn-secondary {
            background-color: #7f8c8d;
            border-color: #7f8c8d;
        }
        .btn-warning {
            background-color: #e67e22;
            border-color: #e67e22;
        }
        .btn-danger {
            background-color: #e74c3c;
            border-color: #e74c3c;
        }
        .table thead th {
            background-color: #34495e;
            color: #ecf0f1;
        }
        .table tbody tr:hover {
            background-color: #ecf0f1;
        }
        .form-control, .form-select {
            border-radius: 5px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .form-label {
            font-weight: 500;
            color: #34495e;
        }
        .shadow-sm {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .content {
                margin-left: 0;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .content.active {
                transform: translateX(100%);
                opacity: 0;
            }
            .header .hamburger {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <span class="hamburger d-flex text-center"><i class="fas fa-bars"></i></span>
        <h4>Salary Management System</h4>
        <span style="width: 40px;"></span> <!-- Spacer for symmetry -->
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="index.php" class="<?php echo ($page_title === 'Dashboard') ? 'active' : ''; ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a href="add_employee.php" class="<?php echo ($page_title === 'Add Employee') ? 'active' : ''; ?>"><i class="fas fa-user-plus me-2"></i>Add Employee</a>
        <a href="add_salary.php" class="<?php echo ($page_title === 'Add Salary') ? 'active' : ''; ?>"><i class="fas fa-money-bill-wave me-2"></i>Add Salary</a>
        <a href="salary.php" class="<?php echo ($page_title === 'View Salaries') ? 'active' : ''; ?>"><i class="fas fa-table me-2"></i>View Salaries</a>
        <a href="final_sheet.php" class="<?php echo ($page_title === 'Final Sheet') ? 'active' : ''; ?>"><i class="fas fa-file-alt me-2"></i>Final Sheet</a>
        <a href="salary_slip.php" class="<?php echo ($page_title === 'Generate Salary Slips') ? 'active' : ''; ?>"><i class="fas fa-file-invoice me-2"></i>Salary Slips</a>
        <a href="manage_company.php" class="<?php echo ($page_title === 'Manage Company') ? 'active' : ''; ?>"><i class="fas fa-cogs me-2"></i>Manage Company</a>
        <a href="logout.php" class="<?php echo ($page_title === 'Logout') ? 'active' : ''; ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <!-- Overlay for sidebar -->
    <div class="sidebar-overlay"></div>

    <!-- Main Content -->
    <div class="content">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.querySelector('.hamburger');
    const sidebar = document.querySelector('.sidebar');
    const content = document.querySelector('.content');
    const overlay = document.querySelector('.sidebar-overlay');

    // Toggle sidebar and content visibility
    hamburger.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        content.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    // Close sidebar when clicking on overlay
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        content.classList.remove('active');
        overlay.classList.remove('active');
    });

    // Close sidebar when clicking a link on mobile
    sidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                content.classList.remove('active');
                overlay.classList.remove('active');
            }
        });
    });

    // Ensure content is visible on page load if sidebar is closed
    if (!sidebar.classList.contains('active')) {
        content.classList.remove('active');
    }
});
</script>