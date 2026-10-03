<?php
namespace App\Core;

/**
 * إشعارات تلقائية للموظفين ذوي الورديات الثابتة.
 *
 * 1. تذكير قبل 5 دقائق من بداية الوردية.
 * 2. تذكير بعد 3 دقائق من انتهاء الوردية إذا لم تُسجّل بصمة انصراف.
 *
 * يُشغّل من Cron Job كل دقيقة تقريباً.
 */
class ShiftNotificationService
{
    /**
     * معالجة التذكيرات وإرجاع عدد الإشعارات المرسلة.
     *
     * @return array{start_reminders:int, end_reminders:int}
     */
    public static function run(): array
    {
        $result = ['start_reminders' => 0, 'end_reminders' => 0];
        $now = new \DateTime();
        $today = $now->format('Y-m-d');
        $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');

        $employees = self::employeesWithShifts([$today, $tomorrow]);

        foreach ($employees as $e) {
            $employeeId = (int)$e['employee_id'];
            $shiftDate = (string)$e['shift_date'];
            $shift = HrService::getEmployeeShift($employeeId, $shiftDate);
            if (!$shift || empty($shift['start_time']) || empty($shift['end_time']) || empty($shift['is_work_day'])) {
                continue;
            }

            $startDt = new \DateTime($shiftDate . ' ' . $shift['start_time']);
            $endDt = new \DateTime($shiftDate . ' ' . $shift['end_time']);
            if ($endDt <= $startDt) {
                $endDt->modify('+1 day');
            }

            // تذكير قبل بداية الوردية (نافذة 0-6 دقائق قبل البداية)
            $diffStartMin = (int)(($startDt->getTimestamp() - $now->getTimestamp()) / 60);
            if ($diffStartMin >= 0 && $diffStartMin <= 6) {
                if (!self::alertExists($employeeId, $shiftDate, 'shift_start_soon')) {
                    self::createAlert($employeeId, $shiftDate, 'shift_start_soon');
                    self::notifyEmployee(
                        $e,
                        'تبدأ ورديتك خلال 5 دقائق',
                        "ورديتك {$shift['name']} تبدأ الساعة {$shift['start_time']}. جهّز نفسك لتسجيل الحضور."
                    );
                    $result['start_reminders']++;
                }
            }

            // تذكير بعد انتهاء الوردية إذا لم تُسجّل بصمة انصراف (3-10 دقائق بعد الانتهاء)
            $diffEndMin = (int)(($now->getTimestamp() - $endDt->getTimestamp()) / 60);
            if ($diffEndMin >= 3 && $diffEndMin <= 10) {
                if (!self::hasClockOut($employeeId, $startDt, $now)) {
                    if (!self::alertExists($employeeId, $shiftDate, 'shift_end_missing_clockout')) {
                        self::createAlert($employeeId, $shiftDate, 'shift_end_missing_clockout');
                        self::notifyEmployee(
                            $e,
                            'يرجى تسجيل بصمة الانصراف',
                            "انتهت ورديتك {$shift['name']} منذ 3 دقائق. يرجى تسجيل بصمة الانصراف."
                        );
                        $result['end_reminders']++;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * قائمة الموظفين النشطين المرتبطين بحساب مستخدم ولديهم وردية (افتراضية أو مخصصة) في التواريخ المحددة.
     */
    private static function employeesWithShifts(array $dates): array
    {
        $out = [];
        foreach ($dates as $date) {
            $rows = Database::query(
                "SELECT e.id AS employee_id, e.user_id, e.full_name, e.branch_id, ? AS shift_date
                 FROM employees e
                 WHERE e.status = 'active' AND e.user_id IS NOT NULL AND e.shift_id IS NOT NULL
                 UNION
                 SELECT e.id AS employee_id, e.user_id, e.full_name, e.branch_id, es.shift_date AS shift_date
                 FROM employee_shifts es
                 JOIN employees e ON e.id = es.employee_id
                 WHERE e.status = 'active' AND e.user_id IS NOT NULL AND es.shift_date = ?",
                [$date, $date]
            )->fetchAll();
            foreach ($rows as $r) {
                $key = $r['employee_id'] . ':' . $r['shift_date'];
                $out[$key] = $r;
            }
        }
        return array_values($out);
    }

    private static function alertExists(int $employeeId, string $date, string $type): bool
    {
        return (bool)Database::query(
            "SELECT id FROM attendance_alerts WHERE employee_id = ? AND alert_date = ? AND alert_type = ? LIMIT 1",
            [$employeeId, $date, $type]
        )->fetch();
    }

    private static function createAlert(int $employeeId, string $date, string $type): void
    {
        Database::query(
            "INSERT INTO attendance_alerts (employee_id, alert_date, alert_type) VALUES (?, ?, ?)",
            [$employeeId, $date, $type]
        );
    }

    private static function hasClockOut(int $employeeId, \DateTime $shiftStart, \DateTime $now): bool
    {
        return (bool)Database::query(
            "SELECT id FROM attendance
             WHERE employee_id = ? AND check_type = 'out' AND checked_at >= ? AND checked_at <= ?
             LIMIT 1",
            [$employeeId, $shiftStart->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]
        )->fetch();
    }

    private static function notifyEmployee(array $employee, string $title, string $body): void
    {
        $userId = (int)($employee['user_id'] ?? 0);
        if (!$userId) {
            return;
        }
        NotificationService::sendToUser($userId, $title, $body, '/hr/attendance', ['push']);
    }
}
