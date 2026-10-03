<?php
namespace App\Models;

use App\Core\Database;

class PlateCostDishMaterialBom
{
    public static function bulkInsert(int $runId, array $lines): void
    {
        foreach ($lines as $l) {
            Database::query(
                "INSERT INTO plate_cost_dish_material_bom
                 (import_run_id, dish_name, dish_code, dish_category, material_type, material_name, unit,
                  base_quantity, resolved_unit_cost, raw_waste_over_yield, department_waste_rate, department_waste_over_yield,
                  actual_consumed_quantity, line_cost)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $runId, $l['dish_name'], $l['dish_code'] ?? null, $l['dish_category'] ?? null, $l['material_type'], $l['material_name'], $l['unit'] ?? null,
                    $l['base_quantity'], $l['resolved_unit_cost'], $l['raw_waste_over_yield'], $l['department_waste_rate'], $l['department_waste_over_yield'],
                    $l['actual_consumed_quantity'], $l['line_cost']
                ]
            );
        }
    }

    public static function getByRun(int $runId): array
    {
        return Database::query(
            "SELECT * FROM plate_cost_dish_material_bom WHERE import_run_id = ? ORDER BY dish_name, id",
            [$runId]
        )->fetchAll();
    }

    public static function getByDish(int $runId, string $name): array
    {
        return Database::query(
            "SELECT * FROM plate_cost_dish_material_bom WHERE import_run_id = ? AND dish_name = ? ORDER BY id",
            [$runId, $name]
        )->fetchAll();
    }

    public static function getByRunGroupedByDish(int $runId): array
    {
        $grouped = [];
        foreach (self::getByRun($runId) as $row) {
            $grouped[$row['dish_name']][] = $row;
        }
        return $grouped;
    }

    public static function updateDepartmentWasteRate(int $id, string $rate): void
    {
        Database::query(
            "UPDATE plate_cost_dish_material_bom SET department_waste_rate = ? WHERE id = ?",
            [$rate, $id]
        );
    }
}
