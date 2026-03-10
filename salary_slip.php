<?php
ob_start();
session_start();
include 'db_connect.php';

// Composer autoload (FPDF/FPDI)
require __DIR__ . '/vendor/autoload.php';
use setasign\Fpdi\Fpdi;

// Logged in?
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// Company for header
$company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM company_details LIMIT 1"));

// Defaults
$current_month_year = date('Y-m');
$page              = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page  = 10;
$offset            = ($page - 1) * $records_per_page;

$month_year  = $_POST['month_year']  ?? ($_GET['month_year']  ?? $current_month_year);
$filter_type = $_POST['filter_type'] ?? ($_GET['filter_type'] ?? '');
$search      = isset($_POST['search']) ? mysqli_real_escape_string($conn, $_POST['search']) : (isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '');

// Build base query + filters
$employees_data = [];
$query      = "";
$conditions = [];
$params     = [];
$types      = "";

if ($month_year) {
    $query  = "SELECT e.id, e.uan_no, e.name, e.payment_type, e.bank_name, s.month_year, s.value_date, s.total_salary, 
                      s.extra_days, s.extra, s.bonus, s.leave_full_days, s.leave_half_days, s.pf, s.professional_tax, 
                      s.late_charge, s.deposit, s.withdrawal, s.uniform, s.gross_salary, s.net_pay, s.extra_days_salary 
               FROM employees e 
               JOIN salaries s ON e.id = s.employee_id 
               WHERE s.month_year = ?";
    $params = [$month_year];
    $types  = "s";

    // Map the same semantics you already use: '', cash, hdfc, apprentice, other
    if ($filter_type) {
        if ($filter_type === 'cash') {
            $conditions[] = "e.payment_type = ?";
            $params[] = 'cash';
            $types   .= "s";
        } elseif ($filter_type === 'hdfc') {
            $conditions[] = "e.payment_type = ? AND e.bank_name = ?";
            $params[] = 'bank';
            $params[] = 'HDFC';
            $types   .= "ss";
        } elseif ($filter_type === 'apprentice') {
            $conditions[] = "e.payment_type = ? AND e.bank_name = ?";
            $params[] = 'bank';
            $params[] = 'Apprentice';
            $types   .= "ss";
        } elseif ($filter_type === 'other') {
            $conditions[] = "e.payment_type = ? AND e.bank_name = ?";
            $params[] = 'bank';
            $params[] = 'Other';
            $types   .= "ss";
        }
    }

    if ($search) {
        $conditions[] = "e.name LIKE ?";
        $params[] = "%$search%";
        $types   .= "s";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    /* ---------- Count for pagination ---------- */
    $count_query = "SELECT COUNT(*) AS total 
                    FROM employees e 
                    JOIN salaries s ON e.id = s.employee_id 
                    WHERE s.month_year = ?";
    if (!empty($conditions)) {
        $count_query .= " AND " . implode(" AND ", $conditions);
    }
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_data   = $count_result->fetch_assoc();
    $total_records = (int)($count_data['total'] ?? 0);
    $total_pages   = max(1, (int)ceil($total_records / $records_per_page));

    // Keep page within range
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $records_per_page;

    /* ---------- Paged data for table ---------- */
    $display_query  = $query . " ORDER BY e.name LIMIT ?, ?";
    $display_params = array_merge($params, [$offset, $records_per_page]);
    $display_types  = $types . "ii";

    $stmt = $conn->prepare($display_query);
    $stmt->bind_param($display_types, ...$display_params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $basic_salary       = (int)round($row['total_salary'] ?? 0, 0);
        $daily_rate         = $basic_salary / 30;
        $extra_days         = (int)($row['extra_days'] ?? 0);
        $extra              = (int)round($row['extra'] ?? 0, 0);
        $bonus              = (int)round($row['bonus'] ?? 0, 0);
        $leave_full_days    = (float)($row['leave_full_days'] ?? 0);
        $leave_half_days    = (float)($row['leave_half_days'] ?? 0);
        $late_charge        = (int)round($row['late_charge'] ?? 0, 0);
        $deposit            = (int)round($row['deposit'] ?? 0, 0);
        $withdrawal         = (int)round($row['withdrawal'] ?? 0, 0);
        $uniform            = (int)round($row['uniform'] ?? 0, 0);
        $pf                 = (int)round($row['pf'] ?? 0, 0);
        $professional_tax   = (int)round($row['professional_tax'] ?? 0, 0);
        $gross_salary       = (int)round($row['gross_salary'] ?? 0, 0);
        $net_pay            = (int)round($row['net_pay'] ?? 0, 0);
        $extra_days_salary  = (int)round($row['extra_days_salary'] ?? 0, 0);

        $leave_deduction = (int)round(($leave_full_days + $leave_half_days * 0.5) * $daily_rate, 0);
        $value_date      = $row['value_date'] ? (new DateTime($row['value_date']))->format('d/m/Y') : '-';

        $employees_data[] = [
            'uan_no'            => $row['uan_no'] ?? 'N/A',
            'name'              => $row['name'],
            'month'             => date('M-y', strtotime($row['month_year'] . '-01')),
            'value_date'        => $value_date,
            'basic_salary'      => $basic_salary,
            'extra_days'        => $extra_days,
            'extra'             => $extra,
            'bonus'             => $bonus,
            'leave_days'        => $leave_full_days + $leave_half_days * 0.5,
            'leave_deduction'   => $leave_deduction,
            'pf'                => $pf,
            'professional_tax'  => $professional_tax,
            'late_charge'       => $late_charge,
            'deposit'           => $deposit,
            'withdrawal'        => $withdrawal,
            'uniform'           => $uniform,
            'gross_salary'      => $gross_salary,
            'net_pay'           => $net_pay,
            'extra_days_salary' => $extra_days_salary
        ];
    }
    $stmt->close();
    $count_stmt->close();
}

/* ---------- PDF Download ---------- */
if (isset($_POST['download_pdf']) && $month_year) {
    $pdf_query = $query;

    // Grouping order when "All"
    if (empty($filter_type)) {
        $pdf_query .= " ORDER BY 
            CASE 
                WHEN e.payment_type = 'cash' THEN 1
                WHEN e.payment_type = 'bank' AND e.bank_name = 'HDFC' THEN 2
                WHEN e.payment_type = 'bank' AND e.bank_name = 'Other' THEN 3
                WHEN e.payment_type = 'bank' AND e.bank_name = 'Apprentice' THEN 4
                ELSE 5
            END, e.name";
    } else {
        $pdf_query .= " ORDER BY e.name";
    }

    $pdf_stmt = $conn->prepare($pdf_query);
    $pdf_stmt->bind_param($types, ...$params);
    $pdf_stmt->execute();
    $pdf_result = $pdf_stmt->get_result();

    $pdf_employees_data = [];
    while ($row = $pdf_result->fetch_assoc()) {
        $basic_salary       = (int)round($row['total_salary'] ?? 0, 0);
        $daily_rate         = $basic_salary / 30;
        $extra_days         = (int)($row['extra_days'] ?? 0);
        $extra              = (int)round($row['extra'] ?? 0, 0);
        $bonus              = (int)round($row['bonus'] ?? 0, 0);
        $leave_full_days    = (float)($row['leave_full_days'] ?? 0);
        $leave_half_days    = (float)($row['leave_half_days'] ?? 0);
        $late_charge        = (int)round($row['late_charge'] ?? 0, 0);
        $deposit            = (int)round($row['deposit'] ?? 0, 0);
        $withdrawal         = (int)round($row['withdrawal'] ?? 0, 0);
        $uniform            = (int)round($row['uniform'] ?? 0, 0);
        $pf                 = (int)round($row['pf'] ?? 0, 0);
        $professional_tax   = (int)round($row['professional_tax'] ?? 0, 0);
        $gross_salary       = (int)round($row['gross_salary'] ?? 0, 0);
        $net_pay            = (int)round($row['net_pay'] ?? 0, 0);
        $extra_days_salary  = (int)round($row['extra_days_salary'] ?? 0, 0);

        $leave_deduction = (int)round(($leave_full_days + $leave_half_days * 0.5) * $daily_rate, 0);
        $value_date      = $row['value_date'] ? (new DateTime($row['value_date']))->format('d/m/Y') : '-';

        $pdf_employees_data[] = [
            'uan_no'            => $row['uan_no'] ?? 'N/A',
            'name'              => $row['name'],
            'month'             => date('M-y', strtotime($row['month_year'] . '-01')),
            'value_date'        => $value_date,
            'basic_salary'      => $basic_salary,
            'extra_days'        => $extra_days,
            'extra'             => $extra,
            'bonus'             => $bonus,
            'leave_days'        => $leave_full_days + $leave_half_days * 0.5,
            'leave_deduction'   => $leave_deduction,
            'pf'                => $pf,
            'professional_tax'  => $professional_tax,
            'late_charge'       => $late_charge,
            'deposit'           => $deposit,
            'withdrawal'        => $withdrawal,
            'uniform'           => $uniform,
            'gross_salary'      => $gross_salary,
            'net_pay'           => $net_pay,
            'extra_days_salary' => $extra_days_salary
        ];
    }
    $pdf_stmt->close();

    if (!empty($pdf_employees_data)) {
        try {
            // Use FPDF directly (autoloaded)
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(false);

            $y_position   = 10;
            $page_height  = 297;
            $margin_bottom= 10;
            $max_y        = $page_height - $margin_bottom;
            $pdf->AddPage();

            for ($i = 0; $i < count($pdf_employees_data); $i++) {
                $fields = [
                    'NAME'                   => $pdf_employees_data[$i]['name'],
                    'UAN NUMBER'             => empty($pdf_employees_data[$i]['uan_no']) ? 'N/A' : $pdf_employees_data[$i]['uan_no'],
                    'SR.NO'                  => $i + 1,
                    'MONTH'                  => $pdf_employees_data[$i]['month'],
                    'BASIC SALARY'           => $pdf_employees_data[$i]['basic_salary'],
                    'EXTRA'                  => $pdf_employees_data[$i]['extra'],
                    'BONUS'                  => $pdf_employees_data[$i]['bonus'],
                    'EXTRA DAYS'             => $pdf_employees_data[$i]['extra_days'],
                    'EXTRA DAYS SALARY'      => $pdf_employees_data[$i]['extra_days_salary'],
                    'LESS DAYS'              => $pdf_employees_data[$i]['leave_days'],
                    'LESS LEAVES'            => $pdf_employees_data[$i]['leave_deduction'],
                    'GROSS SALARY'           => $pdf_employees_data[$i]['gross_salary'],
                    'LESS: PF'               => $pdf_employees_data[$i]['pf'],
                    'LESS: PROFESSIONAL TAX' => $pdf_employees_data[$i]['professional_tax'],
                    'LESS: LATE CHARGE'      => $pdf_employees_data[$i]['late_charge'],
                    'LESS: DEPOSIT'          => $pdf_employees_data[$i]['deposit'],
                    'LESS: WITHDRAWAL'       => $pdf_employees_data[$i]['withdrawal'],
                    'LESS: UNIFORM'          => $pdf_employees_data[$i]['uniform'],
                    'NET SALARY'             => $pdf_employees_data[$i]['net_pay']
                ];

                // Only show non-zero (plus mandatory fields)
                $field_count = 0;
                foreach ($fields as $label => $value) {
                    if ($value != 0 || in_array($label, ['SR.NO', 'UAN NUMBER', 'MONTH', 'NAME', 'BASIC SALARY', 'GROSS SALARY', 'NET SALARY'])) {
                        $field_count++;
                    }
                }

                $header_height = 30;
                $line_height   = 5;
                $slip_height   = $header_height + ($field_count * $line_height);

                if ($y_position + $slip_height > $max_y) {
                    $pdf->AddPage();
                    $y_position = 10;
                }

                // Outer box
                $pdf->SetXY(10, $y_position);
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->Rect(10, $y_position, 190, $slip_height, 'D');

                // Company header
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->SetXY(10, $y_position + 5);
                $pdf->Cell(190, 8, strtoupper($company['company_name'] ?? 'COMPANY'), 0, 1, 'C');

                $pdf->SetFont('Arial', '', 10);
                $pdf->SetXY(10, $y_position + 13);
                $pdf->Cell(190, 6, ($company['address'] ?? 'Address Not Set'), 0, 1, 'C');

                $pdf->SetFont('Arial', '', 12);
                $pdf->SetXY(10, $y_position + 19);
                $pdf->Cell(190, 6, "Salary Slip", 0, 1, 'C');

                // Value date (right)
                $pdf->SetFont('Arial', '', 12);
                $pdf->SetXY(150, $y_position + 5);
                $pdf->Cell(50, 6, $pdf_employees_data[$i]['value_date'], 0, 1, 'R');

                $pdf->SetLineWidth(0.5);
                $pdf->Line(10, $y_position + 25, 200, $y_position + 25);

                // Body
                $pdf->SetFont('Arial', '', 12);
                $current_y = $y_position + 30;

                foreach ($fields as $label => $value) {
                    if ($value != 0 || in_array($label, ['SR.NO', 'UAN NUMBER', 'MONTH', 'NAME', 'BASIC SALARY', 'GROSS SALARY', 'NET SALARY'])) {
                        $pdf->SetXY(20, $current_y);
                        if ($label === 'NET SALARY' || $label === 'GROSS SALARY') {
                            $pdf->SetFont('Arial', 'B', 12);
                        }
                        $pdf->Cell(100, 5, $label, 0, 0, 'L');
                        $pdf->Cell(70, 5, $value, 0, 1, 'R');
                        if ($label === 'NET SALARY' || $label === 'GROSS SALARY') {
                            $pdf->SetFont('Arial', '', 12);
                        }
                        $current_y += 5;
                    }
                }
                $y_position = $current_y + 10;
            }

            $filename = "Salary_Slips_" . $month_year . "_" . ($filter_type ?: 'All') . ".pdf";
            ob_end_clean();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $pdf->Output('D', $filename);
            exit();
        } catch (Exception $e) {
            error_log("Error generating PDF: " . $e->getMessage());
            echo "An error occurred while generating the PDF file.";
            exit();
        }
    } else {
        $error = "No data available to generate PDF.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $company ? htmlspecialchars($company['company_name']) : 'Payroll Management'; ?> - Salary Slips</title>
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

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border:none; padding:10px 18px; border-radius:10px; font-weight:600;
            box-shadow:0 4px 15px rgba(102,126,234,.3);
        }
        .btn-success { border-radius:10px; font-weight:600; }

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
                    <i class="fas fa-file-invoice"></i>
                    <?php echo $company ? htmlspecialchars($company['company_name']) : 'Payroll Management'; ?> — Salary Slips
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
            <li><a href="salary.php"><i class="fas fa-table"></i>View Salaries</a></li>
            <li><a href="final_sheet.php"><i class="fas fa-file-alt"></i>Final Sheet</a></li>
            <li><a href="salary_slip.php" class="active"><i class="fas fa-file-invoice"></i>Salary Slips</a></li>
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
                            <label for="month_year" class="form-label">Select Month & Year</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                <input type="month" id="month_year" name="month_year" class="form-control" value="<?php echo htmlspecialchars($month_year); ?>" required>
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
                                    <option value=""          <?php echo $filter_type === '' ? 'selected' : ''; ?>>All</option>
                                    <option value="cash"      <?php echo $filter_type === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="hdfc"      <?php echo $filter_type === 'hdfc' ? 'selected' : ''; ?>>HDFC Bank</option>
                                    <option value="apprentice"<?php echo $filter_type === 'apprentice' ? 'selected' : ''; ?>>Apprentice</option>
                                    <option value="other"     <?php echo $filter_type === 'other' ? 'selected' : ''; ?>>Other Banks</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 d-flex justify-content-end">
                            <button type="submit" name="download_pdf" class="btn btn-success w-100">
                                <i class="fas fa-file-pdf me-2"></i> Download Salary Slips
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
                    <h4 class="mb-3"><i class="fas fa-list me-2"></i>Salary Slip Data</h4>

                    <?php if (isset($error)): ?>
                        <p class="text-danger text-center mb-3"><?php echo $error; ?></p>
                    <?php endif; ?>

                    <div class="table-wrap">
                        <table class="table align-middle table-hover">
                            <thead>
                                <tr>
                                    <th>SR.NO</th>
                                    <th>NAME</th>
                                    <th>BASIC SALARY</th>
                                    <th>EXTRA</th>
                                    <th>BONUS</th>
                                    <th>EXTRA DAYS</th>
                                    <th>EXTRA DAYS SALARY (₹)</th>
                                    <th>LESS DAYS</th>
                                    <th>LESS LEAVES</th>
                                    <th>GROSS SALARY</th>
                                    <th>LESS: PF</th>
                                    <th>LESS: PROFESSIONAL TAX</th>
                                    <th>LESS: LATE CHARGE</th>
                                    <th>LESS: DEPOSIT</th>
                                    <th>LESS: WITHDRAWAL</th>
                                    <th>LESS: UNIFORM</th>
                                    <th>VALUE DATE</th>
                                    <th>UAN_NO</th>
                                    <th>MONTH</th>
                                    <th>NET SALARY</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($employees_data)): ?>
                                    <tr>
                                        <td colspan="20" class="text-center">No salary slips available for the selected filters.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($employees_data as $index => $employee): ?>
                                        <tr>
                                            <td><?php echo $offset + $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                            <td><?php echo "₹" . number_format($employee['basic_salary'], 0); ?></td>
                                            <td><?php echo $employee['extra'] ? "₹" . number_format($employee['extra'], 0) : '-'; ?></td>
                                            <td><?php echo $employee['bonus'] ? "₹" . number_format($employee['bonus'], 0) : '-'; ?></td>
                                            <td><?php echo $employee['extra_days'] ? (int)$employee['extra_days'] : '-'; ?></td>
                                            <td><?php echo $employee['extra_days_salary'] ? "₹" . number_format($employee['extra_days_salary'], 0) : '-'; ?></td>
                                            <td><?php echo $employee['leave_days'] ? $employee['leave_days'] : '-'; ?></td>
                                            <td><?php echo $employee['leave_deduction'] ? "₹" . number_format($employee['leave_deduction'], 0) : '-'; ?></td>
                                            <td><?php echo "₹" . number_format($employee['gross_salary'], 0); ?></td>
                                            <td><?php echo $employee['pf'] ? "₹" . number_format($employee['pf'], 0) : '-'; ?></td>
                                            <td><?php echo $employee['professional_tax'] ? "₹" . number_format($employee['professional_tax'], 0) : '-'; ?></td>
                                            <td><?php echo $employee['late_charge'] ? "₹" . number_format($employee['late_charge'], 0) : '-'; ?></td>
                                            <td><?php echo $employee['deposit'] ? "₹" . number_format($employee['deposit'], 0) : '-'; ?></td>
                                            <td><?php echo $employee['withdrawal'] ? "₹" . number_format($employee['withdrawal'], 0) : '-'; ?></td>
                                            <td><?php echo $employee['uniform'] ? "₹" . number_format($employee['uniform'], 0) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($employee['value_date']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['uan_no']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['month']); ?></td>
                                            <td><strong><?php echo "₹" . number_format($employee['net_pay'], 0); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if (!empty($month_year) && !empty($total_pages) && $total_pages > 1) { ?>
                    <nav aria-label="Salary slip pagination" class="mt-4">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Sidebar toggle + filter behavior (same as your other page)
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

        const form            = document.getElementById('filterForm');
        const monthYearInput  = document.getElementById('month_year');
        const searchInput     = document.getElementById('search');
        const filterTypeInput = document.getElementById('filter_type');

        let debounceTimeout;
        function submitForm() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.set('month_year', monthYearInput.value);
                url.searchParams.set('search', searchInput.value);
                url.searchParams.set('filter_type', filterTypeInput.value);
                url.searchParams.set('page', 1);
                window.history.pushState({}, '', url);
                form.submit();
            }, 300);
        }

        monthYearInput.addEventListener('change', function(){ if (monthYearInput.value) submitForm(); });
        searchInput.addEventListener('input', submitForm);
        filterTypeInput.addEventListener('change', submitForm);

        const currentSearchValue = searchInput.value;
        if (currentSearchValue) { searchInput.focus(); searchInput.setSelectionRange(currentSearchValue.length, currentSearchValue.length); }

        window.preserveFilters = function(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            url.searchParams.set('month_year', monthYearInput.value);
            url.searchParams.set('search', searchInput.value);
            url.searchParams.set('filter_type', filterTypeInput.value);
            window.history.pushState({}, '', url);

            const addHidden = (name, val) => { const i = document.createElement('input'); i.type='hidden'; i.name=name; i.value=val; form.appendChild(i); };
            addHidden('page', page);
            addHidden('month_year', monthYearInput.value);
            addHidden('search', searchInput.value);
            addHidden('filter_type', filterTypeInput.value);

            form.submit();
        }
    });
    </script>
</body>
</html>
<?php
// (Optional) close statements if you open new ones above
?>
