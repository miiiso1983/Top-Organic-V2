<?php
namespace App\Core;

/**
 * خدمة إدارة أنواع الحجز - Reservation Type Management
 */
class ReservationTypeService
{
    public static function list(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM reservation_types";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order, id";
        return Database::query($sql)->fetchAll();
    }

    public static function getByCode(string $code): ?array
    {
        $row = Database::query("SELECT * FROM reservation_types WHERE code = ?", [$code])->fetch();
        return $row ?: null;
    }

    public static function getById(int $id): ?array
    {
        $row = Database::query("SELECT * FROM reservation_types WHERE id = ?", [$id])->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $code = self::slugify($data['name_ar'] ?? '');
        if (!$code) {
            throw new \RuntimeException('اسم النوع مطلوب');
        }
        $code = $data['code'] ?? $code;
        if (self::getByCode($code)) {
            throw new \RuntimeException('الكود مستخدم مسبقاً');
        }
        $fields = self::fields();
        $params = [];
        $cols = [];
        $placeholders = [];
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $cols[] = $f;
                $placeholders[] = '?';
                $params[] = $data[$f] !== '' ? $data[$f] : null;
            }
        }
        if (!in_array('code', $cols, true)) {
            $cols[] = 'code'; $placeholders[] = '?'; $params[] = $code;
        }
        $sql = "INSERT INTO reservation_types (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        Database::query($sql, $params);
        return (int)Database::lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $type = self::getById($id);
        if (!$type) {
            throw new \RuntimeException('النوع غير موجود');
        }
        if (!empty($data['code']) && $data['code'] !== $type['code'] && self::getByCode($data['code'])) {
            throw new \RuntimeException('الكود مستخدم مسبقاً');
        }
        $fields = self::fields();
        $set = [];
        $params = [];
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $set[] = "$f = ?";
                $params[] = $data[$f] !== '' ? $data[$f] : null;
            }
        }
        if (!$set) return false;
        $params[] = $id;
        Database::query("UPDATE reservation_types SET " . implode(',', $set) . " WHERE id = ?", $params);
        return true;
    }

    public static function delete(int $id): bool
    {
        $type = self::getById($id);
        if (!$type) return false;
        $count = (int)Database::query("SELECT COUNT(*) AS c FROM reservations WHERE reservation_type = ?", [$type['code']])->fetch()['c'];
        if ($count > 0) {
            throw new \RuntimeException('لا يمكن الحذف: يوجد حجوزات مرتبطة بهذا النوع');
        }
        Database::query("DELETE FROM reservation_types WHERE id = ?", [$id]);
        return true;
    }

    public static function applyTypeRules(array $type, array $data): array
    {
        $guests = (int)($data['guests'] ?? $type['default_guests']);
        if ($guests < (int)$type['min_guests']) {
            throw new \RuntimeException('الحد الأدنى للضيوف لنوع الحجز ' . $type['name_ar'] . ' هو ' . $type['min_guests']);
        }
        if ($guests > (int)$type['max_guests']) {
            throw new \RuntimeException('الحد الأقصى للضيوف لنوع الحجز ' . $type['name_ar'] . ' هو ' . $type['max_guests']);
        }
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $data['reserved_at']) ?: \DateTime::createFromFormat('Y-m-d H:i:s', $data['reserved_at']);
        if ($dt) {
            $now = new \DateTime();
            $diffHours = ($dt->getTimestamp() - $now->getTimestamp()) / 3600;
            if ($diffHours < (int)$type['min_advance_hours']) {
                throw new \RuntimeException('يجب الحجز قبل ' . $type['min_advance_hours'] . ' ساعة على الأقل لنوع ' . $type['name_ar']);
            }
        }
        return array_merge($data, [
            'guests' => $guests,
            'duration_minutes' => (int)($data['duration_minutes'] ?? $type['duration_minutes']),
        ]);
    }

    public static function estimatePrice(array $type, int $guests): ?Decimal
    {
        if (empty($type['price_per_person'])) return null;
        return Decimal::mul((string)$type['price_per_person'], (string)$guests);
    }

    public static function renderTemplate(string $template, array $vars): string
    {
        return str_replace(array_map(fn($k) => '{' . $k . '}', array_keys($vars)), array_values($vars), $template);
    }

    public static function canManageType(string $code, ?int $userId = null): bool
    {
        $type = self::getByCode($code);
        if (!$type) return Auth::can('reservations.manage');
        if ($type['permission_code'] && Auth::can($type['permission_code'])) return true;
        return Auth::can('reservations.manage') || Auth::can('reservations.types.manage');
    }

    private static function fields(): array
    {
        return ['code','name_ar','name_en','description','icon','color','min_guests','max_guests','default_guests','min_advance_hours','max_advance_days','duration_minutes','price_per_person','deposit_amount','deposit_required','requires_approval','requires_manager_confirmation','requires_contract','allows_partial_payment','workflow_template_id','permission_code','message_template_confirm','message_template_reminder','message_template_cancel','requirements','default_notes','is_active','sort_order'];
    }

    private static function slugify(string $text): string
    {
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '_', $text);
        $text = trim($text, '_');
        $text = strtolower($text);
        return $text ?: 'type';
    }
}
