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
    $db->exec("UPDATE attendance SET checked_at='2026-10-15 10:00:00' WHERE employee_id=1 AND check_type='in'");
    $db->exec("UPDATE attendance SET checked_at='2026-10-16 12:00:00' WHERE employee_id=1 AND check_type='out'");
    $db->exec("DELETE FROM attendance WHERE employee_id=5 AND check_type='out'");
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
    $expected = ['A' => 26, 'B' => 8, 'C' => 8, 'D' => 8, 'E' => 0];
    $found = [];
    $total = 0.0;
    for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
        $code = (string)$sheet->getCell('A' . $row)->getValue();
        if (!array_key_exists($code, $expected)) continue;
        $cell = $sheet->getCell('G' . $row);
        $value = $cell->getValue();
        if (!is_numeric($value) || $cell->getDataType() !== 'n' || abs((float)$value * 24 - $expected[$code]) > 0.000001) {
            throw new RuntimeException('Incorrect numeric duration for ' . $code);
        }
        if ($cell->getStyle()->getNumberFormat()->getFormatCode() !== '[h]:mm') {
            throw new RuntimeException('Duration must not wrap at 24 hours');
        }
        if ($code === 'A' && !str_contains((string)$sheet->getCell('F' . $row)->getValue(), '2026-10-16')) {
            throw new RuntimeException('Following-day clock-out date is missing');
        }
        if ($code === 'E' && $sheet->getCell('F' . $row)->getValue() !== 'غير مسجل') {
            throw new RuntimeException('Missing punch must have an explicit label');
        }
        $found[] = $code;
        $total += (float)$value * 24;
    }
    if (count($found) !== 5 || abs($total - 50) > 0.000001) throw new RuntimeException('Incorrect exported row count or sum');
    $book->disconnectWorksheets();
    echo "PASS: actual attendance Excel export preserves numeric 26h/8h/0h durations, sums, next-day dates and missing-punch labels.\n";
} finally {
    unlink($file);
}
