<?php
declare(strict_types=1);

namespace App\Core;

/**
 * خدمة تحويل الوحدات إلى الوحدة الأساسية والعكس
 * يدعم سلاسل متداخلة (غم → كغم → ...)
 */
class UnitConversionService
{
    /**
     * تحويل كمية من وحدة معينة إلى الوحدة الأساسية (المخزن)
     */
    public static function toBaseUnit(int $unitId, float $qty): float
    {
        if ($qty == 0) {
            return 0;
        }
        $unit = Database::query("SELECT base_unit_id, factor FROM units WHERE id = ?", [$unitId])->fetch();
        if (!$unit) {
            return $qty;
        }
        $factor = (float) ($unit['factor'] ?? 1);
        if ($factor == 0) {
            return $qty;
        }
        // إذا كانت الوحدة متوسطة للأساسية، حول للأساسية أولاً
        if ($unit['base_unit_id'] !== null) {
            $baseQty = $qty * $factor;
            return self::toBaseUnit((int) $unit['base_unit_id'], $baseQty);
        }
        return $qty;
    }

    /**
     * تحويل من الوحدة الأساسية إلى وحدة معينة
     */
    public static function fromBaseUnit(int $unitId, float $baseQty): float
    {
        if ($baseQty == 0) {
            return 0;
        }
        $unit = Database::query("SELECT base_unit_id, factor FROM units WHERE id = ?", [$unitId])->fetch();
        if (!$unit) {
            return $baseQty;
        }
        $factor = (float) ($unit['factor'] ?? 1);
        if ($factor == 0) {
            return $baseQty;
        }
        if ($unit['base_unit_id'] !== null) {
            return self::fromBaseUnit((int) $unit['base_unit_id'], $baseQty) / $factor;
        }
        return $baseQty / $factor;
    }

    /**
     * تحويل مباشر بين وحدتين
     */
    public static function convert(int $fromUnitId, int $toUnitId, float $qty): float
    {
        if ($fromUnitId === $toUnitId) {
            return $qty;
        }
        $base = self::toBaseUnit($fromUnitId, $qty);
        return self::fromBaseUnit($toUnitId, $base);
    }

    public static function getUnitName(int $unitId): string
    {
        $row = Database::query("SELECT name FROM units WHERE id = ?", [$unitId])->fetch();
        return $row['name'] ?? '';
    }

    /**
     * الوحدة الأساسية لمادة (تتبع حتى أبعد مستوى المخزن)
     */
    public static function getBaseUnitId(int $unitId): int
    {
        $unit = Database::query("SELECT base_unit_id, id FROM units WHERE id = ?", [$unitId])->fetch();
        if (!$unit || $unit['base_unit_id'] === null) {
            return $unitId;
        }
        return self::getBaseUnitId((int) $unit['base_unit_id']);
    }

    /**
     * هل الوحدتان من نفس العائلة (وزن/حجم/عدد)؟
     */
    public static function sameFamily(int $unitA, int $unitB): bool
    {
        if ($unitA === $unitB) {
            return true;
        }
        return self::getBaseUnitId($unitA) === self::getBaseUnitId($unitB);
    }
}
