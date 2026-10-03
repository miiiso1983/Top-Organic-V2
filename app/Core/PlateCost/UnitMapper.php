<?php
declare(strict_types=1);

namespace App\Core\PlateCost;

use App\Core\Database;
use App\Core\UnitConversionService;

class UnitMapper
{
    private const ALIASES = [
        'kg' => 'كغم', 'kilo' => 'كغم', 'kilogram' => 'كغم', 'kilograms' => 'كغم', 'كغم' => 'كغم',
        'g' => 'غم', 'gram' => 'غم', 'grams' => 'غم', 'غرام' => 'غم', 'غم' => 'غم',
        'l' => 'لتر', 'liter' => 'لتر', 'litre' => 'لتر', 'liters' => 'لتر', 'litres' => 'لتر', 'ltr' => 'لتر', 'لتر' => 'لتر',
        'ml' => 'مل', 'milliliter' => 'مل', 'millilitre' => 'مل', 'milliliters' => 'مل', 'millilitres' => 'مل', 'مل' => 'مل',
        'pcs' => 'حبة', 'piece' => 'حبة', 'pieces' => 'حبة', 'pc' => 'حبة', 'حبة' => 'حبة', 'قطعة' => 'حبة', 'قطع' => 'حبة',
    ];

    public static function resolveUnitId(?string $unit): ?int
    {
        $name = self::normalizeUnitName($unit);
        if ($name === null) {
            return null;
        }
        $row = Database::query("SELECT id FROM units WHERE name = ?", [$name])->fetch();
        return $row ? (int) $row['id'] : null;
    }

    public static function resolveUnitName(?string $unit): ?string
    {
        return self::normalizeUnitName($unit);
    }

    public static function getUnit(int $unitId): ?array
    {
        return Database::query("SELECT * FROM units WHERE id = ?", [$unitId])->fetch() ?: null;
    }

    public static function convertToBase(float $qty, int $unitId): float
    {
        return UnitConversionService::toBaseUnit($unitId, $qty);
    }

    public static function convertFromBase(float $baseQty, int $unitId): float
    {
        return UnitConversionService::fromBaseUnit($unitId, $baseQty);
    }

    public static function baseUnitId(int $unitId): int
    {
        return UnitConversionService::getBaseUnitId($unitId);
    }

    public static function sameFamily(int $unitA, int $unitB): bool
    {
        return UnitConversionService::sameFamily($unitA, $unitB);
    }

    public static function unitTypeLabel(int $unitId): string
    {
        $baseId = self::baseUnitId($unitId);
        $base = self::getUnit($baseId);
        $name = $base['name'] ?? '';
        return match ($name) {
            'كغم' => 'وزن',
            'لتر' => 'حجم',
            'حبة' => 'عدد',
            default => 'أخرى',
        };
    }

    private static function normalizeUnitName(?string $unit): ?string
    {
        if ($unit === null) {
            return null;
        }
        $key = mb_strtolower(trim($unit));
        return self::ALIASES[$key] ?? null;
    }
}
