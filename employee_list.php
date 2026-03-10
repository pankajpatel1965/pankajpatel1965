<?php
ob_start();
session_start();
include 'db_connect.php';

// Logged in?
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// Company (for header/title)
$company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM company_details LIMIT 1"));

/* ------------------ FILTERS & PAGINATION ------------------ */
$search          = isset($_POST['search']) ? trim($_POST['search']) : (isset($_GET['search']) ? trim($_GET['search']) : '');
$payment_filter  = isset($_POST['payment_filter']) ? trim($_POST['payment_filter']) : (isset($_GET['payment_filter']) ? trim($_GET['payment_filter']) : '');
$page            = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page        = 15;

// Build WHERE with prepared statements
$whereParts = [];
$params = [];
$types  = '';

if ($search !== '') {
    $whereParts[] = "e.name LIKE ?";
    $params[] = "%$search%";
    $types   .= 's';
}
if ($payment_filter !== '') {
    switch ($payment_filter) {
        case 'cash':
            $whereParts[] = "e.payment_type = 'cash'";
            break;
        case 'hdfc':
            $whereParts[] = "e.payment_type = 'bank' AND e.bank_name = 'HDFC'";
            break;
        case 'other':
            $whereParts[] = "e.payment_type = 'bank' AND e.bank_name = 'Other'";
            break;
        case 'apprentice':
            $whereParts[] = "e.payment_type = 'bank' AND e.bank_name = 'Apprentice'";
            break;
    }
}

$whereSql = '';
if (!empty($whereParts)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
}

/* ------------------ COUNTS & TOTALS ------------------ */
$count_sql = "SELECT COUNT(*) AS total, COALESCE(SUM(e.salary),0) AS total_salary FROM employees e" . $whereSql;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();
$total_employees = (int)($count_res['total'] ?? 0);
$total_salary    = (float)($count_res['total_salary'] ?? 0);

$total_pages = max(1, (int)ceil($total_employees / $per_page));
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $per_page;

/* ------------------ DATA (PAGED) ------------------ */
$list_sql = "
    SELECT e.*
    FROM employees e
    $whereSql
    ORDER BY e.id DESC
    LIMIT ?, ?
";
$params2 = $params;
$types2  = $types . 'ii';
$params2[] = $offset;
$params2[] = $per_page;

$list_stmt = $conn->prepare($list_sql);
$list_stmt->bind_param($types2, ...$params2);
$list_stmt->execute();
$list_result = $list_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $company ? htmlspecialchars($company['company_name']) : 'Employee Management'; ?> - Employee List</title>
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
        .filter-card { padding:24px; margin-bottom:24px; }
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

        /* Employee header */
        .employee-header {
            display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:12px;
            border-bottom:2px solid #f1f3f5;
        }
        .employee-title { color:#2c3e50; font-weight:700; margin:0; font-size:1.25rem; display:flex; align-items:center; gap:10px; }
        .total-salary-badge {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color:#fff; padding:10px 18px; border-radius:25px; font-weight:600; box-shadow:0 4px 15px rgba(39,174,96,.25);
        }

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
            .employee-header { flex-direction:column; align-items:flex-start; gap:12px; }
            .total-salary-badge { align-self:stretch; text-align:center; }
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
                    <i class="fas fa-users"></i>
                    <?php echo $company ? htmlspecialchars($company['company_name']) : 'Employee Management'; ?>
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
            <li><a href="employee_list.php" class="active"><i class="fas fa-users"></i>Employee List</a></li>
            <li><a href="add_employee.php"><i class="fas fa-user-plus"></i>Add Employee</a></li>
            <li><a href="add_salary.php"><i class="fas fa-money-bill-wave"></i>Add Salary</a></li>
            <li><a href="salary.php"><i class="fas fa-table"></i>View Salaries</a></li>
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
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search by Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="search" name="search" class="form-control" placeholder="Enter employee name"
                                       value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="payment_filter" class="form-label">Filter by Payment Type</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-filter"></i></span>
                                <select id="payment_filter" name="payment_filter" class="form-select">
                                    <option value="">All Payment Types</option>
                                    <option value="cash"       <?php echo $payment_filter==='cash'?'selected':''; ?>>Cash</option>
                                    <option value="hdfc"       <?php echo $payment_filter==='hdfc'?'selected':''; ?>>HDFC Bank</option>
                                    <option value="apprentice" <?php echo $payment_filter==='apprentice'?'selected':''; ?>>Apprentice</option>
                                    <option value="other"      <?php echo $payment_filter==='other'?'selected':''; ?>>Other Bank</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <!-- Optional: add export or actions here later -->
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="row">
            <div class="col-xl-12">
                <div class="table-card">
                    <div class="employee-header">
                        <h4 class="employee-title">
                            <i class="fas fa-list"></i>
                            Employee Directory
                            <span class="badge bg-primary ms-2"><?php echo $total_employees; ?> employees</span>
                        </h4>
                        <div class="total-salary-badge">
                            <i class="fas fa-rupee-sign me-2"></i>
                            Total: ₹<?php echo number_format($total_salary, 2); ?>
                        </div>
                    </div>

                    <?php if ($total_employees === 0) { ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-users mb-3" style="font-size:3rem; color:#dee2e6;"></i>
                            <h5 class="mb-1">No employees found</h5>
                            <p class="mb-0">Try a different name or payment filter.</p>
                        </div>
                    <?php } else { ?>
                        <div class="table-wrap">
                            <table class="table align-middle table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Position</th>
                                        <th>Salary</th>
                                        <th>Payment Type</th>
                                        <th>MICR</th>
                                        <th>IFSC</th>
                                        <th>Account</th>
                                        <th>Bank</th>
                                        <th>PF No</th>
                                        <th>UAN</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php while ($row = $list_result->fetch_assoc()) {
                                    $payBadge = 'badge-other';
                                    $displayType = ucfirst($row['payment_type'] ?? '');
                                    if (($row['payment_type'] ?? '') === 'cash') {
                                        $payBadge = 'badge-cash';
                                        $displayType = 'Cash';
                                    } else {
                                        $bn = $row['bank_name'] ?? '';
                                        if ($bn) $displayType = $bn . ' Bank';
                                    }
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo (int)$row['id']; ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['contact_number'] ?? '-'); ?></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($row['position'] ?? '-'); ?></span></td>
                                        <td><strong>₹<?php echo number_format((float)($row['salary'] ?? 0), 0); ?></strong></td>
                                        <td><span class="badge <?php echo $payBadge; ?>"><?php echo htmlspecialchars($displayType ?: '-'); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['micr_code'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['ifsc_code'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['account_number'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['bank_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['provident_fund_ac_no'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['uan_no'] ?? '-'); ?></td>
                                        <td class="text-nowrap">
                                            <a href="edit_employee.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- DELETE uses custom modal -->
                                            <a href="#"
                                               class="btn btn-danger btn-sm js-delete"
                                               data-delete-url="delete_employee.php?id=<?php echo (int)$row['id']; ?>"
                                               title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1) { ?>
                        <nav aria-label="Employee list pagination" class="mt-4">
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
                    <?php } ?>
                </div>
            </div>
        </div>
    </div><!-- /.content -->

    <!-- Fancy Confirm Modal -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true" aria-labelledby="confirmDeleteLabel">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content fancy-modal">
          <div class="modal-body text-center p-4">
            <div class="icon-wrap mb-3">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h5 id="confirmDeleteLabel" class="mb-2 fw-bold">Delete this employee?</h5>
            <p class="text-muted mb-4">This action cannot be undone.</p>
            <div class="d-flex gap-2 justify-content-center">
              <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
              <a id="confirmDeleteBtn" href="#" class="btn btn-danger px-4">
                Yes, delete
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Sidebar + filters + delete modal
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
        const searchInput     = document.getElementById('search');
        const paymentFilter   = document.getElementById('payment_filter');

        let debounceTimeout;
        function submitForm() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.set('search', searchInput.value);
                url.searchParams.set('payment_filter', paymentFilter.value);
                url.searchParams.set('page', 1);
                window.history.pushState({}, '', url);
                form.submit();
            }, 300);
        }

        searchInput.addEventListener('input', submitForm);
        paymentFilter.addEventListener('change', submitForm);

        const currentSearchValue = searchInput.value;
        if (currentSearchValue) { searchInput.focus(); searchInput.setSelectionRange(currentSearchValue.length, currentSearchValue.length); }

        window.preserveFilters = function(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            url.searchParams.set('search', searchInput.value);
            url.searchParams.set('payment_filter', paymentFilter.value);
            window.history.pushState({}, '', url);

            const addHidden = (name, val) => { const i = document.createElement('input'); i.type='hidden'; i.name=name; i.value=val; form.appendChild(i); };
            addHidden('page', page);
            addHidden('search', searchInput.value);
            addHidden('payment_filter', paymentFilter.value);

            form.submit();
        }

        // ---- Delete Modal Wiring ----
        const deleteLinks = document.querySelectorAll('.js-delete');
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
$list_stmt->close();
$count_stmt->close();
?>
