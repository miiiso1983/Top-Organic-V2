<?php
declare(strict_types=1);
namespace App\Core;

final class AttendanceIntegrityService
{
    /** OS-reported suspicion, not a finding of misconduct. Never retain coordinates or photos. */
    public static function record(int $employeeId, string $type, string $origin): void
    {
        try {
            $userId = ApiAuth::id();
            if (!$userId) return;
            $details = 'أبلغ الجهاز عن موقع مولّد بالمحاكاة. تم رفض محاولة تسجيل ' . ($type === 'out' ? 'الانصراف' : 'الحضور')
                . '. مصدر الاكتشاف: ' . ($origin === 'server' ? 'فحص الطلب في الخادم' : 'فحص التطبيق قبل إرسال البصمة')
                . '. هذه إشارة اشتباه وليست إثباتاً للتلاعب. لم تُسجَّل بصمة حضور أو انصراف.';
            // Collapse identical retries in the same minute. The HTTP request log still records each call.
            $recent = Database::query("SELECT id FROM audit_log WHERE user_id=? AND action='attendance_mock_location' AND entity='employees' AND entity_id=? AND details=? AND created_at>=? LIMIT 1", [$userId,$employeeId,$details,date('Y-m-d H:i:s',time()-60)])->fetchColumn();
            if (!$recent) Helpers::audit('attendance_mock_location','employees',$employeeId,$details,$userId);
        } catch (\Throwable $e) {
            // A logging failure must never turn a rejection into an accepted punch.
            error_log('Attendance mock-location audit failed (' . get_class($e) . ')');
        }
    }
}
