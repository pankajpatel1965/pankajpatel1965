<?php
include 'db_connect.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// Get dashboard statistics
$total_employees_query = "SELECT COUNT(*) as count FROM employees";
$total_employees_result = mysqli_query($conn, $total_employees_query);
$total_employees = mysqli_fetch_assoc($total_employees_result)['count'];

$total_salary_query = "SELECT SUM(salary) as total FROM employees";
$total_salary_result = mysqli_query($conn, $total_salary_query);
$total_salary = mysqli_fetch_assoc($total_salary_result)['total'] ?? 0;

$payment_types_query = "SELECT payment_type, COUNT(*) as count, SUM(salary) as total_amount FROM employees GROUP BY payment_type";
$payment_types_result = mysqli_query($conn, $payment_types_query);
$payment_stats = [];
while ($row = mysqli_fetch_assoc($payment_types_result)) {
    $payment_stats[] = $row;
}

$positions_query = "SELECT position, COUNT(*) as count FROM employees GROUP BY position ORDER BY count DESC LIMIT 5";
$positions_result = mysqli_query($conn, $positions_query);
$position_stats = [];
while ($row = mysqli_fetch_assoc($positions_result)) {
    $position_stats[] = $row;
}

// Get recent employees
$recent_employees_query = "SELECT * FROM employees ORDER BY id DESC LIMIT 5";
$recent_employees_result = mysqli_query($conn, $recent_employees_query);

$page_title = "Dashboard";
$company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM company_details LIMIT 1"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company ? $company['company_name'] : 'Employee Management System'; ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            overflow-x: hidden;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 100%;
        }

        .hamburger {
            display: none;
            font-size: 20px;
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .hamburger:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .header-title {
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Sidebar Styles */
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            height: 100vh;
            width: 280px;
            position: fixed;
            top: 70px;
            left: 0;
            padding: 20px 0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            z-index: 999;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 0 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-brand h5 {
            color: #ecf0f1;
            font-weight: 600;
            margin: 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            margin: 5px 15px;
        }

        .sidebar-menu a {
            color: #bdc3c7;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            gap: 12px;
        }

        .sidebar-menu a:hover {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .sidebar-menu a i {
            width: 20px;
            font-size: 16px;
        }

        /* Content Area */
        .content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 70px);
        }

        /* Dashboard Cards */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stats-label {
            color: #7f8c8d;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Chart Card */
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            margin-top: 20px;
        }

        /* Table Styles */
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            margin-top: 20px;
        }

        .table-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .custom-table {
            margin: 0;
            background: white;
        }

        .custom-table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            border: none;
            padding: 15px 12px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .custom-table tbody tr {
            transition: all 0.3s ease;
        }

        .custom-table tbody tr:hover {
            background: #f8f9ff;
            transform: scale(1.01);
        }

        .custom-table tbody td {
            padding: 12px;
            vertical-align: middle;
            border-top: 1px solid #e9ecef;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-action {
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-280px);
            }
            
            .content {
                margin-left: 0;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .hamburger {
                display: block;
            }

            .header-title {
                font-size: 1.1rem;
            }

            .stats-card {
                margin-bottom: 20px;
            }

            .content {
                padding: 20px 15px;
            }
        }

        /* Sidebar Overlay */
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

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Custom Colors for Stats Cards */
        .stats-employees { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stats-salary { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .stats-cash { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stats-bank { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span class="hamburger">
                    <i class="fas fa-bars"></i>
                </span>
                <div class="header-title">
                    <i class="fas fa-chart-line"></i>
                    <?php echo $company ? $company['company_name'] : 'Payroll Management'; ?>
                </div>
            </div>
            <div class="header-actions">
                <div class="user-profile">
                    <i class="fas fa-user-circle" style="font-size: 1.2rem;"></i>
                    <span>Admin</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <h5><i class="fas fa-building me-2"></i>Control Panel</h5>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
            <li><a href="employee_list.php"><i class="fas fa-users"></i>Employee List</a></li>
            <li><a href="add_employee.php"><i class="fas fa-user-plus"></i>Add Employee</a></li>
            <li><a href="add_salary.php"><i class="fas fa-money-bill-wave"></i>Add Salary</a></li>
            <li><a href="salary.php"><i class="fas fa-table"></i>View Salaries</a></li>
            <li><a href="final_sheet.php"><i class="fas fa-file-alt"></i>Final Sheet</a></li>
            <li><a href="salary_slip.php"><i class="fas fa-file-invoice"></i>Salary Slips</a></li>
            <li><a href="manage_company.php"><i class="fas fa-cogs"></i>Manage Company</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
        </ul>
    </div>

    <!-- Overlay for sidebar -->
    <div class="sidebar-overlay"></div>

    <!-- Main Content -->
    <div class="content">
        <!-- Dashboard Stats -->
        <div class="row fade-in">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card">
                    <div class="stats-icon stats-employees">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($total_employees); ?></div>
                    <div class="stats-label">Total Employees</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card">
                    <div class="stats-icon stats-salary">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stats-number">₹<?php echo number_format($total_salary, 0); ?></div>
                    <div class="stats-label">Total Salary</div>
                </div>
            </div>

            <?php
            $cash_count = 0;
            $bank_count = 0;
            foreach ($payment_stats as $stat) {
                if ($stat['payment_type'] == 'cash') $cash_count = $stat['count'];
                if ($stat['payment_type'] == 'bank') $bank_count = $stat['count'];
            }
            ?>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card">
                    <div class="stats-icon stats-cash">
                        <i class="fas fa-money-bill"></i>
                    </div>
                    <div class="stats-number"><?php echo $cash_count; ?></div>
                    <div class="stats-label">Cash Payments</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stats-card">
                    <div class="stats-icon stats-bank">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="stats-number"><?php echo $bank_count; ?></div>
                    <div class="stats-label">Bank Transfers</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-lg-6">
                <div class="chart-card fade-in">
                    <h5 class="table-title mb-3">Payment Distribution</h5>
                    <canvas id="paymentChart"></canvas>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="chart-card fade-in">
                    <h5 class="table-title mb-3">Top Positions</h5>
                    <canvas id="positionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Employees Table -->
        <div class="table-card fade-in">
            <div class="table-header">
                <h5 class="table-title">Recent Employees</h5>
                <a href="employee_list.php" class="btn btn-primary">
                    <i class="fas fa-eye me-2"></i>View All
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Salary</th>
                            <th>Payment Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($recent_employees_result) > 0): ?>
                            <?php while ($employee = mysqli_fetch_assoc($recent_employees_result)): ?>
                                <tr>
                                    <td><strong>#<?php echo $employee['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?php echo htmlspecialchars($employee['position']); ?>
                                        </span>
                                    </td>
                                    <td><strong>₹<?php echo number_format($employee['salary']); ?></strong></td>
                                    <td>
                                        <?php if ($employee['payment_type'] == 'cash'): ?>
                                            <span class="badge bg-warning text-dark">Cash</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Bank</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" 
                                               class="btn btn-warning btn-action">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_employee.php?id=<?php echo $employee['id']; ?>" 
                                               class="btn btn-danger btn-action"
                                               onclick="return confirm('Are you sure?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-users fa-2x mb-3"></i><br>
                                    No employees found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <script>
        // Sidebar Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');

            hamburger.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.style.display = 'none';
            });

            // Payment Distribution Chart
            const paymentData = <?php echo json_encode($payment_stats); ?>;
            const paymentLabels = paymentData.map(item => item.payment_type.charAt(0).toUpperCase() + item.payment_type.slice(1));
            const paymentCounts = paymentData.map(item => item.count);

            new Chart(document.getElementById('paymentChart'), {
                type: 'doughnut',
                data: {
                    labels: paymentLabels,
                    datasets: [{
                        data: paymentCounts,
                        backgroundColor: ['#3498db', '#27ae60', '#f39c12', '#9b59b6'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Position Chart
            const positionData = <?php echo json_encode($position_stats); ?>;
            const positionLabels = positionData.map(item => item.position);
            const positionCounts = positionData.map(item => item.count);

            new Chart(document.getElementById('positionChart'), {
                type: 'bar',
                data: {
                    labels: positionLabels,
                    datasets: [{
                        label: 'Employees',
                        data: positionCounts,
                        backgroundColor: '#667eea',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>