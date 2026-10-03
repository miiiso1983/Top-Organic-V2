<?php
namespace App\Core;

class DashboardService
{
    public static function filters(): array
    {
        return [
            'from' => (string)($_GET['from'] ?? date('Y-m-01')),
            'to' => (string)($_GET['to'] ?? date('Y-m-d')),
            'branch_id' => (int)($_GET['branch_id'] ?? 0),
            'category_id' => (int)($_GET['category_id'] ?? 0),
            'employee_id' => (int)($_GET['employee_id'] ?? 0),
            'status' => (string)($_GET['status'] ?? ''),
            'order_type' => (string)($_GET['order_type'] ?? ''),
        ];
    }

    public static function dashboardData(array $filters): array
    {
        return [
            'kpis' => self::summaryKpis($filters),
            'salesByDate' => self::salesByDate($filters),
            'salesByPaymentMethod' => self::salesByPaymentMethod($filters),
            'salesByOrderType' => self::salesByOrderType($filters),
            'topItems' => self::topItems($filters),
            'vatSummary' => self::vatSummary($filters),
            'inventoryMovement' => self::inventoryMovement($filters),
            'stockValueByCategory' => self::stockValueByCategory($filters),
            'hrAttendanceSummary' => self::hrAttendanceSummary($filters),
            'employeePerformance' => self::employeePerformance($filters),
            'expensesByCategory' => self::expensesByCategory($filters),
            'captainPerformance' => self::captainPerformance($filters),
        ];
    }

    private static function orderWhere(array $filters, string $table = 'o'): array
    {
        $where = ["{$table}.status <> 'cancelled'"];
        $params = [];
        if ($filters['from'] && $filters['to']) {
            $where[] = "{$table}.created_at >= ? AND {$table}.created_at <= ?";
            $params[] = $filters['from'] . ' 00:00:00';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if ($filters['branch_id']) {
            $where[] = "{$table}.branch_id = ?";
            $params[] = $filters['branch_id'];
        }
        if ($filters['status']) {
            $where[] = "{$table}.status = ?";
            $params[] = $filters['status'];
        }
        if ($filters['order_type']) {
            $where[] = "{$table}.order_type = ?";
            $params[] = $filters['order_type'];
        }
        return [implode(' AND ', $where), $params];
    }

    private static function expenseWhere(array $filters): array
    {
        $where = ["e.status IN ('approved','paid')"];
        $params = [];
        if ($filters['from'] && $filters['to']) {
            $where[] = 'e.expense_date BETWEEN ? AND ?';
            $params[] = $filters['from'];
            $params[] = $filters['to'];
        }
        return [implode(' AND ', $where), $params];
    }

    public static function summaryKpis(array $filters): array
    {
        [$where, $params] = self::orderWhere($filters, 'o');
        $summary = Database::query(
            "SELECT COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS total_sales, COALESCE(AVG(total),0) AS avg_order FROM orders o WHERE $where",
            $params
        )->fetch();

        $orderItems = Database::query(
            "SELECT oi.menu_item_id, oi.qty, DATE(o.created_at) AS order_date
             FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE $where",
            $params
        )->fetchAll();
        $cost = 0.0;
        foreach ($orderItems as $r) {
            $cost += AccountingService::recipeCost((int)$r['menu_item_id'], (float)$r['qty'], $r['order_date']);
        }

        [$eWhere, $eParams] = self::expenseWhere($filters);
        $expenses = Database::query("SELECT COALESCE(SUM(amount),0) AS total FROM expenses e WHERE $eWhere", $eParams)->fetch();

        return [
            'orders_count' => (int)$summary['orders_count'],
            'total_sales' => (float)$summary['total_sales'],
            'cost' => $cost,
            'profit' => (float)$summary['total_sales'] - $cost,
            'avg_order' => (float)$summary['avg_order'],
            'inventory_value' => self::inventoryValue($filters),
            'expenses' => (float)$expenses['total'],
        ];
    }

    public static function salesByDate(array $filters): array
    {
        [$where, $params] = self::orderWhere($filters, 'o');
        return Database::query(
            "SELECT DATE(o.created_at) AS d, COUNT(*) AS orders_count, COALESCE(SUM(o.total),0) AS total FROM orders o WHERE $where GROUP BY DATE(o.created_at) ORDER BY d",
            $params
        )->fetchAll();
    }

    public static function salesByPaymentMethod(array $filters): array
    {
        [$where, $params] = self::orderWhere($filters, 'o');
        return Database::query(
            "SELECT op.method, COUNT(DISTINCT op.order_id) AS orders_count, COALESCE(SUM(op.amount),0) AS total
             FROM order_payments op JOIN orders o ON o.id = op.order_id WHERE $where GROUP BY op.method ORDER BY total DESC",
            $params
        )->fetchAll();
    }

    public static function salesByOrderType(array $filters): array
    {
        [$where, $params] = self::orderWhere($filters, 'o');
        return Database::query(
            "SELECT o.order_type, COUNT(*) AS orders_count, COALESCE(SUM(o.total),0) AS total FROM orders o WHERE $where GROUP BY o.order_type ORDER BY total DESC",
            $params
        )->fetchAll();
    }

    public static function topItems(array $filters): array
    {
        [$where, $params] = self::orderWhere($filters, 'o');
        if ($filters['category_id']) {
            $where .= ' AND mi.category_id = ?';
            $params[] = $filters['category_id'];
        }
        return Database::query(
            "SELECT mi.id, mi.name, COALESCE(SUM(oi.qty),0) AS qty, COALESCE(SUM(oi.total),0) AS total
             FROM order_items oi JOIN menu_items mi ON mi.id = oi.menu_item_id JOIN orders o ON o.id = oi.order_id
             WHERE $where GROUP BY mi.id ORDER BY qty DESC, total DESC LIMIT 10",
            $params
        )->fetchAll();
    }

    public static function vatSummary(array $filters): array
    {
        [$where, $params] = self::orderWhere($filters, 'o');
        return Database::query(
            "SELECT COUNT(*) AS taxable_orders, COALESCE(SUM(tax),0) AS total_vat, COALESCE(AVG(tax),0) AS avg_vat FROM orders o WHERE $where AND o.tax > 0",
            $params
        )->fetch();
    }

    public static function inventoryMovement(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if ($filters['from'] && $filters['to']) {
            $where[] = 'm.created_at >= ? AND m.created_at <= ?';
            $params[] = $filters['from'] . ' 00:00:00';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if ($filters['branch_id']) {
            $where[] = 'w.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        return Database::query(
            "SELECT m.movement_type, COUNT(*) AS movements_count, COALESCE(SUM(m.qty),0) AS qty
             FROM stock_movements m LEFT JOIN warehouses w ON w.id = m.warehouse_id
             WHERE " . implode(' AND ', $where) . ' GROUP BY m.movement_type',
            $params
        )->fetchAll();
    }

    public static function inventoryValue(array $filters): float
    {
        $where = ['i.is_active = 1'];
        $params = [];
        if ($filters['branch_id']) {
            $where[] = 'w.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        $rows = Database::query(
            "SELECT i.id, i.unit_id, i.cost_price, COALESCE(SUM(s.qty),0) AS qty
             FROM items i LEFT JOIN stock s ON s.item_id = i.id LEFT JOIN warehouses w ON w.id = s.warehouse_id
             WHERE " . implode(' AND ', $where) . ' GROUP BY i.id',
            $params
        )->fetchAll();
        $unitMap = self::loadUnitMap();
        $total = 0.0;
        foreach ($rows as $r) {
            $qty = self::fromBaseUnitCached((int)$r['unit_id'], (float)$r['qty'], $unitMap);
            $total += $qty * (float)$r['cost_price'];
        }
        return $total;
    }

    public static function stockValueByCategory(array $filters): array
    {
        $where = ['i.is_active = 1'];
        $params = [];
        if ($filters['branch_id']) {
            $where[] = 'w.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        $rows = Database::query(
            "SELECT ic.id, ic.name, i.id AS item_id, i.unit_id, i.cost_price, COALESCE(SUM(s.qty),0) AS qty
             FROM items i JOIN item_categories ic ON ic.id = i.category_id
             LEFT JOIN stock s ON s.item_id = i.id LEFT JOIN warehouses w ON w.id = s.warehouse_id
             WHERE " . implode(' AND ', $where) . ' GROUP BY i.id, ic.id ORDER BY ic.name, i.name',
            $params
        )->fetchAll();
        $unitMap = self::loadUnitMap();
        $map = [];
        foreach ($rows as $r) {
            $qty = self::fromBaseUnitCached((int)$r['unit_id'], (float)$r['qty'], $unitMap);
            $value = $qty * (float)$r['cost_price'];
            $id = (int)$r['id'];
            if (!isset($map[$id])) {
                $map[$id] = ['name' => $r['name'], 'items_count' => 0, 'total_value' => 0.0];
            }
            $map[$id]['items_count']++;
            $map[$id]['total_value'] += $value;
        }
        usort($map, fn($a, $b) => $b['total_value'] <=> $a['total_value']);
        return $map;
    }

    public static function hrAttendanceSummary(array $filters): array
    {
        $dateWhere = [];
        $dateParams = [];
        if ($filters['from'] && $filters['to']) {
            $dateWhere[] = 'a.checked_at >= ? AND a.checked_at <= ?';
            $dateParams[] = $filters['from'] . ' 00:00:00';
            $dateParams[] = $filters['to'] . ' 23:59:59';
        }
        $join = $dateWhere ? (' AND ' . implode(' AND ', $dateWhere)) : '';
        $where = ["e.file_status = 'open'"];
        $params = [];
        if ($filters['employee_id']) {
            $where[] = 'e.id = ?';
            $params[] = $filters['employee_id'];
        }
        if ($filters['branch_id']) {
            $where[] = 'e.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        return Database::query(
            "SELECT e.id, e.emp_code, e.full_name, e.department, COUNT(a.id) AS checks,
                    SUM(CASE WHEN a.check_type = 'in' THEN 1 ELSE 0 END) AS check_ins,
                    SUM(CASE WHEN a.check_type = 'out' THEN 1 ELSE 0 END) AS check_outs,
                    MIN(CASE WHEN a.check_type = 'in' THEN a.checked_at END) AS first_in,
                    MAX(CASE WHEN a.check_type = 'out' THEN a.checked_at END) AS last_out
             FROM employees e LEFT JOIN attendance a ON a.employee_id = e.id $join
             WHERE " . implode(' AND ', $where) . ' GROUP BY e.id ORDER BY e.full_name',
            array_merge($dateParams, $params)
        )->fetchAll();
    }

    public static function employeePerformance(array $filters): array
    {
        $where = ["e.file_status = 'open'"];
        $params = [];
        if ($filters['employee_id']) {
            $where[] = 'e.id = ?';
            $params[] = $filters['employee_id'];
        }
        if ($filters['branch_id']) {
            $where[] = 'e.branch_id = ?';
            $params[] = $filters['branch_id'];
        }
        $from = $filters['from'] . ' 00:00:00';
        $to = $filters['to'] . ' 23:59:59';
        return Database::query(
            "SELECT e.id, e.emp_code, e.full_name, e.department,
                    COALESCE((SELECT AVG(total_score) FROM evaluations WHERE employee_id = e.id AND status = 'finalized' AND created_at >= ? AND created_at <= ?),0) AS avg_score,
                    COALESCE((SELECT COUNT(*) FROM tasks WHERE assigned_to = e.id AND status = 'done' AND created_at >= ? AND created_at <= ?),0) AS tasks_completed
             FROM employees e
             WHERE " . implode(' AND ', $where) . ' ORDER BY avg_score DESC, tasks_completed DESC',
            array_merge([$from, $to, $from, $to], $params)
        )->fetchAll();
    }

    public static function expensesByCategory(array $filters): array
    {
        [$where, $params] = self::expenseWhere($filters);
        return Database::query(
            "SELECT e.category, COUNT(*) AS expenses_count, COALESCE(SUM(e.amount),0) AS total
             FROM expenses e WHERE $where GROUP BY e.category ORDER BY total DESC",
            $params
        )->fetchAll();
    }

    public static function captainPerformance(array $filters): array
    {
        [$where, $params] = self::orderWhere($filters, 'o');
        if ($filters['employee_id']) {
            $where .= ' AND e.id = ?';
            $params[] = $filters['employee_id'];
        }
        return Database::query(
            "SELECT u.id, COALESCE(e.full_name, u.full_name) AS captain_name, e.emp_code, e.id AS employee_id,
                    COUNT(o.id) AS orders_count, COALESCE(SUM(o.total),0) AS total_sales
             FROM users u
             LEFT JOIN employees e ON e.user_id = u.id
             LEFT JOIN orders o ON o.created_by = u.id AND $where
             WHERE u.is_active = 1
             GROUP BY u.id, e.id, e.full_name, e.emp_code ORDER BY orders_count DESC, total_sales DESC",
            $params
        )->fetchAll();
    }

    private static function loadUnitMap(): array
    {
        $units = Database::query('SELECT id, base_unit_id, factor FROM units')->fetchAll();
        $map = [];
        foreach ($units as $u) {
            $map[(int)$u['id']] = [(int)$u['base_unit_id'], (float)$u['factor']];
        }
        return $map;
    }

    private static function fromBaseUnitCached(int $unitId, float $baseQty, array $unitMap): float
    {
        if ($baseQty == 0 || !isset($unitMap[$unitId])) {
            return $baseQty;
        }
        [$baseUnitId, $factor] = $unitMap[$unitId];
        if ($factor == 0) {
            return $baseQty;
        }
        if ($baseUnitId !== null && isset($unitMap[$baseUnitId])) {
            return self::fromBaseUnitCached($baseUnitId, $baseQty / $factor, $unitMap);
        }
        return $baseQty / $factor;
    }
}
