<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Helpers, ImportHelper, Response};

/**
 * إدارة الورديات (Shifts)
 */
class ShiftController extends Controller
{
    public function index(): void
    {
        $shifts = Database::query(
            "SELECT * FROM shifts ORDER BY is_active DESC, start_time, id"
        )->fetchAll();
        $workDays = [];
        $rows = Database::query("SELECT shift_id, day_of_week FROM shift_work_days")->fetchAll();
        foreach ($rows as $r) {
            $workDays[(int)$r['shift_id']][(int)$r['day_of_week']] = true;
        }
        // جلب فترات الوردية لكل وردية
        $segments = [];
        $segRows = Database::query(
            "SELECT shift_id, start_time, end_time, segment_order FROM shift_segments ORDER BY shift_id, segment_order"
        )->fetchAll();
        foreach ($segRows as $sr) {
            $segments[(int)$sr['shift_id']][] = [
                'start_time' => substr($sr['start_time'], 0, 5),
                'end_time'   => substr($sr['end_time'], 0, 5),
            ];
        }
        $this->view('hr/shifts', [
            'title'    => 'الورديات',
            'shifts'   => $shifts,
            'workDays' => $workDays,
            'segments' => $segments,
        ]);
    }

    public function store(): void
    {
        $name = trim((string)$this->input('name'));
        $isAjax = !empty($this->input('ajax'));
        $segments = self::parseSegmentsFromRequest($_POST);
        if ($name === '' || empty($segments)) {
            $message = 'اسم الوردية وفترة واحدة على الأقل مطلوبة';
            if ($isAjax) {
                Response::json(['ok' => false, 'error' => $message], 422);
                return;
            }
            $this->flash('danger', $message);
            Response::redirect('/hr/shifts');
            return;
        }
        // حفظ أول فترة في الحقول الأصلية للتوافقية
        $start = $segments[0]['start_time'];
        $end = $segments[0]['end_time'];
        Database::query(
            "INSERT INTO shifts (name, start_time, end_time) VALUES (?,?,?)",
            [$name, $start, $end]
        );
        $shiftId = (int)Database::lastInsertId();
        $this->saveSegments($shiftId, $segments);
        $this->saveWorkDays($shiftId, $_POST['work_days'] ?? []);
        Helpers::audit('إضافة وردية', 'shifts', $shiftId, $name);
        $shift = Database::query("SELECT * FROM shifts WHERE id = ?", [$shiftId])->fetch();
        if ($isAjax) {
            Response::json(['ok' => true, 'shift' => $shift], 201);
            return;
        }
        $this->flash('success', 'تم إضافة الوردية');
        Response::redirect('/hr/shifts');
    }

    public function update(string $id): void
    {
        $shiftId = (int)$id;
        $name = trim((string)$this->input('name'));
        $segments = self::parseSegmentsFromRequest($_POST);
        if ($name === '' || empty($segments)) {
            $this->flash('danger', 'اسم الوردية وفترة واحدة على الأقل مطلوبة');
            Response::redirect('/hr/shifts');
            return;
        }
        // تحديث الحقول الأصلية بأول فترة للتوافقية
        $start = $segments[0]['start_time'];
        $end = $segments[0]['end_time'];
        Database::query(
            "UPDATE shifts SET name = ?, start_time = ?, end_time = ? WHERE id = ?",
            [$name, $start, $end, $shiftId]
        );
        $this->saveSegments($shiftId, $segments);
        $this->saveWorkDays($shiftId, $_POST['work_days'] ?? []);
        Helpers::audit('تعديل وردية', 'shifts', $shiftId, $name);
        $this->flash('success', 'تم تحديث الوردية');
        Response::redirect('/hr/shifts');
    }

    /** تحليل فترات الوردية من بيانات POST */
    private static function parseSegmentsFromRequest(array $data): array
    {
        $segments = [];
        $startTimes = (array)($data['seg_start'] ?? []);
        $endTimes = (array)($data['seg_end'] ?? []);

        // دعم الإرسال القديم (فترة واحدة عبر start_time/end_time)
        if (empty($startTimes) && !empty($data['start_time']) && !empty($data['end_time'])) {
            $startTimes = [(string)$data['start_time']];
            $endTimes = [(string)$data['end_time']];
        }

        foreach ($startTimes as $i => $start) {
            $start = trim((string)$start);
            $end = trim((string)($endTimes[$i] ?? ''));
            if ($start === '' || $end === '') continue;
            $segments[] = ['start_time' => $start, 'end_time' => $end];
        }

        // إزالة المكررات
        $seen = [];
        $segments = array_filter($segments, function ($s) use (&$seen) {
            $key = $s['start_time'] . '-' . $s['end_time'];
            if (isset($seen[$key])) return false;
            $seen[$key] = true;
            return true;
        });

        return array_values($segments);
    }

    /** حفظ فترات الوردية */
    private function saveSegments(int $shiftId, array $segments): void
    {
        Database::query("DELETE FROM shift_segments WHERE shift_id = ?", [$shiftId]);
        $totalMinutes = 0;
        foreach ($segments as $i => $seg) {
            Database::query(
                "INSERT INTO shift_segments (shift_id, start_time, end_time, segment_order) VALUES (?,?,?,?)",
                [$shiftId, $seg['start_time'], $seg['end_time'], $i + 1]
            );
            $startTs = strtotime($seg['start_time']);
            $endTs = strtotime($seg['end_time']);
            $diff = $endTs - $startTs;
            if ($diff < 0) $diff += 86400; // وردية ليلية تعبر منتصف الليل
            $totalMinutes += (int)($diff / 60);
        }
        Database::query("UPDATE shifts SET total_minutes = ? WHERE id = ?", [$totalMinutes, $shiftId]);
    }

    private static function parseWorkDays($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_numeric'));
        }
        $value = (string)$value;
        if ($value === '') {
            return [0, 1, 2, 3, 4];
        }
        return array_filter(array_map('intval', explode(',', $value)), function ($d) {
            return $d >= 0 && $d <= 6;
        });
    }

    private function saveWorkDays(int $shiftId, array $days): void
    {
        Database::query("DELETE FROM shift_work_days WHERE shift_id = ?", [$shiftId]);
        foreach ($days as $day) {
            $d = (int)$day;
            if ($d >= 0 && $d <= 6) {
                Database::query(
                    "INSERT INTO shift_work_days (shift_id, day_of_week) VALUES (?, ?)",
                    [$shiftId, $d]
                );
            }
        }
    }

    public function destroy(string $id): void
    {
        $shiftId = (int)$id;
        $shift = Database::query("SELECT name FROM shifts WHERE id = ?", [$shiftId])->fetch();
        if (!$shift) {
            $this->flash('danger', 'الوردية غير موجودة');
            Response::redirect('/hr/shifts');
            return;
        }
        // روابط الوردية في employees (shift_id) و employee_shifts معالجة بـ ON DELETE CASCADE/SET NULL
        Database::query("DELETE FROM shifts WHERE id = ?", [$shiftId]);
        Helpers::audit('حذف وردية', 'shifts', $shiftId, $shift['name']);
        $this->flash('success', 'تم حذف الوردية');
        Response::redirect('/hr/shifts');
    }

    public function bulkDelete(): void
    {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            $this->flash('danger', 'لم يتم اختيار ورديات للحذف');
            Response::redirect('/hr/shifts');
            return;
        }
        $deleted = 0;
        foreach ($ids as $id) {
            $shiftId = (int)$id;
            if ($shiftId <= 0) {
                continue;
            }
            $shift = Database::query("SELECT name FROM shifts WHERE id = ?", [$shiftId])->fetch();
            if (!$shift) {
                continue;
            }
            Database::query("DELETE FROM shifts WHERE id = ?", [$shiftId]);
            Helpers::audit('حذف وردية', 'shifts', $shiftId, $shift['name']);
            $deleted++;
        }
        $this->flash('success', 'تم حذف ' . $deleted . ' وردية');
        Response::redirect('/hr/shifts');
    }

    /** تنزيل نموذج Excel فارغ لاستيراد الورديات */
    public function template(): void
    {
        ImportHelper::downloadTemplate(
            ['name', 'start_time', 'end_time', 'work_days'],
            'shifts_template.xlsx',
            ['وردية صباحية', '08:00', '16:00', '0,1,2,3,4']
        );
    }

    /** استيراد الورديات من ملف Excel */
    public function import(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/hr/shifts');
            return;
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/hr/shifts');
            return;
        }
        $rows = $read['rows'];
        $errors = ImportHelper::validateRequired($rows, ['name', 'start_time', 'end_time']);
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'بيانات ناقصة في الملف');
            Response::redirect('/hr/shifts');
            return;
        }

        $inserted = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $name = trim((string)$row['name']);
            $start = ImportHelper::parseExcelTime($row['start_time']);
            $end = ImportHelper::parseExcelTime($row['end_time']);
            if (!$start || !$end) {
                $failed++;
                continue;
            }
            try {
                Database::query(
                    "INSERT INTO shifts (name, start_time, end_time) VALUES (?, ?, ?)",
                    [$name, $start, $end]
                );
                $shiftId = (int)Database::lastInsertId();
                $this->saveWorkDays($shiftId, self::parseWorkDays($row['work_days'] ?? '0,1,2,3,4'));
                Helpers::audit('استيراد وردية', 'shifts', $shiftId, $name);
                $inserted++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->flash('warning', "تم استيراد $inserted وردية، وفشل $failed صف");
        } else {
            $this->flash('success', "تم استيراد $inserted وردية بنجاح");
        }
        Response::redirect('/hr/shifts');
    }
}
