<?php
declare(strict_types=1);

// Run after composer install: php tests/AttendanceExcelExportTest.php
require getenv('TEST_VENDOR_AUTOLOAD') ?: __DIR__ . '/../vendor/autoload.php';

if (($argv[1] ?? '') === '--export') {
    ob_start();
    require __DIR__ . '/HrReportDepartmentTest.php'; // In-memory synthetic employees only.
    ob_end_clean();
    require __DIR__ . '/../app/Core/ExportHelper.php';
    require __DIR__ . '/../app/Core/Controller.php';
    require __DIR__ . '/../app/Controllers/HrController.php';
    $db->exec("INSERT INTO shifts VALUES (1,'Night','20:00:00','04:00:00'),(2,'Day','08:00:00','16:00:00')");
    $db->exec("UPDATE employees SET attendance_type='shift',shift_id=1 WHERE id IN (1,3,4)");
    $db->exec("UPDATE employees SET attendance_type='shift',shift_id=2 WHERE id=2");
    $db->exec("UPDATE attendance SET checked_at='2026-10-15 20:00:00' WHERE employee_id IN (1,3,4) AND check_type='in'");
    $db->exec("UPDATE attendance SET checked_at='2026-10-16 04:00:00',source='request' WHERE employee_id=1 AND check_type='out'");
    $db->exec("UPDATE attendance SET checked_at='2026-10-16 10:00:00' WHERE employee_id=2 AND check_type='out'");
    $db->exec("UPDATE attendance SET checked_at='2026-10-16 04:30:00' WHERE employee_id=3 AND check_type='out'");
    $db->exec("UPDATE attendance SET checked_at='2026-10-16 10:00:01' WHERE employee_id=4 AND check_type='out'");
    $db->exec("DELETE FROM attendance WHERE employee_id=5 AND check_type='out'");
    $db->exec("INSERT INTO users VALUES(77,'Synthetic Approver')");
    $db->exec("INSERT INTO missing_attendance_requests VALUES(91,'Synthetic correction')");
    $db->exec("INSERT INTO attendance_corrections VALUES(1,91,2,1,'out','2026-10-16 03:00:00','2026-10-16 04:00:00','mobile',77,'2026-10-17 10:00:00')");
    // Exact flexible boundary, one second beyond it, and fixed six-hour boundary.
    foreach ([[6,'F','flexible',0,'08:00:00','02:00:00'],[7,'G','flexible',0,'08:00:00','02:00:01'],[8,'H','shift',1,'20:00:00','10:00:00']] as [$id,$code,$mode,$sid,$in,$out]) {
        $db->prepare('INSERT INTO employees VALUES (?,?,?,?,?,?,?,?,?)')->execute([$id,$code,$code,'صالة',1,1,$sid,$mode,8]);
        $db->prepare("INSERT INTO attendance (employee_id,check_type,checked_at,source) VALUES (?,'in',?,'test')")->execute([$id,'2026-10-15 '.$in]);
        $db->prepare("INSERT INTO attendance (employee_id,check_type,checked_at,source) VALUES (?,'out',?,'test')")->execute([$id,'2026-10-16 '.$out]);
    }
    $_SESSION = ['role_name' => 'مدير النظام', 'perms' => []];
    $_GET = ['type' => 'attendance', 'period' => '2026-10', 'format' => 'excel'];
    (new App\Controllers\HrController())->exportReports();
    exit;
}

$file = tempnam(sys_get_temp_dir(), 'attendance-export-test-');
if ($file === false) throw new RuntimeException('Cannot create temporary export');
try {
    $process = proc_open([PHP_BINARY, __FILE__, '--export'], [
        0 => ['pipe', 'r'], 1 => ['file', $file, 'w'], 2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot run synthetic export');
    fclose($pipes[0]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException($error);
    $book = PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    $sheet = $book->getActiveSheet();
    $expected = ['A' => 8, 'B' => null, 'C' => 8.5, 'D' => 0, 'E' => 0, 'F' => 18, 'G' => 0, 'H' => 14];
    $found = [];
    $total = 0.0;
    for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
        $code = (string)$sheet->getCell('A' . $row)->getValue();
        if (!array_key_exists($code, $expected)) continue;
        $cell = $sheet->getCell('G' . $row);
        $value = $cell->getValue();
        if($expected[$code]===null){
            if($value!=='-' || $cell->getDataType()!=='s')throw new RuntimeException('Incomplete shift must export a dash, not zero or 26 hours');
            $found[]=$code;continue;
        }
        if (!is_numeric($value) || $cell->getDataType() !== 'n' || abs((float)$value * 24 - $expected[$code]) > 0.000001) {
            throw new RuntimeException('Incorrect numeric duration for ' . $code);
        }
        if ($cell->getStyle()->getNumberFormat()->getFormatCode() !== '[h]:mm') {
            throw new RuntimeException('Duration must not wrap at 24 hours');
        }
        if (in_array($code, ['D','G'], true) && ($sheet->getCell('F'.$row)->getValue() === '-' || $sheet->getCell('H'.$row)->getValue() !== 'تجاوز حد احتساب الدوام')) throw new RuntimeException('Overlong shift must retain actual exit and explicit zero-hours status');
        if ($code === 'A' && !str_contains((string)$sheet->getCell('F' . $row)->getValue(), '2026-10-16')) {
            throw new RuntimeException('Following-day clock-out date is missing');
        }
        if ($code === 'E' && $sheet->getCell('F' . $row)->getValue() !== '-') {
            throw new RuntimeException('Missing punch must have an explicit label');
        }
        if($code==='A' && (!str_contains((string)$sheet->getCell('J'.$row)->getValue(),'03:00:00') || !str_contains((string)$sheet->getCell('K'.$row)->getValue(),'طلب #91') || $sheet->getCell('H'.$row)->getValue()!=='حضور مصحح'))throw new RuntimeException('Excel correction provenance missing');
        $found[] = $code;
        $total += (float)$value * 24;
    }
    if (count($found) !== 9 || abs($total - 48.5) > 0.000001) throw new RuntimeException('Incorrect exported row count or sum');
    $book->disconnectWorksheets();
    echo "PASS: actual attendance Excel export preserves 8h/8.5h, exact 6h/18h boundaries and numeric zero beyond them, retains late exit times, rejects cross-shift 26h pairing, includes next-day dates and correction audit.\n";
} finally {
    unlink($file);
}
