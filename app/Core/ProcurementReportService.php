<?php
namespace App\Core;

/**
 * خدمة تقارير المشتريات والمواد والمخزون
 */
class ProcurementReportService
{
    /** فلاتر مشتركة: فرع، قسم، مورد، مادة، فترة */
    public static function filters(array $params): array
    {
        return [
            'from' => (string)($params['from'] ?? date('Y-m-01')),
            'to' => (string)($params['to'] ?? date('Y-m-d')),
            'branch_id' => (int)($params['branch_id'] ?? 0),
            'department_id' => (int)($params['department_id'] ?? 0),
            'supplier_id' => (int)($params['supplier_id'] ?? 0),
            'item_id' => (int)($params['item_id'] ?? 0),
            'status' => (string)($params['status'] ?? ''),
            'q' => (string)($params['q'] ?? ''),
        ];
    }

    private static function dateClause(string $column, array $f): string
    {
        return " AND DATE($column) BETWEEN '{$f['from']}' AND '{$f['to']}' ";
    }

    private static function idClause(string $column, int $value): string
    {
        return $value ? " AND $column = $value " : '';
    }

    private static function likeClause(string $column, string $value): string
    {
        return $value ? " AND $column LIKE '%" . addslashes($value) . "%' " : '';
    }

    /** 1) تقرير طلبات المواد */
    public static function materialRequests(array $f): array
    {
        $sql = "SELECT mr.*, b.name AS branch_name, d.name AS department_name, u.full_name AS requester_name,
                       TIMESTAMPDIFF(HOUR, mr.created_at, NOW()) AS cycle_hours
                FROM material_requests mr
                LEFT JOIN branches b ON b.id = mr.branch_id
                LEFT JOIN departments d ON d.id = mr.department_id
                LEFT JOIN users u ON u.id = mr.requested_by
                WHERE 1=1 "
                . self::dateClause('mr.created_at', $f)
                . self::idClause('mr.branch_id', $f['branch_id'])
                . self::idClause('mr.department_id', $f['department_id'])
                . self::likeClause('mr.request_no', $f['q'])
                . ($f['status'] ? " AND mr.status = '{$f['status']}' " : '')
                . " ORDER BY mr.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 2) تقرير طلبات الشراء */
    public static function purchaseRequests(array $f): array
    {
        $sql = "SELECT pr.*, u.full_name AS requester_name, mr.request_no AS mr_no
                FROM purchase_requests pr
                LEFT JOIN users u ON u.id = pr.requested_by
                LEFT JOIN material_requests mr ON mr.id = pr.material_request_id
                WHERE 1=1 "
                . self::dateClause('pr.created_at', $f)
                . self::likeClause('pr.request_no', $f['q'])
                . ($f['status'] ? " AND pr.status = '{$f['status']}' " : '')
                . " ORDER BY pr.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 3) تقرير طلبات المناقلة */
    public static function transferRequests(array $f): array
    {
        $sql = "SELECT t.*, fw.name AS from_warehouse, tw.name AS to_warehouse, mr.request_no AS mr_no,
                       u.full_name AS prepared_by_name
                FROM stock_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN material_requests mr ON mr.id = t.material_request_id
                LEFT JOIN users u ON u.id = t.prepared_by
                WHERE 1=1 "
                . self::dateClause('t.created_at', $f)
                . self::idClause('t.from_warehouse_id', $f['branch_id'])
                . self::idClause('t.to_warehouse_id', $f['department_id'])
                . self::likeClause('t.transfer_no', $f['q'])
                . " ORDER BY t.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 4) تقرير أوامر الشراء */
    public static function purchaseOrders(array $f): array
    {
        $sql = "SELECT po.*, s.name AS supplier_name, pr.request_no AS pr_no, mr.request_no AS mr_no
                FROM purchase_orders po
                LEFT JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN purchase_requests pr ON pr.id = po.request_id
                LEFT JOIN material_requests mr ON mr.id = pr.material_request_id
                WHERE 1=1 "
                . self::dateClause('po.created_at', $f)
                . self::idClause('po.supplier_id', $f['supplier_id'])
                . self::likeClause('po.po_no', $f['q'])
                . ($f['status'] ? " AND po.status = '{$f['status']}' " : '')
                . " ORDER BY po.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 5) تقرير الموردين */
    public static function suppliers(): array
    {
        return Database::query(
            "SELECT s.*, COUNT(po.id) AS po_count, COALESCE(SUM(po.total),0) AS total_amount
             FROM suppliers s
             LEFT JOIN purchase_orders po ON po.supplier_id = s.id
             GROUP BY s.id
             ORDER BY total_amount DESC"
        )->fetchAll();
    }

    /** 6) المواد الأكثر طلباً */
    public static function topRequestedItems(array $f): array
    {
        $sql = "SELECT i.name AS item_name, SUM(mri.requested_qty) AS total_qty, COUNT(DISTINCT mri.material_request_id) AS request_count
                FROM material_request_items mri
                JOIN items i ON i.id = mri.item_id
                JOIN material_requests mr ON mr.id = mri.material_request_id
                WHERE 1=1 "
                . self::dateClause('mr.created_at', $f)
                . self::idClause('mr.branch_id', $f['branch_id'])
                . self::idClause('mri.item_id', $f['item_id'])
                . " GROUP BY i.id ORDER BY total_qty DESC LIMIT 50";
        return Database::query($sql)->fetchAll();
    }

    /** 7) المواد غير المتوفرة */
    public static function unavailableItems(): array
    {
        return Database::query(
            "SELECT i.*, COALESCE(SUM(s.qty),0) AS stock_qty
             FROM items i
             LEFT JOIN stock s ON s.item_id = i.id
             WHERE i.is_active = 1
             GROUP BY i.id
             HAVING stock_qty <= 0
             ORDER BY i.name"
        )->fetchAll();
    }

    /** 8) المواد الراكدة (لا حركة منذ 90 يوم) */
    public static function stagnantItems(int $days = 90): array
    {
        $date = date('Y-m-d', strtotime("-$days days"));
        return Database::query(
            "SELECT i.*, COALESCE(SUM(s.qty),0) AS stock_qty, MAX(sm.created_at) AS last_movement
             FROM items i
             LEFT JOIN stock s ON s.item_id = i.id
             LEFT JOIN stock_movements sm ON sm.item_id = i.id AND sm.created_at > ?
             WHERE i.is_active = 1
             GROUP BY i.id
             HAVING stock_qty > 0 AND last_movement IS NULL
             ORDER BY i.name",
            [$date]
        )->fetchAll();
    }

    /** 9) تقرير الفروقات في الاستلام */
    public static function receiptVariances(array $f): array
    {
        // دعم الأعمدة المتقدمة إن وُجدت (migration 77) مع fallback على qty الأساسي
        $sql = "SELECT gr.receipt_no, po.po_no, i.name AS item_name,
                       COALESCE(gri.expected_qty, poi.qty) AS expected_qty,
                       gri.qty AS actual_qty,
                       COALESCE(gri.variance_qty, poi.qty - gri.qty) AS variance_qty,
                       COALESCE(gri.damaged_qty, 0) AS damaged_qty,
                       COALESCE(gri.accepted_qty, gri.qty) AS accepted_qty,
                       gri.damage_reason, gr.created_at
                FROM goods_receipt_items gri
                JOIN goods_receipts gr ON gr.id = gri.receipt_id
                JOIN purchase_orders po ON po.id = gr.po_id
                JOIN items i ON i.id = gri.item_id
                LEFT JOIN purchase_order_items poi ON poi.po_id = po.id AND poi.item_id = gri.item_id
                WHERE 1=1 "
                . self::dateClause('gr.created_at', $f)
                . self::idClause('gri.item_id', $f['item_id'])
                . " AND (COALESCE(gri.variance_qty, poi.qty - gri.qty, 0) != 0 OR COALESCE(gri.damaged_qty, 0) != 0)
                ORDER BY gr.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 10) زمن دورة الطلب */
    public static function requestCycleTime(array $f): array
    {
        $sql = "SELECT mr.request_no, mr.status, mr.created_at,
                       TIMESTAMPDIFF(HOUR, mr.created_at, NOW()) AS approval_hours,
                       TIMESTAMPDIFF(HOUR, mr.created_at, COALESCE(gr.created_at, NOW())) AS receipt_hours
                FROM material_requests mr
                LEFT JOIN purchase_requests pr ON pr.material_request_id = mr.id
                LEFT JOIN purchase_orders po ON po.request_id = pr.id
                LEFT JOIN goods_receipts gr ON gr.po_id = po.id
                WHERE 1=1 "
                . self::dateClause('mr.created_at', $f)
                . " ORDER BY mr.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 11) تقرير الاستلام */
    public static function receipts(array $f): array
    {
        $sql = "SELECT gr.*, po.po_no, w.name AS warehouse_name, i.name AS item_name,
                       COALESCE(gri.expected_qty, poi.qty) AS expected_qty,
                       gri.qty AS actual_qty,
                       COALESCE(gri.accepted_qty, gri.qty) AS accepted_qty,
                       COALESCE(gri.damaged_qty, 0) AS damaged_qty,
                       COALESCE(gri.variance_qty, poi.qty - gri.qty) AS variance_qty
                FROM goods_receipts gr
                JOIN goods_receipt_items gri ON gri.receipt_id = gr.id
                JOIN purchase_orders po ON po.id = gr.po_id
                JOIN warehouses w ON w.id = gr.warehouse_id
                JOIN items i ON i.id = gri.item_id
                LEFT JOIN purchase_order_items poi ON poi.po_id = po.id AND poi.item_id = gri.item_id
                WHERE 1=1 "
                . self::dateClause('gr.created_at', $f)
                . self::idClause('gr.warehouse_id', $f['branch_id'])
                . self::idClause('gri.item_id', $f['item_id'])
                . self::likeClause('gr.receipt_no', $f['q'])
                . " ORDER BY gr.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 12) تقرير المناقلات */
    public static function transfers(array $f): array
    {
        $sql = "SELECT t.*, fw.name AS from_warehouse, tw.name AS to_warehouse, mr.request_no AS mr_no
                FROM stock_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN material_requests mr ON mr.id = t.material_request_id
                WHERE 1=1 "
                . self::dateClause('t.created_at', $f)
                . self::idClause('t.from_warehouse_id', $f['branch_id'])
                . self::idClause('t.to_warehouse_id', $f['department_id'])
                . self::likeClause('t.transfer_no', $f['q'])
                . " ORDER BY t.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 13) تكاليف المشتريات */
    public static function purchaseCosts(array $f): array
    {
        $sql = "SELECT po.po_no, s.name AS supplier_name, SUM(poi.qty * poi.unit_price) AS line_total, po.total, po.created_at
                FROM purchase_orders po
                JOIN purchase_order_items poi ON poi.po_id = po.id
                LEFT JOIN suppliers s ON s.id = po.supplier_id
                WHERE 1=1 "
                . self::dateClause('po.created_at', $f)
                . self::idClause('po.supplier_id', $f['supplier_id'])
                . " GROUP BY po.id ORDER BY po.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 14) تحليل أسعار الشراء */
    public static function priceAnalysis(array $f): array
    {
        $sql = "SELECT i.name AS item_name, s.name AS supplier_name, AVG(poi.unit_price) AS avg_price,
                       MIN(poi.unit_price) AS min_price, MAX(poi.unit_price) AS max_price, COUNT(*) AS po_count
                FROM purchase_order_items poi
                JOIN items i ON i.id = poi.item_id
                LEFT JOIN purchase_orders po ON po.id = poi.po_id
                LEFT JOIN suppliers s ON s.id = po.supplier_id
                WHERE 1=1 "
                . self::dateClause('po.created_at', $f)
                . self::idClause('poi.item_id', $f['item_id'])
                . self::idClause('po.supplier_id', $f['supplier_id'])
                . " GROUP BY poi.item_id, po.supplier_id ORDER BY i.name, avg_price DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 15) استهلاك الأقسام */
    public static function departmentConsumption(array $f): array
    {
        $sql = "SELECT d.name AS department_name, i.name AS item_name, SUM(mri.requested_qty) AS total_qty, COUNT(DISTINCT mri.material_request_id) AS request_count
                FROM material_request_items mri
                JOIN material_requests mr ON mr.id = mri.material_request_id
                LEFT JOIN departments d ON d.id = mr.department_id
                JOIN items i ON i.id = mri.item_id
                WHERE 1=1 "
                . self::dateClause('mr.created_at', $f)
                . self::idClause('mr.department_id', $f['department_id'])
                . self::idClause('mri.item_id', $f['item_id'])
                . " GROUP BY d.id, i.id ORDER BY total_qty DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 16) تقرير المواد الجديدة */
    public static function newItems(array $f): array
    {
        $sql = "SELECT i.*, c.name AS category_name, u.name AS unit_name
                FROM items i
                LEFT JOIN item_categories c ON c.id = i.category_id
                LEFT JOIN units u ON u.id = i.unit_id
                WHERE i.is_active = 1 "
                . self::dateClause('i.created_at', $f)
                . self::likeClause('i.name', $f['q'])
                . " ORDER BY i.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 17) تقرير الموافقات */
    public static function approvals(array $f): array
    {
        $sql = "SELECT r.id, r.entity_type, r.status, w.name AS workflow_name, u.full_name AS requester_name,
                       r.created_at, TIMESTAMPDIFF(HOUR, r.created_at, NOW()) AS hours
                FROM legacy_approval_requests r
                LEFT JOIN legacy_approval_workflows w ON w.id = r.workflow_id
                LEFT JOIN users u ON u.id = r.requested_by
                WHERE 1=1 "
                . self::dateClause('r.created_at', $f)
                . self::likeClause('r.entity_type', $f['q'])
                . ($f['status'] ? " AND r.status = '{$f['status']}' " : '')
                . " ORDER BY r.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }

    /** 18) تقرير التصنيع (إنتاج) */
    public static function production(array $f): array
    {
        $sql = "SELECT p.*, i.name AS item_name, u.full_name AS created_by_name
                FROM production_orders p
                LEFT JOIN items i ON i.id = p.item_id
                LEFT JOIN users u ON u.id = p.created_by
                WHERE 1=1 "
                . self::dateClause('p.created_at', $f)
                . self::likeClause('p.production_no', $f['q'])
                . ($f['status'] ? " AND p.status = '{$f['status']}' " : '')
                . " ORDER BY p.id DESC LIMIT 1000";
        return Database::query($sql)->fetchAll();
    }


    /**
     * لقطة ذكاء اصطناعي للمشتريات: بيانات جاهزة للموديلات الذكية
     * للتنبؤ بالطلب والتصنيع وتحسين المخزون.
     */
    public static function aiSnapshot(): array
    {
        $today = date('Y-m-d');
        $firstDay = date('Y-m-01');
        $pendingMr = (int)Database::query("SELECT COUNT(*) AS c FROM material_requests WHERE status IN ('submitted','pending') AND deleted_at IS NULL")->fetch()['c'];
        $pendingPr = (int)Database::query("SELECT COUNT(*) AS c FROM purchase_requests WHERE status = 'pending'")->fetch()['c'];
        $openPo = (int)Database::query("SELECT COUNT(*) AS c FROM purchase_orders WHERE status IN ('sent','partially_received')")->fetch()['c'];
        $unpaidInvoices = (float)Database::query("SELECT COALESCE(SUM(remaining_amount),0) AS t FROM purchase_invoices WHERE payment_status IN ('unpaid','partial')")->fetch()['t'];
        $monthlySpend = (float)Database::query(
            "SELECT COALESCE(SUM(gri.qty * gri.unit_price),0) AS t
             FROM goods_receipts gr
             JOIN goods_receipt_items gri ON gri.receipt_id = gr.id
             WHERE DATE(gr.created_at) BETWEEN ? AND ?",
            [$firstDay, $today]
        )->fetch()['t'];
        $lowStock = StockService::lowStock();
        $topRequested = Database::query(
            "SELECT i.name, SUM(mri.requested_qty) AS qty
             FROM material_request_items mri
             JOIN items i ON i.id = mri.item_id
             JOIN material_requests mr ON mr.id = mri.material_request_id
             WHERE mr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY i.id ORDER BY qty DESC LIMIT 10"
        )->fetchAll();
        return [
            'pending_material_requests' => $pendingMr,
            'pending_purchase_requests' => $pendingPr,
            'open_purchase_orders' => $openPo,
            'unpaid_invoices' => $unpaidInvoices,
            'monthly_procurement_spend' => $monthlySpend,
            'low_stock_items' => $lowStock,
            'top_requested_items_30d' => $topRequested,
            'generated_at' => $today,
        ];
    }

}
