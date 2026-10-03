<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, Settings, AccountingService, ExportHelper};

class OrderController extends Controller
{
    private function fetchOrders(string $status, string $type, string $from, string $to, int $limit = 200): array
    {
        $where  = "1=1";
        $params = [];
        if ($status) { $where .= " AND o.status = ?"; $params[] = $status; }
        if ($type)   { $where .= " AND o.order_type = ?"; $params[] = $type; }
        if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where .= " AND DATE(o.created_at) >= ?"; $params[] = $from;
        }
        if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where .= " AND DATE(o.created_at) <= ?"; $params[] = $to;
        }

        $sql = "SELECT o.*, t.name AS table_name, u.full_name AS cashier
                FROM orders o
                LEFT JOIN dining_tables t ON t.id = o.table_id
                LEFT JOIN users u ON u.id = o.created_by
                WHERE $where ORDER BY o.id DESC";
        if ($limit > 0) {
            $sql .= " LIMIT $limit";
        }
        return Database::query($sql, $params)->fetchAll();
    }

    public function index(): void
    {
        $status = (string) $this->input('status');
        $type   = (string) $this->input('type');
        $from   = (string) $this->input('from');
        $to     = (string) $this->input('to');

        $orders = $this->fetchOrders($status, $type, $from, $to);

        $this->view('sales/orders', [
            'title'  => 'الطلبات',
            'orders' => $orders,
            'status' => $status,
            'type'   => $type,
            'from'   => $from,
            'to'     => $to,
        ]);
    }

    public function export(): void
    {
        $status = (string) $this->input('status');
        $type   = (string) $this->input('type');
        $from   = (string) $this->input('from');
        $to     = (string) $this->input('to');
        $format = (string) $this->input('format', 'excel');

        $orders = $this->fetchOrders($status, $type, $from, $to, 0);

        $statusLabels = ['pending'=>'معلق','confirmed'=>'مؤكد','preparing'=>'قيد التحضير','ready'=>'جاهز',
                         'served'=>'مقدم','delivering'=>'قيد التوصيل','completed'=>'مكتمل','cancelled'=>'ملغي'];
        $typeLabels   = ['dine_in'=>'صالة','takeaway'=>'سفري','delivery'=>'توصيل','online'=>'إلكتروني'];
        $paymentLabels = ['unpaid'=>'غير مدفوع','partial'=>'جزئي','paid'=>'مدفوع','refunded'=>'مسترجع'];

        $headers = ['رقم الطلب', 'النوع', 'الطاولة', 'الحالة', 'الإجمالي', 'حالة الدفع', 'الكاشير', 'الوقت'];
        $rows = [];
        foreach ($orders as $o) {
            $rows[] = [
                $o['order_no'] ?? '',
                $typeLabels[$o['order_type']] ?? ($o['order_type'] ?? ''),
                $o['table_name'] ? 'طاولة ' . $o['table_name'] : '-',
                $statusLabels[$o['status']] ?? ($o['status'] ?? ''),
                (float) ($o['total'] ?? 0),
                $paymentLabels[$o['payment_status']] ?? ($o['payment_status'] ?? ''),
                $o['cashier'] ?? '-',
                $o['created_at'] ?? '',
            ];
        }

        $title = 'تقرير الطلبات';
        $period = '';
        if ($from && $to) { $period = "من $from إلى $to"; }
        elseif ($from) { $period = "من $from"; }
        elseif ($to) { $period = "حتى $to"; }

        if ($format === 'excel') {
            ExportHelper::excel('orders_' . date('Ymd_His') . '.xlsx', [[
                'title' => $title,
                'report_title' => $title,
                'period' => $period,
                'headers' => $headers,
                'rows' => $rows,
            ]]);
        }
        ExportHelper::pdf('orders_' . date('Ymd_His') . '.pdf', [
            'title' => $title,
            'period' => $period,
            'headers' => $headers,
            'rows' => $rows,
        ]);
    }

    public function show(string $id): void
    {
        $order = Database::query(
            "SELECT o.*, t.name AS table_name, t.status AS table_status, u.full_name AS cashier, at.name AS app_type_name
             FROM orders o
             LEFT JOIN dining_tables t ON t.id = o.table_id
             LEFT JOIN users u ON u.id = o.created_by
             LEFT JOIN app_types at ON at.id = o.app_type_id
             WHERE o.id = ?", [(int)$id]
        )->fetch();
        if (!$order) { Response::abort(404, 'الطلب غير موجود'); }

        $items = Database::query(
            "SELECT oi.*, mi.name, ks.name AS kitchen_section_name FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             LEFT JOIN kitchen_sections ks ON ks.id = oi.kitchen_section_id
             WHERE oi.order_id = ?", [(int)$id]
        )->fetchAll();
        $log = Database::query(
            "SELECT l.*, u.full_name FROM order_status_log l
             LEFT JOIN users u ON u.id = l.changed_by WHERE l.order_id = ? ORDER BY l.id", [(int)$id]
        )->fetchAll();
        $payments = Database::query(
            "SELECT * FROM order_payments WHERE order_id = ?", [(int)$id]
        )->fetchAll();

        $tables = Database::query(
            "SELECT t.id, t.name, t.floor, t.hall, t.status AS table_status,
               (SELECT o.order_no FROM orders o
                WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.id DESC LIMIT 1) AS current_order
             FROM dining_tables t
             WHERE t.id != ? ORDER BY t.floor, t.hall, t.name",
            [(int)($order['table_id'] ?? 0)]
        )->fetchAll();

        $this->view('sales/order_show', ['title' => 'الطلب ' . $order['order_no'],
            'order' => $order, 'items' => $items, 'log' => $log, 'payments' => $payments, 'tables' => $tables]);
    }

    /** عرض فاتورة مصممة للطباعة على طابعات حرارية */
    public function receipt(string $id): void
    {
        $order = Database::query(
            "SELECT o.*, t.name AS table_name, u.full_name AS cashier, b.name AS branch_name
             FROM orders o
             LEFT JOIN dining_tables t ON t.id = o.table_id
             LEFT JOIN users u ON u.id = o.created_by
             LEFT JOIN branches b ON b.id = o.branch_id WHERE o.id = ?", [(int)$id]
        )->fetch();
        if (!$order) { Response::abort(404, 'الطلب غير موجود'); }

        $items = Database::query(
            "SELECT oi.*, mi.name, ks.name AS kitchen_section_name, pk.name AS preparation_kitchen_name FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             LEFT JOIN kitchen_sections ks ON ks.id = oi.kitchen_section_id
             LEFT JOIN preparation_kitchens pk ON pk.id = oi.preparation_kitchen_id
             WHERE oi.order_id = ? ORDER BY oi.id", [(int)$id]
        )->fetchAll();
        $payments = Database::query(
            "SELECT * FROM order_payments WHERE order_id = ?", [(int)$id]
        )->fetchAll();

        $this->viewBare('sales/receipt', [
            'order'    => $order,
            'items'    => $items,
            'payments' => $payments,
            'settings' => Settings::printer(),
        ]);
    }

    public function updateStatus(string $id): void
    {
        $status = (string)$this->input('status');
        $allowed = ['pending','confirmed','preparing','ready','served','delivering','completed'];
        if (!in_array($status, $allowed, true)) { Response::abort(422, 'حالة غير صالحة'); }

        Database::query("UPDATE orders SET status = ?, completed_at = IF(?='completed', NOW(), completed_at) WHERE id = ?",
            [$status, $status, (int)$id]);
        Database::query("INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,?,?)",
            [(int)$id, $status, Auth::id()]);
        Helpers::queueSync('orders', (int)$id, 'update', ['status' => $status]);

        if ($status === 'completed') {
            Database::query(
                "UPDATE dining_tables SET status='available'
                 WHERE id = (SELECT table_id FROM orders WHERE id = ?)", [(int)$id]);

            // إثبات إيراد المبيعات المتبقية على حساب التطبيق للطلبات الإلكترونية
            $order = Database::query(
                "SELECT order_type, app_type_id, subtotal, discount, delivery_fee, total FROM orders WHERE id = ?", [(int)$id]
            )->fetch();
            if ($order && $order['order_type'] === 'online' && !empty($order['app_type_id'])) {
                $paid = (float)Database::query(
                    "SELECT COALESCE(SUM(amount),0) AS s FROM order_payments WHERE order_id = ?", [(int)$id]
                )->fetchColumn();
                $remaining = max(0, (float)$order['total'] - $paid);
                if ($remaining > 0) {
                    $grossBase = (float)$order['subtotal'] + (float)($order['delivery_fee'] ?? 0);
                    $total = max(0.01, (float)$order['total']);
                    $grossRemaining = $remaining * ($grossBase / $total);
                    $discountRemaining = $remaining * ((float)$order['discount'] / $total);
                    AccountingService::postAppRevenue((int)$id, $grossRemaining, $discountRemaining);
                }
            }
        }
        $this->flash('success', 'تم تحديث حالة الطلب');
        Response::redirect('/orders/' . (int)$id);
    }

    public function cancel(string $id): void
    {
        Database::query("UPDATE orders SET status='cancelled' WHERE id = ?", [(int)$id]);
        AccountingService::reverseCogsItems((int)$id, []); // عكس كل التكلفة
        AccountingService::reverseAllRevenue((int)$id); // عكس إيرادات المبيعات
        Database::query("INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,'cancelled',?)",
            [(int)$id, Auth::id()]);
        Database::query(
            "UPDATE dining_tables SET status='available'
             WHERE id = (SELECT table_id FROM orders WHERE id = ?)", [(int)$id]);
        Helpers::queueSync('orders', (int)$id, 'update', ['status' => 'cancelled']);
        Helpers::audit('إلغاء طلب', 'orders', (int)$id, (string)$this->input('reason'));
        $this->flash('success', 'تم إلغاء الطلب');
        Response::redirect('/orders/' . (int)$id);
    }

    public function returnItems(string $id): void
    {
        $orderId = (int)$id;
        $itemId  = (int)$this->input('order_item_id');
        $qty     = (float)$this->input('qty', 1);
        $reason  = (string)$this->input('reason');

        $order = Database::query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
        if (!$order || in_array($order['status'], ['cancelled'])) {
            $this->flash('danger', 'الطلب غير قابل للتعديل');
            Response::redirect('/orders/' . $orderId);
        }

        $item = Database::query(
            "SELECT oi.*, mi.name FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             WHERE oi.id = ? AND oi.order_id = ?",
            [$itemId, $orderId]
        )->fetch();
        if (!$item) {
            $this->flash('danger', 'صنف الطلب غير موجود');
            Response::redirect('/orders/' . $orderId);
        }

        $itemQty = (float)$item['qty'];
        if ($qty <= 0 || $qty > $itemQty) {
            $this->flash('danger', 'الكمية المرتجعة يجب أن تكون بين 0.5 و ' . $itemQty);
            Response::redirect('/orders/' . $orderId);
        }

        $amount = (float)$item['unit_price'] * $qty;

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO order_returns (order_id, order_item_id, qty, amount, reason, created_by)
                 VALUES (?,?,?,?,?,?)",
                [$orderId, $itemId, $qty, $amount, $reason, Auth::id()]
            );
            $returnId = (int)Database::lastInsertId();

            $newQty   = $itemQty - $qty;
            $newTotal = $newQty * (float)$item['unit_price'];
            Database::query(
                "UPDATE order_items SET qty = ?, total = ?, kitchen_status = ? WHERE id = ?",
                [$newQty, $newTotal, $newQty <= 0 ? 'cancelled' : $item['kitchen_status'], $itemId]
            );

            $this->recalculateOrder($orderId);

            $paidAfter = (float)Database::query(
                "SELECT COALESCE(SUM(amount),0) AS s FROM order_payments WHERE order_id = ?",
                [$orderId]
            )->fetch()['s'];
            $newOrder = Database::query("SELECT total FROM orders WHERE id = ?", [$orderId])->fetch();
            $newTotalOrder = (float)$newOrder['total'];
            $paymentStatus = $paidAfter >= $newTotalOrder ? 'paid' : ($paidAfter > 0 ? 'partial' : 'unpaid');
            Database::query("UPDATE orders SET payment_status = ? WHERE id = ?", [$paymentStatus, $orderId]);

            $discountPortion = 0;
            if ((float)$order['subtotal'] > 0) {
                $discountPortion = $amount * ((float)$order['discount'] / (float)$order['subtotal']);
            }
            AccountingService::reverseCogsItems($orderId, [$itemId], [$itemId => $qty]);
            AccountingService::reduceRevenue($orderId, $amount, $discountPortion);

            $alreadyRefunded = (float)Database::query(
                "SELECT COALESCE(SUM(amount),0) AS s FROM cash_transactions
                 WHERE reference = ? AND trx_type = 'out'",
                ['RET-' . $orderId]
            )->fetch()['s'];
            $shouldRefund = max(0, $paidAfter - $newTotalOrder);
            $refundAmount = max(0, $shouldRefund - $alreadyRefunded);
            if ($refundAmount > 0) {
                Database::query(
                    "INSERT INTO cash_transactions (register_id, trx_type, amount, category, reference, created_by)
                     VALUES (1,'out',?,'مرتجعات',?,?)",
                    [$refundAmount, 'RET-' . $orderId, Auth::id()]
                );
                Database::query("UPDATE cash_registers SET balance = balance - ? WHERE id = 1", [$refundAmount]);
            }

            Helpers::queueSync('order_returns', $returnId, 'insert', [
                'order_id' => $orderId, 'order_item_id' => $itemId, 'qty' => $qty,
                'amount' => $amount, 'reason' => $reason,
            ]);
            Helpers::audit('مرتجع', 'orders', $orderId, ($item['name'] ?: 'صنف') . ' - ' . $reason);

            Database::commit();
            $this->flash('success', 'تم تسجيل المرتجع: ' . ($item['name'] ?: 'صنف') . ' بكمية ' . $qty . ' بمبلغ ' . Helpers::money($amount));
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ في تسجيل المرتجع: ' . $e->getMessage());
        }
        Response::redirect('/orders/' . $orderId);
    }

    public function pay(string $id): void
    {
        $amount = (float)$this->input('amount');
        $method = (string)$this->input('method', 'cash');
        if ($amount <= 0) { Response::abort(422, 'مبلغ غير صالح'); }

        $order = Database::query("SELECT * FROM orders WHERE id = ?", [(int)$id])->fetch();
        Database::query("INSERT INTO order_payments (order_id, method, amount, received_by) VALUES (?,?,?,?)",
            [(int)$id, $method, $amount, Auth::id()]);
        Helpers::queueSync('order_payments', (int)Database::lastInsertId(), 'insert', [
            'order_id' => (int)$id, 'method' => $method, 'amount' => $amount,
        ]);

        $paid = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) AS s FROM order_payments WHERE order_id = ?", [(int)$id]
        )->fetchColumn();
        $status = $paid >= (float)$order['total'] ? 'paid' : 'partial';
        Database::query("UPDATE orders SET payment_status = ? WHERE id = ?", [$status, (int)$id]);
        Database::query(
            "INSERT INTO cash_transactions (register_id, trx_type, amount, category, reference, created_by)
             VALUES (1,'in',?,'مبيعات',?,?)",
            [$amount, $order['order_no'], Auth::id()]);
        Database::query("UPDATE cash_registers SET balance = balance + ? WHERE id = 1", [$amount]);

        $grossBase = (float)$order['subtotal'] + (float)($order['delivery_fee'] ?? 0);
        $total = max(0.01, (float)$order['total']);
        $grossPortion = $amount * ($grossBase / $total);
        $discountPortion = $amount * ((float)$order['discount'] / $total);
        AccountingService::postRevenue((int)$id, $grossPortion, $discountPortion, $method);

        $this->flash('success', 'تم تسجيل الدفعة');
        Response::redirect('/orders/' . (int)$id);
    }

    /** تقسيم الطلب: إنشاء طلب جديد من الأصناف المحددة */
    public function splitOrder(string $id): void
    {
        $orderId = (int)$id;
        $order = Database::query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
        if (!$order || in_array($order['status'], ['completed','cancelled'])) {
            $this->flash('danger', 'الطلب غير قابل للتقسيم');
            Response::redirect('/orders/' . $orderId);
        }

        $selected = array_map('intval', (array)($_POST['item_ids'] ?? []));
        if (empty($selected)) {
            $this->flash('danger', 'اختر صنفاً واحداً على الأقل');
            Response::redirect('/orders/' . $orderId);
        }

        // تأكد من أن لم يتم تحديد كل الأصناف
        $itemCount = (int)Database::query(
            "SELECT COUNT(*) AS c FROM order_items WHERE order_id = ?",
            [$orderId]
        )->fetch()['c'];
        if (count($selected) >= $itemCount) {
            $this->flash('danger', 'لا يمكن تقسيم كل الأصناف، اختر بعضها فقط');
            Response::redirect('/orders/' . $orderId);
        }

        Database::beginTransaction();
        try {
            $newOrderNo = Helpers::docNumber('ORD', 'orders', 'order_no');
            Database::query(
                "INSERT INTO orders (order_no, branch_id, order_type, table_id, customer_id, status, subtotal, discount, tax, total, payment_status, created_by)
                 VALUES (?,?,?,?,?,?,0,0,0,0,'unpaid',?)",
                [
                    $newOrderNo,
                    $order['branch_id'],
                    $order['order_type'],
                    $order['table_id'],
                    $order['customer_id'],
                    $order['status'],
                    Auth::id(),
                ]
            );
            $newOrderId = (int)Database::lastInsertId();

            // نقل الأصناف المحددة إلى الطلب الجديد
            Database::query(
                "UPDATE order_items SET order_id = ? WHERE id IN (" . implode(',', array_fill(0, count($selected), '?')) . ") AND order_id = ?",
                array_merge([$newOrderId], $selected, [$orderId])
            );

            $this->recalculateOrder($orderId);
            $this->recalculateOrder($newOrderId);

            Database::query(
                "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,?,?)",
                [$newOrderId, $order['status'], Auth::id()]
            );
            Helpers::audit('تقسيم طلب', 'orders', $newOrderId, "من الطلب #" . $order['order_no']);
            Database::commit();

            $this->flash('success', "تم تقسيم الطلب. الطلب الجديد: $newOrderNo");
            Response::redirect('/orders/' . $newOrderId);
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ في تقسيم الطلب: ' . $e->getMessage());
            Response::redirect('/orders/' . $orderId);
        }
    }

    /** إلغاء صنف من الطلب */
    public function cancelItem(string $id): void
    {
        $orderId = (int)$id;
        $itemId = (int)$this->input('order_item_id');
        $reason = (string)$this->input('reason');

        $order = Database::query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
        if (!$order || in_array($order['status'], ['completed','cancelled'])) {
            $this->flash('danger', 'الطلب غير قابل للتعديل');
            Response::redirect('/orders/' . $orderId);
        }

        $item = Database::query(
            "SELECT oi.*, mi.name FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             WHERE oi.id = ? AND oi.order_id = ?",
            [$itemId, $orderId]
        )->fetch();
        if (!$item) {
            $this->flash('danger', 'الصنف غير موجود');
            Response::redirect('/orders/' . $orderId);
        }

        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE order_items SET kitchen_status = 'cancelled', qty = 0, total = 0 WHERE id = ?",
                [$itemId]
            );
            $this->recalculateOrder($orderId);
            Helpers::audit('إلغاء صنف من طلب', 'orders', $orderId, ($item['name'] ?: 'صنف') . ' - ' . $reason);
            Database::commit();
            $this->flash('success', "تم إلغاء الصنف: {$item['name']}");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/orders/' . $orderId);
    }

    /** نقل صنف من طاولة لأخرى */
    public function moveItem(string $id): void
    {
        $orderId = (int)$id;
        $itemId = (int)$this->input('order_item_id');
        $targetTableId = (int)$this->input('target_table_id');

        $order = Database::query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
        if (!$order || in_array($order['status'], ['completed','cancelled'])) {
            $this->flash('danger', 'الطلب غير قابل للنقل');
            Response::redirect('/orders/' . $orderId);
        }

        $item = Database::query(
            "SELECT oi.*, mi.name FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             WHERE oi.id = ? AND oi.order_id = ?",
            [$itemId, $orderId]
        )->fetch();
        if (!$item) {
            $this->flash('danger', 'الصنف غير موجود');
            Response::redirect('/orders/' . $orderId);
        }

        if ($targetTableId <= 0) {
            $this->flash('danger', 'اختر الطاولة الهدف');
            Response::redirect('/orders/' . $orderId);
        }

        Database::beginTransaction();
        try {
            // البحث عن طلب نشط للطاولة الهدف
            $targetOrder = Database::query(
                "SELECT * FROM orders WHERE table_id = ? AND status NOT IN ('completed','cancelled') ORDER BY id DESC LIMIT 1",
                [$targetTableId]
            )->fetch();

            if (!$targetOrder) {
                $newOrderNo = Helpers::docNumber('ORD', 'orders', 'order_no');
                Database::query(
                    "INSERT INTO orders (order_no, branch_id, order_type, table_id, status, subtotal, discount, tax, total, payment_status, created_by)
                     VALUES (?,?,?,?,?,0,0,0,0,'unpaid',?)",
                    [
                        $newOrderNo,
                        $order['branch_id'],
                        'dine_in',
                        $targetTableId,
                        'confirmed',
                        Auth::id(),
                    ]
                );
                $targetOrderId = (int)Database::lastInsertId();
                Database::query(
                    "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,?,?)",
                    [$targetOrderId, 'confirmed', Auth::id()]
                );
            } else {
                $targetOrderId = (int)$targetOrder['id'];
            }

            Database::query(
                "UPDATE order_items SET order_id = ? WHERE id = ?",
                [$targetOrderId, $itemId]
            );

            $this->recalculateOrder($orderId);
            $this->recalculateOrder($targetOrderId);

            // إذا كانت الطاولة المصدرة تابعة للطلب القديم ولم يبقً له أصناف، تحريرها
            $remaining = (int)Database::query(
                "SELECT COUNT(*) AS c FROM order_items WHERE order_id = ? AND kitchen_status != 'cancelled'",
                [$orderId]
            )->fetch()['c'];
            if ($remaining === 0) {
                Database::query("UPDATE orders SET status = 'cancelled' WHERE id = ?", [$orderId]);
                Database::query(
                    "UPDATE dining_tables SET status = 'available' WHERE id = (SELECT table_id FROM orders WHERE id = ?)",
                    [$orderId]
                );
            }

            Helpers::audit('نقل صنف لطاولة أخرى', 'orders', $targetOrderId, $item['name'] ?: 'صنف');
            Database::commit();
            $this->flash('success', "تم نقل الصنف: {$item['name']}");
            Response::redirect('/orders/' . $targetOrderId);
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ في نقل الصنف: ' . $e->getMessage());
            Response::redirect('/orders/' . $orderId);
        }
    }

    /** نقل الطلب بأكمله إلى طاولة فارغة أخرى */
    public function moveOrder(string $id): void
    {
        $orderId = (int)$id;
        $targetTableId = (int)$this->input('target_table_id');

        $order = Database::query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
        if (!$order || in_array($order['status'], ['completed','cancelled'])) {
            $this->flash('danger', 'الطلب غير قابل للنقل');
            Response::redirect('/orders/' . $orderId);
        }
        if ($targetTableId <= 0) {
            $this->flash('danger', 'اختر الطاولة الهدف');
            Response::redirect('/orders/' . $orderId);
        }
        if ((int)$order['table_id'] === $targetTableId) {
            $this->flash('danger', 'الطلب موجود على نفس الطاولة');
            Response::redirect('/orders/' . $orderId);
        }

        // الطاولة الهدف يجب أن تكون فارغة
        $busy = Database::query(
            "SELECT 1 FROM orders WHERE table_id = ? AND status NOT IN ('completed','cancelled') LIMIT 1",
            [$targetTableId]
        )->fetch();
        if ($busy) {
            $this->flash('danger', 'الطاولة الهدف مشغولة، اختر طاولة فارغة أو انقل الأصناف بدلاً من نقل الطلب كاملاً');
            Response::redirect('/orders/' . $orderId);
        }

        Database::beginTransaction();
        try {
            $oldTableId = (int)$order['table_id'];

            Database::query("UPDATE orders SET table_id = ? WHERE id = ?", [$targetTableId, $orderId]);

            // تحرير الطاولة القديمة إذا لم يبقَ عليها طلبات مفتوحة
            if ($oldTableId > 0) {
                $stillBusy = Database::query(
                    "SELECT 1 FROM orders WHERE table_id = ? AND status NOT IN ('completed','cancelled') LIMIT 1",
                    [$oldTableId]
                )->fetch();
                if (!$stillBusy) {
                    Database::query("UPDATE dining_tables SET status = 'available' WHERE id = ?", [$oldTableId]);
                }
            }

            // حجز الطاولة الجديدة
            Database::query("UPDATE dining_tables SET status = 'occupied' WHERE id = ?", [$targetTableId]);

            Helpers::audit('نقل طلب كامل لطاولة', 'orders', $orderId, "الطاولة الجديدة: $targetTableId");
            Database::commit();
            $this->flash('success', 'تم نقل الطلب إلى الطاولة الجديدة');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ في نقل الطلب: ' . $e->getMessage());
        }
        Response::redirect('/orders/' . $orderId);
    }

    /** إعادة حساب إجمالي الطلب بعد تعديل الأصناف */
    private function recalculateOrder(int $orderId): void
    {
        $items = Database::query(
            "SELECT COALESCE(SUM(total),0) AS subtotal FROM order_items
             WHERE order_id = ? AND kitchen_status != 'cancelled'",
            [$orderId]
        )->fetch();
        $subtotal = (float)$items['subtotal'];
        $order = Database::query("SELECT discount, delivery_fee FROM orders WHERE id = ?", [$orderId])->fetch();
        $discount     = (float)($order['discount'] ?? 0);
        $deliveryFee  = (float)($order['delivery_fee'] ?? 0);
        $total        = max(0, $subtotal - $discount + $deliveryFee);
        Database::query(
            "UPDATE orders SET subtotal = ?, total = ? WHERE id = ?",
            [$subtotal, $total, $orderId]
        );
    }
}
