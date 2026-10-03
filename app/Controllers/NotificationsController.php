<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Auth, Helpers};

/**
 * مركز الإشعارات — صندوق موحد لكل إشعارات النظام (Workflow/PTT/HR/عامة)
 */
class NotificationsController extends Controller
{
    public function index(): void
    {
        $uid = (int)Auth::id();
        $rows = Database::query(
            "SELECT * FROM notifications WHERE user_id = ?
             ORDER BY is_read ASC, id DESC LIMIT 100",
            [$uid]
        )->fetchAll();
        $this->view('notifications/index', [
            'title' => 'الإشعارات',
            'rows' => $rows,
            'unread' => Helpers::unreadCount($uid),
        ]);
    }

    /** فتح إشعار = قراءته ثم التوجيه لرابطه */
    public function open(string $id): void
    {
        $row = Database::query(
            "SELECT * FROM notifications WHERE id = ? AND user_id = ?",
            [(int)$id, (int)Auth::id()]
        )->fetch();
        if (!$row) Response::abort(404, 'الإشعار غير موجود');
        Helpers::markNotificationRead((int)$id);
        Response::redirect($row['link'] ?: '/notifications');
    }

    public function markRead(string $id): void
    {
        Helpers::markNotificationRead((int)$id);
        Response::redirect('/notifications');
    }

    public function readAll(): void
    {
        Database::query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [(int)Auth::id()]
        );
        $this->flash('success', 'تم تعليم الكل كمقروء');
        Response::redirect('/notifications');
    }

    /** عدّاد غير المقروء — لتحديث الجرس دورياً */
    public function countJson(): void
    {
        Response::json(['ok' => true, 'unread' => Helpers::unreadCount((int)Auth::id())]);
    }

    /** تسجيل اشتراك Web Push من المتصفح (جلسة ويب) */
    public function pushSubscribe(): void
    {
        try {
            $data = $this->jsonInput();
            $data['platform'] = 'web';
            \App\Core\Workflow\PushService::subscribe((int)Auth::id(), $data);
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function pushUnsubscribe(): void
    {
        $data = $this->jsonInput();
        \App\Core\Workflow\PushService::unsubscribe((int)Auth::id(), (string)($data['endpoint'] ?? ''));
        Response::json(['ok' => true]);
    }
}
