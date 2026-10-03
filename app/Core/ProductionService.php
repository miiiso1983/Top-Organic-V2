<?php
declare(strict_types=1);

namespace App\Core;

/**
 * خدمة عمليات التصنيع الداخلي
 * تستهلك مواد أولية وتنتج مادة جهززتها في مخزن معين
 */
class ProductionService
{
    /**
     * التصنيع بناءً على وصفة محددة
     */
    public static function manufacture(
        int $recipeId,
        float $outputQty,
        int $fromWarehouseId,
        int $toWarehouseId,
        ?string $reference = null,
        ?string $notes = null
    ): int {
        $recipe = Database::query(
            "SELECT * FROM production_recipes WHERE id = ? AND is_active = 1",
            [$recipeId]
        )->fetch();
        if (!$recipe) {
            throw new \Exception('وصفة التصنيع غير موجودة');
        }
        $outputItemId = (int) $recipe['output_item_id'];
        $outputQtyBase = (float) $recipe['output_qty'];
        if ($outputQtyBase <= 0) {
            $outputQtyBase = 1;
        }
        $factor = $outputQty / $outputQtyBase;
        $ref = $reference ?? ('MNF-' . date('YmdHis'));
        Database::beginTransaction();
        try {
            $ingredients = Database::query(
                "SELECT * FROM production_recipe_ingredients WHERE recipe_id = ?",
                [$recipeId]
            )->fetchAll();
            foreach ($ingredients as $ing) {
                $need = (float) $ing['qty'] * $factor;
                $available = StockService::balance($fromWarehouseId, (int) $ing['item_id']);
                if ($available < $need) {
                    throw new \Exception(
                        "الكمية غير كافية للمادة: {$ing['item_id']} (مطلوب: $need ، موجود: $available)"
                    );
                }
                StockService::move($fromWarehouseId, (int) $ing['item_id'], 'out', $need, 0, $ref, $notes ?? 'استهلاك تصنيع');
            }
            StockService::move($toWarehouseId, $outputItemId, 'in', $outputQty, 0, $ref, $notes ?? 'ناتج تصنيع');
            Database::commit();
            return $outputItemId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * التصنيع من قسم إلى قسم (منتج → مبيعات)
     */
    public static function manufactureAndTransfer(
        int $recipeId,
        float $outputQty,
        int $manufacturingWarehouseId,
        int $salesWarehouseId,
        ?string $reference = null,
        ?string $notes = null
    ): void {
        self::manufacture($recipeId, $outputQty, $manufacturingWarehouseId, $manufacturingWarehouseId, $reference, $notes);
        $recipe = Database::query("SELECT output_item_id FROM production_recipes WHERE id = ?", [$recipeId])->fetch();
        $outputItemId = (int) $recipe['output_item_id'];
        StockService::move($manufacturingWarehouseId, $outputItemId, 'transfer_out', $outputQty, 0, $reference, $notes ?? 'تحويل للمبيعات');
        StockService::move($salesWarehouseId, $outputItemId, 'transfer_in', $outputQty, 0, $reference, $notes ?? 'استلام من التصنيع');
    }
}
