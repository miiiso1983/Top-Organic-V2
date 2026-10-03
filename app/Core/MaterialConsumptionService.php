<?php
namespace App\Core;

class MaterialConsumptionService
{
    public static function dateRange(): array
    {
        $to = (string)($_GET['to'] ?? date('Y-m-d'));
        $from = (string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days', strtotime($to))));
        return [$from, $to];
    }

    public static function filters(): array
    {
        return [
            'from' => (string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))),
            'to' => (string)($_GET['to'] ?? date('Y-m-d')),
            'item_id' => (int)($_GET['item_id'] ?? 0),
            'branch_id' => (int)($_GET['branch_id'] ?? 0),
            'section_id' => (int)($_GET['section_id'] ?? 0),
        ];
    }

    public static function bySales(string $from, string $to, int $itemId = 0, int $branchId = 0): array
    {
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $where = "WHERE o.status = 'completed' AND o.completed_at BETWEEN ? AND ?";
        if ($branchId) { $where .= " AND o.branch_id = ?"; $params[] = $branchId; }
        if ($itemId) { $where .= " AND ri.item_id = ?"; $params[] = $itemId; }

        $theoretical = Database::query(
            "SELECT i.id AS item_id, i.name AS item_name, u.name AS unit_name,
                    SUM(oi.qty * ri.qty / r.yield_qty) AS theoretical_qty,
                    SUM(oi.qty * ri.qty / r.yield_qty * i.cost_price) AS theoretical_cost
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN recipes r ON r.menu_item_id = oi.menu_item_id
             JOIN recipe_ingredients ri ON ri.recipe_id = r.id
             JOIN items i ON i.id = ri.item_id
             JOIN units u ON u.id = i.unit_id
             $where
             GROUP BY i.id
             ORDER BY i.name",
            $params
        )->fetchAll();

        $params2 = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $where2 = "WHERE sm.movement_type = 'consumption' AND sm.created_at BETWEEN ? AND ?";
        if ($itemId) { $where2 .= " AND sm.item_id = ?"; $params2[] = $itemId; }

        $actual = Database::query(
            "SELECT sm.item_id, i.name AS item_name, u.name AS unit_name,
                    SUM(sm.qty) AS actual_qty,
                    SUM(sm.qty * i.cost_price) AS actual_cost
             FROM stock_movements sm
             JOIN items i ON i.id = sm.item_id
             JOIN units u ON u.id = i.unit_id
             $where2
             GROUP BY sm.item_id
             ORDER BY i.name",
            $params2
        )->fetchAll();

        return self::mergeActualTheoretical($theoretical, $actual, 'item_id');
    }

    public static function byRecipe(string $from, string $to, int $itemId = 0, int $branchId = 0): array
    {
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $where = "WHERE o.status = 'completed' AND o.completed_at BETWEEN ? AND ?";
        if ($branchId) { $where .= " AND o.branch_id = ?"; $params[] = $branchId; }
        if ($itemId) { $where .= " AND ri.item_id = ?"; $params[] = $itemId; }

        return Database::query(
            "SELECT mi.name AS menu_item_name, i.name AS item_name, u.name AS unit_name,
                    SUM(oi.qty) AS sold_qty,
                    SUM(oi.qty * ri.qty / r.yield_qty) AS consumed_qty,
                    SUM(oi.qty * ri.qty / r.yield_qty * i.cost_price) AS consumed_cost
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN recipes r ON r.menu_item_id = oi.menu_item_id
             JOIN recipe_ingredients ri ON ri.recipe_id = r.id
             JOIN items i ON i.id = ri.item_id
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             JOIN units u ON u.id = i.unit_id
             $where
             GROUP BY oi.menu_item_id, ri.item_id
             ORDER BY mi.name, i.name",
            $params
        )->fetchAll();
    }

    public static function byProduction(string $from, string $to, int $itemId = 0): array
    {
        $params = [$from, $to];
        $where = "WHERE po.status = 'done' AND po.production_date BETWEEN ? AND ?";
        if ($itemId) { $where .= " AND pc.item_id = ?"; $params[] = $itemId; }

        return Database::query(
            "SELECT i.id AS item_id, i.name AS item_name, u.name AS unit_name,
                    SUM(po.planned_qty) AS planned_output,
                    SUM(po.actual_qty) AS actual_output,
                    SUM(po.waste_qty) AS waste_output,
                    SUM(pc.qty) AS consumed_qty,
                    SUM(pc.qty * i.cost_price) AS consumed_cost
             FROM production_orders po
             JOIN production_consumption pc ON pc.production_id = po.id
             JOIN items i ON i.id = pc.item_id
             JOIN units u ON u.id = i.unit_id
             $where
             GROUP BY i.id
             ORDER BY i.name",
            $params
        )->fetchAll();
    }

    public static function byDepartment(string $from, string $to, int $sectionId = 0, int $branchId = 0): array
    {
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $where = "WHERE o.status = 'completed' AND o.completed_at BETWEEN ? AND ?";
        if ($branchId) { $where .= " AND o.branch_id = ?"; $params[] = $branchId; }
        if ($sectionId) { $where .= " AND oi.kitchen_section_id = ?"; $params[] = $sectionId; }

        return Database::query(
            "SELECT ks.name AS section_name, i.name AS item_name, u.name AS unit_name,
                    SUM(oi.qty * ri.qty / r.yield_qty) AS theoretical_qty,
                    SUM(oi.qty * ri.qty / r.yield_qty * i.cost_price) AS theoretical_cost,
                    SUM(oi.qty) AS sold_items
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             LEFT JOIN kitchen_sections ks ON ks.id = oi.kitchen_section_id
             JOIN recipes r ON r.menu_item_id = oi.menu_item_id
             JOIN recipe_ingredients ri ON ri.recipe_id = r.id
             JOIN items i ON i.id = ri.item_id
             JOIN units u ON u.id = i.unit_id
             $where
             GROUP BY oi.kitchen_section_id, ri.item_id
             ORDER BY ks.name, i.name",
            $params
        )->fetchAll();
    }

    public static function varianceAnalysis(string $from, string $to, int $itemId = 0): array
    {
        $sales = self::bySales($from, $to, $itemId);
        $params = [$from, $to];
        $where = "WHERE po.status = 'done' AND po.production_date BETWEEN ? AND ?";
        if ($itemId) { $where .= " AND pc.item_id = ?"; $params[] = $itemId; }

        $production = Database::query(
            "SELECT pc.item_id, SUM(pc.qty) AS qty, SUM(pc.qty * i.cost_price) AS cost
             FROM production_consumption pc
             JOIN production_orders po ON po.id = pc.production_id
             JOIN items i ON i.id = pc.item_id
             $where
             GROUP BY pc.item_id",
            $params
        )->fetchAll();

        $waste = self::wasteSummary($from, $to, $itemId);

        $rows = [];
        foreach ($sales as $s) {
            $id = $s['item_id'];
            $prod = self::findByKey($production, 'item_id', $id);
            $w = self::findByKey($waste, 'item_id', $id);
            $actual = (float)$s['actual_qty'] + (float)($prod['qty'] ?? 0);
            $theoretical = (float)$s['theoretical_qty'];
            $wasteQty = (float)($w['qty'] ?? 0);
            $variance = $actual - $theoretical;
            $loss = max(0, $variance - $wasteQty);
            $rows[] = [
                'item_id' => $id,
                'item_name' => $s['item_name'],
                'unit_name' => $s['unit_name'],
                'theoretical_qty' => $theoretical,
                'actual_qty' => $actual,
                'variance_qty' => $variance,
                'waste_qty' => $wasteQty,
                'loss_qty' => $loss,
                'theoretical_cost' => $s['theoretical_cost'],
                'actual_cost' => (float)$s['actual_cost'] + (float)($prod['cost'] ?? 0),
            ];
        }
        return $rows;
    }

    public static function wasteSummary(string $from, string $to, int $itemId = 0): array
    {
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $where = "WHERE w.created_at BETWEEN ? AND ?";
        if ($itemId) { $where .= " AND w.item_id = ?"; $params[] = $itemId; }

        $log = Database::query(
            "SELECT w.item_id, i.name AS item_name, u.name AS unit_name,
                    SUM(w.qty) AS qty,
                    SUM(w.qty * i.cost_price) AS cost
             FROM waste_log w
             JOIN items i ON i.id = w.item_id
             JOIN units u ON u.id = i.unit_id
             $where
             GROUP BY w.item_id
             ORDER BY i.name",
            $params
        )->fetchAll();

        $params2 = [$from, $to];
        $where2 = "WHERE po.status = 'done' AND po.production_date BETWEEN ? AND ?";
        if ($itemId) { $where2 .= " AND po.item_id = ?"; $params2[] = $itemId; }

        $prod = Database::query(
            "SELECT po.item_id, i.name AS item_name, u.name AS unit_name,
                    SUM(po.waste_qty) AS qty,
                    SUM(po.waste_qty * i.cost_price) AS cost
             FROM production_orders po
             JOIN items i ON i.id = po.item_id
             JOIN units u ON u.id = i.unit_id
             $where2
             GROUP BY po.item_id
             ORDER BY i.name",
            $params2
        )->fetchAll();

        return array_merge($log, $prod);
    }

    public static function wasteDetails(string $from, string $to, int $itemId = 0): array
    {
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $where = "WHERE w.created_at BETWEEN ? AND ?";
        if ($itemId) { $where .= " AND w.item_id = ?"; $params[] = $itemId; }
        return Database::query(
            "SELECT w.created_at, i.name AS item_name, u.name AS unit_name, w.qty, w.reason, w.logged_by
             FROM waste_log w
             JOIN items i ON i.id = w.item_id
             JOIN units u ON u.id = i.unit_id
             $where
             ORDER BY w.created_at DESC",
            $params
        )->fetchAll();
    }

    public static function itemsList(): array
    {
        return Database::query("SELECT id, name FROM items WHERE is_active = 1 ORDER BY name")->fetchAll();
    }

    public static function branchesList(): array
    {
        return Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();
    }

    public static function sectionsList(): array
    {
        return Database::query("SELECT id, name FROM kitchen_sections WHERE is_active = 1 ORDER BY name")->fetchAll();
    }

    private static function mergeActualTheoretical(array $theoretical, array $actual, string $key): array
    {
        $map = [];
        foreach ($theoretical as $t) { $map[$t[$key]] = $t; }
        foreach ($actual as $a) {
            if (!isset($map[$a[$key]])) {
                $map[$a[$key]] = [
                    'item_id' => $a['item_id'],
                    'item_name' => $a['item_name'],
                    'unit_name' => $a['unit_name'],
                    'theoretical_qty' => 0,
                    'theoretical_cost' => 0,
                ];
            }
            $map[$a[$key]]['actual_qty'] = $a['actual_qty'];
            $map[$a[$key]]['actual_cost'] = $a['actual_cost'];
        }
        foreach ($map as &$row) {
            if (!isset($row['actual_qty'])) { $row['actual_qty'] = 0; $row['actual_cost'] = 0; }
            $row['variance_qty'] = (float)$row['actual_qty'] - (float)$row['theoretical_qty'];
        }
        return array_values($map);
    }

    private static function findByKey(array $rows, string $key, $value): ?array
    {
        foreach ($rows as $r) { if ((string)$r[$key] === (string)$value) return $r; }
        return null;
    }
}
