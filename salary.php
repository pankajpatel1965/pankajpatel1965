<?php
ob_start();
session_start();
include 'db_connect.php';

// PHPSpreadsheet
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Logged in?
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// Company for header
$company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM company_details LIMIT 1"));

/* ------------------ EXPORT (unchanged logic) ------------------ */
if (isset($_POST['export_excel']) && isset($_POST['month_year']) && !empty($_POST['month_year'])) {
    $month_year  = $_POST['month_year'];
    $search      = isset($_POST['search']) ? mysqli_real_escape_string($conn, $_POST['search']) : '';
    $sort_order  = isset($_POST['sort_order']) ? $_POST['sort_order'] : 'desc';
    $filter_type = isset($_POST['filter_type']) ? $_POST['filter_type'] : 'all';

    $conditions = ["s.month_year = ?"];
    $params = [$month_year];
    $types = "s";
    if ($search) { $conditions[] = "e.name LIKE ?"; $params[] = "%$search%"; $types .= "s"; }
    if ($filter_type !== 'all') {
        if ($filter_type === 'Cash')         { $conditions[] = "e.payment_type = 'cash'"; }
        elseif ($filter_type === 'HDFC Bank'){ $conditions[] = "e.payment_type = 'bank' AND e.bank_name = 'HDFC'"; }
        elseif ($filter_type === 'Apprentice'){ $conditions[] = "e.payment_type = 'bank' AND e.bank_name = 'Apprentice'"; }
        elseif ($filter_type === 'Other Bank'){ $conditions[] = "e.payment_type = 'bank' AND e.bank_name = 'Other'"; }
    }

    $export_query = "SELECT e.id AS employee_id, s.id AS salary_id, e.name, s.month_year, s.total_salary, s.extra_days, s.extra, s.bonus,
                     s.leave_full_days, s.leave_half_days, s.pf, s.late_charge, s.deposit, s.withdrawal, s.uniform, s.professional_tax,
                     e.payment_type, e.bank_name, s.gross_salary, s.net_pay
                     FROM employees e
                     JOIN salaries s ON e.id = s.employee_id";
    if (!empty($conditions)) { $export_query .= " WHERE " . implode(" AND ", $conditions); }
    $export_query .= " ORDER BY s.net_pay " . ($sort_order === 'asc' ? 'ASC' : 'DESC');

    $export_stmt = $conn->prepare($export_query);
    if (!empty($params)) { $export_stmt->bind_param($types, ...$params); }
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();

    if ($export_result->num_rows === 0) { echo "No data available to export."; exit(); }

    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'SR','Name','Month-Year','Basic Salary','Extra Days','Extra Days Salary','Extra','Bonus',
            'Leave Full Days','Leave Half Days','Leave Deduction','Gross Salary','Provident Fund',
            'Professional Tax','Late Charge','Deposit','Withdrawal','Uniform','Net Salary','Payment Type'
        ];
        $sheet->fromArray($headers, NULL, 'A1');

        $row_number = 2; $sr = 1;
        while ($row = $export_result->fetch_assoc()) {
            $basic_salary = (int)round($row['total_salary'] ?? 0, 0);
            $daily_rate = $basic_salary / 30;
            $extra_days = $row['extra_days'] ?? 0;
            $extra = (int)round($row['extra'] ?? 0, 0);
            $bonus = (int)round($row['bonus'] ?? 0, 0);
            $leave_full_days = $row['leave_full_days'] ?? 0;
            $leave_half_days = $row['leave_half_days'] ?? 0;
            $late_charge = (int)round($row['late_charge'] ?? 0, 0);
            $deposit = (int)round($row['deposit'] ?? 0, 0);
            $withdrawal = (int)round($row['withdrawal'] ?? 0, 0);
            $uniform = (int)round($row['uniform'] ?? 0, 0);
            $pf = (int)round($row['pf'] ?? 0, 0);
            $professional_tax = (int)round($row['professional_tax'] ?? 0, 0);
            $gross_salary = (int)round($row['gross_salary'] ?? 0, 0);
            $net_pay = (int)round($row['net_pay'] ?? 0, 0);

            $extra_days_salary = (int)round($daily_rate * $extra_days, 0);
            $leave_deduction   = (int)round(($leave_full_days + $leave_half_days * 0.5) * $daily_rate, 0);
            $display_payment_type = $row['payment_type'] === 'cash' ? 'Cash' : ($row['bank_name'] ? $row['bank_name'].' Bank' : 'Bank');

            $sheet->fromArray([
                $sr++, $row['name'], $row['month_year'], $basic_salary, $extra_days, $extra_days_salary, $extra, $bonus,
                $leave_full_days, $leave_half_days, $leave_deduction, $gross_salary, $pf, $professional_tax,
                $late_charge, $deposit, $withdrawal, $uniform, $net_pay, $display_payment_type
            ], NULL, 'A'.$row_number);
            $row_number++;
        }

        foreach (range('A','T') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

        $writer = new Xlsx($spreadsheet);
        $filename = "Salaries_$month_year.xlsx";
        ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $export_stmt->close();
        exit();
    } catch (Exception $e) {
        error_log("Error generating Excel: " . $e->getMessage());
        echo "An error occurred while generating the Excel file.";
        exit();
    }
}

/* ------------------ PAGE DATA (unchanged logic) ------------------ */
$current_month_year = date('Y-m', strtotime('first day of previous month'));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$salaries_per_page = 10;

$month_year  = $_POST['month_year']  ?? ($_GET['month_year']  ?? $current_month_year);
$search      = isset($_POST['search']) ? mysqli_real_escape_string($conn, $_POST['search']) : (isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '');
$sort_order  = $_POST['sort_order']  ?? ($_GET['sort_order']  ?? 'desc');
$filter_type = $_POST['filter_type'] ?? ($_GET['filter_type'] ?? 'all');

$conditions = []; $params = []; $types = "";
if ($month_year) { $conditions[] = "s.month_year = ?"; $params[] = $month_year; $types .= "s"; }
if ($search)     { $conditions[] = "e.name LIKE ?";    $params[] = "%$search%"; $types .= "s"; }
if ($filter_type !== 'all') {
    if ($filter_type === 'Cash')         { $conditions[] = "e.payment_type = 'cash'"; }
    elseif ($filter_type === 'HDFC Bank'){ $conditions[] = "e.payment_type = 'bank' AND e.bank_name = 'HDFC'"; }
    elseif ($filter_type === 'Apprentice'){ $conditions[] = "e.payment_type = 'bank' AND e.bank_name = 'Apprentice'"; }
    elseif ($filter_type === 'Other Bank'){ $conditions[] = "e.payment_type = 'bank' AND e.bank_name = 'Other'"; }
}

// Count
$count_query = "SELECT COUNT(*) as total FROM employees e JOIN salaries s ON e.id = s.employee_id";
if (!empty($conditions)) { $count_query .= " WHERE " . implode(" AND ", $conditions); }
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_data = $count_result->fetch_assoc();
$total_salaries = $count_data['total'];
$total_pages = ceil($total_salaries / $salaries_per_page);
$page = max(1, min($page, max(1, $total_pages)));
$offset = ($page - 1) * $salaries_per_page;

// Totals
$total_query = "SELECT 
    SUM(s.total_salary) AS total_basic_salary,
    SUM(s.extra) AS total_extra,
    SUM(s.bonus) AS total_bonus,
    SUM(s.pf) AS total_pf,
    SUM(s.professional_tax) AS total_professional_tax,
    SUM(s.late_charge) AS total_late_charge,
    SUM(s.deposit) AS total_deposit,
    SUM(s.withdrawal) AS total_withdrawal,
    SUM(s.uniform) AS total_uniform,
    SUM(s.gross_salary) AS total_gross_salary,
    SUM(s.net_pay) AS total_net_pay,
    SUM(ROUND((s.leave_full_days + s.leave_half_days * 0.5) * (s.total_salary / 30))) AS total_leave_deduction,
    SUM(ROUND((s.total_salary / 30) * s.extra_days)) AS total_extra_days_salary,
    SUM(s.extra_days) AS total_extra_days,
    SUM(s.leave_full_days) AS total_leave_full_days,
    SUM(s.leave_half_days) AS total_leave_half_days
FROM employees e 
JOIN salaries s ON e.id = s.employee_id";
if (!empty($conditions)) { $total_query .= " WHERE " . implode(" AND ", $conditions); }
$total_stmt = $conn->prepare($total_query);
if (!empty($params)) { $total_stmt->bind_param($types, ...$params); }
$total_stmt->execute();
$totals = $total_stmt->get_result()->fetch_assoc();

$total_basic_salary       = round($totals['total_basic_salary'] ?? 0, 0);
$total_extra              = round($totals['total_extra'] ?? 0, 0);
$total_bonus              = round($totals['total_bonus'] ?? 0, 0);
$total_leave_deduction    = round($totals['total_leave_deduction'] ?? 0, 0);
$total_gross_salary       = round($totals['total_gross_salary'] ?? 0, 0);
$total_pf                 = round($totals['total_pf'] ?? 0, 0);
$total_professional_tax   = round($totals['total_professional_tax'] ?? 0, 0);
$total_late_charge        = round($totals['total_late_charge'] ?? 0, 0);
$total_deposit            = round($totals['total_deposit'] ?? 0, 0);
$total_withdrawal         = round($totals['total_withdrawal'] ?? 0, 0);
$total_uniform            = round($totals['total_uniform'] ?? 0, 0);
$total_net_pay            = round($totals['total_net_pay'] ?? 0, 0);
$total_extra_days_salary  = round($totals['total_extra_days_salary'] ?? 0, 0);
$total_extra_days         = round($totals['total_extra_days'] ?? 0, 0);
$total_leave_full_days    = round($totals['total_leave_full_days'] ?? 0, 0);
$total_leave_half_days    = round($totals['total_leave_half_days'] ?? 0, 0);

// Data (paged)
$query = "SELECT e.id AS employee_id, s.id AS salary_id, e.name, s.month_year, s.total_salary, s.extra_days, s.extra, s.bonus, 
          s.leave_full_days, s.leave_half_days, s.pf, s.late_charge, s.deposit, s.withdrawal, s.uniform, s.professional_tax, 
          e.payment_type, e.bank_name, s.gross_salary, s.net_pay 
          FROM employees e 
          JOIN salaries s ON e.id = s.employee_id";
if (!empty($conditions)) { $query .= " WHERE " . implode(" AND ", $conditions); }
$query .= " ORDER BY s.net_pay " . ($sort_order === 'asc' ? 'ASC' : 'DESC') . " LIMIT ?, ?";
$params2 = $params; $types2 = $types . "ii"; $params2[] = $offset; $params2[] = $salaries_per_page;

$stmt = $conn->prepare($query);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();

$data_rows = [];
while ($row = $result->fetch_assoc()) {
    $basic_salary    = (int)round($row['total_salary'] ?? 0, 0);
    $daily_rate      = $basic_salary / 30;
    $extra_days      = $row['extra_days'] ?? 0;
    $extra           = (int)round($row['extra'] ?? 0, 0);
    $bonus           = (int)round($row['bonus'] ?? 0, 0);
    $leave_full_days = $row['leave_full_days'] ?? 0;
    $leave_half_days = $row['leave_half_days'] ?? 0;
    $late_charge     = (int)round($row['late_charge'] ?? 0, 0);
    $deposit         = (int)round($row['deposit'] ?? 0, 0);
    $withdrawal      = (int)round($row['withdrawal'] ?? 0, 0);
    $uniform         = (int)round($row['uniform'] ?? 0, 0);
    $pf              = (int)round($row['pf'] ?? 0, 0);
    $professional_tax= (int)round($row['professional_tax'] ?? 0, 0);
    $gross_salary    = (int)round($row['gross_salary'] ?? 0, 0);
    $net_pay         = (int)round($row['net_pay'] ?? 0, 0);

    $extra_days_salary = (int)round($daily_rate * $extra_days, 0);
    $leave_deduction   = (int)round(($leave_full_days + $leave_half_days * 0.5) * $daily_rate, 0);
    $display_payment_type = $row['payment_type'] === 'cash' ? 'Cash' : ($row['bank_name'] ? $row['bank_name'].' Bank' : 'Bank');

    $data_rows[] = [
        'employee_id' => $row['employee_id'],
        'salary_id'   => $row['salary_id'],
        'name'        => $row['name'],
        'month_year'  => $row['month_year'],
        'basic_salary'=> $basic_salary,
        'extra_days'  => $extra_days,
        'extra_days_salary' => $extra_days_salary,
        'extra'       => $extra,
        'bonus'       => $bonus,
        'leave_full_days' => $leave_full_days,
        'leave_half_days' => $leave_half_days,
        'leave_deduction' => $leave_deduction,
        'gross_salary'    => $gross_salary,
        'pf'              => $pf,
        'professional_tax'=> $professional_tax,
        'late_charge'     => $late_charge,
        'deposit'         => $deposit,
        'withdrawal'      => $withdrawal,
        'uniform'         => $uniform,
        'net_pay'         => $net_pay,
        'payment_type'    => $display_payment_type
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $company ? htmlspecialchars($company['company_name']) : 'Payroll Management'; ?> - View Salaries</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Fonts & libs -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            overflow-x: hidden;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color:#fff; padding:15px 20px; position:fixed; top:0; width:100%; z-index:1000;
            box-shadow:0 4px 20px rgba(0,0,0,.1); backdrop-filter: blur(10px);
        }
        .header-content { display:flex; justify-content:space-between; align-items:center; }
        .hamburger { display:none; font-size:20px; cursor:pointer; padding:10px; border-radius:8px; transition:.3s; }
        .hamburger:hover { background: rgba(255,255,255,.1); }
        .header-title { font-size:1.4rem; font-weight:600; display:flex; align-items:center; gap:10px; }
        .user-profile { display:flex; align-items:center; gap:10px; padding:8px 15px; border-radius:25px; background: rgba(255,255,255,.1); }
        .user-profile:hover { background: rgba(255,255,255,.2); }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            height:100vh; width:280px; position:fixed; top:70px; left:0; padding:20px 0;
            box-shadow:4px 0 20px rgba(0,0,0,.1); transition:transform .3s ease; z-index:999; overflow-y:auto;
        }
        .sidebar-brand { padding:0 25px 20px; border-bottom:1px solid rgba(255,255,255,.1); margin-bottom:20px; }
        .sidebar-brand h5 { color:#ecf0f1; font-weight:600; margin:0; }
        .sidebar-menu { list-style:none; padding:0; }
        .sidebar-menu li { margin:5px 15px; }
        .sidebar-menu a {
            color:#bdc3c7; padding:12px 15px; display:flex; align-items:center; gap:12px;
            text-decoration:none; border-radius:10px; transition:.3s; font-weight:500;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color:#fff; transform: translateX(5px);
            box-shadow:0 4px 15px rgba(52,152,219,.3);
        }
        .sidebar-menu a i { width:20px; font-size:16px; }

        /* Overlay (mobile) */
        .sidebar-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.5); z-index:998; }
        .sidebar.active + .sidebar-overlay { display:block; }

        /* Content */
        .content { margin-left:280px; margin-top:70px; padding:30px; min-height:calc(100vh - 70px); transition: margin-left .3s; }

        /* Cards / Controls */
        .filter-card, .table-card {
            background:#fff; border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,.1);
            position:relative; overflow:hidden;
        }
        .filter-card::before, .table-card::before {
            content:''; position:absolute; top:0; left:0; width:100%; height:5px;
            background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .filter-card { padding:24px; }
        .table-card { padding:28px; }

        .form-label { font-weight:600; color:#2c3e50; }
        .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border:none; color:#fff; border-radius:12px 0 0 12px;
        }
        .form-control, .form-select {
            border:2px solid #e9ecef; border-radius:12px; padding:12px 16px; background:#f8f9fa; transition:.3s;
        }
        .input-group .form-control, .input-group .form-select { border-left:none; border-radius:0 12px 12px 0; }
        .form-control:focus, .form-select:focus { border-color:#667eea; box-shadow:0 0 0 .2rem rgba(102,126,234,.25); background:#fff; }

        /* Table */
        .table-wrap { position:relative; overflow-x:auto; max-height:60vh; border-radius:14px; }
        table thead th {
            position: sticky; top: 0; z-index: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color:#fff; border-color: transparent !important;
        }
        table tbody tr:hover { background:#f6f8ff; }
        .badge-cash{ background:#16a085; }
        .badge-hdfc{ background:#e74c3c; }
        .badge-apprentice{ background:#8e44ad; }
        .badge-other{ background:#2980b9; }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border:none; padding:10px 18px; border-radius:10px; font-weight:600;
            box-shadow:0 4px 15px rgba(102,126,234,.3);
        }
        .btn-success { border-radius:10px; font-weight:600; }

        /* Fancy confirm modal styling + animations */
        .fancy-modal {
          border: none;
          border-radius: 18px;
          overflow: hidden;
          background:
            radial-gradient(1200px 300px at 50% -10%, rgba(118,75,162,0.12), transparent 70%),
            radial-gradient(1000px 400px at 120% 120%, rgba(102,126,234,0.10), transparent 70%),
            #ffffff;
          box-shadow: 0 24px 60px rgba(0,0,0,.18);
          transform-origin: center;
          animation: modalPop .35s cubic-bezier(.2,.8,.2,1);
        }
        .fancy-modal .icon-wrap {
          width: 68px; height: 68px;
          border-radius: 50%;
          margin: 0 auto;
          display: grid; place-items: center;
          background: radial-gradient(circle at 30% 30%, #ffe3e3, #ffd6d6);
          color: #e74c3c;
          box-shadow: 0 10px 24px rgba(231, 76, 60, .25), inset 0 0 0 6px rgba(231, 76, 60, .08);
          animation: iconDrop .5s ease-out;
        }
        .fancy-modal .icon-wrap i { font-size: 26px; }

        .modal.fade .modal-dialog {
          transform: translateY(10px) scale(.98);
          transition: transform .25s ease;
        }
        .modal.show .modal-dialog {
          transform: translateY(0) scale(1);
        }

        @keyframes modalPop {
          0%   { transform: scale(.92); opacity: 0; }
          100% { transform: scale(1); opacity: 1; }
        }
        @keyframes iconDrop {
          0%   { transform: translateY(-10px) scale(.9); opacity: 0; }
          100% { transform: translateY(0) scale(1); opacity: 1; }
        }

        /* Responsive */
        @media (max-width:768px){
            .sidebar{ transform: translateX(-280px); }
            .sidebar.active{ transform: translateX(0); }
            .content{ margin-left:0; padding:20px 15px; }
            .hamburger{ display:inline-block; }
            .header-title{ font-size:1.1rem; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div style="display:flex; align-items:center; gap:15px;">
                <span class="hamburger"><i class="fas fa-bars"></i></span>
                <div class="header-title">
                    <i class="fas fa-table"></i>
                    <?php echo $company ? htmlspecialchars($company['company_name']) : 'Payroll Management'; ?>
                </div>
            </div>
            <div class="user-profile">
                <i class="fas fa-user-circle" style="font-size:1.2rem;"></i>
                <span>Admin</span>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <h5><i class="fas fa-building me-2"></i>Control Panel</h5>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
            <li><a href="employee_list.php"><i class="fas fa-users"></i>Employee List</a></li>
            <li><a href="add_employee.php"><i class="fas fa-user-plus"></i>Add Employee</a></li>
            <li><a href="add_salary.php"><i class="fas fa-money-bill-wave"></i>Add Salary</a></li>
            <li><a href="salary.php" class="active"><i class="fas fa-table"></i>View Salaries</a></li>
            <li><a href="final_sheet.php"><i class="fas fa-file-alt"></i>Final Sheet</a></li>
            <li><a href="salary_slip.php"><i class="fas fa-file-invoice"></i>Salary Slips</a></li>
            <li><a href="manage_company.php"><i class="fas fa-cogs"></i>Manage Company</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
        </ul>
    </div>

    <!-- Overlay for mobile -->
    <div class="sidebar-overlay"></div>

    <!-- Content -->
    <div class="content">

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-xl-10 col-lg-11 mx-auto">
                <div class="filter-card">
                    <form method="POST" id="filterForm" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="month_year" class="form-label">Filter by Month & Year</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                <input type="month" id="month_year" name="month_year" class="form-control" value="<?php echo htmlspecialchars($month_year); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search by Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" id="search" name="search" class="form-control" placeholder="Enter name" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="filter_type" class="form-label">Filter by Type</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-filter"></i></span>
                                <select id="filter_type" name="filter_type" class="form-select">
                                    <option value="all"       <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="Cash"      <?php echo $filter_type === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="HDFC Bank" <?php echo $filter_type === 'HDFC Bank' ? 'selected' : ''; ?>>HDFC Bank</option>
                                    <option value="Apprentice"<?php echo $filter_type === 'Apprentice' ? 'selected' : ''; ?>>Apprentice</option>
                                    <option value="Other Bank"<?php echo $filter_type === 'Other Bank' ? 'selected' : ''; ?>>Other Bank</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="sort_order" class="form-label">Sort by Salary</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-sort-amount-down-alt"></i></span>
                                <select id="sort_order" name="sort_order" class="form-select">
                                    <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>High to Low</option>
                                    <option value="asc"  <?php echo $sort_order === 'asc'  ? 'selected' : ''; ?>>Low to High</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" name="export_excel" class="btn btn-success">
                                <i class="fas fa-file-excel me-2"></i> Export to Excel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="row">
            <div class="col-xl-12">
                <div class="table-card">
                    <h4 class="mb-3"><i class="fas fa-list me-2"></i>Employee Salaries</h4>

                    <div class="table-wrap">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th>SR</th>
                                    <th>Name</th>
                                    <th>Month-Year</th>
                                    <th>Basic Salary</th>
                                    <th>Extra Days</th>
                                    <th>Extra Days Salary (₹)</th>
                                    <th>Extra</th>
                                    <th>Bonus</th>
                                    <th>Leave Full</th>
                                    <th>Leave Half</th>
                                    <th>Leave Deduction</th>
                                    <th>Gross Salary</th>
                                    <th>PF</th>
                                    <th>Prof. Tax</th>
                                    <th>Late</th>
                                    <th>Deposit</th>
                                    <th>Withdrawal</th>
                                    <th>Uniform</th>
                                    <th>Net Salary</th>
                                    <th>Payment Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $sr = $offset + 1;
                                foreach ($data_rows as $row) { 
                                    $badgeClass = 'badge-other';
                                    if ($row['payment_type'] === 'Cash') $badgeClass = 'badge-cash';
                                    elseif ($row['payment_type'] === 'HDFC Bank') $badgeClass = 'badge-hdfc';
                                    elseif ($row['payment_type'] === 'Apprentice') $badgeClass = 'badge-apprentice';
                                ?>
                                <tr>
                                    <td><?php echo $sr++; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['month_year']); ?></td>
                                    <td><?php echo "₹" . number_format($row['basic_salary'], 0); ?></td>
                                    <td><?php echo $row['extra_days'] ? (int)$row['extra_days'] : '-'; ?></td>
                                    <td><?php echo $row['extra_days_salary'] ? "₹" . number_format($row['extra_days_salary'], 0) : '-'; ?></td>
                                    <td><?php echo $row['extra'] ? "₹" . number_format($row['extra'], 0) : '-'; ?></td>
                                    <td><?php echo $row['bonus'] ? "₹" . number_format($row['bonus'], 0) : '-'; ?></td>
                                    <td><?php echo $row['leave_full_days'] ? (int)$row['leave_full_days'] : '-'; ?></td>
                                    <td><?php echo $row['leave_half_days'] ? (int)$row['leave_half_days'] : '-'; ?></td>
                                    <td><?php echo $row['leave_deduction'] ? "₹" . number_format($row['leave_deduction'], 0) : '-'; ?></td>
                                    <td><?php echo "₹" . number_format($row['gross_salary'], 0); ?></td>
                                    <td><?php echo $row['pf'] ? "₹" . number_format($row['pf'], 0) : '-'; ?></td>
                                    <td><?php echo $row['professional_tax'] ? "₹" . number_format($row['professional_tax'], 0) : '-'; ?></td>
                                    <td><?php echo $row['late_charge'] ? "₹" . number_format($row['late_charge'], 0) : '-'; ?></td>
                                    <td><?php echo $row['deposit'] ? "₹" . number_format($row['deposit'], 0) : '-'; ?></td>
                                    <td><?php echo $row['withdrawal'] ? "₹" . number_format($row['withdrawal'], 0) : '-'; ?></td>
                                    <td><?php echo $row['uniform'] ? "₹" . number_format($row['uniform'], 0) : '-'; ?></td>
                                    <td><strong><?php echo "₹" . number_format($row['net_pay'], 0); ?></strong></td>
                                    <td>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($row['payment_type']); ?></span>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="edit_salary.php?id=<?php echo $row['salary_id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <!-- NEW: Fancy delete trigger -->
                                        <a href="#"
                                           class="btn btn-danger btn-sm js-delete-salary"
                                           data-delete-url="delete_salary.php?id=<?php echo $row['salary_id']; ?>"
                                           title="Delete">
                                           <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_basic_salary, 0); ?></strong></td>
                                    <td><strong><?php echo $total_extra_days; ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_extra_days_salary, 0); ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_extra, 0); ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_bonus, 0); ?></strong></td>
                                    <td><strong><?php echo $total_leave_full_days; ?></strong></td>
                                    <td><strong><?php echo $total_leave_half_days; ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_leave_deduction, 0); ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_gross_salary, 0); ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_pf, 0); ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_professional_tax, 0); ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_late_charge, 0); ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_deposit, 0); ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_withdrawal, 0); ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_uniform, 0); ?></strong></td>
                                    <td><strong><?php echo "₹" . number_format($total_net_pay, 0); ?></strong></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1) { ?>
                    <nav aria-label="Salary list pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="#" onclick="<?php echo $page > 1 ? 'preserveFilters(' . ($page - 1) . ')' : 'return false'; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php
                            $range = 2;
                            $start = max(1, $page - $range);
                            $end   = min($total_pages, $page + $range);

                            if ($start > 2) {
                                echo '<li class="page-item"><a class="page-link" href="#" onclick="preserveFilters(1)">1</a></li>';
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            for ($i = $start; $i <= $end; $i++) {
                                echo '<li class="page-item '.($page==$i?'active':'').'"><a class="page-link" href="#" onclick="preserveFilters('.$i.')">'.$i.'</a></li>';
                            }
                            if ($end < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                echo '<li class="page-item"><a class="page-link" href="#" onclick="preserveFilters('.$total_pages.')">'.$total_pages.'</a></li>';
                            }
                            ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="#" onclick="<?php echo $page < $total_pages ? 'preserveFilters(' . ($page + 1) . ')' : 'return false'; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div><!-- /.content -->

    <!-- Fancy Confirm Modal (shared) -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true" aria-labelledby="confirmDeleteLabel">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content fancy-modal">
          <div class="modal-body text-center p-4">
            <div class="icon-wrap mb-3">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h5 id="confirmDeleteLabel" class="mb-2 fw-bold">Delete this record?</h5>
            <p class="text-muted mb-4">This action cannot be undone.</p>
            <div class="d-flex gap-2 justify-content-center">
              <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
              <a id="confirmDeleteBtn" href="#" class="btn btn-danger px-4">Yes, delete</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Sidebar toggle + Filters + Animated delete modal
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.querySelector('.hamburger');
        const sidebar   = document.querySelector('.sidebar');
        const overlay   = document.querySelector('.sidebar-overlay');
        if (hamburger) {
            hamburger.addEventListener('click', function () {
                sidebar.classList.toggle('active');
                overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
            });
        }
        if (overlay) {
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('active');
                overlay.style.display = 'none';
            });
        }

        // Filter auto-submit
        const form            = document.getElementById('filterForm');
        const monthYearInput  = document.getElementById('month_year');
        const searchInput     = document.getElementById('search');
        const sortOrderInput  = document.getElementById('sort_order');
        const filterTypeInput = document.getElementById('filter_type');

        let debounceTimeout;
        function submitForm() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.set('month_year', monthYearInput.value);
                url.searchParams.set('search', searchInput.value);
                url.searchParams.set('sort_order', sortOrderInput.value);
                url.searchParams.set('filter_type', filterTypeInput.value);
                url.searchParams.set('page', 1);
                window.history.pushState({}, '', url);
                form.submit();
            }, 300);
        }

        monthYearInput.addEventListener('change', function(){ if (monthYearInput.value) submitForm(); });
        searchInput.addEventListener('input', submitForm);
        sortOrderInput.addEventListener('change', submitForm);
        filterTypeInput.addEventListener('change', submitForm);

        const currentSearchValue = searchInput.value;
        if (currentSearchValue) { searchInput.focus(); searchInput.setSelectionRange(currentSearchValue.length, currentSearchValue.length); }

        window.preserveFilters = function(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            url.searchParams.set('month_year', monthYearInput.value);
            url.searchParams.set('search', searchInput.value);
            url.searchParams.set('sort_order', sortOrderInput.value);
            url.searchParams.set('filter_type', filterTypeInput.value);
            window.history.pushState({}, '', url);

            const addHidden = (name, val) => { const i = document.createElement('input'); i.type='hidden'; i.name=name; i.value=val; form.appendChild(i); };
            addHidden('page', page);
            addHidden('month_year', monthYearInput.value);
            addHidden('search', searchInput.value);
            addHidden('sort_order', sortOrderInput.value);
            addHidden('filter_type', filterTypeInput.value);

            form.submit();
        }

        // --------- Animated Delete Modal (salary rows) ----------
        const deleteLinks = document.querySelectorAll('.js-delete-salary');
        const confirmBtn  = document.getElementById('confirmDeleteBtn');
        const modalEl     = document.getElementById('confirmDeleteModal');
        const bsModal     = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true });

        deleteLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.getAttribute('data-delete-url');
                confirmBtn.setAttribute('href', url);
                bsModal.show();
            });
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            confirmBtn.setAttribute('href', '#');
        });
    });
    </script>
</body>
</html>
<?php
$stmt->close();
$total_stmt->close();
$count_stmt->close();
?>
