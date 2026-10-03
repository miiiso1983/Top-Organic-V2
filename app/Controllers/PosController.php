<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, StockService, AccountingService, MenuService};

class PosController extends Controller
{
    public function index(): void
    {
        $floors = Database::query("SELECT * FROM floors WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
        $floorId = $this->input('floor_id') ? (int)$this->input('floor_id') : null;

        $menu = MenuService::menuForFloor($floorId);
        $categories = $menu['categories'];
        $items = $menu['items'];
        $tablesQuery = "SELECT * FROM dining_tables";
        $params = [];
        if ($floorId) {
            $tablesQuery .= " WHERE floor_id = ?";
            $params[] = $floorId;
        }
        $tablesQuery .= " ORDER BY name";
        $tables = Database::query($tablesQuery, $params)->fetchAll();

        $appTypes = Database::query(
            "SELECT id, name, code FROM app_types WHERE is_active = 1 ORDER BY name"
        )->fetchAll();

        $openOrders = Database::query(
            "SELECT o.id, o.order_no, o.status, o.total, o.payment_status, t.name AS table_name
             FROM orders o
             LEFT JOIN dining_tables t ON t.id = o.table_id
             WHERE o.status NOT IN ('completed','cancelled')
             ORDER BY o.id DESC LIMIT 30"
        )->fetchAll();

        $this->view('sales/pos', [
            'title'      => 'نقطة البيع POS',
            'categories' => $categories,
            'items'      => $items,
            'tables'     => $tables,
            'floors'     => $floors,
            'floorId'    => $floorId,
            'appTypes'   => $appTypes,
            'openOrders' => $openOrders,
        ]);
    }

    public function checkout(): void
    {
        $data = $this->jsonInput();
        $cart = $data['cart'] ?? [];
        if (!$cart) {
            Response::json(['error' => 'السلة فارغة'], 422);
            return;
        }

        $orderType = $data['order_type'] ?? 'dine_in';
        $tableId   = !empty($data['table_id']) ? (int)$data['table_id'] : null;
        $discount  = (float)($data['discount'] ?? 0);
        $appTypeId = ($orderType === 'online' && !empty($data['app_type_id'])) ? (int)$data['app_type_id'] : null;
        if ($orderType === 'online' && !$appTypeId) {
            Response::json(['error' => 'اختر نوع التطبيق للطلب الإلكتروني'], 422);
            return;
        }

        Database::beginTransaction();
        try {
            $orderNo = Helpers::docNumber('ORD', 'orders', 'order_no');
            $subtotal = 0;
            foreach ($cart as $line) {
                $subtotal += (float)$line['price'] * (float)$line['qty'];
            }
            $total = max(0, $subtotal - $discount + (float)($data['delivery_fee'] ?? 0));

            Database::query(
                "INSERT INTO orders (order_no, branch_id, order_type, app_type_id, table_id, status, subtotal, discount, total,
                                     delivery_address, delivery_fee, notes, created_by)
                 VALUES (?,?,?,?,?,'confirmed',?,?,?,?,?,?,?)",
                [$orderNo, Auth::branchId(), $orderType, $appTypeId, $tableId, $subtotal, $discount, $total,
                 $data['delivery_address'] ?? null, (float)($data['delivery_fee'] ?? 0),
                 $data['notes'] ?? null, Auth::id()]
            );
            $orderId = (int)Database::lastInsertId();
            $floorKitchenId = $this->floorKitchenForTable($tableId);

            foreach ($cart as $line) {
                $item = Database::query("SELECT * FROM menu_items WHERE id = ?", [(int)$line['id']])->fetch();
                if (!$item) continue;
                $preparationKitchenId = $floorKitchenId ?? $item['preparation_kitchen_id'];
                Database::query(
                    "INSERT INTO order_items (order_id, menu_item_id, qty, unit_price, total, notes, station_id, kitchen_section_id, preparation_kitchen_id)
                     VALUES (?,?,?,?,?,?,?,?,?)",
                    [$orderId, $item['id'], (float)$line['qty'], $item['price'],
                     $item['price'] * (float)$line['qty'], $line['notes'] ?? null, $item['station_id'], $item['kitchen_section_id'], $preparationKitchenId]
                );
                // استهلاك مكونات الوصفة من مخزن المطبخ (المخزن رقم 2)
                StockService::consumeRecipe((int)$item['id'], (float)$line['qty'], 2, $orderNo);
            }

            if ($tableId && $orderType === 'dine_in') {
                Database::query("UPDATE dining_tables SET status='occupied' WHERE id = ?", [$tableId]);
            }

            // تكلفة البضاعة المباعة وإثبات الإيرادات
            AccountingService::postCogs($orderId);
            $grossRevenue = $subtotal + (float)($data['delivery_fee'] ?? 0);

            // دفع فوري إن وُجد
            if (!empty($data['paid_amount']) && (float)$data['paid_amount'] > 0) {
                $method = (string)($data['payment_method'] ?? 'cash');
                $paid   = (float)$data['paid_amount'];
                Database::query(
                    "INSERT INTO order_payments (order_id, method, amount, received_by) VALUES (?,?,?,?)",
                    [$orderId, $method, $paid, Auth::id()]
                );
                $paymentId = (int)Database::lastInsertId();

                if ($method === 'credit') {
                    // الآجل لا يؤثر على الصندوق
                    Database::query("UPDATE orders SET payment_status = 'unpaid' WHERE id = ?", [$orderId]);
                } else {
                    $status = $paid >= $total ? 'paid' : 'partial';
                    Database::query("UPDATE orders SET payment_status = ? WHERE id = ?", [$status, $orderId]);
                    Database::query(
                        "INSERT INTO cash_transactions (register_id, trx_type, amount, category, reference, created_by)
                         VALUES (1,'in',?,'مبيعات',?,?)",
                        [$paid, $orderNo, Auth::id()]
                    );
                    Database::query("UPDATE cash_registers SET balance = balance + ? WHERE id = 1", [$paid]);
                    AccountingService::postRevenue($orderId, $grossRevenue, $discount, $method);
                }

                Helpers::queueSync('order_payments', $paymentId, 'insert', [
                    'order_id' => $orderId, 'method' => $method, 'amount' => $paid,
                ]);
            }

            Database::query(
                "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,'confirmed',?)",
                [$orderId, Auth::id()]
            );
            Helpers::queueSync('orders', $orderId, 'insert', [
                'order_no'    => $orderNo,
                'order_type'  => $orderType,
                'table_id'    => $tableId,
                'subtotal'    => $subtotal,
                'discount'    => $discount,
                'total'       => $total,
                'cart'        => $cart,
            ]);
            Helpers::audit('إنشاء طلب', 'orders', $orderId, $orderNo);

            Database::commit();

            $sections = Database::query(
                "SELECT DISTINCT ks.id, ks.name FROM order_items oi
                 JOIN kitchen_sections ks ON ks.id = oi.kitchen_section_id
                 WHERE oi.order_id = ? AND ks.id IS NOT NULL",
                [$orderId]
            )->fetchAll();
            $preparationKitchens = Database::query(
                "SELECT DISTINCT pk.id, pk.name, p.name AS printer_name, p.type AS printer_type
                 FROM order_items oi
                 JOIN preparation_kitchens pk ON pk.id = oi.preparation_kitchen_id
                 LEFT JOIN printers p ON p.kitchen_id = pk.id AND p.is_active = 1
                 WHERE oi.order_id = ? AND pk.id IS NOT NULL",
                [$orderId]
            )->fetchAll();

            $centralKitchenReceipt = '/kitchen/order/' . $orderId . '/receipt';
            $preparationReceipts = array_map(fn($k) => [
                'id'           => (int)$k['id'],
                'name'         => $k['name'],
                'printer_name' => $k['printer_name'],
                'url'          => '/kitchen/order/' . $orderId . '/preparation/' . $k['id'] . '/receipt',
            ], $preparationKitchens);

            Response::json([
                'success'                    => true,
                'order_id'                   => $orderId,
                'order_no'                   => $orderNo,
                'receipt_url'                => '/orders/' . $orderId . '/receipt',
                'central_kitchen_receipt_url'=> $centralKitchenReceipt,
                'preparation_kitchen_receipts'=> $preparationReceipts,
                'kitchen_receipts'           => array_map(fn($s) => [
                    'id'   => (int)$s['id'],
                    'name' => $s['name'],
                    'url'  => '/kitchen/section/' . $s['id'] . '/receipt',
                ], $sections),
                'preparation_kitchen_receipts_legacy' => array_map(fn($k) => [
                    'id'   => (int)$k['id'],
                    'name' => $k['name'],
                    'url'  => '/kitchen/preparation/' . $k['id'] . '/receipt',
                ], $preparationKitchens),
            ]);
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    private function floorKitchenForTable(int $tableId): ?int
    {
        if (!$tableId) return null;
        $row = Database::query(
            "SELECT f.preparation_kitchen_id FROM dining_tables t
             JOIN floors f ON f.id = t.floor_id
             WHERE t.id = ? AND f.preparation_kitchen_id IS NOT NULL",
            [$tableId]
        )->fetch();
        return $row ? (int)$row['preparation_kitchen_id'] : null;
    }
}
