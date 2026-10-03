<?php
namespace App\Core;

/**
 * خدمة قائمة الانتظار - Waiting List Module
 * ربط تلقائي بمخطط الطاولات (Floor Plan).
 */
class WaitingListService
{
    private const WAITING_STATUSES = ['waiting'];

    /** إضافة عميل إلى قائمة الانتظار */
    public static function add(array $data, int $userId): int
    {
        $partySize = (int)($data['party_size'] ?? $data['guests'] ?? 2);
        $expectedAt = (string)($data['expected_arrival_at'] ?? $data['reserved_at'] ?? date('Y-m-d H:i:s'));
        $branchId = (int)($data['branch_id'] ?? Auth::branchId() ?? 0);
        $duration = (int)($data['duration_minutes'] ?? Settings::get('reservation_default_duration', 90));

        if (self::hasSuitableAvailableTable($partySize, $expectedAt, $duration, $branchId)) {
            throw new \RuntimeException('توجد طاولة مناسبة متاحة في هذا الوقت، لا يمكن الإضافة لقائمة الانتظار');
        }

        $data['force_status'] = 'waiting';
        $data['guests'] = $partySize;
        $data['reserved_at'] = $expectedAt;
        $data['party_size'] = $partySize;
        $data['expected_arrival_at'] = $expectedAt;
        $data['is_vip'] = !empty($data['is_vip']) ? 1 : 0;

        $id = ReservationService::create($data, $userId);
        self::updatePriorityScore($id);
        return $id;
    }

    /** تحويل من قائمة الانتظار إلى حجز مباشر (Direct Check-in) */
    public static function directCheckIn(int $id, int $tableId, int $userId): bool
    {
        $res = ReservationService::get($id);
        if (!$res || !in_array($res['status'], self::WAITING_STATUSES, true)) {
            throw new \RuntimeException('العميل ليس في قائمة الانتظار');
        }
        $partySize = (int)($res['party_size'] ?? $res['guests']);
        $table = Database::query("SELECT * FROM dining_tables WHERE id = ?", [$tableId])->fetch();
        if (!$table) {
            throw new \RuntimeException('الطاولة غير موجودة');
        }
        if (!in_array($table['status'], ['available','reserved','merged'], true)) {
            throw new \RuntimeException('الطاولة غير متاحة حالياً');
        }
        $capacity = (int)($table['merged_capacity'] ?? $table['max_capacity'] ?? $table['capacity'] ?? $table['seats'] ?? 0);
        if ($capacity < $partySize) {
            throw new \RuntimeException('سعة الطاولة (' . $capacity . ') لا تكفي لعدد الأفراد (' . $partySize . ')');
        }
        if (!ReservationService::isTableAvailable($tableId, $res['expected_arrival_at'] ?? $res['reserved_at'], (int)$res['duration_minutes'], $id)) {
            throw new \RuntimeException('الطاولة محجوزة في نفس الموعد');
        }

        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE reservations SET status='seated', table_id=?, checked_in_at=NOW(), updated_at=NOW() WHERE id=?",
                [$tableId, $id]
            );
            Database::query("UPDATE dining_tables SET status='occupied' WHERE id=?", [$tableId]);
            ReservationService::log($id, 'checked_in', $userId, $res['status'], 'seated', 'تحويل مباشر من قائمة الانتظار إلى الطاولة ' . $table['name']);
            Database::commit();
            return true;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** إزالة عميل من قائمة الانتظار (إلغاء) */
    public static function remove(int $id, int $userId): bool
    {
        $res = ReservationService::get($id);
        if (!$res || !in_array($res['status'], self::WAITING_STATUSES, true)) {
            throw new \RuntimeException('العميل ليس في قائمة الانتظار');
        }
        Database::query("UPDATE reservations SET status='cancelled', updated_at=NOW() WHERE id=?", [$id]);
        ReservationService::log($id, 'cancelled', $userId, $res['status'], 'cancelled', 'إزالة من قائمة الانتظار');
        return true;
    }

    /** قائمة العملاء في الانتظار مرتبة حسب الأولوية */
    public static function list(?int $branchId = null): array
    {
        self::recalculatePriority();
        $sql = "SELECT r.*, b.name AS branch_name, t.name AS table_name
                FROM reservations r
                LEFT JOIN branches b ON b.id = r.branch_id
                LEFT JOIN dining_tables t ON t.id = r.table_id
                WHERE r.status = 'waiting'";
        $params = [];
        if ($branchId) {
            $sql .= " AND r.branch_id = ?";
            $params[] = $branchId;
        }
        $sql .= " ORDER BY r.priority_score DESC, r.expected_arrival_at ASC, r.created_at ASC";
        return Database::query($sql, $params)->fetchAll();
    }

    /** إعادة حساب درجة الأولوية لجميع المنتظرين */
    public static function recalculatePriority(): void
    {
        $vipBoost = (bool)Settings::get('waiting_list_vip_priority', 1) ? 1000 : 0;
        Database::query(
            "UPDATE reservations
             SET priority_score = (TIMESTAMPDIFF(MINUTE, created_at, NOW()) * 10)
                                  + (CASE WHEN is_vip = 1 AND ? > 0 THEN ? ELSE 0 END)
             WHERE status = 'waiting'",
            [$vipBoost, $vipBoost]
        );
    }

    /** تحديث درجة الأولوية لحجز واحد */
    public static function updatePriorityScore(int $id): void
    {
        $vipBoost = (bool)Settings::get('waiting_list_vip_priority', 1) ? 1000 : 0;
        Database::query(
            "UPDATE reservations
             SET priority_score = (TIMESTAMPDIFF(MINUTE, created_at, NOW()) * 10)
                                  + (CASE WHEN is_vip = 1 AND ? > 0 THEN ? ELSE 0 END)
             WHERE id = ?",
            [$vipBoost, $vipBoost, $id]
        );
    }

    /** مستمع حدث: عندما تصبح طاولة متاحة */
    public static function onTableAvailable(int $tableId): void
    {
        if (!Settings::get('waiting_list_auto_notify', 1)) return;
        $table = Database::query("SELECT * FROM dining_tables WHERE id = ?", [$tableId])->fetch();
        if (!$table) return;
        $capacity = (int)($table['merged_capacity'] ?? $table['max_capacity'] ?? $table['capacity'] ?? $table['seats'] ?? 0);
        $branchId = (int)($table['branch_id'] ?? 0);

        self::recalculatePriority();
        $next = Database::query(
            "SELECT r.* FROM reservations r
             WHERE r.status = 'waiting'
               AND (r.branch_id = ? OR ? = 0)
               AND r.party_size <= ?
               AND r.waiting_notified = 0
             ORDER BY r.priority_score DESC, r.expected_arrival_at ASC, r.created_at ASC
             LIMIT 1",
            [$branchId, $branchId, $capacity]
        )->fetch();

        if (!$next) return;

        self::notifyGuest((int)$next['id'], $table);
        Database::query("UPDATE reservations SET waiting_notified = 1 WHERE id = ?", [(int)$next['id']]);
    }

    /** إرسال تنبيه للعميل المنتظر */
    public static function notifyGuest(int $id, ?array $table = null): void
    {
        $res = ReservationService::getWithDetails($id);
        if (!$res || $res['status'] !== 'waiting') return;
        $tableName = $table ? $table['name'] : 'متاحة';
        $body = 'طاولة ' . $tableName . ' أصبحت متاحة لعدد ' . ($res['party_size'] ?? $res['guests']) . ' أفراد. يرجى التوجه للمطعم.';
        $title = 'طاولة متاحة - قائمة الانتظار';
        $link = '/waiting-list';

        ReservationService::log($id, 'notified', $res['created_by'] ?? null, 'waiting', 'waiting', 'إشعار توفر طاولة: ' . $tableName);

        // إشعار داخلي للمستخدم الذي أضاف العميل
        if ($res['created_by']) {
            Helpers::notify((int)$res['created_by'], $title, $body, $link);
        }
        // إشعار للمديرين
        $managers = Database::query(
            "SELECT DISTINCT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1
               AND (r.level >= 30 OR u.role_id IN (
                   SELECT rp.role_id FROM role_permissions rp
                   JOIN permissions p ON p.id = rp.permission_id
                   WHERE p.code IN ('waiting_list.manage','reservations.manage')
               ))"
        )->fetchAll();
        foreach ($managers as $m) {
            Helpers::notify((int)$m['id'], $title, $body, $link);
        }
        // إشعار عبر SMS/WhatsApp إذا كان مفعلاً
        if (Settings::get('waiting_list_sms_notify', 0) && $res['customer_phone']) {
            NotificationService::sendWhatsApp($res['customer_phone'], $body);
        }
    }

    /** التحقق من وجود طاولة مناسبة متاحة */
    public static function hasSuitableAvailableTable(int $guests, string $at, int $duration = 90, ?int $branchId = null): bool
    {
        $tables = ReservationService::findAvailableTables($guests, $at, $duration, null, $branchId);
        return !empty($tables);
    }

    /** اقتراح أفضل طاولة لعميل في الانتظار */
    public static function suggestTableFor(int $id): ?array
    {
        $res = ReservationService::get($id);
        if (!$res || $res['status'] !== 'waiting') return null;
        $partySize = (int)($res['party_size'] ?? $res['guests']);
        $at = $res['expected_arrival_at'] ?? $res['reserved_at'];
        $duration = (int)($res['duration_minutes'] ?? 90);
        $branchId = (int)($res['branch_id'] ?? 0);
        return ReservationService::suggestBestTable($partySize, $at, $duration, $res['reservation_type'] ?? 'dine_in', $branchId ?: null, $id);
    }
}
