<?php
declare(strict_types=1);

namespace App\Core\PlateCost;

use App\Core\Database;
use App\Core\Decimal;
use App\Models\{PlateCostDish, PlateCostDishMaterialBom, PlateCostPreparedComponentBom, PlateCostPurchasePrice};

class RecalculationService
{
    /**
     * إعادة حساب كامل run استناداً إلى البيانات المخزنة في جداول snapshots.
     * تُستخدم بعد تعديل هدر سطر أو سعر/هدر مادة شراء.
     */
    public static function recalculateRun(int $runId): void
    {
        $input = self::buildInput($runId);
        $result = CalculationService::calculate($input);

        Database::beginTransaction();
        try {
            foreach ($input['purchase_prices'] as &$p) {
                $p['raw_waste_over_yield'] = self::rawWasteOverYield((string) $p['raw_waste_rate'])->toString(8);
            }
            unset($p);
            PlateCostPurchasePrice::bulkInsert($runId, $input['purchase_prices']);

            Database::query("DELETE FROM plate_cost_prepared_component_bom WHERE import_run_id = ?", [$runId]);
            $prepared = [];
            foreach ($result->preparedLines as $l) {
                $prepared[] = (array) $l;
            }
            PlateCostPreparedComponentBom::bulkInsert($runId, $prepared);

            Database::query("DELETE FROM plate_cost_dish_material_bom WHERE import_run_id = ?", [$runId]);
            $materials = [];
            foreach ($result->dishMaterialLines as $l) {
                $materials[] = (array) $l;
            }
            PlateCostDishMaterialBom::bulkInsert($runId, $materials);

            $dishes = [];
            foreach ($result->dishes as $d) {
                $dishes[] = (array) $d;
            }
            PlateCostDish::bulkInsert($runId, $dishes);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function buildInput(int $runId): array
    {
        $prices = PlateCostPurchasePrice::getByRun($runId);

        $prepared = [];
        foreach (PlateCostPreparedComponentBom::getByRun($runId) as $r) {
            $prepared[] = [
                'prepared_name' => $r['prepared_name'],
                'ingredient_name' => $r['ingredient_name'],
                'ingredient_code' => $r['ingredient_code'] ?? null,
                'base_quantity' => $r['base_quantity'],
                'unit' => $r['unit'] ?? null,
                'department_waste_rate' => $r['department_waste_rate'],
            ];
        }

        $materials = [];
        foreach (PlateCostDishMaterialBom::getByRun($runId) as $m) {
            $materials[] = [
                'dish_name' => $m['dish_name'],
                'dish_code' => $m['dish_code'] ?? null,
                'dish_category' => $m['dish_category'] ?? null,
                'material_type' => $m['material_type'],
                'material_name' => $m['material_name'],
                'unit' => $m['unit'] ?? null,
                'base_quantity' => $m['base_quantity'],
                'department_waste_rate' => $m['department_waste_rate'],
            ];
        }

        $dishes = [];
        foreach (PlateCostDish::getByRun($runId) as $d) {
            $dishes[] = [
                'dish_name' => $d['dish_name'],
                'dish_code' => $d['dish_code'] ?? null,
                'dish_category' => $d['dish_category'] ?? null,
                'sale_unit' => $d['sale_unit'] ?? null,
                'sale_price' => $d['sale_price'],
            ];
        }

        return [
            'purchase_prices' => $prices,
            'prepared_components' => $prepared,
            'dish_materials' => $materials,
            'dishes' => $dishes,
        ];
    }

    private static function rawWasteOverYield(string $rate): Decimal
    {
        $d = Decimal::of($rate);
        return $d->compareTo(0) < 0 ? $d : $d->div(Decimal::of(1)->sub($d));
    }
}
