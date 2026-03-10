<?php
ob_start();
session_start();
include 'db_connect.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// Fetch single company row if exists
$company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM company_details LIMIT 1"));

// Handle create/update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_company'])) {
    $company_name              = trim($_POST['company_name'] ?? '');
    $branch_code               = trim($_POST['branch_code'] ?? '');
    $sender                    = trim($_POST['sender'] ?? '');
    $remitter_account_no       = trim($_POST['remitter_account_no'] ?? '');
    $remitter_name             = trim($_POST['remitter_name'] ?? '');
    $debit_account             = trim($_POST['debit_account'] ?? '');
    $beneficiary_account_type  = trim($_POST['beneficiary_account_type'] ?? '');
    $remittance_details        = trim($_POST['remittance_details'] ?? '');
    $debit_account_system      = trim($_POST['debit_account_system'] ?? '');
    $originator_of_remittance  = trim($_POST['originator_of_remittance'] ?? '');
    $address                   = trim($_POST['address'] ?? '');

    if ($company) {
        $sql = "UPDATE company_details SET 
                    company_name = ?, 
                    branch_code = ?, 
                    sender = ?, 
                    remitter_account_no = ?, 
                    remitter_name = ?, 
                    debit_account = ?, 
                    beneficiary_account_type = ?, 
                    remittance_details = ?, 
                    debit_account_system = ?, 
                    originator_of_remittance = ?, 
                    address = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssssi",
            $company_name,
            $branch_code,
            $sender,
            $remitter_account_no,
            $remitter_name,
            $debit_account,
            $beneficiary_account_type,
            $remittance_details,
            $debit_account_system,
            $originator_of_remittance,
            $address,
            $company['id']
        );
    } else {
        $sql = "INSERT INTO company_details 
                (company_name, branch_code, sender, remitter_account_no, remitter_name, debit_account, beneficiary_account_type, remittance_details, debit_account_system, originator_of_remittance, address) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssss",
            $company_name,
            $branch_code,
            $sender,
            $remitter_account_no,
            $remitter_name,
            $debit_account,
            $beneficiary_account_type,
            $remittance_details,
            $debit_account_system,
            $originator_of_remittance,
            $address
        );
    }

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: manage_company.php?saved=1");
        exit();
    } else {
        $error = "Error: " . $stmt->error;
        $stmt->close();
    }
}

// Refresh company row after potential insert
$company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM company_details LIMIT 1"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $company ? htmlspecialchars($company['company_name']) : 'Payroll Management'; ?> - Manage Company</title>
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

        /* Form Card */
        .form-card {
            background:#fff; border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,.1);
            position:relative; overflow:hidden; padding:32px; margin-bottom:24px;
        }
        .form-card::before {
            content:''; position:absolute; top:0; left:0; width:100%; height:5px;
            background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .form-header { text-align:center; margin-bottom:30px; padding-bottom:18px; border-bottom:2px solid #f1f3f5; }
        .form-title { font-size:1.8rem; font-weight:700; color:#2c3e50; display:flex; align-items:center; justify-content:center; gap:12px; }
        .form-subtitle { color:#7f8c8d; font-size:1.05rem; }

        .form-label { font-weight:600; color:#2c3e50; margin-bottom:8px; display:block; }
        .required::after { content:" *"; color:#e74c3c; font-weight:700; }

        .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border:none; color:#fff; border-radius:12px 0 0 12px;
        }
        .form-control, .form-select {
            border:2px solid #e9ecef; border-radius:12px; padding:12px 16px; background:#f8f9fa; transition:.3s;
        }
        .input-group .form-control { border-left:none; border-radius:0 12px 12px 0; }
        .form-control:focus, .form-select:focus { border-color:#667eea; box-shadow:0 0 0 .2rem rgba(102,126,234,.25); background:#fff; }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border:none; padding:12px 24px; border-radius:12px; font-weight:600; box-shadow:0 4px 15px rgba(102,126,234,.3);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            border:none; padding:12px 24px; border-radius:12px; font-weight:600; color:#fff;
        }
        .alert-success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color:#fff; border:none; border-radius:12px; font-weight:600;
        }
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color:#fff; border:none; border-radius:12px; font-weight:600;
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
                    <i class="fas fa-building"></i>
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
            <h5><i class="fas fa-tools me-2"></i>Control Panel</h5>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
            <li><a href="employee_list.php"><i class="fas fa-users"></i>Employee List</a></li>
            <li><a href="add_employee.php"><i class="fas fa-user-plus"></i>Add Employee</a></li>
            <li><a href="add_salary.php"><i class="fas fa-money-bill-wave"></i>Add Salary</a></li>
            <li><a href="salary.php"><i class="fas fa-table"></i>View Salaries</a></li>
            <li><a href="final_sheet.php"><i class="fas fa-file-alt"></i>Final Sheet</a></li>
            <li><a href="salary_slip.php"><i class="fas fa-file-invoice"></i>Salary Slips</a></li>
            <li><a href="manage_company.php" class="active"><i class="fas fa-cogs"></i>Manage Company</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
        </ul>
    </div>

    <!-- Overlay for mobile -->
    <div class="sidebar-overlay"></div>

    <!-- Content -->
    <div class="content">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">
                <?php if (isset($_GET['saved'])) { ?>
                    <div class="alert alert-success d-flex align-items-center justify-content-between px-4 py-3 mb-3">
                        <div><i class="fas fa-check-circle me-2"></i> Company details saved successfully.</div>
                        <button class="btn btn-light btn-sm" onclick="this.closest('.alert').remove()">OK</button>
                    </div>
                <?php } ?>
                <?php if (isset($error)) { ?>
                    <div class="alert alert-danger px-4 py-3 mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php } ?>

                <div class="form-card">
                    <div class="form-header">
                        <h2 class="form-title">
                            <i class="fas fa-building"></i>
                            <?php echo $company ? 'Update Company' : 'Add Company'; ?>
                        </h2>
                        <p class="form-subtitle">Set your organization’s banking and remittance info</p>
                    </div>

                    <form method="POST" id="companyForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required" for="company_name">Company Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                    <input type="text" id="company_name" name="company_name" class="form-control" required
                                           value="<?php echo $company ? htmlspecialchars($company['company_name']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="branch_code">Branch Code</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-code-branch"></i></span>
                                    <input type="text" id="branch_code" name="branch_code" class="form-control" required
                                           value="<?php echo $company ? htmlspecialchars($company['branch_code']) : ''; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required" for="sender">Sender</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                    <input type="text" id="sender" name="sender" class="form-control" required
                                           value="<?php echo $company ? htmlspecialchars($company['sender']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="remitter_account_no">Remitter Account No</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                    <input type="text" id="remitter_account_no" name="remitter_account_no" class="form-control" required
                                           value="<?php echo $company ? htmlspecialchars($company['remitter_account_no']) : ''; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required" for="remitter_name">Remitter Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" id="remitter_name" name="remitter_name" class="form-control" required
                                           value="<?php echo $company ? htmlspecialchars($company['remitter_name']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="debit_account">Debit Account</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-wallet"></i></span>
                                    <input type="text" id="debit_account" name="debit_account" class="form-control" required
                                           value="<?php echo $company ? htmlspecialchars($company['debit_account']) : ''; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required" for="beneficiary_account_type">Beneficiary Account Type</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                                    <input type="text" id="beneficiary_account_type" name="beneficiary_account_type" class="form-control" required
                                           value="<?php echo $company ? htmlspecialchars($company['beneficiary_account_type']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="remittance_details">Remittance Details</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-file-signature"></i></span>
                                    <textarea id="remittance_details" name="remittance_details" class="form-control" required rows="1"><?php echo $company ? htmlspecialchars($company['remittance_details']) : ''; ?></textarea>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required" for="debit_account_system">Debit Account System</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-network-wired"></i></span>
                                    <input type="text" id="debit_account_system" name="debit_account_system" class="form-control" required
                                           value="<?php echo $company ? htmlspecialchars($company['debit_account_system']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="originator_of_remittance">Originator of Remittance</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-cog"></i></span>
                                    <input type="text" id="originator_of_remittance" name="originator_of_remittance" class="form-control" required
                                           value="<?php echo $company ? htmlspecialchars($company['originator_of_remittance']) : ''; ?>">
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label required" for="address">Company Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                    <textarea id="address" name="address" class="form-control" rows="3" required><?php echo $company ? htmlspecialchars($company['address']) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6 mb-2">
                                <button type="submit" name="update_company" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i><?php echo $company ? 'Update Company' : 'Add Company'; ?>
                                </button>
                            </div>
                            <div class="col-md-6 mb-2">
                                <a href="index.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </form>
                </div><!-- /.form-card -->
            </div>
        </div>
    </div><!-- /.content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
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

        // Tiny UX polish on focus
        document.querySelectorAll('.form-control, .form-select, textarea').forEach(el => {
            el.addEventListener('focus', () => el.parentElement.style.transform = 'scale(1.02)');
            el.addEventListener('blur',  () => el.parentElement.style.transform = 'scale(1)');
        });
    });
    </script>
</body>
</html>
