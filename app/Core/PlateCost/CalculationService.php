<?php
declare(strict_types=1);

namespace App\Core\PlateCost;

use App\Core\Decimal;

class CalculationService
{
    public static function calculate(array $parsed): CalculationResultDTO
    {
        $errors = [];
        $warnings = [];
        $priceMap = self::buildPurchasePriceMap($parsed['purchase_prices'] ?? [], $errors, $warnings);
        $preparedLines = self::calculatePreparedComponentLines($parsed['prepared_components'] ?? [], $priceMap, $errors, $warnings);
        $preparedCostMap = self::buildPreparedCostMap($preparedLines);
        $dishMaterialLines = self::calculateDishMaterialLines($parsed['dish_materials'] ?? [], $priceMap, $preparedCostMap, $errors, $warnings);
        $dishCostMap = self::buildDishCostMap($dishMaterialLines);
        $dishes = self::calculateDishes($parsed['dishes'] ?? [], $dishCostMap, $errors, $warnings);

        return new CalculationResultDTO($priceMap, $preparedLines, $dishMaterialLines, $dishes, $errors, $warnings);
    }

    private static function buildPurchasePriceMap(array $rows, array &$errors, array &$warnings): array
    {
        $map = [];
        foreach ($rows as $r) {
            $name = self::normalize($r['material_name'] ?? '');
            if ($name === '') { continue; }
            if (isset($map[$name])) {
                $errors[] = "تكرار مادة في أسعار الشراء: {$name}";
                continue;
            }
            $rate = self::num($r['raw_waste_rate'] ?? 0);
            $map[$name] = new PurchasePriceDTO(
                $name,
                self::trimOrNull($r['material_category'] ?? null),
                self::num($r['unit_price'] ?? 0),
                $rate,
                self::rawWasteOverYield($rate)->toString(8),
                null, null, null, null
            );
        }
        return $map;
    }

    private static function buildPreparedCostMap(array $lines): array
    {
        $costs = [];
        foreach ($lines as $l) {
            $costs[$l->prepared_name] = ($costs[$l->prepared_name] ?? Decimal::of(0))->add($l->line_cost);
        }
        return $costs;
    }

    private static function calculatePreparedComponentLines(array $rows, array $priceMap, array &$errors, array &$warnings): array
    {
        $lines = [];
        foreach ($rows as $r) {
            $preparedName = self::normalize($r['prepared_name'] ?? '');
            $ingredientName = self::normalize($r['ingredient_name'] ?? '');
            if ($preparedName === '' || $ingredientName === '') { continue; }
            $price = $priceMap[$ingredientName] ?? null;
            if (!$price) {
                $errors[] = "لم يتم العثور على سعر شراء للمادة: {$ingredientName}";
                continue;
            }
            $baseQty = self::num($r['base_quantity'] ?? 0);
            $unitPrice = Decimal::of($price->unit_price);
            $rawRate = Decimal::of($price->raw_waste_rate);
            $rawOverYield = Decimal::of($price->raw_waste_over_yield);
            $rawWasteQty = Decimal::of($baseQty)->mul($rawOverYield);
            $rawWasteCost = $rawWasteQty->mul($unitPrice);
            $deptRate = self::num($r['department_waste_rate'] ?? 0);
            self::guardWasteRate($deptRate, $ingredientName, $errors, $warnings);
            $deptOverYield = self::departmentWasteOverYield($deptRate);
            $deptWasteQty = Decimal::of($baseQty)->add($rawWasteQty)->mul($deptOverYield);
            $deptWasteCost = $deptWasteQty->mul($unitPrice);
            $actualQty = Decimal::of($baseQty)->add($rawWasteQty)->add($deptWasteQty);
            $lineCost = $actualQty->mul($unitPrice);
            $lines[] = new PreparedComponentLineDTO(
                $preparedName, $ingredientName, self::trimOrNull($r['ingredient_code'] ?? null),
                $baseQty, self::trimOrNull($r['unit'] ?? null), $price->unit_price,
                $price->raw_waste_rate, $price->raw_waste_over_yield,
                $rawWasteQty->toString(6), $rawWasteCost->toString(6),
                $deptRate, $deptOverYield->toString(8), $deptWasteQty->toString(6), $deptWasteCost->toString(6),
                $actualQty->toString(6), $lineCost->toString(6)
            );
        }
        return $lines;
    }

    private static function calculateDishMaterialLines(array $rows, array $priceMap, array $preparedCostMap, array &$errors, array &$warnings): array
    {
        $lines = [];
        foreach ($rows as $r) {
            $dishName = self::normalize($r['dish_name'] ?? '');
            $materialName = self::normalize($r['material_name'] ?? '');
            $type = self::trimOrNull($r['material_type'] ?? 'مادة اولية') ?? 'مادة اولية';
            if ($dishName === '' || $materialName === '') { continue; }
            if ($type === 'محضر') {
                if (!isset($preparedCostMap[$materialName])) {
                    $errors[] = "لم يتم العثور على محضّر: {$materialName} (مستخدم في {$dishName})";
                    continue;
                }
                $unitCost = $preparedCostMap[$materialName]->toString(6);
            } else {
                $price = $priceMap[$materialName] ?? null;
                if (!$price) {
                    $errors[] = "لم يتم العثور على سعر شراء للمادة: {$materialName} (مستخدم في {$dishName})";
                    continue;
                }
                $unitCost = $price->unit_price;
            }
            $baseQty = self::num($r['base_quantity'] ?? 0);
            $priceDto = $priceMap[$materialName] ?? null;
            $rawOverYield = Decimal::of($priceDto ? $priceDto->raw_waste_over_yield : '0');
            $deptRate = self::num($r['department_waste_rate'] ?? 0);
            self::guardWasteRate($deptRate, $materialName, $errors, $warnings);
            $deptOverYield = self::departmentWasteOverYield($deptRate);
            $base = Decimal::of($baseQty);
            $actualQty = $base->add($base->mul($rawOverYield))->add($base->mul($deptOverYield));
            $lineCost = $actualQty->mul($unitCost);
            $lines[] = new DishMaterialLineDTO(
                $dishName, self::trimOrNull($r['dish_code'] ?? null), self::trimOrNull($r['dish_category'] ?? null),
                $type, $materialName, self::trimOrNull($r['unit'] ?? null), $baseQty, $unitCost,
                $rawOverYield->toString(8), $deptRate, $deptOverYield->toString(8),
                $actualQty->toString(6), $lineCost->toString(6)
            );
        }
        return $lines;
    }

    private static function buildDishCostMap(array $lines): array
    {
        $costs = [];
        foreach ($lines as $l) {
            $costs[$l->dish_name] = ($costs[$l->dish_name] ?? Decimal::of(0))->add($l->line_cost);
        }
        return $costs;
    }

    private static function calculateDishes(array $rows, array $dishCostMap, array &$errors, array &$warnings): array
    {
        $dishes = [];
        foreach ($rows as $r) {
            $name = self::normalize($r['dish_name'] ?? '');
            if ($name === '') { continue; }
            if (!isset($dishCostMap[$name])) {
                $errors[] = "لم يتم العثور على مواد للطبق: {$name}";
                continue;
            }
            $dishCost = $dishCostMap[$name];
            $salePrice = Decimal::of(self::num($r['sale_price'] ?? 0));
            $profit = $salePrice->sub($dishCost);
            $markup = $dishCost->isZero() ? Decimal::of(0) : $profit->div($dishCost);
            $dishes[] = new DishDTO(
                $name, self::trimOrNull($r['dish_code'] ?? null), self::trimOrNull($r['dish_category'] ?? null),
                self::trimOrNull($r['sale_unit'] ?? null), $dishCost->toString(6), $salePrice->toString(6),
                $profit->toString(6), $markup->toString(8)
            );
        }
        return $dishes;
    }

    private static function rawWasteOverYield(string $rate): Decimal
    {
        $d = Decimal::of($rate);
        return $d->compareTo(0) < 0 ? $d : $d->div(Decimal::of(1)->sub($d));
    }

    private static function departmentWasteOverYield(string $rate): Decimal
    {
        return Decimal::of($rate)->div(Decimal::of(1)->sub($rate));
    }

    private static function guardWasteRate(string $rate, string $material, array &$errors, array &$warnings): void
    {
        $d = Decimal::of($rate);
        if ($d->compareTo(1) >= 0) {
            $errors[] = "معدل هدر غير صالح (>= 1) للمادة: {$material}";
        }
        if ($d->isNegative()) {
            $warnings[] = "معدل هدر سالب للمادة: {$material} (تم الحفاظ عليه كما في Excel)";
        }
    }

    private static function num(mixed $value): string
    {
        if ($value === null || $value === '') { return '0'; }
        return is_numeric($value) ? (string) $value : '0';
    }

    private static function normalize(mixed $value): string
    {
        if ($value === null) { return ''; }
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        return $value;
    }

    private static function trimOrNull(mixed $value): ?string
    {
        if ($value === null) { return null; }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
