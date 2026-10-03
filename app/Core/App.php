<?php
namespace App\Core;

/**
 * نواة التطبيق: الإعدادات + التمهيد
 */
class App
{
    private static array $config = [];

    public static function boot(): void
    {
        self::$config = require BASE_PATH . '/config/config.php';
        date_default_timezone_set(self::$config['timezone']);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name(self::$config['session_name']);
            session_start();
        }
        // إعادة تحميل بيانات المستخدم/الدور/الصلاحيات من قاعدة البيانات في كل طلب
        // لتنعكس تغييرات الصلاحيات مباشرة دون إعادة تسجيل الدخول.
        Auth::refresh();
        HrWorkflowService::register();

        // ربط الموافقات الديناميكية بالحجوزات
        WorkflowEngine::registerOutcomeHandler('reservation_approval', function (array $request, string $status, int $actorId, string $notes) {
            ReservationService::applyWorkflowOutcome($request, $status, $actorId, $notes);
        });

        // ربط الموافقات الديناميكية بطلبات المواد والمشتريات
        WorkflowEngine::registerOutcomeHandler('material_request', function (array $request, string $status, int $actorId, string $notes) {
            MaterialRequestService::handleWorkflowOutcome($request, $status, $actorId, $notes);
        });
        WorkflowEngine::registerOutcomeHandler('purchase_request', function (array $request, string $status, int $actorId, string $notes) {
            $entityId = (int)($request['data']['entity_id'] ?? 0);
            if (!$entityId) return;
            if ($status === 'approved') {
                Database::query("UPDATE purchase_requests SET status = 'approved' WHERE id = ?", [$entityId]);
            } elseif ($status === 'rejected') {
                Database::query("UPDATE purchase_requests SET status = 'rejected' WHERE id = ?", [$entityId]);
            }
        });
    }

    public static function config(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }
}
