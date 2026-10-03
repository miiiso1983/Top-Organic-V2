<?php
namespace App\Core\Workflow;

/**
 * محرك تقييم الشروط (Rule Engine) — بدون برمجة.
 *
 * صيغة القاعدة (DSL مخزّن كـ JSON):
 * {
 *   "logic": "AND" | "OR",
 *   "not": false,
 *   "conditions": [
 *     {"field": "amount", "op": ">", "value": 500},
 *     {"logic": "OR", "conditions": [ ...مجموعة متداخلة... ]}
 *   ]
 * }
 *
 * السياق الممرر:
 * ['data'=>[...حقول النموذج...], 'request'=>[...], 'requester'=>[...], 'entity'=>[...]]
 */
final class RuleEvaluator
{
    /** العمليات المدعومة (أسماء داخلية) */
    public const OPERATORS = [
        'eq', 'neq', 'gt', 'lt', 'gte', 'lte',
        'in', 'not_in', 'contains', 'starts_with', 'ends_with',
        'between', 'is_empty', 'is_not_empty',
    ];

    /** خريطة الرموز/الأسماء المعروضة → الاسم الداخلي */
    private const ALIASES = [
        '=' => 'eq', '==' => 'eq', '!=' => 'neq', '<>' => 'neq',
        '>' => 'gt', '<' => 'lt', '>=' => 'gte', '<=' => 'lte',
        'IN' => 'in', 'NOT IN' => 'not_in',
        'Contains' => 'contains', 'Starts With' => 'starts_with', 'Ends With' => 'ends_with',
        'Between' => 'between',
    ];

    /** تقييم قاعدة كاملة ضد سياق — تعيد true/false */
    public static function evaluate(?array $rule, array $context): bool
    {
        if (empty($rule) || empty($rule['conditions'])) {
            return true; // لا شرط = يمرّ دائماً
        }
        return self::evalGroup($rule, $context);
    }

    private static function evalGroup(array $group, array $ctx): bool
    {
        $logic = strtoupper((string)($group['logic'] ?? 'AND')) === 'OR' ? 'OR' : 'AND';
        $result = $logic === 'AND';
        foreach ((array)$group['conditions'] as $cond) {
            $ok = isset($cond['conditions']) && is_array($cond['conditions'])
                ? self::evalGroup($cond, $ctx)
                : self::evalCondition($cond, $ctx);
            if ($logic === 'AND' && !$ok) { $result = false; break; }
            if ($logic === 'OR' && $ok)   { $result = true;  break; }
        }
        return !empty($group['not']) ? !$result : $result;
    }

    private static function evalCondition(array $cond, array $ctx): bool
    {
        $op = self::normalizeOp((string)($cond['op'] ?? 'eq'));
        if (!in_array($op, self::OPERATORS, true)) return false;
        $actual = self::resolveField((string)($cond['field'] ?? ''), $ctx);
        $ok = self::compare($actual, $op, $cond['value'] ?? null);
        return !empty($cond['not']) ? !$ok : $ok;
    }

    private static function normalizeOp(string $op): string
    {
        $op = trim($op);
        return self::ALIASES[$op] ?? strtolower($op);
    }

    /**
     * حل مسار حقل من السياق. يدعم:
     *  amount            → data أولاً ثم request
     *  data.amount / request.branch_id / requester.department / entity.supplier_id
     */
    public static function resolveField(string $path, array $ctx)
    {
        $path = trim($path);
        if ($path === '') return null;
        if (str_contains($path, '.')) {
            $parts = explode('.', $path);
            $root = array_shift($parts);
            $value = $ctx[$root] ?? null;
            foreach ($parts as $p) {
                if (!is_array($value)) return null;
                $value = $value[$p] ?? null;
            }
            return $value;
        }
        if (array_key_exists($path, (array)($ctx['data'] ?? []))) return $ctx['data'][$path];
        return $ctx['request'][$path] ?? null;
    }

    private static function compare($actual, string $op, $expected): bool
    {
        switch ($op) {
            case 'is_empty':     return $actual === null || $actual === '' || $actual === [];
            case 'is_not_empty': return !($actual === null || $actual === '' || $actual === []);
            case 'in':           return in_array($actual, self::asList($expected), false);
            case 'not_in':       return !in_array($actual, self::asList($expected), false);
            case 'contains':     return str_contains((string)$actual, (string)$expected);
            case 'starts_with':  return str_starts_with((string)$actual, (string)$expected);
            case 'ends_with':    return str_ends_with((string)$actual, (string)$expected);
            case 'between':
                [$min, $max] = array_pad(self::asList($expected), 2, null);
                return self::orderedCompare($actual, $min) >= 0 && self::orderedCompare($actual, $max) <= 0;
        }
        $cmp = self::orderedCompare($actual, $expected);
        return match ($op) {
            'eq'  => $cmp === 0,
            'neq' => $cmp !== 0,
            'gt'  => $cmp > 0,
            'lt'  => $cmp < 0,
            'gte' => $cmp >= 0,
            'lte' => $cmp <= 0,
            default => false,
        };
    }

    /** مقارنة مرتبة واعية بالنوع: أرقام → رقمية، تواريخ → زمنية، وإلا نصية */
    private static function orderedCompare($a, $b): int
    {
        if ($a === null || $b === null) return $a === $b ? 0 : ($a === null ? -1 : 1);
        if (is_bool($a) || is_bool($b) || $a === 0 || $a === 1) {
            if (is_bool($b) || $b === 0 || $b === 1 || $b === true || $b === false) {
                return (int)(bool)$a <=> (int)(bool)$b;
            }
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float)$a <=> (float)$b;
        }
        $ta = is_string($a) ? strtotime($a) : false;
        $tb = is_string($b) ? strtotime($b) : false;
        if ($ta !== false && $tb !== false) {
            return $ta <=> $tb;
        }
        return strcmp((string)$a, (string)$b);
    }

    private static function asList($value): array
    {
        if (is_array($value)) return $value;
        return array_values(array_filter(array_map('trim', explode(',', (string)$value)), fn($v) => $v !== ''));
    }
}
