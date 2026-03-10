<?php
include 'db_connect.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// Process form submission before any output
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $position = $_POST['position'];
    $salary = $_POST['salary'];
    $payment_type = $_POST['payment_type'];
    $contact_number = $_POST['contact_number'];
    
    $micr_code = $payment_type === 'bank' ? $_POST['micr_code'] : NULL;
    $ifsc_code = $payment_type === 'bank' ? $_POST['ifsc_code'] : NULL;
    $account_number = $payment_type === 'bank' ? $_POST['account_number'] : NULL;
    $bank_name = $payment_type === 'bank' ? $_POST['bank_name'] : NULL;
    $provident_fund_ac_no = $payment_type === 'bank' ? $_POST['provident_fund_ac_no'] : NULL;
    $uan_no = $payment_type === 'bank' ? $_POST['uan_no'] : NULL;

    $sql = "INSERT INTO employees (name, email, position, salary, payment_type, micr_code, ifsc_code, account_number, bank_name, provident_fund_ac_no, uan_no, contact_number) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssss", $name, $email, $position, $salary, $payment_type, $micr_code, $ifsc_code, $account_number, $bank_name, $provident_fund_ac_no, $uan_no, $contact_number);
            
    if ($stmt->execute()) {
        header("Location: index.php");
        exit();
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}

$company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM company_details LIMIT 1"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company ? $company['company_name'] : 'Employee Management System'; ?> - Add Employee</title>
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

        /* Form Card Styles */
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f8f9fa;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .form-subtitle {
            color: #7f8c8d;
            font-size: 1.1rem;
            font-weight: 400;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background-color: white;
        }

        .form-control:hover, .form-select:hover {
            border-color: #bdc3c7;
            background-color: white;
        }

        /* Bank Fields Section */
        .bank-fields-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
            border: 2px dashed #dee2e6;
            transition: all 0.3s ease;
        }

        .bank-fields-section.show {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff 0%, #e6f2ff 100%);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 14px 30px;
            font-weight: 600;
            border-radius: 12px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            border: none;
            padding: 14px 30px;
            font-weight: 600;
            border-radius: 12px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #7f8c8d 0%, #6c7b7d 100%);
            color: white;
        }

        /* Alert Styles */
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            border: none;
            border-radius: 12px;
            color: white;
            padding: 15px 20px;
            font-weight: 500;
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-down {
            animation: slideDown 0.4s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-280px);
            }
            
            .content {
                margin-left: 0;
                padding: 20px 15px;
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

            .form-card {
                padding: 25px 20px;
            }

            .form-title {
                font-size: 1.6rem;
            }

            .col-md-6 {
                margin-bottom: 15px;
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

        /* Input Icons */
        .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 12px 0 0 12px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .input-group .form-control:focus {
            border-left: 2px solid #667eea;
        }

        /* Required Field Indicator */
        .required::after {
            content: " *";
            color: #e74c3c;
            font-weight: bold;
        }
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
                    <i class="fas fa-user-plus"></i>
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
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
            <li><a href="employee_list.php"><i class="fas fa-users"></i>Employee List</a></li>
            <li><a href="add_employee.php" class="active"><i class="fas fa-user-plus"></i>Add Employee</a></li>
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
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">
                <div class="form-card fade-in">
                    <div class="form-header">
                        <h2 class="form-title">
                            <i class="fas fa-user-plus"></i>
                            Add New Employee
                        </h2>
                        <p class="form-subtitle">Enter employee details to add them to the system</p>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger text-center mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="employeeForm">
                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="name" class="form-label required">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" id="name" name="name" class="form-control" 
                                           placeholder="Enter full name" required>
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           placeholder="Enter email address">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="position" class="form-label required">Position</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-briefcase"></i>
                                    </span>
                                    <input type="text" id="position" name="position" class="form-control" 
                                           placeholder="Enter job position" required>
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="salary" class="form-label required">Monthly Salary</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-rupee-sign"></i>
                                    </span>
                                    <input type="number" id="salary" name="salary" class="form-control" 
                                           placeholder="Enter salary amount" step="1" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <input type="text" id="contact_number" name="contact_number" class="form-control" 
                                           placeholder="e.g., +919876543210" maxlength="20">
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="payment_type" class="form-label required">Payment Method</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-credit-card"></i>
                                    </span>
                                    <select id="payment_type" name="payment_type" class="form-select" required>
                                        <option value="cash">Cash Payment</option>
                                        <option value="bank">Bank Transfer</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Details Section -->
                        <div id="bank_fields" class="bank-fields-section" style="display: none;">
                            <h4 class="section-title">
                                <i class="fas fa-university"></i>
                                Bank Account Details
                            </h4>
                            
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="bank_name" class="form-label">Bank Name</label>
                                    <select id="bank_name" name="bank_name" class="form-select">
                                        <option value="HDFC">HDFC Bank</option>
                                        <option value="Other">Other Bank</option>
                                        <option value="Apprentice">Apprentice Account</option>
                                    </select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="account_number" class="form-label">Account Number</label>
                                    <input type="text" id="account_number" name="account_number" 
                                           class="form-control" placeholder="Enter account number" maxlength="20">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="ifsc_code" class="form-label">IFSC Code</label>
                                    <input type="text" id="ifsc_code" name="ifsc_code" 
                                           class="form-control" placeholder="Enter IFSC code" maxlength="11">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="micr_code" class="form-label">MICR Code</label>
                                    <input type="text" id="micr_code" name="micr_code" 
                                           class="form-control" placeholder="Enter MICR code" maxlength="9">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="provident_fund_ac_no" class="form-label">Provident Fund A/C No</label>
                                    <input type="text" id="provident_fund_ac_no" name="provident_fund_ac_no" 
                                           class="form-control" placeholder="Enter PF account number" maxlength="50">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="uan_no" class="form-label">UAN Number</label>
                                    <input type="text" id="uan_no" name="uan_no" 
                                           class="form-control" placeholder="Enter UAN number" maxlength="50">
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row mt-4">
                            <div class="col-md-6 mb-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    Add Employee
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="index.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar Toggle
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

            // Payment Type Toggle
            const paymentType = document.getElementById('payment_type');
            const bankFields = document.getElementById('bank_fields');
            const requiredInputs = bankFields.querySelectorAll('#micr_code, #ifsc_code, #account_number');
            const bankNameSelect = document.getElementById('bank_name');

            function toggleBankFields() {
                if (paymentType.value === 'bank') {
                    bankFields.style.display = 'block';
                    bankFields.classList.add('show', 'slide-down');
                    for (let input of requiredInputs) input.required = true;
                    bankNameSelect.required = true;
                } else {
                    bankFields.style.display = 'none';
                    bankFields.classList.remove('show');
                    for (let input of requiredInputs) input.required = false;
                    bankNameSelect.required = false;
                }
            }

            paymentType.addEventListener('change', toggleBankFields);

            // Form validation
            const form = document.getElementById('employeeForm');
            form.addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();
                const position = document.getElementById('position').value.trim();
                const salary = document.getElementById('salary').value;

                if (!name || !position || !salary) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }

                if (salary <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid salary amount.');
                    return false;
                }

                // Validate bank fields if bank payment is selected
                if (paymentType.value === 'bank') {
                    const ifsc = document.getElementById('ifsc_code').value.trim();
                    const account = document.getElementById('account_number').value.trim();
                    
                    if (!ifsc || !account) {
                        e.preventDefault();
                        alert('Please fill in all required bank details.');
                        return false;
                    }
                }
            });

            // Input animations
            const inputs = document.querySelectorAll('.form-control, .form-select');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>