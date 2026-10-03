<?php
namespace App\Core;

/**
 * خدمة إدارة العملاء - Customer Service
 */
class CustomerService
{
    public static function search(string $q, int $limit = 20): array
    {
        $term = '%' . $q . '%';
        return Database::query(
            "SELECT * FROM customers
             WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?
             ORDER BY is_vip DESC, name
             LIMIT ?",
            [$term, $term, $term, $limit]
        )->fetchAll();
    }

    public static function getByPhone(string $phone): ?array
    {
        $row = Database::query("SELECT * FROM customers WHERE phone = ?", [$phone])->fetch();
        return $row ?: null;
    }

    public static function getById(int $id): ?array
    {
        $row = Database::query("SELECT * FROM customers WHERE id = ?", [$id])->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        if (!$name) {
            throw new \RuntimeException('اسم العميل مطلوب');
        }
        Database::query(
            "INSERT INTO customers (name, phone, email, address, is_vip, birth_date, preferences, notes)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                $name,
                $phone,
                $data['email'] ?? null,
                $data['address'] ?? null,
                (int)($data['is_vip'] ?? 0),
                $data['birth_date'] ?? null,
                $data['preferences'] ?? null,
                $data['notes'] ?? null,
            ]
        );
        return (int)Database::lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $cust = self::getById($id);
        if (!$cust) return false;
        $set = [];
        $params = [];
        $fields = ['name','phone','email','address','is_vip','birth_date','preferences','notes','last_call_center_note'];
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $set[] = "$f = ?";
                $params[] = $data[$f] !== '' ? $data[$f] : null;
            }
        }
        if (!$set) return false;
        $params[] = $id;
        Database::query("UPDATE customers SET " . implode(',', $set) . " WHERE id = ?", $params);
        return true;
    }

    public static function getReservations(int $customerId, ?string $status = null, int $limit = 50): array
    {
        $sql = "SELECT r.*, b.name AS branch_name, t.name AS table_name
                FROM reservations r
                LEFT JOIN branches b ON b.id = r.branch_id
                LEFT JOIN dining_tables t ON t.id = r.table_id
                WHERE r.customer_id = ?";
        $params = [$customerId];
        if ($status) {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY r.reserved_at DESC LIMIT ?";
        $params[] = $limit;
        return Database::query($sql, $params)->fetchAll();
    }
}
