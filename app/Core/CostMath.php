<?php
declare(strict_types=1);

namespace App\Core;

/**
 * حساب التكلفة الدقيق (المرجع: ملف كلفة المواد)
 * - الهدر تراكمي وقسمة لا ضرب: gross = net / (1 - w1) / (1 - w2)
 * - زيادة الناتج تخفّض الكمية المطلوبة: gross = net / (1 + gain)
 * - مؤشرات الربحية: ربح إجمالي، نسبة تكلفة الغذاء، هامش على الكلفة
 */
class CostMath
{
    /** أقصى نسبة هدر مقبولة لتفادي القسمة على صفر */
    public const MAX_WASTE = 0.95;

    /**
     * تنظيف نسبة الهدر ضمن المدى المسموح.
     * القيمة السالبة تعني زيادة/تضخم في المادة (_gain_) بدلاً من هدر.
     */
    public static function normalizeWaste(float $rate): float
    {
        if ($rate < -self::MAX_WASTE) {
            return -self::MAX_WASTE;
        }
        return $rate > self::MAX_WASTE ? self::MAX_WASTE : $rate;
    }

    /** تنظيف نسبة زيادة الناتج (لا تقل عن صفر) */
    public static function normalizeGain(float $rate): float
    {
        return $rate < 0 ? 0.0 : $rate;
    }

    /**
     * الكمية الإجمالية المطلوبة من الكمية الصافية
     * @param float $netQty الكمية الصافية (بالوحدة الأساسية)
     * @param float $lineWaste هدر المادة الأولية
     * @param float $headerWaste هدر القسم/التحضير
     * @param float $yieldGain زيادة الناتج بعد الطبخ
     */
    public static function grossQty(float $netQty, float $lineWaste = 0.0, float $headerWaste = 0.0, float $yieldGain = 0.0): float
    {
        if ($netQty == 0.0) {
            return 0.0;
        }
        $w1 = self::normalizeWaste($lineWaste);
        $w2 = self::normalizeWaste($headerWaste);
        $g = self::normalizeGain($yieldGain);
        return $netQty / (1 - $w1) / (1 - $w2) / (1 + $g);
    }

    /** كلفة السطر = الكمية الإجمالية × كلفة وحدة المكوّن */
    public static function lineCost(float $grossQty, float $unitCost): float
    {
        return round($grossQty * $unitCost, 4);
    }

    /** كلفة الوحدة الواحدة من إجمالي الكلفة وكمية الناتج */
    public static function unitCost(float $totalCost, float $yieldQty): float
    {
        return $totalCost / max($yieldQty, 0.0001);
    }

    /**
     * مؤشرات الربحية لوحدة واحدة
     * @return array{profit: float, food_cost_pct: float, margin_pct: float, markup_pct: float}
     */
    public static function profitability(float $unitCost, float $salePrice): array
    {
        $profit = $salePrice - $unitCost;
        return [
            'profit' => $profit,
            'food_cost_pct' => $salePrice > 0 ? ($unitCost / $salePrice) : 0.0,
            'margin_pct' => $salePrice > 0 ? ($profit / $salePrice) : 0.0,
            'markup_pct' => $unitCost > 0 ? ($profit / $unitCost) : 0.0,
        ];
    }
}
