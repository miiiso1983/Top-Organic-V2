<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, ApiAuth, Helpers, Response, StockService, MenuService, AccountingService};

class CaptainController extends Controller
{
    /** شاشة اختيار الطاولة */
    public function index(): void
    {
        $tables = Database::query(
            "SELECT t.*, f.name AS floor_name,
               (SELECT o.id FROM orders o
                WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.id DESC LIMIT 1) AS current_order_id,
               (SELECT o.order_no FROM orders o
                WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.id DESC LIMIT 1) AS current_order,
               (SELECT o.total FROM orders o
                WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.id DESC LIMIT 1) AS current_order_total
             FROM dining_tables t
             LEFT JOIN floors f ON f.id = t.floor_id
             ORDER BY f.sort_order, t.floor, t.name"
        )->fetchAll();

        $this->view('sales/captain', [
            'title'  => 'اختيار طاولة',
            'tables' => $tables,
        ]);
    }

    /** شاشة إدخال طلب لطاولة محددة */
    public function table(string $table_id): void
    {
        $tableId = (int)$table_id;
        $table = Database::query(
            "SELECT t.*, f.name AS floor_name,
               (SELECT o.id FROM orders o
                WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.id DESC LIMIT 1) AS current_order_id,
               (SELECT o.order_no FROM orders o
                WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.id DESC LIMIT 1) AS current_order,
               (SELECT o.total FROM orders o
                WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.id DESC LIMIT 1) AS current_order_total
             FROM dining_tables t
             LEFT JOIN floors f ON f.id = t.floor_id
             WHERE t.id = ?",
            [$tableId]
        )->fetch();
        if (!$table) {
            Response::abort(404, 'الطاولة غير موجودة');
        }

        $currentOrder = null;
        $currentItems = [];
        if (!empty($table['current_order_id'])) {
            $currentOrder = Database::query(
                "SELECT * FROM orders WHERE id = ?", [(int)$table['current_order_id']]
            )->fetch();
            $currentItems = Database::query(
                "SELECT oi.*, mi.name FROM order_items oi
                 JOIN menu_items mi ON mi.id = oi.menu_item_id
                 WHERE oi.order_id = ? AND oi.kitchen_status != 'cancelled'
                 ORDER BY oi.id",
                [(int)$table['current_order_id']]
            )->fetchAll();
        }

        $menu = MenuService::menuForFloor((int)($table['floor_id'] ?? 0));
        $categories = $menu['categories'];
        $items = $menu['items'];

        $this->view('sales/captain_table', [
            'title'        => 'طلب ' . $table['name'],
            'table'        => $table,
            'currentOrder' => $currentOrder,
            'currentItems' => $currentItems,
            'categories'   => $categories,
            'items'        => $items,
        ]);
    }

    /** نقطة نهاية ويب لإنشاء/إضافة طلب كابتن */
    public function store(): void
    {
        $data = $this->jsonInput();
        $user = [
            'id'        => Auth::id(),
            'branch_id' => Auth::branchId(),
            'full_name' => Auth::name(),
        ];
        $result = $this->processOrder($data, $user);
        Response::json($result, $result['_status'] ?? 200);
    }

    /** نقطة نهاية API للموبايل */
    public function apiOrder(): void
    {
        $user = ApiAuth::user();
        if (!$user) {
            Response::json(['error' => 'Unauthorized'], 401);
            return;
        }
        $data   = $this->jsonInput();
        $result = $this->processOrder($data, $user);
        Response::json($result, $result['_status'] ?? 200);
    }

    /** المنطق المشترك للويب والموبايل */
    private function processOrder(array $data, array $user): array
    {
        $tableId = (int)($data['table_id'] ?? 0);
        $orderId = !empty($data['order_id']) ? (int)$data['order_id'] : null;
        $cart    = $data['cart'] ?? [];
        $notes   = (string)($data['notes'] ?? '');

        if (!$tableId) {
            return ['error' => 'اختر الطاولة', '_status' => 422];
        }
        if (!$cart) {
            return ['error' => 'السلة فارغة', '_status' => 422];
        }

        $table = Database::query("SELECT * FROM dining_tables WHERE id = ?", [$tableId])->fetch();
        if (!$table) {
            return ['error' => 'الطاولة غير موجودة', '_status' => 422];
        }

        Database::beginTransaction();
        try {
            if ($orderId) {
                // إضافة على طلب مفتوح
                $order = Database::query(
                    "SELECT * FROM orders WHERE id = ? AND status NOT IN ('completed','cancelled')",
                    [$orderId]
                )->fetch();
                if (!$order) {
                    Database::rollBack();
                    return ['error' => 'الطلب غير مفتوح أو مكتمل', '_status' => 422];
                }
                if ((int)$order['table_id'] !== $tableId) {
                    Database::rollBack();
                    return ['error' => 'الطاولة لا تطابق الطلب', '_status' => 422];
                }

                $this->insertCartItems($orderId, $cart, $order['order_no'], (int)$order['table_id']);
                $this->recalculateOrder($orderId);

                // إعادة الطلب للمطبخ إذا كان قد أصبح جاهزاً/مقدماً
                if (in_array($order['status'], ['ready','served'], true)) {
                    Database::query("UPDATE orders SET status = 'preparing' WHERE id = ?", [$orderId]);
                }

                $newTotal = (float)Database::query("SELECT total FROM orders WHERE id = ?", [$orderId])->fetch()['total'];
                Helpers::audit('إضافة صنف للطلب', 'orders', $orderId, $order['order_no'], $user['id']);
                Database::commit();

                $preparationKitchens = Database::query(
                    "SELECT DISTINCT pk.id, pk.name, p.name AS printer_name, p.type AS printer_type
                     FROM order_items oi
                     JOIN preparation_kitchens pk ON pk.id = oi.preparation_kitchen_id
                     LEFT JOIN printers p ON p.kitchen_id = pk.id AND p.is_active = 1
                     WHERE oi.order_id = ? AND pk.id IS NOT NULL",
                    [$orderId]
                )->fetchAll();
                $preparationReceipts = array_map(fn($k) => [
                    'id'           => (int)$k['id'],
                    'name'         => $k['name'],
                    'printer_name' => $k['printer_name'],
                    'url'          => '/kitchen/order/' . $orderId . '/preparation/' . $k['id'] . '/receipt',
                ], $preparationKitchens);

                return [
                    'success'                     => true,
                    'order_id'                    => $orderId,
                    'order_no'                    => $order['order_no'],
                    'total'                       => $newTotal,
                    'is_addon'                    => true,
                    'central_kitchen_receipt_url' => '/kitchen/order/' . $orderId . '/receipt',
                    'preparation_kitchen_receipts'=> $preparationReceipts,
                ];
            }

            // طلب جديد
            if ($table['status'] !== 'available') {
                Database::rollBack();
                return ['error' => 'الطاولة غير متاحة', '_status' => 422];
            }

            $orderNo  = Helpers::docNumber('ORD', 'orders', 'order_no');
            $subtotal = 0;
            foreach ($cart as $line) {
                $subtotal += (float)($line['price'] ?? 0) * (float)($line['qty'] ?? 1);
            }
            $total = max(0, $subtotal);

            Database::query(
                "INSERT INTO orders (order_no, branch_id, order_type, table_id, status, subtotal, discount, total,
                                     payment_status, notes, created_by)
                 VALUES (?, ?, 'dine_in', ?, 'preparing', ?, 0, ?, 'unpaid', ?, ?)",
                [$orderNo, $user['branch_id'] ?? null, $tableId, $subtotal, $total, $notes, $user['id']]
            );
            $orderId = (int)Database::lastInsertId();

            $this->insertCartItems($orderId, $cart, $orderNo, $tableId);

            Database::query("UPDATE dining_tables SET status = 'occupied' WHERE id = ?", [$tableId]);
            Database::query(
                "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,'preparing',?)",
                [$orderId, $user['id']]
            );
            Helpers::queueSync('orders', $orderId, 'insert', [
                'order_no'    => $orderNo,
                'order_type'  => 'dine_in',
                'table_id'    => $tableId,
                'subtotal'    => $subtotal,
                'discount'    => 0,
                'total'       => $total,
                'cart'        => $cart,
                'status'      => 'preparing',
            ]);
            Helpers::audit('إنشاء طلب من الكابتن', 'orders', $orderId, $orderNo, $user['id']);

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

            return [
                'success'                     => true,
                'order_id'                    => $orderId,
                'order_no'                    => $orderNo,
                'total'                       => $total,
                'receipt_url'                 => '/orders/' . $orderId . '/receipt',
                'central_kitchen_receipt_url' => $centralKitchenReceipt,
                'preparation_kitchen_receipts'=> $preparationReceipts,
                'kitchen_receipts'            => array_map(fn($s) => [
                    'id'   => (int)$s['id'],
                    'name' => $s['name'],
                    'url'  => '/kitchen/section/' . $s['id'] . '/receipt',
                ], $sections),
                'preparation_kitchen_receipts_legacy' => array_map(fn($k) => [
                    'id'   => (int)$k['id'],
                    'name' => $k['name'],
                    'url'  => '/kitchen/preparation/' . $k['id'] . '/receipt',
                ], $preparationKitchens),
            ];
        } catch (\Throwable $e) {
            Database::rollBack();
            return ['error' => $e->getMessage(), '_status' => 500];
        }
    }

    private function insertCartItems(int $orderId, array $cart, string $orderNo, int $tableId = 0): void
    {
        $floorKitchenId = $this->floorKitchenForTable($tableId);
        foreach ($cart as $line) {
            $item = Database::query("SELECT * FROM menu_items WHERE id = ?", [(int)($line['id'] ?? 0)])->fetch();
            if (!$item) { continue; }
            $qty = (float)($line['qty'] ?? 1);
            $preparationKitchenId = $floorKitchenId ?? ($item['preparation_kitchen_id'] ?? null);
            Database::query(
                "INSERT INTO order_items (order_id, menu_item_id, qty, unit_price, total, notes, station_id, kitchen_section_id, preparation_kitchen_id)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$orderId, $item['id'], $qty, $item['price'], $item['price'] * $qty,
                 $line['notes'] ?? null, $item['station_id'] ?? null, $item['kitchen_section_id'] ?? null, $preparationKitchenId]
            );
            StockService::consumeRecipe((int)$item['id'], $qty, 2, $orderNo);
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

    private function recalculateOrder(int $orderId): void
    {
        $order = Database::query("SELECT discount, delivery_fee FROM orders WHERE id = ?", [$orderId])->fetch();
        $subtotal = (float)Database::query(
            "SELECT COALESCE(SUM(total),0) AS s FROM order_items WHERE order_id = ? AND kitchen_status != 'cancelled'",
            [$orderId]
        )->fetch()['s'];
        $discount    = (float)($order['discount'] ?? 0);
        $deliveryFee = (float)($order['delivery_fee'] ?? 0);
        $total       = max(0, $subtotal - $discount + $deliveryFee);
        Database::query(
            "UPDATE orders SET subtotal = ?, total = ? WHERE id = ?",
            [$subtotal, $total, $orderId]
        );
    }

    /** طلب حساب + طباعة فاتورة للطابعة الحرارية */
    public function printBill(string $id): void
    {
        $orderId = (int) $id;
        $order = Database::query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
        if (!$order) {
            Response::json(['ok' => false, 'error' => 'الطلب غير موجود'], 404);
            return;
        }
        if (in_array($order['status'], ['completed', 'cancelled'], true) || $order['payment_status'] === 'paid') {
            Response::json(['ok' => false, 'error' => 'الطلب مغلق أو مدفوع مسبقاً']);
            return;
        }

        $tableId = (int) ($order['table_id'] ?? 0);
        if ($tableId) {
            Database::query("UPDATE dining_tables SET status = 'bill_requested' WHERE id = ?", [$tableId]);
            Helpers::queueSync('dining_tables', $tableId, 'update', ['status' => 'bill_requested']);
        }

        Database::query(
            "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,?,?)",
            [$orderId, 'served', Auth::id()]
        );
        Helpers::audit('طلب حساب', 'orders', $orderId, $order['order_no']);

        Response::json([
            'ok' => true,
            'message' => 'تم طلب الحساب وإرسال أمر الطباعة',
            'receipt_url' => '/orders/' . $orderId . '/receipt',
        ]);
    }

    /** إتمام حساب طاولة: تسجيل الدفع، الإيراد، وإغلاق الجلسة */
    public function settlePayment(string $id): void
    {
        $orderId = (int) $id;
        $order = Database::query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
        if (!$order) {
            Response::json(['ok' => false, 'error' => 'الطلب غير موجود'], 404);
            return;
        }
        if (in_array($order['status'], ['completed', 'cancelled'], true) || $order['payment_status'] === 'paid') {
            Response::json(['ok' => false, 'error' => 'الطلب مدفوع مسبقاً']);
            return;
        }

        $tableId = (int) ($order['table_id'] ?? 0);
        $total = (float) $order['total'];
        $paidSoFar = (float) Database::query(
            "SELECT COALESCE(SUM(amount),0) AS s FROM order_payments WHERE order_id = ?",
            [$orderId]
        )->fetchColumn();
        $remaining = max(0.0, $total - $paidSoFar);

        if ($remaining <= 0.0001) {
            Database::query(
                "UPDATE orders SET status = 'completed', payment_status = 'paid', completed_at = NOW() WHERE id = ?",
                [$orderId]
            );
            if ($tableId) {
                Database::query("UPDATE dining_tables SET status = 'cleaning' WHERE id = ?", [$tableId]);
                Helpers::queueSync('dining_tables', $tableId, 'update', ['status' => 'cleaning']);
            }
            Helpers::queueSync('orders', $orderId, 'update', [
                'status' => 'completed', 'payment_status' => 'paid', 'completed_at' => date('Y-m-d H:i:s'),
            ]);
            Helpers::audit('إتمام حساب', 'orders', $orderId, $order['order_no']);
            Response::json(['ok' => true, 'message' => 'تم إتمام الحساب', 'redirect' => '/captain']);
            return;
        }

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO order_payments (order_id, method, amount, received_by) VALUES (?,?,?,?)",
                [$orderId, 'cash', $remaining, Auth::id()]
            );
            $paymentId = (int) Database::lastInsertId();

            Database::query(
                "UPDATE orders SET status = 'completed', payment_status = 'paid', completed_at = NOW() WHERE id = ?",
                [$orderId]
            );

            Database::query(
                "INSERT INTO cash_transactions (register_id, trx_type, amount, category, reference, created_by)
                 VALUES (1,'in',?,'مبيعات',?,?)",
                [$remaining, $order['order_no'], Auth::id()]
            );
            Database::query("UPDATE cash_registers SET balance = balance + ? WHERE id = 1", [$remaining]);

            $grossBase = (float)$order['subtotal'] + (float)($order['delivery_fee'] ?? 0);
            $orderTotal = max(0.01, (float)$order['total']);
            $grossPortion = $remaining * ($grossBase / $orderTotal);
            $discountPortion = $remaining * ((float)$order['discount'] / $orderTotal);
            AccountingService::postRevenue($orderId, $grossPortion, $discountPortion, 'نقدي');

            if ($tableId) {
                Database::query("UPDATE dining_tables SET status = 'cleaning' WHERE id = ?", [$tableId]);
            }

            Database::query(
                "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,?,?)",
                [$orderId, 'completed', Auth::id()]
            );

            Database::commit();

            Helpers::queueSync('order_payments', $paymentId, 'insert', [
                'order_id' => $orderId, 'method' => 'cash', 'amount' => $remaining,
            ]);
            Helpers::queueSync('orders', $orderId, 'update', [
                'status' => 'completed', 'payment_status' => 'paid', 'completed_at' => date('Y-m-d H:i:s'),
            ]);
            if ($tableId) {
                Helpers::queueSync('dining_tables', $tableId, 'update', ['status' => 'cleaning']);
            }
            Helpers::audit('إتمام حساب', 'orders', $orderId, $order['order_no'] . ' - ' . $remaining);

            Response::json([
                'ok' => true,
                'message' => 'تم إتمام الحساب وتسجيل الإيراد',
                'redirect' => '/captain',
            ]);
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

}
