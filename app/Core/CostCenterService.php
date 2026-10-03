<?php
namespace App\Core;

/**
 * خدمة مراكز الكلفة: شجرة، ربط، توزيع، وتحليل الربحية
 */
class CostCenterService
{
    public static function all(): array
    {
        return Database::query(
            "SELECT cc.*, p.name AS parent_name, u.full_name AS manager_name
             FROM cost_centers cc
             LEFT JOIN cost_centers p ON p.id = cc.parent_id
             LEFT JOIN users u ON u.id = cc.manager_id
             ORDER BY cc.name"
        )->fetchAll();
    }

    public static function active(): array
    {
        return Database::query("SELECT * FROM cost_centers WHERE is_active=1 ORDER BY name")->fetchAll();
    }

    /**
     * قائمة مراكز الكلفة النشطة لعرضها في قوائم الاختيار،
     * مع إزالة التكرارات حسب الاسم (يُحتفظ بأقل id).
     */
    public static function dropdown(): array
    {
        return Database::query(
            "SELECT MIN(id) AS id, name FROM cost_centers WHERE is_active=1 GROUP BY name ORDER BY name"
        )->fetchAll();
    }

    /**
     * قائمة مراكز الكلفة الفريدة للتقارير التي تحتاج الكود أيضاً.
     */
    public static function dropdownWithCode(): array
    {
        return Database::query(
            "SELECT MIN(id) AS id, name, MIN(code) AS code FROM cost_centers WHERE is_active=1 GROUP BY name ORDER BY name"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::query(
            "SELECT cc.*, p.name AS parent_name, u.full_name AS manager_name
             FROM cost_centers cc
             LEFT JOIN cost_centers p ON p.id = cc.parent_id
             LEFT JOIN users u ON u.id = cc.manager_id
             WHERE cc.id = ?",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        Database::query(
            "INSERT INTO cost_centers (name, code, description, parent_id, manager_id, is_active)
             VALUES (?,?,?,?,?,?)",
            [$data['name'], $data['code'] ?? null, $data['description'] ?? null, ($data['parent_id'] ?? null) ?: null, ($data['manager_id'] ?? null) ?: null, $data['is_active'] ?? 1]
        );
        return (int)Database::lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::query(
            "UPDATE cost_centers SET name=?, code=?, description=?, parent_id=?, manager_id=?, is_active=?
             WHERE id=?",
            [$data['name'], $data['code'] ?? null, $data['description'] ?? null, ($data['parent_id'] ?? null) ?: null, ($data['manager_id'] ?? null) ?: null, $data['is_active'] ?? 1, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::query("DELETE FROM cost_centers WHERE id = ?", [$id]);
    }

    public static function tree(): array
    {
        $rows = self::all();
        return self::buildTree($rows, null);
    }

    private static function buildTree(array $rows, ?int $parentId): array
    {
        $tree = [];
        foreach ($rows as $r) {
            $pid = isset($r['parent_id']) && $r['parent_id'] !== null ? (int)$r['parent_id'] : null;
            if ($pid === $parentId) {
                $r['children'] = self::buildTree($rows, (int)$r['id']);
                $tree[] = $r;
            }
        }
        return $tree;
    }

    public static function childrenIds(int $id): array
    {
        $ids = [$id];
        $rows = self::all();
        self::collectChildren($rows, $id, $ids);
        return $ids;
    }

    private static function collectChildren(array $rows, int $parentId, array &$ids): void
    {
        foreach ($rows as $r) {
            if ((int)($r['parent_id'] ?? 0) === $parentId) {
                $ids[] = (int)$r['id'];
                self::collectChildren($rows, (int)$r['id'], $ids);
            }
        }
    }

    public static function link(string $table, int $id, ?int $costCenterId): void
    {
        $allowed = ['orders','purchase_orders','cash_transactions','bank_transactions','payments','cash_advances','custody_expenses','expenses','journal_entries','fixed_assets','bank_accounts'];
        if (!in_array($table, $allowed, true)) throw new \RuntimeException('جدول غير مسموح');
        Database::query("UPDATE {$table} SET cost_center_id = ? WHERE id = ?", [$costCenterId, $id]);
    }

    public static function allocate(string $sourceType, int $sourceId, array $allocations): void
    {
        Database::query("DELETE FROM cost_center_allocations WHERE source_type = ? AND source_id = ?", [$sourceType, $sourceId]);
        $totalPct = 0.0;
        foreach ($allocations as $a) {
            $pct = (float)($a['percentage'] ?? 0);
            $amt = (float)($a['amount'] ?? 0);
            if ($pct <= 0 && $amt <= 0) continue;
            Database::query(
                "INSERT INTO cost_center_allocations (source_type, source_id, cost_center_id, percentage, amount)
                 VALUES (?,?,?,?,?)",
                [$sourceType, $sourceId, (int)($a['cost_center_id'] ?? 0), $pct, $amt]
            );
            $totalPct += $pct;
        }
        if ($totalPct > 100.001) throw new \RuntimeException('مجموع النسب يتجاوز 100%');
    }

    public static function allocations(string $sourceType, int $sourceId): array
    {
        return Database::query(
            "SELECT a.*, cc.name AS cost_center_name FROM cost_center_allocations a
             JOIN cost_centers cc ON cc.id = a.cost_center_id
             WHERE a.source_type = ? AND a.source_id = ?",
            [$sourceType, $sourceId]
        )->fetchAll();
    }

    public static function profitability(int $id, string $from, string $to): array
    {
        $ids = self::childrenIds($id);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $revenue = (float)Database::query(
            "SELECT COALESCE(SUM(total),0) AS s FROM orders
             WHERE cost_center_id IN ({$in}) AND status NOT IN ('cancelled') AND DATE(created_at) BETWEEN ? AND ?",
            array_merge($ids, [$from, $to])
        )->fetch()['s'];
        $expenses = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) AS s FROM expenses
             WHERE cost_center_id IN ({$in}) AND status IN ('approved','paid') AND DATE(expense_date) BETWEEN ? AND ?",
            array_merge($ids, [$from, $to])
        )->fetch()['s'];
        $custodyExpenses = (float)Database::query(
            "SELECT COALESCE(SUM(ce.amount),0) AS s FROM custody_expenses ce
             WHERE ce.cost_center_id IN ({$in}) AND DATE(ce.expense_date) BETWEEN ? AND ?",
            array_merge($ids, [$from, $to])
        )->fetch()['s'];
        $totalExpenses = $expenses + $custodyExpenses;
        return ['revenue' => $revenue, 'expenses' => $totalExpenses, 'profit' => $revenue - $totalExpenses];
    }

    public static function transactions(int $id, string $from, string $to): array
    {
        $ids = self::childrenIds($id);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $orders = Database::query(
            "SELECT 'order' AS type, o.id, o.order_no AS ref, o.created_at AS date, o.total AS amount, 'revenue' AS direction, c.name AS party
             FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
             WHERE o.cost_center_id IN ({$in}) AND o.status NOT IN ('cancelled') AND DATE(o.created_at) BETWEEN ? AND ?",
            array_merge($ids, [$from, $to])
        )->fetchAll();
        $expenses = Database::query(
            "SELECT 'expense' AS type, e.id, e.expense_no AS ref, e.expense_date AS date, e.amount, 'expense' AS direction, e.category AS party
             FROM expenses e
             WHERE e.cost_center_id IN ({$in}) AND e.status IN ('approved','paid') AND DATE(e.expense_date) BETWEEN ? AND ?",
            array_merge($ids, [$from, $to])
        )->fetchAll();
        $custody = Database::query(
            "SELECT 'custody_expense' AS type, ce.id, CONCAT('Custody-', ce.custody_id) AS ref, ce.expense_date AS date, ce.amount, 'expense' AS direction, ce.category AS party
             FROM custody_expenses ce
             WHERE ce.cost_center_id IN ({$in}) AND DATE(ce.expense_date) BETWEEN ? AND ?",
            array_merge($ids, [$from, $to])
        )->fetchAll();
        $all = array_merge($orders, $expenses, $custody);
        usort($all, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $all;
    }

    public static function allocationSummary(string $from, string $to): array
    {
        return Database::query(
            "SELECT cc.id, cc.name, COALESCE(SUM(a.amount),0) AS allocated_amount
             FROM cost_centers cc
             LEFT JOIN cost_center_allocations a ON a.cost_center_id = cc.id
             LEFT JOIN expenses e ON e.id = a.source_id AND a.source_type = 'expense'
             WHERE DATE(e.expense_date) BETWEEN ? AND ? OR a.id IS NULL
             GROUP BY cc.id, cc.name
             ORDER BY cc.name",
            [$from, $to]
        )->fetchAll();
    }
}
