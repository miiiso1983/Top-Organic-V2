<?php
namespace App\Core;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * استيراد البيانات من Excel مع التحقق
 */
class ImportHelper
{
    /**
     * قراءة ملف Excel وإرجاع الأعمدة والصفوف
     * @return array{headers: array, rows: array, errors: array}
     */
    public static function readFile(string $tmpPath, bool $includeEmptyHeaders = false): array
    {
        if (!file_exists($tmpPath)) {
            return ['headers' => [], 'rows' => [], 'errors' => ['ملف غير موجود']];
        }
        try {
            $reader = IOFactory::createReaderForFile($tmpPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);
            if (empty($data)) {
                return ['headers' => [], 'rows' => [], 'errors' => ['الملف فارغ']];
            }
            $headers = array_map(fn($h) => strtolower(trim((string)$h)), array_shift($data));
            $rows = [];
            foreach ($data as $row) {
                $mapped = [];
                foreach ($headers as $colLetter => $key) {
                    if (!$key) {
                        if (!$includeEmptyHeaders) { continue; }
                        $key = 'col_' . $colLetter;
                    }
                    $mapped[$key] = $row[$colLetter] ?? null;
                }
                // تخطي الصفوف الفارغة
                if (array_filter($mapped, fn($v) => $v !== null && $v !== '') === []) {
                    continue;
                }
                $rows[] = $mapped;
            }
            return ['headers' => $headers, 'rows' => $rows, 'errors' => []];
        } catch (\Throwable $e) {
            return ['headers' => [], 'rows' => [], 'errors' => ['فشل قراءة الملف: ' . $e->getMessage()]];
        }
    }

    /** التحقق من أعمدة مطلوبة */
    public static function validateRequired(array $rows, array $columns): array
    {
        $errors = [];
        foreach ($rows as $idx => $row) {
            $line = $idx + 2;
            foreach ($columns as $col) {
                if (empty($row[$col]) && !is_numeric($row[$col] ?? null)) {
                    $errors[] = "الصف $line: العمود $col مطلوب";
                }
            }
        }
        return $errors;
    }

    /** التحقق من إيجاد رموز مكتبة في ملف واحد وعدم تكرارها */
    public static function validateUnique(array $rows, string $column): array
    {
        $errors = [];
        $seen = [];
        foreach ($rows as $idx => $row) {
            $val = trim((string)($row[$column] ?? ''));
            if (!$val) { continue; }
            if (isset($seen[$val])) {
                $errors[] = "الصف " . ($idx + 2) . ': قيمة ' . $column . ' مكررة في الملف ("' . $val . '")';
            }
            $seen[$val] = true;
        }
        return $errors;
    }

    /** التحقق من عدم تواجد القيم في قاعدة البيانات */
    public static function validateNotInDb(array $rows, string $column, string $table, string $dbColumn): array
    {
        $errors = [];
        $values = [];
        foreach ($rows as $row) {
            $val = trim((string)($row[$column] ?? ''));
            if ($val) { $values[] = $val; }
        }
        if (!$values) { return $errors; }
        $in = implode(',', array_fill(0, count($values), '?'));
        $existing = Database::query("SELECT $dbColumn FROM $table WHERE $dbColumn IN ($in)", $values)->fetchAll();
        $existing = array_column($existing, $dbColumn);
        foreach ($rows as $idx => $row) {
            $val = trim((string)($row[$column] ?? ''));
            if (in_array($val, $existing, true)) {
                $errors[] = "الصف " . ($idx + 2) . ': القيمة "' . $val . '" موجودة مسبقاً';
            }
        }
        return $errors;
    }

    /** التحقق من وجود مفتاح خارجي في جدول مرجعي */
    public static function validateForeignKey(array $rows, string $column, string $table, string $dbColumn): array
    {
        $errors = [];
        $values = [];
        foreach ($rows as $row) {
            $val = trim((string)($row[$column] ?? ''));
            if ($val !== '') { $values[] = (int)$val; }
        }
        if (!$values) { return $errors; }
        $in = implode(',', array_fill(0, count($values), '?'));
        $existing = Database::query("SELECT $dbColumn FROM $table WHERE $dbColumn IN ($in)", $values)->fetchAll();
        $existing = array_map('intval', array_column($existing, $dbColumn));
        foreach ($rows as $idx => $row) {
            $val = trim((string)($row[$column] ?? ''));
            if ($val !== '' && !in_array((int)$val, $existing, true)) {
                $errors[] = "الصف " . ($idx + 2) . ': القيمة "' . $val . '" غير موجودة في ' . $table;
            }
        }
        return $errors;
    }

    /** تنزيل نموذج Excel فارغ */
    public static function downloadTemplate(array $headers, string $filename, array $sampleRow = []): void
    {
        $sampleRows = $sampleRow ? [$sampleRow] : [];
        self::downloadTemplateMulti($headers, $filename, $sampleRows);
    }

    /** تنزيل نموذج Excel بعدة صفوف نموذجية */
    public static function downloadTemplateMulti(array $headers, string $filename, array $sampleRows = []): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->fromArray($headers, null, 'A1');
        $rowNum = 2;
        foreach ($sampleRows as $sampleRow) {
            $sheet->fromArray(array_values($sampleRow), null, 'A' . $rowNum);
            $rowNum++;
        }
        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    /** نموذج ملف عملياتي (للعرض) */
    public static function flashErrors(array $errors): void
    {
        $_SESSION['import_errors'] = $errors;
    }

    public static function getErrors(): array
    {
        $errors = $_SESSION['import_errors'] ?? [];
        unset($_SESSION['import_errors']);
        return $errors;
    }

    /** تحويل تاريخ Excel المسلسل (مثل 45474) أو نص إلى Y-m-d */
    public static function parseExcelDate($value): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }
        if (is_numeric($value) && (float)$value > 0) {
            try {
                $dt = Date::excelToDateTimeObject((float)$value);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        $date = strtotime((string)$value);
        if ($date !== false) {
            return date('Y-m-d', $date);
        }
        return null;
    }

    /** تحويل وقت Excel المسلسل (كسر من اليوم) أو نص إلى H:i */
    public static function parseExcelTime($value): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }
        if (is_numeric($value)) {
            $seconds = round((float)$value * 86400);
            $hours = floor($seconds / 3600) % 24;
            $minutes = floor(($seconds % 3600) / 60);
            return sprintf('%02d:%02d', (int)$hours, (int)$minutes);
        }
        if (preg_match('/^(\d{1,2})[:\h](\d{2})\s*(?::\d{2})?\s*(AM|PM)?/i', (string)$value, $m)) {
            $h = (int)$m[1];
            $min = (int)$m[2];
            $ampm = strtoupper($m[3] ?? '');
            if ($ampm === 'PM' && $h < 12) $h += 12;
            if ($ampm === 'AM' && $h === 12) $h = 0;
            return sprintf('%02d:%02d', $h, $min);
        }
        return null;
    }
}
