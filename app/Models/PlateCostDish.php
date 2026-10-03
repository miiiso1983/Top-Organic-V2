<?php
namespace App\Models;

use App\Core\Database;

class PlateCostDish
{
    public static function bulkInsert(int $runId, array $dishes): void
    {
        foreach ($dishes as $d) {
            Database::query(
                "INSERT INTO plate_cost_dishes
                 (import_run_id, dish_name, dish_code, dish_category, sale_unit, dish_cost, sale_price, gross_profit, markup_on_cost)
                 VALUES (?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                 dish_code = VALUES(dish_code),
                 dish_category = VALUES(dish_category),
                 sale_unit = VALUES(sale_unit),
                 dish_cost = VALUES(dish_cost),
                 sale_price = VALUES(sale_price),
                 gross_profit = VALUES(gross_profit),
                 markup_on_cost = VALUES(markup_on_cost)",
                [
                    $runId, $d['dish_name'], $d['dish_code'] ?? null, $d['dish_category'] ?? null, $d['sale_unit'] ?? null,
                    $d['dish_cost'], $d['sale_price'], $d['gross_profit'], $d['markup_on_cost']
                ]
            );
        }
    }

    public static function getByRun(int $runId): array
    {
        return Database::query(
            "SELECT * FROM plate_cost_dishes WHERE import_run_id = ? ORDER BY dish_name",
            [$runId]
        )->fetchAll();
    }

    public static function getByName(int $runId, string $name): ?array
    {
        $row = Database::query(
            "SELECT * FROM plate_cost_dishes WHERE import_run_id = ? AND dish_name = ?",
            [$runId, $name]
        )->fetch();
        return $row ?: null;
    }
}
