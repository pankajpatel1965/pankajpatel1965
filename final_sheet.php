<?php
ob_start();
session_start();
include 'db_connect.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

$company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM company_details LIMIT 1"));

$month_year  = isset($_POST['month_year']) ? $_POST['month_year'] : '';
$filter_type = isset($_POST['filter_type']) ? $_POST['filter_type'] : '';
$search      = isset($_POST['search']) ? $_POST['search'] : '';
$sort_order  = isset($_POST['sort_order']) ? $_POST['sort_order'] : 'desc';

/* ------------ Format month label (e.g., June-2025) ------------ */
$formatted_month_year = '';
if ($month_year) {
    $dateObj = DateTime::createFromFormat('Y-m', $month_year);
    if ($dateObj) { $formatted_month_year = $dateObj->format('F-Y'); }
}

/* ---------------- Display name for filter tag ------------------ */
$filter_display_name = '';
switch ($filter_type) {
    case 'cash':       $filter_display_name = 'Cash'; break;
    case 'hdfc':       $filter_display_name = 'HDFC Bank'; break;
    case 'other':      $filter_display_name = 'Other Banks'; break;
    case 'apprentice': $filter_display_name = 'Apprentice'; break;
    case 'pf':         $filter_display_name = 'Provident Fund'; break;
    case 'pt':         $filter_display_name = 'Professional Tax'; break;
    default:           $filter_display_name = 'All';
}
$month_filter_display = $formatted_month_year ? "$filter_display_name $formatted_month_year" : $filter_display_name;

/* ----------------------------- Query ----------------------------- */
$query = "SELECT e.id, e.name, e.email, e.payment_type, e.bank_name, e.micr_code, e.ifsc_code, e.account_number, 
                 s.month_year, s.value_date, s.total_salary, s.extra_days, s.extra, s.bonus, s.leave_full_days, s.leave_half_days, 
                 s.deduct_pf, s.pf, s.professional_tax, s.late_charge, s.deposit, s.withdrawal, s.uniform, s.net_pay 
          FROM employees e 
          JOIN salaries s ON e.id = s.employee_id";

$conditions = [];
$params = [];
$types = "";

if ($month_year) {
    $conditions[] = "s.month_year = ?";
    $params[] = $month_year;
    $types .= "s";
}
if ($filter_type) {
    if ($filter_type === 'cash') {
        $conditions[] = "e.payment_type = ?";
        $params[] = 'cash';
        $types .= "s";
    } elseif ($filter_type === 'hdfc') {
        $conditions[] = "e.payment_type = ? AND e.bank_name = ?";
        $params[] = 'bank';
        $params[] = 'HDFC';
        $types .= "ss";
    } elseif ($filter_type === 'other') {
        $conditions[] = "e.payment_type = ? AND e.bank_name = ?";
        $params[] = 'bank';
        $params[] = 'Other';
        $types .= "ss";
    } elseif ($filter_type === 'apprentice') {
        $conditions[] = "e.payment_type = ? AND e.bank_name = ?";
        $params[] = 'bank';
        $params[] = 'Apprentice';
        $types .= "ss";
    } elseif ($filter_type === 'pf') {
        $conditions[] = "s.deduct_pf = ?";
        $params[] = 1;
        $types .= "i";
    } elseif ($filter_type === 'pt') {
        $conditions[] = "s.professional_tax > 0";
    }
}
if ($search) {
    $conditions[] = "e.name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}
$query .= " ORDER BY s.net_pay " . ($sort_order === 'asc' ? 'ASC' : 'DESC');

$stmt = $conn->prepare($query);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();

/* -------------------- Build data & totals -------------------- */
$excel_data = [];
$pf_excel_data = [];
$pt_excel_data = [];

$total_amount = 0;
$total_net_pay = 0;
$total_basic_salary = 0;
$total_extra = 0;
$total_extra_days_salary = 0;
$total_bonus = 0;
$total_leave_deduction = 0;
$total_pf = 0;
$total_pt = 0;

$sr = 1;
$data_rows = [];

while ($row = $result->fetch_assoc()) {
    $basic_salary     = round($row['total_salary']);
    $daily_rate       = $basic_salary / 30;
    $extra_days       = $row['extra_days'] ?? 0;
    $extra            = round($row['extra'] ?? 0);
    $bonus            = round($row['bonus'] ?? 0);
    $leave_full_days  = $row['leave_full_days'] ?? 0;
    $leave_half_days  = $row['leave_half_days'] ?? 0;
    $late_charge      = round($row['late_charge'] ?? 0);
    $deposit          = round($row['deposit'] ?? 0);
    $withdrawal       = round($row['withdrawal'] ?? 0);
    $uniform          = round($row['uniform'] ?? 0);
    $net_pay          = round($row['net_pay']);
    $pf               = round($row['pf'] ?? 0);
    $professional_tax = round($row['professional_tax'] ?? 0);

    $extra_days_salary = round($daily_rate * $extra_days);
    $leave_deduction   = round(($leave_full_days + $leave_half_days * 0.5) * $daily_rate);
    $value_date        = $row['value_date'] ? (new DateTime($row['value_date']))->format('d/m/Y') : '-';

    if ($filter_type === 'cash') {
        $data_row = [
            'Srno' => $sr,
            'Employee Name' => $row['name'],
            'Net Salary' => $net_pay
        ];
    } elseif ($filter_type === 'hdfc') {
        $data_row = [
            'Srno' => $sr,
            'Employee Name' => $row['name'],
            'Net Salary' => $net_pay,
            'MICR Code' => $row['micr_code'] ?: '-',
            'IFSC Code' => $row['ifsc_code'] ?: '-',
            'Beneficiary Account No.' => $row['account_number'] ?: '-'
        ];
    } elseif ($filter_type === 'other') {
        $data_row = [
            'Srno' => $sr,
            'Employee Name' => $row['name'],
            'Net Salary' => $net_pay,
            'MICR Code' => $row['micr_code'] ?: '-',
            'IFSC Code' => $row['ifsc_code'] ?: '-',
            'Beneficiary Account No.' => $row['account_number'] ?: '-',
            'value_date' => $value_date, // keep for table display
            'email' => $row['email'] ?: '-'
        ];
    } elseif ($filter_type === 'pf') {
        $data_row = [
            'Srno' => $sr,
            'Name' => $row['name'],
            'Basic Salary' => $basic_salary,
            'Extra' => $extra,
            'Extra Days Salary (₹)' => $extra_days_salary,
            'Bonus' => $bonus,
            'Leave Deduction' => $leave_deduction,
            'Gross Salary' => $basic_salary + $extra + $extra_days_salary + $bonus - $leave_deduction,
            'Less: Provident Fund' => $pf
        ];
    } elseif ($filter_type === 'pt') {
        $data_row = [
            'Srno' => $sr,
            'Name' => $row['name'],
            'Professional Tax' => $professional_tax
        ];
    } else {
        // default: bank file (with minimal bank fields)
        $data_row = [
            'Srno' => $sr,
            'Employee Name' => $row['name'],
            'Net Salary' => $net_pay,
            'MICR Code' => $row['micr_code'] ?: '-',
            'IFSC Code' => $row['ifsc_code'] ?: '-',
            'Beneficiary Account No.' => $row['account_number'] ?: '-'
        ];
    }

    $data_rows[] = $data_row;

    if ($row['deduct_pf']) {
        $pf_excel_data[] = [
            'Srno' => $sr,
            'Name' => $row['name'],
            'Basic Salary' => $basic_salary,
            'Extra' => $extra,
            'Extra Days Salary (₹)' => $extra_days_salary,
            'Bonus' => $bonus,
            'Leave Deduction' => $leave_deduction,
            'Gross Salary' => $basic_salary + $extra + $extra_days_salary + $bonus - $leave_deduction,
            'Less: Provident Fund' => $pf
        ];
        $total_pf += $pf;
    }

    if ($row['professional_tax'] > 0) {
        $pt_excel_data[] = [
            'Srno' => $sr,
            'Name' => $row['name'],
            'Professional Tax' => $professional_tax
        ];
        $total_pt += $professional_tax;
    }

    $total_net_pay           += $net_pay;
    $total_basic_salary      += $basic_salary;
    $total_extra             += $extra;
    $total_extra_days_salary += $extra_days_salary;
    $total_bonus             += $bonus;
    $total_leave_deduction   += $leave_deduction;
    $total_amount            += $net_pay;

    $sr++;
}

$excel_data = $data_rows;

/* ---------------- Append totals row for chosen export ---------------- */
if ($filter_type === 'cash') {
    $excel_data[] = ['Srno' => '', 'Employee Name' => 'TOTAL', 'Net Salary' => $total_net_pay];
} elseif ($filter_type === 'hdfc') {
    $excel_data[] = ['Srno' => '', 'Employee Name' => 'TOTAL', 'Net Salary' => $total_net_pay, 'MICR Code' => '', 'IFSC Code' => '', 'Beneficiary Account No.' => ''];
} elseif ($filter_type === 'pf') {
    $excel_data[] = [
        'Srno' => '', 'Name' => 'TOTAL', 'Basic Salary' => $total_basic_salary, 'Extra' => $total_extra,
        'Extra Days Salary (₹)' => $total_extra_days_salary, 'Bonus' => $total_bonus, 'Leave Deduction' => $total_leave_deduction,
        'Gross Salary' => $total_basic_salary + $total_extra + $total_extra_days_salary + $total_bonus - $total_leave_deduction,
        'Less: Provident Fund' => $total_pf
    ];
} elseif ($filter_type === 'pt') {
    $excel_data[] = ['Srno' => '', 'Name' => 'TOTAL', 'Professional Tax' => $total_pt];
} else {
    $excel_data[] = ['Srno' => '', 'Employee Name' => 'TOTAL', 'Net Salary' => $total_net_pay, 'MICR Code' => '', 'IFSC Code' => '', 'Beneficiary Account No.' => ''];
}

/* ------------------------------ EXPORTS ------------------------------ */
if (isset($_POST['download_excel'])) {
    if (empty($excel_data)) {
        $error = "No data available to download. Please apply filters or ensure data exists.";
    } else {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $merge_range = ($filter_type === 'cash' || $filter_type === 'pt') ? 'A1:C1' : ($filter_type === 'pf' ? 'A1:I1' : 'A1:F1');
        $column_count = ($filter_type === 'cash' || $filter_type === 'pt') ? 3 : ($filter_type === 'pf' ? 9 : 6);

        $sheet->setCellValue('A1', "M/S. NILAYKUMAR AND BROS JEWELLERS");
        $sheet->mergeCells($merge_range);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', '1ST, 2ND, 3RD, 4TH FLOOR');
        $sheet->setCellValue('A3', 'KRISHNA OPAL');
        $sheet->setCellValue('A4', 'ANAND V V NAGAR ROAD');
        $sheet->setCellValue('A5', 'ANAND - 388001');

        for ($row = 2; $row <= 5; $row++) {
            $sheet->mergeCells("A$row:" . Coordinate::stringFromColumnIndex($column_count) . "$row");
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        if ($filter_type === 'cash') {
            $sheet->setCellValue('C6', 'Total: ₹' . number_format((float)$total_amount, 2));
            $sheet->setCellValue('A6', $month_filter_display);
            $sheet->getStyle('C6')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('C6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        } elseif ($filter_type === 'pf') {
            $sheet->setCellValue('H6', 'Total Gross: ₹' . number_format((float)($total_basic_salary + $total_extra + $total_extra_days_salary + $total_bonus - $total_leave_deduction), 2));
            $sheet->setCellValue('I6', $month_filter_display);
            $sheet->getStyle('H6')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('H6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('I6')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('I6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        } elseif ($filter_type === 'pt') {
            $sheet->setCellValue('C6', 'Total PT: ₹' . number_format((float)$total_pt, 2));
            $sheet->setCellValue('A6', $month_filter_display);
            $sheet->getStyle('C6')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('C6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        } else {
            $sheet->setCellValue('E6', 'Total: ₹' . number_format((float)$total_amount, 2));
            $sheet->setCellValue('F6', $month_filter_display);
            $sheet->getStyle('E6')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('E6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('F6')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('F6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        if ($filter_type === 'cash') {
            $headers = ['Srno', 'Employee Name', 'Net Salary'];
        } elseif ($filter_type === 'pf') {
            $headers = ['Srno', 'Name', 'Basic Salary', 'Extra', 'Extra Days Salary (₹)', 'Bonus', 'Leave Deduction', 'Gross Salary', 'Less: Provident Fund'];
        } elseif ($filter_type === 'pt') {
            $headers = ['Srno', 'Name', 'Professional Tax'];
        } else {
            $headers = ['Srno', 'Employee Name', 'Net Salary', 'MICR Code', 'IFSC Code', 'Beneficiary Account No.'];
        }

        $col = 1;
        foreach ($headers as $header) {
            $cell = Coordinate::stringFromColumnIndex($col) . '7';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $col++;
        }

        $row_num = 8;
        foreach ($excel_data as $data_row) {
            $col = 1;
            foreach ($data_row as $key => $value) {
                $cell = Coordinate::stringFromColumnIndex($col) . $row_num;
                // Format account numbers as text to prevent scientific notation
                if ($key === 'Beneficiary Account No.' && $value !== '-' && is_numeric($value)) {
                    $sheet->setCellValueExplicit($cell, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($cell, $value);
                }
                $col++;
            }
            $row_num++;
        }

        $column_range = ($filter_type === 'cash' || $filter_type === 'pt') ? range('A', 'C') : ($filter_type === 'pf' ? range('A', 'I') : range('A', 'F'));
        foreach ($column_range as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $filename = "Final_Sheet_" . ($filter_type ?: 'All') . "_" . date('Ymd_His') . ".xlsx";
        try {
            ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit();
        } catch (Exception $e) {
            error_log("Error generating Excel: " . $e->getMessage());
            echo "An error occurred while generating the file.";
            exit();
        }
    }
}

if (isset($_POST['download_pf_excel']) && $month_year) {
    if (empty($pf_excel_data)) {
        $error = "No employees with Provident Fund deductions found for the selected month.";
    } else {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', "M/S. NILAYKUMAR AND BROS JEWELLERS");
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', '1ST, 2ND, 3RD, 4TH FLOOR');
        $sheet->setCellValue('A3', 'KRISHNA OPAL');
        $sheet->setCellValue('A4', 'ANAND V V NAGAR ROAD');
        $sheet->setCellValue('A5', 'ANAND - 388001');

        for ($row = 2; $row <= 5; $row++) {
            $sheet->mergeCells("A$row:I$row");
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->setCellValue('H6', 'Total Gross: ₹' . number_format((float)($total_basic_salary + $total_extra + $total_extra_days_salary + $total_bonus - $total_leave_deduction), 2));
        $sheet->setCellValue('I6', $month_filter_display);
        $sheet->getStyle('H6')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('H6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('I6')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('I6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $headers = ['Srno', 'Name', 'Basic Salary', 'Extra', 'Extra Days Salary (₹)', 'Bonus', 'Leave Deduction', 'Gross Salary', 'Less: Provident Fund'];
        $col = 1;
        foreach ($headers as $header) {
            $cell = Coordinate::stringFromColumnIndex($col) . '7';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $col++;
        }

        $row_num = 8;
        foreach ($pf_excel_data as $data_row) {
            $col = 1;
            foreach ($data_row as $value) {
                $cell = Coordinate::stringFromColumnIndex($col) . $row_num;
                $sheet->setCellValue($cell, $value);
                $col++;
            }
            $row_num++;
        }

        $totals_row = $row_num;
        $sheet->setCellValue('A' . $totals_row, '');
        $sheet->setCellValue('B' . $totals_row, 'TOTAL');
        $sheet->setCellValue('C' . $totals_row, $total_basic_salary);
        $sheet->setCellValue('D' . $totals_row, $total_extra);
        $sheet->setCellValue('E' . $totals_row, $total_extra_days_salary);
        $sheet->setCellValue('F' . $totals_row, $total_bonus);
        $sheet->setCellValue('G' . $totals_row, $total_leave_deduction);
        $sheet->setCellValue('H' . $totals_row, $total_basic_salary + $total_extra + $total_extra_days_salary + $total_bonus - $total_leave_deduction);
        $sheet->setCellValue('I' . $totals_row, $total_pf);
        $sheet->getStyle('A' . $totals_row . ':I' . $totals_row)->getFont()->setBold(true);

        foreach (range('A', 'I') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $filename = "PF_Deductions_" . $month_year . "_" . date('Ymd_His') . ".xlsx";
        try {
            ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit();
        } catch (Exception $e) {
            error_log("Error generating PF Excel: " . $e->getMessage());
            echo "An error occurred while generating the PF file.";
            exit();
        }
    }
}

if (isset($_POST['download_pt_excel']) && $month_year) {
    if (empty($pt_excel_data)) {
        $error = "No employees with Professional Tax deductions found for the selected month.";
    } else {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', "M/S. NILAYKUMAR AND BROS JEWELLERS");
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', '1ST, 2ND, 3RD, 4TH FLOOR');
        $sheet->setCellValue('A3', 'KRISHNA OPAL');
        $sheet->setCellValue('A4', 'ANAND V V NAGAR ROAD');
        $sheet->setCellValue('A5', 'ANAND - 388001');

        for ($row = 2; $row <= 5; $row++) {
            $sheet->mergeCells("A$row:C$row");
            $sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->setCellValue('C6', 'Total PT: ₹' . number_format((float)$total_pt, 2));
        $sheet->setCellValue('A6', $month_filter_display);
        $sheet->getStyle('C6')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('C6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A6')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $headers = ['Srno', 'Name', 'Professional Tax'];
        $col = 1;
        foreach ($headers as $header) {
            $cell = Coordinate::stringFromColumnIndex($col) . '7';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $col++;
        }

        $row_num = 8;
        foreach ($pt_excel_data as $data_row) {
            $col = 1;
            foreach ($data_row as $value) {
                $cell = Coordinate::stringFromColumnIndex($col) . $row_num;
                $sheet->setCellValue($cell, $value);
                $col++;
            }
            $row_num++;
        }

        $totals_row = $row_num;
        $sheet->setCellValue('A' . $totals_row, '');
        $sheet->setCellValue('B' . $totals_row, 'TOTAL');
        $sheet->setCellValue('C' . $totals_row, $total_pt);
        $sheet->getStyle('A' . $totals_row . ':C' . $totals_row)->getFont()->setBold(true);

        foreach (range('A', 'C') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $filename = "PT_Deductions_" . $month_year . "_" . date('Ymd_His') . ".xlsx";
        try {
            ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit();
        } catch (Exception $e) {
            error_log("Error generating PT Excel: " . $e->getMessage());
            echo "An error occurred while generating the PT file.";
            exit();
        }
    }
}

$result->data_seek(0); // harmless; preserves original flow
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $company ? htmlspecialchars($company['company_name']) : 'Payroll Management'; ?> - Final Sheet</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      overflow-x: hidden;
      color:#2c3e50;
  }

  /* Header (same as salaries page) */
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

  /* Sidebar (same as salaries page) */
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
  .content { margin-left:280px; margin-top:70px; padding:28px; min-height:calc(100vh - 70px); transition: margin-left .3s; }

  /* Cards & small UI (kept from your Final Sheet) */
  .card-soft{
      background:#ffffff; border-radius:20px; padding:24px 22px;
      box-shadow:0 10px 30px rgba(0,0,0,.08); position:relative; overflow:hidden;
  }
  .card-soft::before{
      content:''; position:absolute; left:0; top:0; height:4px; width:100%;
      background:linear-gradient(135deg,#667eea,#764ba2);
  }
  .section-title{ font-size:1.4rem; font-weight:800; margin:0; letter-spacing:.2px; }

  .form-label{ font-weight:600; color:#2c3e50; margin-bottom:8px; }
  .form-control, .form-select{
      border:2px solid #e9ecef; border-radius:12px; padding:12px 14px; background:#f8f9fa;
      transition:.25s ease; font-size:1rem;
  }
  .form-control:focus, .form-select:focus{
      background:#fff; border-color:#667eea; box-shadow:0 0 0 .22rem rgba(102,126,234,.25);
  }

  .toolbar{ display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap; }
  .total-pill{ background:#22c55e; color:#fff; border-radius:999px; padding:8px 14px; font-weight:800; letter-spacing:.2px; }
  .icon-btn{ border:none; background:transparent; cursor:pointer; padding:8px; border-radius:12px; transition:.2s ease; display:inline-flex; align-items:center; justify-content:center; }
  .icon-btn:hover{ background:rgba(0,0,0,.05); transform:translateY(-1px); }
  .icon-btn:focus{ outline:none; box-shadow:0 0 0 .2rem rgba(102,126,234,.25); }

  .table-wrap { position:relative; overflow-x:auto; max-height:60vh; border-radius:16px; }
  table thead th {
      position: sticky; top: 0; z-index: 1;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      color:#fff; border-color: transparent !important;
      vertical-align:middle;
  }
  .table{ margin:0; }
  .table tbody tr:hover{ background:#f6f8ff; }

  /* Responsive */
  @media (max-width:768px){
      .sidebar{ transform: translateX(-280px); }
      .sidebar.active{ transform: translateX(0); }
      .content{ margin-left:0; padding:20px 16px; }
      .hamburger{ display:inline-block; }
      .header-title{ font-size:1.1rem; }
  }
</style>
</head>
<body>

<!-- Header (matched to salaries page) -->
<div class="header">
  <div class="header-content">
    <div style="display:flex; align-items:center; gap:15px;">
      <span class="hamburger"><i class="fas fa-bars"></i></span>
      <div class="header-title">
        <i class="fas fa-file-invoice-dollar"></i>
        <?php echo $company ? htmlspecialchars($company['company_name']) : 'Payroll Management'; ?> — Final Sheet
      </div>
    </div>
    <div class="user-profile">
      <i class="fas fa-user-circle" style="font-size:1.2rem;"></i>
      <span>Admin</span>
    </div>
  </div>
</div>

<!-- Sidebar (matched to salaries page) -->
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
    <li><a class="active" href="final_sheet.php"><i class="fas fa-file-invoice-dollar"></i>Final Sheet</a></li>
    <li><a href="salary_slip.php"><i class="fas fa-file-invoice"></i>Salary Slips</a></li>
    <li><a href="manage_company.php"><i class="fas fa-cogs"></i>Manage Company</a></li>
    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
  </ul>
</div>

<!-- Overlay for mobile -->
<div class="sidebar-overlay"></div>

<!-- Main Content -->
<div class="content">
  <!-- Filters Card -->
  <div class="row mb-4 justify-content-center">
    <div class="col-xl-10 col-lg-11">
      <div class="card-soft">
        <div class="d-flex align-items-center gap-2 mb-3">
          <i class="fas fa-filter"></i>
          <h2 class="section-title mb-0">Filter</h2>
        </div>
        <form method="POST" id="filterForm" class="row g-3">
          <div class="col-md-3">
            <label for="month_year" class="form-label">Month & Year</label>
            <?php
              $default_month_year = date('Y-m');
              $month_year_value = isset($_POST['month_year']) && !empty($_POST['month_year']) ? $_POST['month_year'] : $default_month_year;
            ?>
            <div class="input-group">
              <span class="input-group-text"><i class="far fa-calendar"></i></span>
              <input type="month" id="month_year" name="month_year" class="form-control" value="<?php echo htmlspecialchars($month_year_value); ?>">
            </div>
          </div>

          <div class="col-md-3">
            <label for="filter_type" class="form-label">Type</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
              <select id="filter_type" name="filter_type" class="form-select">
                <option value="">All</option>
                <option value="cash" <?php echo $filter_type === 'cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="hdfc" <?php echo $filter_type === 'hdfc' ? 'selected' : ''; ?>>HDFC Bank</option>
                <option value="other" <?php echo $filter_type === 'other' ? 'selected' : ''; ?>>Other Banks</option>
                <option value="apprentice" <?php echo $filter_type === 'apprentice' ? 'selected' : ''; ?>>Apprentice</option>
                <option value="pf" <?php echo $filter_type === 'pf' ? 'selected' : ''; ?>>Provident Fund</option>
                <option value="pt" <?php echo $filter_type === 'pt' ? 'selected' : ''; ?>>Professional Tax</option>
              </select>
            </div>
          </div>

          <div class="col-md-3">
            <label for="search" class="form-label">Search by Name</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
              <input type="text" id="search" name="search" class="form-control" placeholder="Start typing…" value="<?php echo htmlspecialchars($search); ?>">
            </div>
          </div>

          <div class="col-md-3">
            <label for="sort_order" class="form-label">Sort by Net Salary</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-arrow-up-wide-short"></i></span>
              <select id="sort_order" name="sort_order" class="form-select">
                <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>High to Low</option>
                <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Low to High</option>
              </select>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Table & Toolbar -->
  <div class="row justify-content-center">
    <div class="col-xl-12">
      <div class="card-soft">
        <div class="toolbar mb-3">
          <h2 class="section-title mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Final Sheet</h2>
          <div class="d-flex align-items-center gap-2">
            <span class="total-pill">Total: ₹<?php echo number_format((float)$total_amount, 2); ?></span>

            <!-- Download main -->
            <form method="POST" class="m-0">
              <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
              <input type="hidden" name="filter_type" value="<?php echo htmlspecialchars($filter_type); ?>">
              <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
              <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sort_order); ?>">
              <button type="submit" name="download_excel" class="icon-btn" title="Download Excel">
                <i class="fas fa-download fa-lg text-success"></i>
              </button>
            </form>

            <?php if ($filter_type === 'pf' || !$filter_type || $filter_type === 'pt'): ?>
              <!-- PF Excel -->
              <form method="POST" class="m-0">
                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                <input type="hidden" name="filter_type" value="pf">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sort_order); ?>">
                <button type="submit" name="download_pf_excel" class="icon-btn" title="Download PF Excel">
                  <i class="fas fa-file-excel fa-lg text-warning"></i>
                </button>
              </form>

              <!-- PT Excel -->
              <form method="POST" class="m-0">
                <input type="hidden" name="month_year" value="<?php echo htmlspecialchars($month_year); ?>">
                <input type="hidden" name="filter_type" value="pt">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sort_order); ?>">
                <button type="submit" name="download_pt_excel" class="icon-btn" title="Download PT Excel">
                  <i class="fas fa-file-excel fa-lg text-info"></i>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <?php if (isset($error)): ?>
          <div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-2"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="table-wrap">
          <table class="table table-striped table-bordered align-middle">
            <thead>
              <tr>
                <?php if ($filter_type === 'cash') { ?>
                  <th>SR</th>
                  <th>Employee Name</th>
                  <th>Net Salary</th>
                <?php } elseif ($filter_type === 'hdfc') { ?>
                  <th>Srno</th>
                  <th>Employee Name</th>
                  <th>Net Salary</th>
                  <th>MICR Code</th>
                  <th>IFSC Code</th>
                  <th>Beneficiary Account No</th>
                <?php } elseif ($filter_type === 'other') { ?>
                  <th>Srno</th>
                  <th>Employee Name</th>
                  <th>Net Salary</th>
                  <th>Value Date</th>
                  <th>Branch Code</th>
                  <th>Sender</th>
                  <th>Remitter Account NO</th>
                  <th>Remitter Name</th>
                  <th>IFSC CODE</th>
                  <th>Debit ACCOUNT</th>
                  <th>Beneficiary Account Type</th>
                  <th>Bank Account Number/Consumer no</th>
                  <th>Beneficiary Name</th>
                  <th>Remittance Details</th>
                  <th>Debit Account System</th>
                  <th>Originator Of Remittance</th>
                  <th>Email/Phone No</th>
                <?php } elseif ($filter_type === 'pf') { ?>
                  <th>Srno</th>
                  <th>Name</th>
                  <th>Basic Salary</th>
                  <th>Extra</th>
                  <th>Extra Days Salary (₹)</th>
                  <th>Bonus</th>
                  <th>Leave Deduction</th>
                  <th>Gross Salary</th>
                  <th>Less: Provident Fund</th>
                <?php } elseif ($filter_type === 'pt') { ?>
                  <th>Srno</th>
                  <th>Name</th>
                  <th>Professional Tax</th>
                <?php } else { ?>
                  <th>Srno</th>
                  <th>Employee Name</th>
                  <th>Net Salary</th>
                  <th>MICR Code</th>
                  <th>IFSC Code</th>
                  <th>Beneficiary Account No</th>
                <?php } ?>
              </tr>
            </thead>
            <tbody>
              <?php 
              $sr = 1;
              foreach ($data_rows as $row) {
                  $net_salary = isset($row['Net Salary']) ? (float)$row['Net Salary'] : 0;
                  $value_date = $row['value_date'] ?? '-';
              ?>
              <tr>
                <?php if ($filter_type === 'cash') { ?>
                  <td><?php echo $sr++; ?></td>
                  <td><?php echo htmlspecialchars($row['Employee Name']); ?></td>
                  <td><?php echo "₹" . number_format($net_salary, 2); ?></td>
                <?php } elseif ($filter_type === 'hdfc') { ?>
                  <td><?php echo $sr++; ?></td>
                  <td><?php echo htmlspecialchars($row['Employee Name']); ?></td>
                  <td><?php echo "₹" . number_format($net_salary, 2); ?></td>
                  <td><?php echo htmlspecialchars($row['MICR Code']); ?></td>
                  <td><?php echo htmlspecialchars($row['IFSC Code']); ?></td>
                  <td><?php echo htmlspecialchars($row['Beneficiary Account No.']); ?></td>
                <?php } elseif ($filter_type === 'other') { ?>
                  <td><?php echo $sr++; ?></td>
                  <td><?php echo htmlspecialchars($row['Employee Name']); ?></td>
                  <td><?php echo "₹" . number_format($net_salary, 2); ?></td>
                  <td><?php echo htmlspecialchars($value_date); ?></td>
                  <td><?php echo htmlspecialchars($company['branch_code'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($company['sender'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($company['remitter_account_no'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($company['remitter_name'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['IFSC Code']); ?></td>
                  <td><?php echo htmlspecialchars($company['debit_account'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($company['beneficiary_account_type'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['Beneficiary Account No.']); ?></td>
                  <td><?php echo htmlspecialchars($row['Employee Name']); ?></td>
                  <td><?php echo htmlspecialchars($company['remittance_details'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($company['debit_account_system'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($company['originator_of_remittance'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                <?php } elseif ($filter_type === 'pf') { ?>
                  <td><?php echo $sr++; ?></td>
                  <td><?php echo htmlspecialchars($row['Name']); ?></td>
                  <td><?php echo "₹" . number_format($row['Basic Salary'], 2); ?></td>
                  <td><?php echo "₹" . number_format($row['Extra'], 2); ?></td>
                  <td><?php echo "₹" . number_format($row['Extra Days Salary (₹)'], 2); ?></td>
                  <td><?php echo "₹" . number_format($row['Bonus'], 2); ?></td>
                  <td><?php echo "₹" . number_format($row['Leave Deduction'], 2); ?></td>
                  <td><?php echo "₹" . number_format($row['Gross Salary'], 2); ?></td>
                  <td><?php echo "₹" . number_format($row['Less: Provident Fund'], 2); ?></td>
                <?php } elseif ($filter_type === 'pt') { ?>
                  <td><?php echo $sr++; ?></td>
                  <td><?php echo htmlspecialchars($row['Name']); ?></td>
                  <td><?php echo "₹" . number_format($row['Professional Tax'], 2); ?></td>
                <?php } else { ?>
                  <td><?php echo $sr++; ?></td>
                  <td><?php echo htmlspecialchars($row['Employee Name']); ?></td>
                  <td><?php echo "₹" . number_format($net_salary, 2); ?></td>
                  <td><?php echo htmlspecialchars($row['MICR Code']); ?></td>
                  <td><?php echo htmlspecialchars($row['IFSC Code']); ?></td>
                  <td><?php echo htmlspecialchars($row['Beneficiary Account No.']); ?></td>
                <?php } ?>
              </tr>
              <?php } ?>

              <!-- Totals row -->
              <tr class="table-info">
                <?php if ($filter_type === 'cash') { ?>
                  <td></td>
                  <td><strong>TOTAL</strong></td>
                  <td><strong><?php echo "₹" . number_format($total_net_pay, 2); ?></strong></td>
                <?php } elseif ($filter_type === 'hdfc') { ?>
                  <td></td>
                  <td><strong>TOTAL</strong></td>
                  <td><strong><?php echo "₹" . number_format($total_net_pay, 2); ?></strong></td>
                  <td></td><td></td><td></td>
                <?php } elseif ($filter_type === 'other') { ?>
                  <td></td>
                  <td><strong>TOTAL</strong></td>
                  <td><strong><?php echo "₹" . number_format($total_net_pay, 2); ?></strong></td>
                  <td></td><td></td><td></td><td></td><td></td><td></td>
                  <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <?php } elseif ($filter_type === 'pf') { ?>
                  <td></td>
                  <td><strong>TOTAL</strong></td>
                  <td><strong><?php echo "₹" . number_format($total_basic_salary, 2); ?></strong></td>
                  <td><strong><?php echo "₹" . number_format($total_extra, 2); ?></strong></td>
                  <td><strong><?php echo "₹" . number_format($total_extra_days_salary, 2); ?></strong></td>
                  <td><strong><?php echo "₹" . number_format($total_bonus, 2); ?></strong></td>
                  <td><strong><?php echo "₹" . number_format($total_leave_deduction, 2); ?></strong></td>
                  <td><strong><?php echo "₹" . number_format($total_basic_salary + $total_extra + $total_extra_days_salary + $total_bonus - $total_leave_deduction, 2); ?></strong></td>
                  <td><strong><?php echo "₹" . number_format($total_pf, 2); ?></strong></td>
                <?php } elseif ($filter_type === 'pt') { ?>
                  <td></td>
                  <td><strong>TOTAL</strong></td>
                  <td><strong><?php echo "₹" . number_format($total_pt, 2); ?></strong></td>
                <?php } else { ?>
                  <td></td>
                  <td><strong>TOTAL</strong></td>
                  <td><strong><?php echo "₹" . number_format($total_net_pay, 2); ?></strong></td>
                  <td></td><td></td><td></td>
                <?php } ?>
              </tr>
            </tbody>
          </table>
        </div><!-- /table-wrap -->
      </div>
    </div>
  </div>
</div><!-- /content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Sidebar toggle (same behavior as salaries page) */
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

  // Filter auto-submit (debounced typing)
  const form            = document.getElementById('filterForm');
  const monthYearInput  = document.getElementById('month_year');
  const searchInput     = document.getElementById('search');
  const sortOrderInput  = document.getElementById('sort_order');
  const filterTypeInput = document.getElementById('filter_type');

  function submitNow(){ form.submit(); }
  let debounce;
  function submitDebounced(){
    clearTimeout(debounce);
    debounce = setTimeout(() => form.submit(), 300);
  }

  monthYearInput.addEventListener('change', () => { if (monthYearInput.value) submitNow(); });
  filterTypeInput.addEventListener('change', submitNow);
  sortOrderInput.addEventListener('change', submitNow);
  searchInput.addEventListener('input', submitDebounced);

  if (searchInput.value){
    searchInput.focus();
    const len = searchInput.value.length;
    searchInput.setSelectionRange(len, len);
  }
});
</script>
</body>
</html>
<?php
$stmt->close();
?>
