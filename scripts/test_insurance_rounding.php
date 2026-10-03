<?php
declare(strict_types=1);
$base = dirname(__DIR__);
define('BASE_PATH', $base);
require $base . '/vendor/autoload.php';
require $base . '/app/Core/App.php';
require $base . '/app/Core/Database.php';
require $base . '/app/Core/Auth.php';
require $base . '/app/Core/Helpers.php';
require $base . '/app/Core/HrService.php';

App\Core\App::boot();

$code = 'TEST_' . time();
App\Core\Database::query("INSERT INTO employees (emp_code, full_name, base_salary, hire_date, file_status) VALUES (?,?,?,?, 'open')", [$code, 'Test Employee', 10000, '2026-01-01']);
$empId = (int) App\Core\Database::lastInsertId();
$period = '2026-09';
App\Core\Database::query("INSERT INTO employee_monthly_insurance (employee_id, period, amount) VALUES (?,?,?)", [$empId, $period, 500]);

try {
    $comp = App\Core\HrService::calculatePayrollComponents($empId, $period);
    echo "Insurance: " . ($comp['insurance'] ?? 'NULL') . "\n";
    echo "Rounded net: " . ($comp['net_salary'] ?? 'NULL') . "\n";
    echo "Rounding adjustment: " . ($comp['rounding_adjustment'] ?? 'NULL') . "\n";
    echo "Total deductions: " . ($comp['total_deductions'] ?? 'NULL') . "\n";

    $ok = true;
    if ((float)($comp['insurance'] ?? 0) !== 500.0) { echo "FAIL insurance\n"; $ok = false; }
    if ((float)($comp['net_salary'] ?? 0) !== 9000.0) { echo "FAIL net rounding\n"; $ok = false; }
    if ((float)($comp['rounding_adjustment'] ?? 0) !== -175.0) { echo "FAIL rounding adjustment\n"; $ok = false; }
    if ((float)($comp['total_deductions'] ?? 0) !== 1000.0) { echo "FAIL total deductions\n"; $ok = false; }
    if ($ok) echo "OK insurance + rounding\n";

    $payrollId = App\Core\HrService::generatePayroll($empId, $period, 1);
    $row = App\Core\Database::query("SELECT * FROM payrolls WHERE id = ?", [$payrollId])->fetch();
    echo "Stored payroll: insurance=" . ($row['insurance'] ?? 'NULL') . " rounding=" . ($row['rounding_adjustment'] ?? 'NULL') . " net=" . ($row['net_salary'] ?? 'NULL') . "\n";
    if ((float)($row['insurance'] ?? 0) === 500.0 && (float)($row['rounding_adjustment'] ?? 0) === -175.0 && (float)($row['net_salary'] ?? 0) === 9000.0) {
        echo "OK generatePayroll stores values\n";
    } else {
        echo "FAIL generatePayroll stores values\n";
    }

    App\Core\Database::query("DELETE FROM employee_monthly_insurance WHERE employee_id = ? AND period = ?", [$empId, $period]);
    $comp2 = App\Core\HrService::calculatePayrollComponents($empId, '2026-10');
    echo "No-insurance: net=" . ($comp2['net_salary'] ?? 'NULL') . " rounding=" . ($comp2['rounding_adjustment'] ?? 'NULL') . " insurance=" . ($comp2['insurance'] ?? 'NULL') . " total_deductions=" . ($comp2['total_deductions'] ?? 'NULL') . "\n";
    if ((float)($comp2['insurance'] ?? 0) !== 0.0 || (float)($comp2['net_salary'] ?? 0) !== 10000.0 || (float)($comp2['rounding_adjustment'] ?? 0) !== 350.0 || (float)($comp2['total_deductions'] ?? 0) !== 0.0) {
        echo "FAIL no insurance period\n";
    } else {
        echo "OK no insurance period\n";
    }

    // تذاكر سفر + تحميلات
    App\Core\Database::query("INSERT INTO employee_monthly_insurance (employee_id, period, amount) VALUES (?,?,?)", [$empId, $period, 500]);
    App\Core\HrService::addEmployeeTravelTicket($empId, $period, 2000, '2026-09-10', '2026-09-20', 'طيران', 'test ticket');
    App\Core\HrService::setEmployeeMonthlyLoading($empId, $period, 'تحميلات', 1000, 'test loading');
    $comp3 = App\Core\HrService::calculatePayrollComponents($empId, $period);
    echo "Travel+Loadings: travel=" . ($comp3['travel_tickets'] ?? 'NULL') . " loadings=" . ($comp3['loadings'] ?? 'NULL') . " net=" . ($comp3['net_salary'] ?? 'NULL') . " rounding=" . ($comp3['rounding_adjustment'] ?? 'NULL') . "\n";
    $ok3 = true;
    if ((float)($comp3['travel_tickets'] ?? 0) !== 2000.0) { echo "FAIL travel tickets
"; $ok3 = false; }
    if ((float)($comp3['loadings'] ?? 0) !== 1000.0) { echo "FAIL loadings
"; $ok3 = false; }
    if ((float)($comp3['net_salary'] ?? 0) !== 10000.0) { echo "FAIL net with travel+loadings
"; $ok3 = false; }
    if ((float)($comp3['rounding_adjustment'] ?? 0) !== -125.0) { echo "FAIL rounding with travel+loadings
"; $ok3 = false; }
    if ($ok3) echo "OK travel tickets + loadings
";

    $payrollId2 = App\Core\HrService::generatePayroll($empId, $period, 1);
    $row2 = App\Core\Database::query("SELECT * FROM payrolls WHERE id = ?", [$payrollId2])->fetch();
    echo "Stored payroll2: travel=" . ($row2['travel_tickets'] ?? 'NULL') . " loadings=" . ($row2['loadings'] ?? 'NULL') . " net=" . ($row2['net_salary'] ?? 'NULL') . "\n";
    if ((float)($row2['travel_tickets'] ?? 0) === 2000.0 && (float)($row2['loadings'] ?? 0) === 1000.0 && (float)($row2['net_salary'] ?? 0) === 10000.0) {
        echo "OK generatePayroll stores travel+loadings\n";
    } else {
        echo "FAIL generatePayroll stores travel+loadings\n";
    }

    // التيبس + الضمان الاجتماعي
    $period2 = '2026-11';
    App\Core\HrService::setEmployeeMonthlyTips($empId, $period2, 3000, 15, 'test tips', 1);
    App\Core\HrService::setEmployeeMonthlySocialSecurity($empId, $period2, 750, 'test ss', 1);
    $comp4 = App\Core\HrService::calculatePayrollComponents($empId, $period2);
    echo "Tips+SS: tips=" . ($comp4['tips'] ?? 'NULL') . " ss=" . ($comp4['social_security'] ?? 'NULL') . " net=" . ($comp4['net_salary'] ?? 'NULL') . " rounding=" . ($comp4['rounding_adjustment'] ?? 'NULL') . " total_deductions=" . ($comp4['total_deductions'] ?? 'NULL') . "\n";
    $ok4 = true;
    if ((float)($comp4['tips'] ?? 0) !== 2000.0) { echo "FAIL tips prorated\n"; $ok4 = false; }
    if ((float)($comp4['social_security'] ?? 0) !== 750.0) { echo "FAIL social security\n"; $ok4 = false; }
    if ((float)($comp4['net_salary'] ?? 0) !== 7000.0) { echo "FAIL net with tips+ss\n"; $ok4 = false; }
    if ((float)($comp4['rounding_adjustment'] ?? 0) !== -37.5) { echo "FAIL rounding with tips+ss\n"; $ok4 = false; }
    if ((float)($comp4['total_deductions'] ?? 0) !== 3000.0) { echo "FAIL total deductions with tips+ss\n"; $ok4 = false; }
    if ($ok4) echo "OK tips + social security\n";

    $payrollId3 = App\Core\HrService::generatePayroll($empId, $period2, 1);
    $row3 = App\Core\Database::query("SELECT * FROM payrolls WHERE id = ?", [$payrollId3])->fetch();
    echo "Stored payroll3: tips=" . ($row3['tips'] ?? 'NULL') . " ss=" . ($row3['social_security'] ?? 'NULL') . " net=" . ($row3['net_salary'] ?? 'NULL') . "\n";
    if ((float)($row3['tips'] ?? 0) === 2000.0 && (float)($row3['social_security'] ?? 0) === 750.0 && (float)($row3['net_salary'] ?? 0) === 7000.0) {
        echo "OK generatePayroll stores tips+ss\n";
    } else {
        echo "FAIL generatePayroll stores tips+ss\n";
    }
} finally {
    App\Core\Database::query("DELETE FROM payrolls WHERE employee_id = ?", [$empId]);
    App\Core\Database::query("DELETE FROM employee_monthly_insurance WHERE employee_id = ?", [$empId]);
    App\Core\Database::query("DELETE FROM employee_travel_tickets WHERE employee_id = ?", [$empId]);
    App\Core\Database::query("DELETE FROM employee_monthly_loadings WHERE employee_id = ?", [$empId]);
    App\Core\Database::query("DELETE FROM employee_monthly_tips WHERE employee_id = ?", [$empId]);
    App\Core\Database::query("DELETE FROM employee_monthly_social_security WHERE employee_id = ?", [$empId]);
    App\Core\Database::query("DELETE FROM employees WHERE id = ?", [$empId]);
}
