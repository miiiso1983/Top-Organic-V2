<?php
namespace App\Models;

use App\Core\Database;

class PlateCostPreparedComponentBom
{
    public static function bulkInsert(int $runId, array $lines): void
    {
        foreach ($lines as $l) {
            Database::query(
                "INSERT INTO plate_cost_prepared_component_bom
                 (import_run_id, prepared_name, ingredient_name, ingredient_code, base_quantity, unit, unit_price,
                  raw_waste_rate, raw_waste_over_yield, raw_waste_quantity, raw_waste_cost,
                  department_waste_rate, department_waste_over_yield, department_waste_quantity, department_waste_cost,
                  actual_consumed_quantity, line_cost)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $runId, $l['prepared_name'], $l['ingredient_name'], $l['ingredient_code'] ?? null, $l['base_quantity'], $l['unit'] ?? null,
                    $l['unit_price'], $l['raw_waste_rate'], $l['raw_waste_over_yield'], $l['raw_waste_quantity'], $l['raw_waste_cost'],
                    $l['department_waste_rate'], $l['department_waste_over_yield'], $l['department_waste_quantity'], $l['department_waste_cost'],
                    $l['actual_consumed_quantity'], $l['line_cost']
                ]
            );
        }
    }

    public static function getByRun(int $runId): array
    {
        return Database::query(
            "SELECT * FROM plate_cost_prepared_component_bom WHERE import_run_id = ? ORDER BY prepared_name, id",
            [$runId]
        )->fetchAll();
    }

    public static function getByPrepared(int $runId, string $name): array
    {
        return Database::query(
            "SELECT * FROM plate_cost_prepared_component_bom WHERE import_run_id = ? AND prepared_name = ? ORDER BY id",
            [$runId, $name]
        )->fetchAll();
    }

    public static function getByRunGroupedByPrepared(int $runId): array
    {
        $grouped = [];
        foreach (self::getByRun($runId) as $row) {
            $grouped[$row['prepared_name']][] = $row;
        }
        return $grouped;
    }

    public static function updateDepartmentWasteRate(int $id, string $rate): void
    {
        Database::query(
            "UPDATE plate_cost_prepared_component_bom SET department_waste_rate = ? WHERE id = ?",
            [$rate, $id]
        );
    }
}
