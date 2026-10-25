<?php
declare(strict_types=1);

// Run with: php tests/HrReportDepartmentTest.php (requires pdo_sqlite).
require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/../app/Core/HrService.php';
require __DIR__ . '/../app/Core/HrReportService.php';

use App\Core\Database;
use App\Core\HrReportService;

date_default_timezone_set('Asia/Baghdad');
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
(new ReflectionProperty(Database::class, 'instance'))->setValue(null, $db);
$db->exec('CREATE TABLE departments (id INTEGER PRIMARY KEY, name TEXT, is_active INTEGER)');
$db->exec('CREATE TABLE employees (id INTEGER PRIMARY KEY, emp_code TEXT, full_name TEXT, department TEXT, department_id INTEGER, branch_id INTEGER, shift_id INTEGER, attendance_type TEXT, required_hours REAL)');
$db->exec('CREATE TABLE attendance (id INTEGER PRIMARY KEY, employee_id INTEGER, check_type TEXT, checked_at TEXT, source TEXT, within_geofence INTEGER DEFAULT 0)');
$db->exec('CREATE TABLE shifts (id INTEGER PRIMARY KEY, name TEXT, start_time TEXT, end_time TEXT)');
$db->exec('CREATE TABLE employee_shifts (employee_id INTEGER, shift_date TEXT, shift_id INTEGER)');
$db->exec('CREATE TABLE shift_work_days (shift_id INTEGER, day_of_week INTEGER)');
$db->exec('CREATE TABLE leave_requests (employee_id INTEGER, date_from TEXT, date_to TEXT, status TEXT)');
$db->exec('CREATE TABLE overtime_requests (employee_id INTEGER, request_date TEXT, hours REAL, status TEXT, biometric_match INTEGER)');
$money = ['base_salary','allowances','overtime','raises','bonuses','travel_tickets','deductions','advances','security_deposit','loadings','tips','social_security','tax_amount','net_salary','rounding_adjustment'];
$db->exec('CREATE TABLE payrolls (employee_id INTEGER, period TEXT, status TEXT, ' . implode(', ', array_map(fn($c) => "$c REAL DEFAULT 0", $money)) . ')');
$db->exec("INSERT INTO departments VALUES (1, 'صالة', 0), (2, 'المطبخ', 1)");
$fixture = [
    [1, 'A', 'A', 'ادارة الصالة', 1, 1, 0, 'flexible', 8],
    [2, 'B', 'B', 'اسم قديم آخر', 1, 1, 0, 'flexible', 8],
    [3, 'C', 'C', 'قسم قديم بدون ربط', null, 1, 0, 'flexible', 8],
    [4, 'D', 'D', 'قسم محذوف', 999, 1, 0, 'flexible', 8],
    [5, 'E', 'E', 'اسم مطبخ قديم', 2, 2, 0, 'flexible', 8],
];
foreach ($fixture as $employee) {
    $db->prepare('INSERT INTO employees VALUES (?,?,?,?,?,?,?,?,?)')->execute($employee);
    foreach (['in' => '08:00:00', 'out' => '16:00:00'] as $type => $time) {
        $db->prepare('INSERT INTO attendance (employee_id, check_type, checked_at, source) VALUES (?,?,?,?)')->execute([$employee[0], $type, "2026-10-15 $time", 'test']);
    }
    $db->prepare("INSERT INTO payrolls (employee_id, period, status, base_salary, net_salary) VALUES (?, '2026-10', 'draft', 100, 100)")->execute([$employee[0]]);
    $db->prepare("INSERT INTO overtime_requests VALUES (?, '2026-10-15', 2, 'approved', 1)")->execute([$employee[0]]);
}
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$expected = ['A' => 'صالة', 'B' => 'صالة', 'C' => 'قسم قديم بدون ربط', 'D' => 'قسم محذوف', 'E' => 'المطبخ'];
foreach (['attendance', 'payroll', 'overtime', 'monthly_performance', 'location'] as $type) {
    $report = HrReportService::getReport($type, ['period' => '2026-10']);
    foreach ($report['rows'] as $row) {
        check($row['department'] === $expected[$row['emp_code']], "$type must match the employee profile department");
    }
    check(count(array_unique(array_column($report['rows'], 'emp_code'))) === 5, "$type must retain employees without a matching department");
}
$summary = HrReportService::getReport('payroll_summary', ['period' => '2026-10']);
$hall = array_values(array_filter($summary['rows'], fn($r) => $r['department'] === 'صالة'));
check(count($hall) === 1 && (int)$hall[0]['employee_count'] === 2 && (float)$hall[0]['total_net_salary'] === 200.0, 'Summary must combine different old names for the same current department');
check(array_sum(array_column($summary['rows'], 'total_net_salary')) == 500, 'Summary must preserve salary totals');
foreach (['attendance', 'monthly_performance'] as $type) {
    $rows = HrReportService::getReport($type, ['period' => '2026-10', 'department_id' => 1, 'branch_id' => 1])['rows'];
    check(count($rows) === 2, "$type department and branch filters must still work");
}
$db->exec("UPDATE departments SET name = 'الصالة الجديدة' WHERE id = 1");
$row = HrReportService::getReport('attendance', ['period' => '2026-10', 'employee_id' => 1])['rows'][0];
check($row['department'] === 'الصالة الجديدة', 'A department rename must appear immediately without updating employee records');
check($row['total_hours'] === '08:00', 'Attendance pairing and hours must remain unchanged');
check($db->query('SELECT department FROM employees WHERE id = 1')->fetchColumn() === 'ادارة الصالة', 'Reports must not rewrite employee data');
echo "PASS: canonical names, renames, inactive departments, missing links, attendance, exports source rows, summary totals and filters.\n";
