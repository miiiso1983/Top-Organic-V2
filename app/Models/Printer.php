<?php
namespace App\Models;

use App\Core\Database;

/**
 * نموذج إدارة الطابعات
 */
class Printer
{
    public static function all(): array
    {
        return Database::query(
            "SELECT p.*, pk.name AS kitchen_name
             FROM printers p
             LEFT JOIN preparation_kitchens pk ON pk.id = p.kitchen_id
             WHERE p.is_active = 1
             ORDER BY p.id DESC"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::query(
            "SELECT p.*, pk.name AS kitchen_name
             FROM printers p
             LEFT JOIN preparation_kitchens pk ON pk.id = p.kitchen_id
             WHERE p.id = ?",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        Database::query(
            "INSERT INTO printers (name, type, kitchen_id, config, is_active)
             VALUES (?,?,?,?,?)",
            [
                (string)($data['name'] ?? ''),
                (string)($data['type'] ?? 'thermal'),
                !empty($data['kitchen_id']) ? (int)$data['kitchen_id'] : null,
                json_encode($data['config'] ?? [], JSON_UNESCAPED_UNICODE),
                !empty($data['is_active']) ? 1 : 0,
            ]
        );
        return (int) Database::lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $p = self::find($id);
        if (!$p) return false;
        Database::query(
            "UPDATE printers SET name = ?, type = ?, kitchen_id = ?, config = ?, is_active = ? WHERE id = ?",
            [
                (string)($data['name'] ?? $p['name']),
                (string)($data['type'] ?? $p['type']),
                !empty($data['kitchen_id']) ? (int)$data['kitchen_id'] : null,
                isset($data['config']) ? json_encode($data['config'], JSON_UNESCAPED_UNICODE) : $p['config'],
                isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : (int)$p['is_active'],
                $id,
            ]
        );
        return true;
    }

    public static function delete(int $id): void
    {
        Database::query("DELETE FROM printers WHERE id = ?", [$id]);
    }

    public static function types(): array
    {
        return [
            'thermal' => 'حرارية',
            'inkjet' => 'حبر',
            'label' => 'ملصقات',
            'network' => 'شبكة',
            'usb' => 'USB',
            'bluetooth' => 'Bluetooth',
        ];
    }
}
