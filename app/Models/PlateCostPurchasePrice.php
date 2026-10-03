<?php
namespace App\Models;

use App\Core\Database;

class PlateCostPurchasePrice
{
    public static function bulkInsert(int $runId, array $prices): void
    {
        foreach ($prices as $p) {
            Database::query(
                "INSERT INTO plate_cost_purchase_prices
                 (import_run_id, material_name, material_category, unit_price, raw_waste_rate, raw_waste_over_yield, currency, price_as_of, supplier_id, source_file_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                 material_category = VALUES(material_category),
                 unit_price = VALUES(unit_price),
                 raw_waste_rate = VALUES(raw_waste_rate),
                 raw_waste_over_yield = VALUES(raw_waste_over_yield)",
                [
                    $runId, $p['material_name'], $p['material_category'] ?? null, $p['unit_price'], $p['raw_waste_rate'],
                    $p['raw_waste_over_yield'], $p['currency'] ?? null, $p['price_as_of'] ?? null, $p['supplier_id'] ?? null, $p['source_file_id'] ?? null
                ]
            );
        }
    }

    public static function getByRun(int $runId): array
    {
        return Database::query(
            "SELECT * FROM plate_cost_purchase_prices WHERE import_run_id = ? ORDER BY material_name",
            [$runId]
        )->fetchAll();
    }

    public static function findByMaterial(int $runId, string $name): ?array
    {
        $row = Database::query(
            "SELECT * FROM plate_cost_purchase_prices WHERE import_run_id = ? AND material_name = ?",
            [$runId, $name]
        )->fetch();
        return $row ?: null;
    }

    public static function updatePrice(int $id, string $unitPrice, string $rawWasteRate): void
    {
        Database::query(
            "UPDATE plate_cost_purchase_prices SET unit_price = ?, raw_waste_rate = ? WHERE id = ?",
            [$unitPrice, $rawWasteRate, $id]
        );
    }
}
