<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Settings};
use App\Models\PreparationKitchen;

class KitchenController extends Controller
{
    public function index(): void
    {
        $sections = Database::query("SELECT * FROM kitchen_sections WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
        $preparationKitchens = Database::query("SELECT * FROM preparation_kitchens WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
        $this->view('kitchen/index', [
            'title'               => 'شاشة المطبخ',
            'sections'            => $sections,
            'preparationKitchens' => $preparationKitchens,
            'orders'              => $this->activeKitchenOrders(),
        ]);
    }

    /** بيانات حية للشاشة المجمعة (تُستدعى بالتحديث التلقائي) */
    public function data(): void
    {
        Response::json(['orders' => $this->activeKitchenOrders()]);
    }

    private function activeKitchenOrders(?int $sectionId = null, ?int $preparationKitchenId = null): array
    {
        $params = [];
        $sectionWhere = '';
        $prepWhere = '';
        if ($sectionId) {
            $sectionWhere = " AND oi.kitchen_section_id = ?";
            $params[] = $sectionId;
        }
        if ($preparationKitchenId) {
            $prepWhere = " AND COALESCE(oi.preparation_kitchen_id, f.preparation_kitchen_id) = ?";
            $params[] = $preparationKitchenId;
        }

        $rows = Database::query(
            "SELECT o.id AS order_id, o.order_no, o.order_type, o.created_at,
                    t.name AS table_name, t.hall AS table_hall, t.floor AS table_floor,
                    oi.id AS item_id, oi.qty, oi.notes, oi.kitchen_status,
                    mi.name AS item_name, mi.prep_minutes,
                    ks.name AS station_name, sec.name AS section_name, pk.name AS preparation_kitchen_name
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             LEFT JOIN dining_tables t ON t.id = o.table_id
             LEFT JOIN floors f ON f.id = t.floor_id
             LEFT JOIN kitchen_stations ks ON ks.id = oi.station_id
             LEFT JOIN kitchen_sections sec ON sec.id = oi.kitchen_section_id
             LEFT JOIN preparation_kitchens pk ON pk.id = COALESCE(oi.preparation_kitchen_id, f.preparation_kitchen_id)
             WHERE o.status IN ('confirmed','preparing')
               AND oi.kitchen_status IN ('queued','preparing')
               $sectionWhere
               $prepWhere
             ORDER BY t.name, o.created_at, oi.id",
            $params
        )->fetchAll();

        $orders = [];
        foreach ($rows as $r) {
            $oid = $r['order_id'];
            if (!isset($orders[$oid])) {
                $elapsed = (int) floor((time() - strtotime($r['created_at'])) / 60);
                $orders[$oid] = [
                    'order_id'    => $oid,
                    'order_no'    => $r['order_no'],
                    'order_type'  => $r['order_type'],
                    'table_name'  => $r['table_name'],
                    'table_hall'  => $r['table_hall'],
                    'table_floor' => $r['table_floor'],
                    'created_at'  => date('c', strtotime($r['created_at'])),
                    'elapsed'     => $elapsed,
                    'overdue'     => false,
                    'items'       => [],
                ];
            }
            $orders[$oid]['items'][] = [
                'item_id'      => $r['item_id'],
                'name'         => $r['item_name'],
                'qty'          => (float)$r['qty'],
                'notes'        => $r['notes'],
                'status'       => $r['kitchen_status'],
                'station'      => $r['station_name'],
                'section'      => $r['section_name'],
                'prep_minutes' => (int)$r['prep_minutes'],
            ];
            if ($orders[$oid]['elapsed'] > (int)$r['prep_minutes']) {
                $orders[$oid]['overdue'] = true;
            }
        }
        return array_values($orders);
    }

    /** شاشة عرض قسم مطبخ محدد */
    public function section(string $id): void
    {
        $sectionId = (int)$id;
        $section = Database::query("SELECT * FROM kitchen_sections WHERE id = ? AND is_active = 1", [$sectionId])->fetch();
        if (!$section) { Response::abort(404, 'القسم غير موجود'); }

        $this->view('kitchen/section', [
            'title'      => 'مطبخ: ' . $section['name'],
            'section'    => $section,
            'orders'     => $this->activeKitchenOrders($sectionId),
        ]);
    }

    /** بيانات حية لقسم مطبخ محدد */
    public function sectionData(string $id): void
    {
        Response::json(['orders' => $this->activeKitchenOrders((int)$id)]);
    }

    /** شاشة عرض مطبخ تحضير محدد */
    public function preparation(string $id): void
    {
        $prepId = (int)$id;
        $kitchen = Database::query("SELECT * FROM preparation_kitchens WHERE id = ? AND is_active = 1", [$prepId])->fetch();
        if (!$kitchen) { Response::abort(404, 'مطبخ التحضير غير موجود'); }

        $this->view('kitchen/preparation', [
            'title'   => 'مطبخ التحضير: ' . $kitchen['name'],
            'kitchen' => $kitchen,
            'orders'  => $this->activeKitchenOrders(null, $prepId),
        ]);
    }

    /** بيانات حية لمطبخ تحضير محدد */
    public function preparationData(string $id): void
    {
        Response::json(['orders' => $this->activeKitchenOrders(null, (int)$id)]);
    }

    public function updateItemStatus(string $id): void
    {
        $status = (string)$this->input('status');
        if (!in_array($status, ['preparing', 'ready', 'served'], true)) {
            Response::json(['error' => 'حالة غير صالحة'], 422);
            return;
        }

        $timestamps = [
            'preparing' => ", started_at = NOW()",
            'ready'     => ", ready_at = NOW()",
            'served'    => "",
        ];
        Database::query(
            "UPDATE order_items SET kitchen_status = ?{$timestamps[$status]} WHERE id = ?",
            [$status, (int)$id]
        );

        // تحديث حالة الطلب الأب تلقائياً
        $orderId = (int) Database::query(
            "SELECT order_id FROM order_items WHERE id = ?", [(int)$id]
        )->fetch()['order_id'];

        $pending = (int) Database::query(
            "SELECT COUNT(*) AS c FROM order_items
             WHERE order_id = ? AND kitchen_status IN ('queued','preparing')",
            [$orderId]
        )->fetch()['c'];

        $newStatus = $pending === 0 ? 'ready' : 'preparing';
        Database::query("UPDATE orders SET status = ? WHERE id = ? AND status IN ('confirmed','preparing')",
            [$newStatus, $orderId]);

        Response::json(['success' => true]);
    }

    /** تجهيز صنف واحد من شاشة قسم المطبخ */
    public function sectionItemStatus(string $sectionId, string $itemId): void
    {
        $this->updateItemStatus($itemId);
    }

    /** تجهيز كل أصناف قسم المطبخ في طلب معين */
    public function sectionReady(string $sectionId, string $orderId): void
    {
        $secId = (int)$sectionId;
        $oid = (int)$orderId;
        Database::query(
            "UPDATE order_items SET kitchen_status = 'ready', ready_at = NOW()
             WHERE order_id = ? AND kitchen_section_id = ? AND kitchen_status IN ('queued','preparing')",
            [$oid, $secId]
        );
        $this->syncOrderStatus($oid);
        Response::json(['success' => true]);
    }

    /** تجهيز صنف واحد من شاشة مطبخ تحضير */
    public function preparationItemStatus(string $kitchenId, string $itemId): void
    {
        $this->updateItemStatus($itemId);
    }

    /** تجهيز كل أصناف مطبخ تحضير في طلب معين */
    public function preparationReady(string $kitchenId, string $orderId): void
    {
        $prepId = (int)$kitchenId;
        $oid = (int)$orderId;
        Database::query(
            "UPDATE order_items SET kitchen_status = 'ready', ready_at = NOW()
             WHERE order_id = ? AND preparation_kitchen_id = ? AND kitchen_status IN ('queued','preparing')",
            [$oid, $prepId]
        );
        $this->syncOrderStatus($oid);
        Response::json(['success' => true]);
    }

    /** تجهيز الطلب بالكامل (كل الأصناف جاهزة) */
    public function readyOrder(string $id): void
    {
        $orderId = (int)$id;
        Database::query(
            "UPDATE order_items SET kitchen_status = 'ready', ready_at = NOW()
             WHERE order_id = ? AND kitchen_status IN ('queued','preparing')",
            [$orderId]
        );
        $this->syncOrderStatus($orderId);
        Response::json(['success' => true]);
    }

    /** إلغاء الطلب بالكامل */
    public function cancelOrder(string $id): void
    {
        $orderId = (int)$id;
        Database::query(
            "UPDATE order_items SET kitchen_status = 'cancelled' WHERE order_id = ?",
            [$orderId]
        );
        Database::query(
            "UPDATE orders SET status = 'cancelled' WHERE id = ? AND status IN ('confirmed','preparing')",
            [$orderId]
        );
        Response::json(['success' => true]);
    }

    /** بون/إيصال طباعة لقسم مطبخ محدد يحتوي على أصنافه فقط */
    public function sectionReceipt(string $id): void
    {
        $sectionId = (int)$id;
        $section = Database::query("SELECT * FROM kitchen_sections WHERE id = ?", [$sectionId])->fetch();
        if (!$section) { Response::abort(404, 'القسم غير موجود'); }

        $orders = $this->activeKitchenOrders($sectionId);
        $this->viewBare('kitchen/section_receipt', [
            'section' => $section,
            'orders'  => $orders,
        ]);
    }

    /** بون/إيصال طباعة لمطبخ تحضير محدد يحتوي على أصنافه فقط */
    public function preparationReceipt(string $id): void
    {
        $prepId = (int)$id;
        $kitchen = Database::query("SELECT * FROM preparation_kitchens WHERE id = ?", [$prepId])->fetch();
        if (!$kitchen) { Response::abort(404, 'مطبخ التحضير غير موجود'); }

        $orders = $this->activeKitchenOrders(null, $prepId);
        $this->viewBare('kitchen/section_receipt', [
            'section' => $kitchen,
            'orders'  => $orders,
        ]);
    }

    /** بون طباعة مركزي للمطبخ: يحتوي على كافة أصناف الطلب */
    public function orderReceipt(string $id): void
    {
        $orderId = (int)$id;
        $order = Database::query(
            "SELECT o.*, t.name AS table_name, t.hall AS table_hall, t.floor AS table_floor
             FROM orders o
             LEFT JOIN dining_tables t ON t.id = o.table_id
             WHERE o.id = ?",
            [$orderId]
        )->fetch();
        if (!$order) { Response::abort(404, 'الطلب غير موجود'); }

        $items = Database::query(
            "SELECT oi.*, mi.name FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             WHERE oi.order_id = ? AND oi.kitchen_status != 'cancelled'
             ORDER BY oi.id",
            [$orderId]
        )->fetchAll();

        $this->viewBare('kitchen/order_receipt', [
            'order'    => $order,
            'items'    => $items,
            'kitchen'  => null,
            'settings' => Settings::printer(),
        ]);
    }

    /** بون طباعة لمركز تحضير محدد داخل طلب محدد */
    public function orderPreparationReceipt(string $id, string $kitchenId): void
    {
        $orderId = (int)$id;
        $prepId = (int)$kitchenId;
        $kitchen = PreparationKitchen::find($prepId);
        if (!$kitchen) { Response::abort(404, 'مطبخ التحضير غير موجود'); }

        $order = Database::query(
            "SELECT o.*, t.name AS table_name, t.hall AS table_hall, t.floor AS table_floor
             FROM orders o
             LEFT JOIN dining_tables t ON t.id = o.table_id
             WHERE o.id = ?",
            [$orderId]
        )->fetch();
        if (!$order) { Response::abort(404, 'الطلب غير موجود'); }

        $items = Database::query(
            "SELECT oi.*, mi.name FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             WHERE oi.order_id = ? AND oi.preparation_kitchen_id = ? AND oi.kitchen_status != 'cancelled'
             ORDER BY oi.id",
            [$orderId, $prepId]
        )->fetchAll();

        $this->viewBare('kitchen/order_receipt', [
            'order'    => $order,
            'items'    => $items,
            'kitchen'  => $kitchen,
            'settings' => Settings::printer(),
        ]);
    }

    private function syncOrderStatus(int $orderId): void
    {
        $pending = (int) Database::query(
            "SELECT COUNT(*) AS c FROM order_items
             WHERE order_id = ? AND kitchen_status IN ('queued','preparing')",
            [$orderId]
        )->fetch()['c'];

        $newStatus = $pending === 0 ? 'ready' : 'preparing';
        Database::query(
            "UPDATE orders SET status = ? WHERE id = ? AND status IN ('confirmed','preparing')",
            [$newStatus, $orderId]
        );
    }
}
