<?php
include 'db_connect.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: salary.php");
    exit();
}

$id = (int)$_GET['id'];

// Company for header title
$company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM company_details LIMIT 1"));

$salary_query = "SELECT s.*, e.name, e.salary AS basic_salary, e.payment_type
                 FROM salaries s
                 JOIN employees e ON s.employee_id = e.id
                 WHERE s.id = ?";
$stmt = $conn->prepare($salary_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$salary = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$salary) {
    header("Location: salary.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $month_year      = $_POST['month_year'];
    $value_date      = $_POST['value_date'];
    $basic_salary    = $_POST['basic_salary'];
    $extra_days      = !empty($_POST['extra_days']) ? $_POST['extra_days'] : NULL;
    $extra           = !empty($_POST['extra']) ? $_POST['extra'] : NULL;
    $bonus           = !empty($_POST['bonus']) ? $_POST['bonus'] : NULL;
    $leave_full_days = !empty($_POST['leave_full_days']) ? $_POST['leave_full_days'] : NULL;
    $leave_half_days = !empty($_POST['leave_half_days']) ? $_POST['leave_half_days'] : NULL;
    $deduct_pf       = isset($_POST['deduct_pf']) ? 1 : 0;
    $late_charge     = !empty($_POST['late_charge']) ? $_POST['late_charge'] : NULL;
    $deposit         = !empty($_POST['deposit']) ? $_POST['deposit'] : NULL;
    $withdrawal      = !empty($_POST['withdrawal']) ? $_POST['withdrawal'] : NULL;
    $uniform         = !empty($_POST['uniform']) ? $_POST['uniform'] : NULL;

    // Recalculate server-side (unchanged logic)
    $daily_rate         = $basic_salary / 30;
    $extra_days_salary  = round($daily_rate * ($extra_days ?? 0));
    $leave_deduction    = round(($leave_full_days + ($leave_half_days * 0.5)) * $daily_rate);
    $gross_salary       = round($basic_salary + $extra_days_salary + ($extra ?? 0) + ($bonus ?? 0) - $leave_deduction);

    $pf = NULL;
    if ($deduct_pf && $salary['payment_type'] !== 'cash') {
        $pf = min(round($gross_salary * 0.12), 1800);
    }

    $professional_tax = ($gross_salary >= 12000) ? 200 : 0;

    $net_pay = round($gross_salary - ($pf ?? 0) - $professional_tax - ($late_charge ?? 0) - ($deposit ?? 0) - ($withdrawal ?? 0) - ($uniform ?? 0));

    $sql = "UPDATE salaries SET
            month_year = ?,
            value_date = ?,
            total_salary = ?,
            extra_days = ?,
            extra_days_salary = ?,
            extra = ?,
            bonus = ?,
            leave_full_days = ?,
            leave_half_days = ?,
            deduct_pf = ?,
            pf = ?,
            professional_tax = ?,
            late_charge = ?,
            deposit = ?,
            withdrawal = ?,
            uniform = ?,
            gross_salary = ?,
            net_pay = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);

    $types  = "ssd"; // month_year (s), value_date (s), total_salary (d)
    $values = [$month_year, $value_date, $basic_salary];

    function addParam(&$types, &$values, $value) {
        if ($value === NULL) { $types .= 's'; $values[] = NULL; }
        else { $types .= 'd'; $values[] = $value; }
    }

    addParam($types, $values, $extra_days);
    addParam($types, $values, $extra_days_salary);
    addParam($types, $values, $extra);
    addParam($types, $values, $bonus);
    addParam($types, $values, $leave_full_days);
    addParam($types, $values, $leave_half_days);

    $types .= 'i'; $values[] = $deduct_pf; // integer
    addParam($types, $values, $pf);
    addParam($types, $values, $professional_tax);
    addParam($types, $values, $late_charge);
    addParam($types, $values, $deposit);
    addParam($types, $values, $withdrawal);
    addParam($types, $values, $uniform);
    addParam($types, $values, $gross_salary);
    addParam($types, $values, $net_pay);

    $types .= 'i'; $values[] = $id;

    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
        header("Location: salary.php");
        exit();
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $company ? htmlspecialchars($company['company_name']) : 'Payroll Management'; ?> - Edit Salary</title>
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

        /* Card/form */
        .form-card {
            background:#fff; border-radius:20px; padding:40px; box-shadow:0 10px 40px rgba(0,0,0,.1);
            position:relative; overflow:hidden;
        }
        .form-card::before { content:''; position:absolute; top:0; left:0; width:100%; height:5px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .form-header { text-align:center; margin-bottom:30px; padding-bottom:20px; border-bottom:2px solid #f8f9fa; }
        .form-title { font-size:2rem; font-weight:700; color:#2c3e50; display:flex; align-items:center; justify-content:center; gap:12px; }
        .form-subtitle { color:#7f8c8d; font-size:1.05rem; }

        .form-group { margin-bottom:22px; }
        .form-label { font-weight:600; color:#2c3e50; margin-bottom:8px; display:block; }
        .form-control, .form-select {
            border:2px solid #e9ecef; border-radius:12px; padding:12px 16px; background:#f8f9fa; transition:.3s;
        }
        .form-control:focus, .form-select:focus { border-color:#667eea; box-shadow:0 0 0 .2rem rgba(102,126,234,.25); background:#fff; }
        .form-control:hover, .form-select:hover { border-color:#bdc3c7; background:#fff; }

        .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border:none; color:#fff; border-radius:12px 0 0 12px;
        }
        .input-group .form-control, .input-group .form-select { border-left:none; border-radius:0 12px 12px 0; }
        .input-group .form-control:focus, .input-group .form-select:focus { border-left:2px solid #667eea; }

        .bank-fields-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border:2px dashed #dee2e6; border-radius:15px; padding:22px; margin-top:10px;
        }
        .section-title { font-weight:600; color:#2c3e50; font-size:1.15rem; display:flex; align-items:center; gap:10px; margin-bottom:12px; }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border:none; padding:14px 30px; border-radius:12px; font-weight:600;
            box-shadow:0 4px 15px rgba(102,126,234,.3); transition:.3s;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow:0 8px 25px rgba(102,126,234,.4); }
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            border:none; padding:14px 30px; border-radius:12px; font-weight:600; color:#fff;
        }
        .btn-secondary:hover { transform: translateY(-2px); }

        .alert-danger { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%); border:none; border-radius:12px; color:#fff; padding:15px 20px; }

        .fade-in { animation: fadeIn .6s ease; }
        @keyframes fadeIn { from {opacity:0; transform:translateY(20px);} to {opacity:1; transform:translateY(0);} }

        #pf_section { align-items:center; }

        /* Responsive */
        @media (max-width:768px){
            .sidebar{ transform: translateX(-280px); }
            .sidebar.active{ transform: translateX(0); }
            .content{ margin-left:0; padding:20px 15px; }
            .hamburger{ display:inline-block; }
            .header-title{ font-size:1.1rem; }
            .form-card{ padding:25px 20px; }
            .form-title{ font-size:1.6rem; }
            .col-md-6{ margin-bottom:15px; }
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
                    <i class="fas fa-file-invoice-dollar"></i>
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

    <!-- Overlay -->
    <div class="sidebar-overlay"></div>

    <!-- Content -->
    <div class="content">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">
                <div class="form-card fade-in">
                    <div class="form-header">
                        <h2 class="form-title">
                            <i class="fas fa-edit"></i>
                            Edit Salary for <?php echo htmlspecialchars($salary['name']); ?>
                        </h2>
                        <p class="form-subtitle">Update the amounts and we’ll recalculate everything</p>
                    </div>

                    <?php if (isset($error)) { echo "<div class='alert alert-danger text-center mb-4'>$error</div>"; } ?>

                    <form method="POST" id="editSalaryForm">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="month_year" class="form-label">Month & Year</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="month" id="month_year" name="month_year" class="form-control"
                                           value="<?php echo htmlspecialchars($salary['month_year']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="value_date" class="form-label">Value Date</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                                    <input type="date" id="value_date" name="value_date" class="form-control"
                                           value="<?php echo htmlspecialchars($salary['value_date']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="basic_salary" class="form-label">Basic Salary (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-rupee-sign"></i></span>
                                    <input type="number" id="basic_salary" name="basic_salary" class="form-control"
                                           step="1" value="<?php echo htmlspecialchars($salary['total_salary']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="extra_days" class="form-label">Extra Days</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-plus-circle"></i></span>
                                    <input type="number" id="extra_days" name="extra_days" class="form-control" step="0.01"
                                           value="<?php echo htmlspecialchars($salary['extra_days']); ?>">
                                </div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label for="extra_days_salary" class="form-label">Extra Days Salary (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calculator"></i></span>
                                    <input type="number" id="extra_days_salary" class="form-control" disabled step="1"
                                           value="<?php echo htmlspecialchars($salary['extra_days_salary']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="extra" class="form-label">Extra (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-plus"></i></span>
                                    <input type="number" id="extra" name="extra" class="form-control" step="1"
                                           value="<?php echo htmlspecialchars($salary['extra']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="bonus" class="form-label">Bonus (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-gift"></i></span>
                                    <input type="number" id="bonus" name="bonus" class="form-control" step="1"
                                           value="<?php echo htmlspecialchars($salary['bonus']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="leave_full_days" class="form-label">Leave Full Days</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-minus"></i></span>
                                    <input type="number" id="leave_full_days" name="leave_full_days" class="form-control" step="0.01"
                                           value="<?php echo htmlspecialchars($salary['leave_full_days']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="leave_half_days" class="form-label">Leave Half Days</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-clock"></i></span>
                                    <input type="number" id="leave_half_days" name="leave_half_days" class="form-control" step="0.01"
                                           value="<?php echo htmlspecialchars($salary['leave_half_days']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="leave_deduction" class="form-label">Leave Deduction (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-minus-circle"></i></span>
                                    <input type="number" id="leave_deduction" class="form-control" disabled step="1">
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="gross_salary" class="form-label">Gross Salary (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-money-bill-wave"></i></span>
                                    <input type="number" id="gross_salary" class="form-control" disabled step="1"
                                           value="<?php echo htmlspecialchars($salary['gross_salary']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row" id="pf_section" style="<?php echo ($salary['payment_type'] === 'cash') ? 'display:none;' : 'display:flex;'; ?>">
                            <div class="col-md-6 form-group">
                                <label for="deduct_pf" class="form-label">Provident Fund</label><br>
                                <input type="checkbox" id="deduct_pf" name="deduct_pf" class="form-check-input" <?php echo $salary['deduct_pf'] ? 'checked' : ''; ?>>
                                <small class="form-text text-muted ms-2">Deduct 12% of gross salary (max ₹1800)</small>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="pf" class="form-label">Less: Provident Fund (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-piggy-bank"></i></span>
                                    <input type="number" id="pf" class="form-control" disabled step="1">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="professional_tax" class="form-label">Less: Professional Tax (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-receipt"></i></span>
                                    <input type="number" id="professional_tax" class="form-control" disabled step="1">
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="late_charge" class="form-label">Less: Late Charge (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hourglass-half"></i></span>
                                    <input type="number" id="late_charge" name="late_charge" class="form-control" step="1"
                                           value="<?php echo htmlspecialchars($salary['late_charge']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="deposit" class="form-label">Less: Deposit (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-building-columns"></i></span>
                                    <input type="number" id="deposit" name="deposit" class="form-control" step="1"
                                           value="<?php echo htmlspecialchars($salary['deposit']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="withdrawal" class="form-label">Less: Withdrawal (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-money-check-alt"></i></span>
                                    <input type="number" id="withdrawal" name="withdrawal" class="form-control" step="1"
                                           value="<?php echo htmlspecialchars($salary['withdrawal']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="uniform" class="form-label">Less: Uniform (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tshirt"></i></span>
                                    <input type="number" id="uniform" name="uniform" class="form-control" step="1"
                                           value="<?php echo htmlspecialchars($salary['uniform']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="net_pay" class="form-label">Net Pay (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hand-holding-usd"></i></span>
                                    <input type="number" id="net_pay" class="form-control" disabled step="1"
                                           value="<?php echo htmlspecialchars($salary['net_pay']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6 mb-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i> Update Salary
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="salary.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Salary List
                                </a>
                            </div>
                        </div>
                    </form>

                </div><!-- /.form-card -->
            </div>
        </div>
    </div>

    <!-- JS libs -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle
        const hamburger = document.querySelector('.hamburger');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
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

        // Form calculation logic (unchanged)
        const basicSalaryInput     = document.getElementById('basic_salary');
        const extraDaysInput       = document.getElementById('extra_days');
        const extraDaysSalaryInput = document.getElementById('extra_days_salary');
        const extraInput           = document.getElementById('extra');
        const bonusInput           = document.getElementById('bonus');
        const leaveFullDaysInput   = document.getElementById('leave_full_days');
        const leaveHalfDaysInput   = document.getElementById('leave_half_days');
        const leaveDeductionInput  = document.getElementById('leave_deduction');
        const grossSalaryInput     = document.getElementById('gross_salary');
        const deductPfCheckbox     = document.getElementById('deduct_pf');
        const pfInput              = document.getElementById('pf');
        const pfSection            = document.getElementById('pf_section');
        const professionalTaxInput = document.getElementById('professional_tax');
        const lateChargeInput      = document.getElementById('late_charge');
        const depositInput         = document.getElementById('deposit');
        const withdrawalInput      = document.getElementById('withdrawal');
        const uniformInput         = document.getElementById('uniform');
        const netPayInput          = document.getElementById('net_pay');

        function calculateSalaries() {
            const basicSalary   = Math.round(parseFloat(basicSalaryInput.value) || 0);
            const extraDays     = parseFloat(extraDaysInput.value) || 0;
            const extra         = Math.round(parseFloat(extraInput.value) || 0);
            const bonus         = Math.round(parseFloat(bonusInput.value) || 0);
            const leaveFullDays = parseFloat(leaveFullDaysInput.value) || 0;
            const leaveHalfDays = parseFloat(leaveHalfDaysInput.value) || 0;
            const lateCharge    = Math.round(parseFloat(lateChargeInput.value) || 0);
            const deposit       = Math.round(parseFloat(depositInput.value) || 0);
            const withdrawal    = Math.round(parseFloat(withdrawalInput.value) || 0);
            const uniform       = Math.round(parseFloat(uniformInput.value) || 0);

            const dailyRate        = basicSalary / 30;
            const extraDaysSalary  = Math.round(dailyRate * extraDays);
            const leaveDeduction   = Math.round((leaveFullDays + leaveHalfDays * 0.5) * dailyRate);

            extraDaysSalaryInput.value = extraDaysSalary;
            leaveDeductionInput.value  = leaveDeduction;

            const grossSalary = Math.round(basicSalary + extraDaysSalary + extra + bonus - leaveDeduction);
            grossSalaryInput.value = grossSalary;

            let pf = 0;
            if (pfSection.style.display !== 'none' && deductPfCheckbox.checked) {
                pf = Math.min(Math.round(grossSalary * 0.12), 1800);
            }
            pfInput.value = pf;

            const professionalTax = grossSalary >= 12000 ? 200 : 0;
            professionalTaxInput.value = professionalTax;

            const netPay = Math.round(grossSalary - pf - professionalTax - lateCharge - deposit - withdrawal - uniform);
            netPayInput.value = netPay;
        }

        // Initial compute + listeners
        calculateSalaries();
        [basicSalaryInput, extraDaysInput, extraInput, bonusInput, leaveFullDaysInput, leaveHalfDaysInput,
         lateChargeInput, depositInput, withdrawalInput, uniformInput].forEach(el => {
            el.addEventListener('input', calculateSalaries);
        });
        deductPfCheckbox.addEventListener('change', calculateSalaries);

        // Little focus animation like other pages
        document.querySelectorAll('.form-control, .form-select').forEach(el => {
            el.addEventListener('focus', function() {
                const grp = this.closest('.input-group');
                if (grp) grp.style.transform = 'scale(1.02)';
            });
            el.addEventListener('blur', function() {
                const grp = this.closest('.input-group');
                if (grp) grp.style.transform = 'scale(1)';
            });
        });
    });
    </script>
</body>
</html>
