<?php
namespace App\Models;

use App\Core\Database;

/**
 * نموذج مراكز التحضير (Preparation Kitchens)
 */
class PreparationKitchen
{
    public static function all(): array
    {
        return Database::query(
            "SELECT pk.*, p.name AS printer_name, p.type AS printer_type
             FROM preparation_kitchens pk
             LEFT JOIN printers p ON p.kitchen_id = pk.id
             WHERE pk.is_active = 1
             ORDER BY pk.sort_order, pk.id"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::query(
            "SELECT pk.*, p.name AS printer_name, p.type AS printer_type
             FROM preparation_kitchens pk
             LEFT JOIN printers p ON p.kitchen_id = pk.id
             WHERE pk.id = ?",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(string $name, int $sortOrder = 0): int
    {
        Database::query(
            "INSERT INTO preparation_kitchens (name, sort_order) VALUES (?, ?)",
            [$name, $sortOrder]
        );
        return (int) Database::lastInsertId();
    }

    public static function update(int $id, string $name, int $sortOrder = 0): void
    {
        Database::query(
            "UPDATE preparation_kitchens SET name = ?, sort_order = ? WHERE id = ?",
            [$name, $sortOrder, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::query("DELETE FROM preparation_kitchens WHERE id = ?", [$id]);
    }
}
