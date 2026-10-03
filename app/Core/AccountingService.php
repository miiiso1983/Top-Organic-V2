<?php
namespace App\Core;

use App\Models\ItemPriceHistory;

/**
 * خدمة المحاسبة: قيود يومية، تكاليف، إهلاك، وتكامل مع النظام
 */
class AccountingService
{
    const DEBIT_NORMAL = ['asset', 'expense'];
    const CREDIT_NORMAL = ['liability', 'equity', 'revenue'];

    /** توليد كود حساب فرعي تلقائيً بناءً على كود الأب */
    public static function generateCode(int $parentId): string
    {
        $parent = Database::query("SELECT code FROM accounts WHERE id = ?", [$parentId])->fetch();
        if (!$parent) {
            throw new \RuntimeException('الحساب الأب غير موجود');
        }
        $parentCode = $parent['code'];
        $last = Database::query(
            "SELECT code FROM accounts WHERE parent_id = ? ORDER BY code DESC LIMIT 1",
            [$parentId]
        )->fetch();
        if (!$last) {
            return strlen($parentCode) === 1 ? $parentCode . '000' : $parentCode . '-01';
        }
        $lastCode = $last['code'];
        if (strlen($parentCode) === 1 && preg_match('/^' . $parentCode . '(\d{3})$/', $lastCode, $m)) {
            $next = (int)$m[1] + 10;
            if ($next < 1000) {
                return $parentCode . sprintf('%03d', $next);
            }
        }
        if (preg_match('/^' . preg_quote($parentCode, '/') . '-(\d+)$/', $lastCode, $m)) {
            return $parentCode . '-' . sprintf('%02d', (int)$m[1] + 1);
        }
        $cnt = (int) Database::query("SELECT COUNT(*) AS c FROM accounts WHERE parent_id = ?", [$parentId])->fetch()['c'];
        return $parentCode . '-' . ($cnt + 1);
    }

    /** رصيد حساب حتى تاريخ معين */
    public static function balance(int $accountId, ?string $toDate = null): float
    {
        $account = Database::query("SELECT type, opening_balance FROM accounts WHERE id = ?", [$accountId])->fetch();
        if (!$account) { return 0; }
        $params = [$accountId];
        $sql = "SELECT COALESCE(SUM(l.debit),0) AS dr, COALESCE(SUM(l.credit),0) AS cr
                FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
                WHERE l.account_id = ? AND e.status = 'posted'";
        if ($toDate) { $sql .= " AND e.entry_date <= ?"; $params[] = $toDate; }
        $row = Database::query($sql, $params)->fetch();
        $dr = (float)$row['dr']; $cr = (float)$row['cr'];
        $bal = (float)$account['opening_balance'];
        return in_array($account['type'], self::DEBIT_NORMAL, true) ? $bal + $dr - $cr : $bal + $cr - $dr;
    }

    /** إيجاد قيد يومية موازنة */
    public static function post(array $data): int
    {
        $lines = $data['lines'] ?? [];
        if (count($lines) < 2) {
            throw new \RuntimeException('القيد يحتاج بندين على الأقل');
        }
        $totalDr = array_sum(array_column($lines, 'debit'));
        $totalCr = array_sum(array_column($lines, 'credit'));
        if (abs($totalDr - $totalCr) > 0.001) {
            throw new \RuntimeException('مجموع المدين لا يساوي مجموع الدائن');
        }
        if ($totalDr <= 0) {
            throw new \RuntimeException('القيد فارغ المبالغ');
        }

        $inTrx = Database::connect()->inTransaction();
        if (!$inTrx) { Database::beginTransaction(); }
        try {
            $entryNo = Helpers::docNumber('JV', 'journal_entries', 'entry_no');
            Database::query(
                "INSERT INTO journal_entries (entry_no, entry_date, description, cost_center_id, source_type, source_id, status, created_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$entryNo, $data['entry_date'] ?? date('Y-m-d'), $data['description'] ?? '',
                 $data['cost_center_id'] ?? null, $data['source_type'] ?? null,
                 $data['source_id'] ?? null, 'posted', Auth::id()]
            );
            $entryId = (int) Database::lastInsertId();
            foreach ($lines as $line) {
                if (empty($line['account_id']) || ((float)($line['debit'] ?? 0) == 0 && (float)($line['credit'] ?? 0) == 0)) { continue; }
                Database::query(
                    "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)",
                    [$entryId, (int)$line['account_id'], (float)($line['debit'] ?? 0), (float)($line['credit'] ?? 0), $line['description'] ?? '']
                );
            }
            if (!$inTrx) { Database::commit(); }
            Helpers::audit('قيد محاسبي', 'journal_entries', $entryId, $entryNo);
            return $entryId;
        } catch (\Throwable $e) {
            if (!$inTrx) { Database::rollBack(); }
            throw $e;
        }
    }

    /** عكس قيد مرحلياً */
    public static function reverse(int $entryId, string $reason = ''): ?int
    {
        $entry = Database::query("SELECT * FROM journal_entries WHERE id = ?", [$entryId])->fetch();
        if (!$entry || $entry['status'] !== 'posted') { return null; }
        $lines = Database::query("SELECT account_id, debit, credit FROM journal_lines WHERE entry_id = ?", [$entryId])->fetchAll();
        $rev = [];
        foreach ($lines as $l) {
            $rev[] = ['account_id' => $l['account_id'], 'debit' => (float)$l['credit'], 'credit' => (float)$l['debit']];
        }
        $newId = self::post([
            'entry_date' => date('Y-m-d'),
            'description' => 'عكس ' . $entry['entry_no'] . ($reason ? ' - ' . $reason : ''),
            'cost_center_id' => $entry['cost_center_id'],
            'source_type' => 'reversal',
            'source_id' => $entryId,
            'lines' => $rev,
        ]);
        Database::query("UPDATE journal_entries SET status='reversed' WHERE id = ?", [$entryId]);
        return $newId;
    }

    /** البحث عن معرف الحساب بالكود */
    public static function accountId(string $code): ?int
    {
        $row = Database::query("SELECT id FROM accounts WHERE code = ? AND is_active = 1", [$code])->fetch();
        return $row ? (int)$row['id'] : null;
    }

    /** تكلفة وصفة لصنف منيو في تاريخ معين (أو السعر الحالي) */
    public static function recipeCost(int $menuItemId, float $qty, ?string $date = null): float
    {
        $advanced = false;
        try {
            // محاولـة استخدام الحقول المتقدمة (unit_id + نسب الهدر الثلاثية)
            $rows = Database::query(
                "SELECT ri.qty, ri.item_id,
                        COALESCE(ri.unit_id, i.unit_id) AS unit_id,
                        COALESCE(ri.waste_percent,0) AS w1,
                        COALESCE(ri.department_waste_percent,0) AS w2,
                        COALESCE(ri.output_waste_percent,0) AS w3
                 FROM recipes r
                 JOIN recipe_ingredients ri ON ri.recipe_id = r.id
                 JOIN items i ON i.id = ri.item_id
                 WHERE r.menu_item_id = ?",
                [$menuItemId]
            )->fetchAll();
            $advanced = true;
        } catch (\Throwable $e) {
            // الأعمدة غير متوفّرة بعد (قبل تشغيل 37_recipes_enhanced.sql) — نرجع للاستعلام البسيط
            $rows = Database::query(
                "SELECT ri.qty, ri.item_id FROM recipes r
                 JOIN recipe_ingredients ri ON ri.recipe_id = r.id
                 WHERE r.menu_item_id = ?",
                [$menuItemId]
            )->fetchAll();
        }

        $cost = 0.0;
        foreach ($rows as $r) {
            // توحيد مصدر التسعير عبر CostingEngine (يدعم purchase_prices/cost_history/standard)
            try {
                $uCost = CostingEngine::ingredientUnitCost((int)$r['item_id'], $date, 'moving_average');
            } catch (\Throwable $e) {
                $uCost = 0.0;
            }
            if ($uCost <= 0) {
                // احتياطي: سجل الأسعار التاريخي
                $uCost = ItemPriceHistory::priceAt((int)$r['item_id'], $date);
            }

            $lineQty = (float)$r['qty'];
            if ($advanced) {
                // تحويل إلى الوحدة الأساسية + تطبيق نسب الهدر الثلاثية (مركّب)
                $unitId = (int)($r['unit_id'] ?? 0);
                $base = UnitConversionService::toBaseUnit($unitId > 0 ? $unitId : $unitId, $lineQty);
                $w1 = ((float)($r['w1'] ?? 0)) / 100.0;
                $w2 = ((float)($r['w2'] ?? 0)) / 100.0;
                $w3 = ((float)($r['w3'] ?? 0)) / 100.0;
                $actual = $base * (1 + $w1) * (1 + $w2) * (1 + $w3);
                $cost += $uCost * $actual * $qty;
            } else {
                // منطق قديم: بدون تحويل ولا هدر تفصيلي
                $cost += $uCost * $lineQty * $qty;
            }
        }
        return $cost;
    }

    /** إثبات تكلفة البضاعة المباعة */
    public static function postCogs(int $orderId): void
    {
        $order = Database::query("SELECT order_no, status, created_at FROM orders WHERE id = ?", [$orderId])->fetch();
        if (!$order || $order['status'] === 'cancelled') { return; }
        $orderDate = !empty($order['created_at']) ? date('Y-m-d', strtotime($order['created_at'])) : date('Y-m-d');
        $cogsAcc = self::accountId('5000');
        $invAcc = self::accountId('1100');
        if (!$cogsAcc || !$invAcc) { return; }

        $items = Database::query("SELECT id, menu_item_id, qty FROM order_items WHERE order_id = ?", [$orderId])->fetchAll();
        $totalCost = 0;
        foreach ($items as $it) {
            $cost = self::recipeCost((int)$it['menu_item_id'], (float)$it['qty'], $orderDate);
            if ($cost <= 0) { continue; }
            $totalCost += $cost;
            $recipe = Database::query("SELECT id FROM recipes WHERE menu_item_id = ?", [(int)$it['menu_item_id']])->fetch();
            Database::query(
                "INSERT INTO cogs_entries (order_id, order_item_id, recipe_id, cost) VALUES (?,?,?,?)",
                [$orderId, $it['id'], $recipe['id'] ?? null, $cost]
            );
        }
        if ($totalCost > 0) {
            $entryId = self::post([
                'entry_date' => $orderDate,
                'description' => 'تكلفة البضاعة المباعة - ' . $order['order_no'],
                'source_type' => 'cogs', 'source_id' => $orderId,
                'lines' => [
                    ['account_id' => $cogsAcc, 'debit' => $totalCost, 'credit' => 0],
                    ['account_id' => $invAcc, 'debit' => 0, 'credit' => $totalCost],
                ],
            ]);
            Database::query("UPDATE cogs_entries SET journal_entry_id = ? WHERE order_id = ? AND journal_entry_id IS NULL", [$entryId, $orderId]);
        }
    }

    /** عكس تكلفة بنداً عن أصناف مرتجعة (فارغة = كل الأصناف). يمكن تمرير $returnedQtyMap بكميات مرتجعة للحساب النسبي. */
    public static function reverseCogsItems(int $orderId, array $orderItemIds = [], array $returnedQtyMap = []): void
    {
        $sql = "SELECT ce.*, oi.qty AS original_qty FROM cogs_entries ce
                JOIN order_items oi ON oi.id = ce.order_item_id
                WHERE ce.order_id = ?";
        $params = [$orderId];
        if ($orderItemIds) {
            $in = implode(',', array_fill(0, count($orderItemIds), '?'));
            $sql .= " AND ce.order_item_id IN ($in)";
            $params = array_merge($params, $orderItemIds);
        }
        $items = Database::query($sql, $params)->fetchAll();
        if (empty($items)) { return; }

        $total = 0;
        $fullReturnItemIds = [];
        foreach ($items as $it) {
            $cost = (float)$it['cost'];
            $originalQty = (float)$it['original_qty'];
            $returnedQty = isset($returnedQtyMap[$it['order_item_id']]) ? (float)$returnedQtyMap[$it['order_item_id']] : 0;
            if ($returnedQty > 0 && $originalQty > 0) {
                $ratio = min(1, $returnedQty / $originalQty);
                $cost *= $ratio;
                if ($returnedQty >= $originalQty) {
                    $fullReturnItemIds[$it['order_item_id']] = true;
                }
            }
            $total += $cost;
        }
        if ($total <= 0) { return; }

        $cogsAcc = self::accountId('5000');
        $invAcc = self::accountId('1100');
        if (!$cogsAcc || !$invAcc) { return; }
        $entryId = self::post([
            'entry_date' => date('Y-m-d'),
            'description' => 'عكس تكلفة بضاعة - طلب ' . $orderId,
            'source_type' => 'cogs_return', 'source_id' => $orderId,
            'lines' => [
                ['account_id' => $invAcc, 'debit' => $total, 'credit' => 0],
                ['account_id' => $cogsAcc, 'debit' => 0, 'credit' => $total],
            ],
        ]);

        // تسمية القيد الأصلي معكوسة فقط عند المرتجع الكامل أو عكس كل الأصناف
        $markAllReversed = empty($returnedQtyMap);
        if ($markAllReversed) {
            $origEntryIds = array_filter(array_unique(array_column($items, 'journal_entry_id')));
        } elseif (!empty($fullReturnItemIds)) {
            $origEntryIds = array_filter(array_unique(array_column(
                array_filter($items, fn($it) => isset($fullReturnItemIds[$it['order_item_id']])),
                'journal_entry_id'
            )));
        } else {
            $origEntryIds = [];
        }
        if ($origEntryIds) {
            $in = implode(',', array_fill(0, count($origEntryIds), '?'));
            Database::query("UPDATE journal_entries SET status = 'reversed' WHERE id IN ($in) AND status = 'posted'", $origEntryIds);
        }
    }

    /** إثبات إيراد مبيعات (نقدية) مع خصم */
    public static function postRevenue(int $orderId, float $grossAmount, float $discount = 0, string $method = 'نقدي'): void
    {
        $order = Database::query("SELECT order_no FROM orders WHERE id = ?", [$orderId])->fetch();
        if (!$order || ($grossAmount <= 0 && $discount <= 0)) { return; }
        $cashAcc = self::accountId('1000');
        $revAcc = self::accountId('4000');
        $discAcc = self::accountId('4100');
        if (!$cashAcc || !$revAcc) { return; }
        $lines = [
            ['account_id' => $cashAcc, 'debit' => max(0, $grossAmount - $discount), 'credit' => 0],
            ['account_id' => $revAcc, 'debit' => 0, 'credit' => $grossAmount],
        ];
        if ($discount > 0 && $discAcc) {
            $lines[] = ['account_id' => $discAcc, 'debit' => $discount, 'credit' => 0];
        }
        self::post([
            'entry_date' => date('Y-m-d'),
            'description' => 'إيراد مبيعات - ' . $order['order_no'] . ' (' . $method . ')',
            'source_type' => 'sale', 'source_id' => $orderId,
            'lines' => $lines,
        ]);
    }

    /** إيجاد أو استحضار حساب ذمم للتطبيق */
    public static function ensureAppAccount(int $appTypeId): ?int
    {
        $app = Database::query("SELECT * FROM app_types WHERE id = ?", [$appTypeId])->fetch();
        if (!$app) { return null; }
        if (!empty($app['account_id'])) { return (int)$app['account_id']; }

        $parentId = self::accountId('1110'); // الذمم المدينة
        if (!$parentId) { $parentId = self::accountId('1'); } // الأصول
        if (!$parentId) { return null; }

        $code = self::generateCode($parentId);
        Database::query(
            "INSERT INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
             VALUES (?, ?, 'asset', ?, 'receivables', 1, 0)",
            [$code, $app['name'], $parentId]
        );
        $accountId = (int)Database::lastInsertId();
        Database::query("UPDATE app_types SET account_id = ? WHERE id = ?", [$accountId, $appTypeId]);
        return $accountId;
    }

    /** إثبات إيراد مبيعات تطبيق (ذمم) مع خصم */
    public static function postAppRevenue(int $orderId, float $grossAmount, float $discount = 0): void
    {
        $order = Database::query(
            "SELECT order_no, app_type_id FROM orders WHERE id = ?",
            [$orderId]
        )->fetch();
        if (!$order || ($grossAmount <= 0 && $discount <= 0) || empty($order['app_type_id'])) { return; }

        $appAccId = self::ensureAppAccount((int)$order['app_type_id']);
        $revAccId = self::accountId('4000');
        $discAcc = self::accountId('4100');
        if (!$appAccId || !$revAccId) { return; }

        $lines = [
            ['account_id' => $appAccId, 'debit' => max(0, $grossAmount - $discount), 'credit' => 0],
            ['account_id' => $revAccId, 'debit' => 0, 'credit' => $grossAmount],
        ];
        if ($discount > 0 && $discAcc) {
            $lines[] = ['account_id' => $discAcc, 'debit' => $discount, 'credit' => 0];
        }
        self::post([
            'entry_date' => date('Y-m-d'),
            'description' => 'إيراد مبيعات - ' . $order['order_no'] . ' (تطبيق)',
            'source_type' => 'sale', 'source_id' => $orderId,
            'lines' => $lines,
        ]);
    }

    /** تكبيد إيراد منخفض للمرتجعات مع خصم */
    public static function reduceRevenue(int $orderId, float $grossAmount, float $discount = 0): void
    {
        if ($grossAmount <= 0 && $discount <= 0) { return; }
        $order = Database::query("SELECT order_no FROM orders WHERE id = ?", [$orderId])->fetch();
        $cashAcc = self::accountId('1000');
        $revAcc = self::accountId('4000');
        $discAcc = self::accountId('4100');
        if (!$cashAcc || !$revAcc) { return; }
        $lines = [
            ['account_id' => $revAcc, 'debit' => $grossAmount, 'credit' => 0],
            ['account_id' => $cashAcc, 'debit' => 0, 'credit' => max(0, $grossAmount - $discount)],
        ];
        if ($discount > 0 && $discAcc) {
            $lines[] = ['account_id' => $discAcc, 'debit' => 0, 'credit' => $discount];
        }
        self::post([
            'entry_date' => date('Y-m-d'),
            'description' => 'مرتجع مبيعات - ' . ($order['order_no'] ?? $orderId),
            'source_type' => 'sale_return', 'source_id' => $orderId,
            'lines' => $lines,
        ]);
    }

    /** عكس كافة إيرادات المبيعات للطلب (للإلغاء) */
    public static function reverseAllRevenue(int $orderId): void
    {
        $entries = Database::query("SELECT id FROM journal_entries WHERE source_type='sale' AND source_id = ? AND status='posted'", [$orderId])->fetchAll();
        foreach ($entries as $e) {
            self::reverse((int)$e['id'], 'إلغاء طلب');
        }
    }

    /** إثبات استلام مشتريات */
    public static function postPurchaseReceipt(int $receiptId): void
    {
        $receipt = Database::query(
            "SELECT gr.*, po.po_no FROM goods_receipts gr
             JOIN purchase_orders po ON po.id = gr.po_id WHERE gr.id = ?",
            [$receiptId]
        )->fetch();
        if (!$receipt) { return; }
        $items = Database::query("SELECT qty, unit_price FROM goods_receipt_items WHERE receipt_id = ?", [$receiptId])->fetchAll();
        $total = 0;
        foreach ($items as $i) { $total += (float)$i['qty'] * (float)$i['unit_price']; }
        if ($total <= 0) { return; }
        $invAcc = self::accountId('1100');
        $payAcc = self::accountId('2000');
        if (!$invAcc || !$payAcc) { return; }
        self::post([
            'entry_date' => date('Y-m-d', strtotime($receipt['created_at'])),
            'description' => 'استلام مشتريات - ' . $receipt['receipt_no'] . ' / ' . $receipt['po_no'],
            'source_type' => 'purchase_receipt', 'source_id' => $receiptId,
            'lines' => [
                ['account_id' => $invAcc, 'debit' => $total, 'credit' => 0],
                ['account_id' => $payAcc, 'debit' => 0, 'credit' => $total],
            ],
        ]);
    }

    /** إثبات هدر/نفايات مخزونية */
    public static function postWaste(int $wasteLogId): void
    {
        $w = Database::query(
            "SELECT w.*, i.name AS item_name, i.cost_price FROM waste_log w JOIN items i ON i.id = w.item_id WHERE w.id = ?",
            [$wasteLogId]
        )->fetch();
        if (!$w) { return; }
        $amount = (float)$w['qty'] * (float)$w['cost_price'];
        if ($amount <= 0) { return; }
        $invAcc = self::accountId('1100');
        $expAcc = self::accountId('5200');
        if (!$invAcc || !$expAcc) { return; }
        self::post([
            'entry_date' => date('Y-m-d', strtotime($w['created_at'] ?? 'now')),
            'description' => 'هدر مخزوني - ' . ($w['item_name'] ?? '') . ' (' . $w['qty'] . ')',
            'source_type' => 'waste', 'source_id' => $wasteLogId,
            'lines' => [
                ['account_id' => $expAcc, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $invAcc, 'debit' => 0, 'credit' => $amount],
            ],
        ]);
    }

    /** إثبات استهلاك مواد الإنتاج */
    public static function postProductionConsumption(int $productionOrderId): void
    {
        $po = Database::query(
            "SELECT p.*, i.name AS item_name FROM production_orders p JOIN items i ON i.id = p.item_id WHERE p.id = ?",
            [$productionOrderId]
        )->fetch();
        if (!$po || $po['status'] === 'cancelled') { return; }
        $items = Database::query(
            "SELECT pc.*, i.name AS ingredient_name, i.cost_price FROM production_consumption pc JOIN items i ON i.id = pc.item_id WHERE pc.production_id = ?",
            [$productionOrderId]
        )->fetchAll();
        if (!$items) { return; }
        $total = 0;
        foreach ($items as $it) { $total += (float)$it['qty'] * (float)$it['cost_price']; }
        if ($total <= 0) { return; }
        $cogsAcc = self::accountId('5000');
        $invAcc = self::accountId('1100');
        if (!$cogsAcc || !$invAcc) { return; }
        self::post([
            'entry_date' => $po['production_date'] ?: date('Y-m-d'),
            'description' => 'استهلاك مواد إنتاج - ' . ($po['production_no'] ?? $productionOrderId) . ' / ' . ($po['item_name'] ?? ''),
            'source_type' => 'production_consumption', 'source_id' => $productionOrderId,
            'lines' => [
                ['account_id' => $cogsAcc, 'debit' => $total, 'credit' => 0],
                ['account_id' => $invAcc, 'debit' => 0, 'credit' => $total],
            ],
        ]);
    }

    /** إثبات تسويات المطابقة البنكية (رسوم/فوائد/تصحيحات) */
    public static function postReconciliationAdjustments(int $reconciliationId): void
    {
        $recon = Database::query("SELECT * FROM bank_reconciliations WHERE id = ?", [$reconciliationId])->fetch();
        if (!$recon) { return; }
        $bankAcc = self::accountId('1010');
        $expAcc = self::accountId('5200');
        $incAcc = self::accountId('4200');
        if (!$bankAcc) { return; }
        $items = Database::query(
            "SELECT * FROM bank_reconciliation_items WHERE reconciliation_id = ? AND is_resolved = 1",
            [$reconciliationId]
        )->fetchAll();
        $lines = [];
        foreach ($items as $it) {
            $type = $it['trx_type'];
            $amount = (float)$it['amount'];
            if ($amount <= 0) { continue; }
            if ($type === 'fee' && $expAcc) {
                $lines[] = ['account_id' => $expAcc, 'debit' => $amount, 'credit' => 0];
                $lines[] = ['account_id' => $bankAcc, 'debit' => 0, 'credit' => $amount];
            } elseif ($type === 'interest' && $incAcc) {
                $lines[] = ['account_id' => $bankAcc, 'debit' => $amount, 'credit' => 0];
                $lines[] = ['account_id' => $incAcc, 'debit' => 0, 'credit' => $amount];
            } elseif ($type === 'correction') {
                if ($amount > 0 && $incAcc) {
                    $lines[] = ['account_id' => $bankAcc, 'debit' => $amount, 'credit' => 0];
                    $lines[] = ['account_id' => $incAcc, 'debit' => 0, 'credit' => $amount];
                } elseif ($amount < 0 && $expAcc) {
                    $amt = abs($amount);
                    $lines[] = ['account_id' => $expAcc, 'debit' => $amt, 'credit' => 0];
                    $lines[] = ['account_id' => $bankAcc, 'debit' => 0, 'credit' => $amt];
                }
            }
        }
        if (count($lines) < 2) { return; }
        self::post([
            'entry_date' => $recon['statement_date'] ?: date('Y-m-d'),
            'description' => 'تسويات مطابقة بنكية - ' . ($recon['statement_date'] ?? '') . ' (BK-' . $reconciliationId . ')',
            'source_type' => 'bank_reconciliation', 'source_id' => $reconciliationId,
            'lines' => $lines,
        ]);
    }

    /** إثبات تسوية جرد (زيادة أو نقصان) */
    public static function postStocktake(int $stocktakeId): void
    {
        $st = Database::query(
            "SELECT st.*, w.name AS warehouse_name FROM stocktakes st JOIN warehouses w ON w.id = st.warehouse_id WHERE st.id = ?",
            [$stocktakeId]
        )->fetch();
        if (!$st) { return; }
        $items = Database::query(
            "SELECT si.*, i.cost_price FROM stocktake_items si JOIN items i ON i.id = si.item_id WHERE si.stocktake_id = ?",
            [$stocktakeId]
        )->fetchAll();
        $surplus = 0; $shortage = 0;
        foreach ($items as $i) {
            $diff = (float)$i['counted_qty'] - (float)$i['system_qty'];
            $cost = abs($diff) * (float)$i['cost_price'];
            if ($diff > 0) { $surplus += $cost; }
            elseif ($diff < 0) { $shortage += $cost; }
        }
        if ($surplus <= 0 && $shortage <= 0) { return; }
        $invAcc = self::accountId('1100');
        $gainAcc = self::accountId('4200');
        $lossAcc = self::accountId('5200');
        if (!$invAcc) { return; }
        $lines = [];
        if ($surplus > 0 && $gainAcc) {
            $lines[] = ['account_id' => $invAcc, 'debit' => $surplus, 'credit' => 0];
            $lines[] = ['account_id' => $gainAcc, 'debit' => 0, 'credit' => $surplus];
        }
        if ($shortage > 0 && $lossAcc) {
            $lines[] = ['account_id' => $lossAcc, 'debit' => $shortage, 'credit' => 0];
            $lines[] = ['account_id' => $invAcc, 'debit' => 0, 'credit' => $shortage];
        }
        if (count($lines) < 2) { return; }
        self::post([
            'entry_date' => date('Y-m-d', strtotime($st['closed_at'] ?? $st['created_at'] ?? 'now')),
            'description' => 'تسوية جرد - ' . ($st['warehouse_name'] ?? 'مخزن') . ' (ST-' . $stocktakeId . ')',
            'source_type' => 'stocktake', 'source_id' => $stocktakeId,
            'lines' => $lines,
        ]);
    }

    /** إثبات تسليم عهدة (الجدول القديم custody) */
    public static function postCustodyIssueLegacy(int $custodyId): void
    {
        $c = Database::query(
            "SELECT c.*, i.name AS item_name, i.cost_price FROM custody c JOIN items i ON i.id = c.item_id WHERE c.id = ?",
            [$custodyId]
        )->fetch();
        if (!$c) { return; }
        $amount = (float)$c['qty'] * (float)$c['cost_price'];
        if ($amount <= 0) { return; }
        $recAcc = self::accountId('1160');
        $invAcc = self::accountId('1100');
        if (!$recAcc || !$invAcc) { return; }
        self::post([
            'entry_date' => date('Y-m-d', strtotime($c['created_at'] ?? 'now')),
            'description' => 'تسليم عهدة - ' . ($c['item_name'] ?? '') . ' (' . ($c['qty'] ?? 0) . ')',
            'source_type' => 'employee_custody', 'source_id' => $custodyId,
            'lines' => [
                ['account_id' => $recAcc, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $invAcc, 'debit' => 0, 'credit' => $amount],
            ],
        ]);
    }

    /** إثبات إرجاع/تلف/فقد عهدة (الجدول القديم custody) */
    public static function postCustodyReturnLegacy(int $custodyId, float $penaltyAmount = 0): void
    {
        $c = Database::query(
            "SELECT c.*, i.name AS item_name, i.cost_price FROM custody c JOIN items i ON i.id = c.item_id WHERE c.id = ?",
            [$custodyId]
        )->fetch();
        if (!$c) { return; }
        $amount = (float)$c['qty'] * (float)$c['cost_price'];
        if ($amount <= 0) { return; }
        $recAcc = self::accountId('1160');
        $invAcc = self::accountId('1100');
        $penAcc = self::accountId('5130');
        if (!$recAcc) { return; }
        $lines = [];
        if ($c['status'] === 'returned' && $invAcc) {
            $lines = [
                ['account_id' => $invAcc, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $recAcc, 'debit' => 0, 'credit' => $amount],
            ];
        } elseif (in_array($c['status'], ['damaged', 'lost'], true) && $penAcc) {
            $lines = [
                ['account_id' => $penAcc, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $recAcc, 'debit' => 0, 'credit' => $amount],
            ];
        }
        if (count($lines) < 2) { return; }
        self::post([
            'entry_date' => date('Y-m-d', strtotime($c['returned_at'] ?? 'now')),
            'description' => ($c['status'] === 'returned' ? 'إرجاع عهدة' : 'تلف/فقد عهدة') . ' - ' . ($c['item_name'] ?? ''),
            'source_type' => 'employee_custody', 'source_id' => $custodyId,
            'lines' => $lines,
        ]);
    }

    /** إثبات تسوية مالية للموظف (مكافأة/خصم/جزاء) — يُستخدم للتسويات خارج مسير الرواتب */
    public static function postAdjustment(int $adjustmentId): void
    {
        $adj = Database::query(
            "SELECT a.*, e.full_name FROM hr_adjustments a JOIN employees e ON e.id = a.employee_id WHERE a.id = ?",
            [$adjustmentId]
        )->fetch();
        if (!$adj || $adj['status'] !== 'approved' || (float)$adj['amount'] <= 0) { return; }
        $salaryPayable = self::accountId(Settings::get('hr_account_salary_payable', '2100'));
        $bonusAcc = self::accountId(Settings::get('hr_account_bonuses', '5110'));
        $deductionAcc = self::accountId(Settings::get('hr_account_deductions', '5120'));
        if (!$salaryPayable) { return; }
        $type = $adj['adjustment_type'];
        if ($type === 'raise') { return; } // الزيادة تُطبق على الرواتب المستقبلية
        $lines = [];
        if ($type === 'bonus') {
            if (!$bonusAcc) { return; }
            $lines = [
                ['account_id' => $bonusAcc, 'debit' => (float)$adj['amount'], 'credit' => 0],
                ['account_id' => $salaryPayable, 'debit' => 0, 'credit' => (float)$adj['amount']],
            ];
        } elseif (in_array($type, ['deduction', 'penalty'], true)) {
            if (!$deductionAcc) { return; }
            $lines = [
                ['account_id' => $salaryPayable, 'debit' => (float)$adj['amount'], 'credit' => 0],
                ['account_id' => $deductionAcc, 'debit' => 0, 'credit' => (float)$adj['amount']],
            ];
        }
        if (count($lines) < 2) { return; }
        $entryId = self::post([
            'entry_date' => $adj['effective_date'] ?: date('Y-m-d'),
            'description' => ($type === 'bonus' ? 'مكافأة' : ($type === 'deduction' ? 'خصم' : 'جزاء')) . ' موظف - ' . ($adj['full_name'] ?? '') . ' (' . ($adj['reason'] ?? '') . ')',
            'source_type' => 'salary_adjustment', 'source_id' => $adjustmentId,
            'lines' => $lines,
        ]);
        try {
            Database::query("UPDATE hr_adjustments SET journal_entry_id = ? WHERE id = ?", [$entryId, $adjustmentId]);
        } catch (\Throwable $e) {
            // ignore if column missing
        }
    }

    /** قيد صرف الراتب: تسوية الالتزام المستحق بالصندوق/البنك */
    public static function postPayroll(int $payrollId): void
    {
        $p = Database::query(
            "SELECT p.*, e.full_name FROM payrolls p JOIN employees e ON e.id = p.employee_id WHERE p.id = ?",
            [$payrollId]
        )->fetch();
        if (!$p || $p['status'] !== 'paid') { return; }
        $payableAcc = self::accountId(Settings::get('hr_account_salary_payable', '2100'));
        $cashAcc = self::accountId(Settings::get('hr_account_cash', '1000'));
        if (!$payableAcc || !$cashAcc) { return; }
        $entryId = self::post([
            'entry_date' => date('Y-m-d', strtotime($p['paid_at'] ?? 'now')),
            'description' => 'صرف راتب - ' . $p['full_name'] . ' (' . $p['period'] . ')',
            'source_type' => 'payroll', 'source_id' => $payrollId,
            'lines' => [
                ['account_id' => $payableAcc, 'debit' => (float)$p['net_salary'], 'credit' => 0],
                ['account_id' => $cashAcc, 'debit' => 0, 'credit' => (float)$p['net_salary']],
            ],
        ]);
        Database::query("UPDATE payrolls SET journal_entry_id = ? WHERE id = ?", [$entryId, $payrollId]);
    }

    /** إثبات استحقاق الرواتب (تكوين بالأجور) عند الاعتماد */
    public static function postPayrollAccrual(int $payrollId): void
    {
        $p = Database::query(
            "SELECT p.*, e.full_name FROM payrolls p JOIN employees e ON e.id = p.employee_id WHERE p.id = ?",
            [$payrollId]
        )->fetch();
        if (!$p) { return; }
        $salaryAcc = self::accountId(Settings::get('hr_account_salary', '5100'));
        $bonusAcc = self::accountId(Settings::get('hr_account_bonuses', '5110'));
        $deductionAcc = self::accountId(Settings::get('hr_account_deductions', '5120'));
        $advanceAcc = self::accountId(Settings::get('hr_account_advances', '1150'));
        $taxAcc = self::accountId(Settings::get('hr_account_tax_payable', '2200'));
        $payableAcc = self::accountId(Settings::get('hr_account_salary_payable', '2100'));
        // حسابات المكونات المستحقة/المصروفة المفصلة
        $insuranceAcc = self::accountId(Settings::get('hr_account_insurance_payable', '2110'));
        $socialAcc = self::accountId(Settings::get('hr_account_social_security_payable', '2120'));
        $loadingsAcc = self::accountId(Settings::get('hr_account_loadings_payable', '2130'));
        $tipsAcc = self::accountId(Settings::get('hr_account_tips_payable', '2140'));
        $securityAcc = self::accountId(Settings::get('hr_account_security_deposit_payable', '2150'));
        $travelAcc = self::accountId(Settings::get('hr_account_travel_tickets', '5130'));
        if (!$salaryAcc || !$payableAcc) {
            throw new \RuntimeException('حساب مصروف الرواتب أو الرواتب المستحقة غير مكون. اذهب إلى إعدادات المحاسبة للموارد البشرية.');
        }

        $components = [
            ['key' => 'bonuses', 'account' => $bonusAcc, 'setting' => 'hr_account_bonuses', 'label' => 'المكافآت'],
            ['key' => 'travel_tickets', 'account' => $travelAcc, 'setting' => 'hr_account_travel_tickets', 'label' => 'تذاكر السفر'],
            ['key' => 'deductions', 'account' => $deductionAcc, 'setting' => 'hr_account_deductions', 'label' => 'الخصومات والجزاءات', 'add_penalties' => true],
            ['key' => 'advances', 'account' => $advanceAcc, 'setting' => 'hr_account_advances', 'label' => 'السلف'],
            ['key' => 'tax_amount', 'account' => $taxAcc, 'setting' => 'hr_account_tax_payable', 'label' => 'الضريبة'],
            ['key' => 'insurance', 'account' => $insuranceAcc, 'setting' => 'hr_account_insurance_payable', 'label' => 'التأمين'],
            ['key' => 'social_security', 'account' => $socialAcc, 'setting' => 'hr_account_social_security_payable', 'label' => 'الضمان الاجتماعي'],
            ['key' => 'loadings', 'account' => $loadingsAcc, 'setting' => 'hr_account_loadings_payable', 'label' => 'التحميلات'],
            ['key' => 'tips', 'account' => $tipsAcc, 'setting' => 'hr_account_tips_payable', 'label' => 'التيبس'],
            ['key' => 'security_deposit', 'account' => $securityAcc, 'setting' => 'hr_account_security_deposit_payable', 'label' => 'تأمين المستحقات'],
        ];
        $deficitAmount = (float)($p['deficit_amount'] ?? 0);
        $missing = [];
        foreach ($components as $c) {
            $amount = (float)($p[$c['key']] ?? 0);
            if (!empty($c['add_penalties'])) {
                $amount += (float)($p['penalties'] ?? 0) + (float)($p['housing_deduction'] ?? 0);
            }
            if ($amount > 0 && empty($c['account'])) {
                $missing[] = $c['label'] . ' (' . $c['setting'] . ')';
            }
        }
        if ($deficitAmount > 0 && empty($advanceAcc)) {
            $missing[] = 'السلف (hr_account_advances)';
        }
        if ($missing) {
            throw new \RuntimeException('الحسابات المحاسبية التالية غير مكونة: ' . implode('، ', $missing) . '. اذهب إلى إعدادات المحاسبة للموارد البشرية.');
        }

        // مصروف الراتب الأساسي: الأساسي + الزيادات + البدلات + الإضافي + تسوية أيام الدوام − خصم الغياب + تعديل التقريب
        $salaryExpense = (float)$p['base_salary'] + (float)$p['raises'] + (float)($p['allowances'] ?? 0) + (float)($p['overtime'] ?? 0) + (float)($p['work_days_settlement'] ?? 0) - (float)$p['absence_deduction'] + (float)($p['rounding_adjustment'] ?? 0);
        $lines = [];
        if ($salaryExpense > 0) {
            $lines[] = ['account_id' => $salaryAcc, 'debit' => $salaryExpense, 'credit' => 0];
        } elseif ($salaryExpense < 0) {
            $lines[] = ['account_id' => $salaryAcc, 'debit' => 0, 'credit' => abs($salaryExpense)];
        }
        // المكافآت + إضافي تيبس (استحقاقات إضافية طبيعتها كالمكافآت)
        $bonusTotal = (float)$p['bonuses'] + (float)($p['tips_addition'] ?? 0);
        if ($bonusAcc && $bonusTotal > 0) {
            $lines[] = ['account_id' => $bonusAcc, 'debit' => $bonusTotal, 'credit' => 0];
        }
        if ($travelAcc && (float)$p['travel_tickets'] > 0) {
            $lines[] = ['account_id' => $travelAcc, 'debit' => (float)$p['travel_tickets'], 'credit' => 0];
        }
        // الخصومات والجزاءات + خدمات السكن (كلها استقطاعات من الموظف)
        $deductionTotal = (float)$p['deductions'] + (float)$p['penalties'] + (float)($p['housing_deduction'] ?? 0);
        if ($deductionAcc && $deductionTotal > 0) {
            $lines[] = ['account_id' => $deductionAcc, 'debit' => 0, 'credit' => $deductionTotal];
        }
        if ($advanceAcc && (float)$p['advances'] > 0) {
            $lines[] = ['account_id' => $advanceAcc, 'debit' => 0, 'credit' => (float)$p['advances']];
        }
        if ($taxAcc && (float)($p['tax_amount'] ?? 0) > 0) {
            $lines[] = ['account_id' => $taxAcc, 'debit' => 0, 'credit' => (float)$p['tax_amount']];
        }
        if ($insuranceAcc && (float)($p['insurance'] ?? 0) > 0) {
            $lines[] = ['account_id' => $insuranceAcc, 'debit' => 0, 'credit' => (float)$p['insurance']];
        }
        if ($socialAcc && (float)($p['social_security'] ?? 0) > 0) {
            $lines[] = ['account_id' => $socialAcc, 'debit' => 0, 'credit' => (float)$p['social_security']];
        }
        if ($loadingsAcc && (float)($p['loadings'] ?? 0) > 0) {
            $lines[] = ['account_id' => $loadingsAcc, 'debit' => 0, 'credit' => (float)$p['loadings']];
        }
        if ($tipsAcc && (float)($p['tips'] ?? 0) > 0) {
            $lines[] = ['account_id' => $tipsAcc, 'debit' => 0, 'credit' => (float)$p['tips']];
        }
        if ($securityAcc && (float)($p['security_deposit'] ?? 0) > 0) {
            $lines[] = ['account_id' => $securityAcc, 'debit' => 0, 'credit' => (float)$p['security_deposit']];
        }
        // عجز الراتب المُحوَّل لسلفة على الموظف (صافي الراتب صُفِّر ويُخصَم العجز من راتب الشهر التالي):
        // يُقيَّد كمدين على حساب السلف (ذمة مستحقة على الموظف) لموازنة القيد بدل تسجيله كرصيد سالب في الرواتب المستحقة.
        if ($deficitAmount > 0 && $advanceAcc) {
            $lines[] = ['account_id' => $advanceAcc, 'debit' => $deficitAmount, 'credit' => 0];
        }
        $lines[] = ['account_id' => $payableAcc, 'debit' => 0, 'credit' => (float)$p['net_salary']];
        $lines = array_values(array_filter($lines, fn($l) => ((float)($l['debit'] ?? 0) != 0 || (float)($l['credit'] ?? 0) != 0)));
        $totalDr = array_sum(array_column($lines, 'debit'));
        if (count($lines) < 2 || $totalDr <= 0) {
            // الراتب لا يولّد أي أثر محاسبي (صافي صفر، لا مصروف، أو مدين غير موجب)
            Database::query("UPDATE payrolls SET accrual_entry_id = NULL WHERE id = ?", [$payrollId]);
            return;
        }
        $entryId = self::post([
            'entry_date' => $p['period'] . '-01',
            'description' => 'استحقاق رواتب - ' . $p['full_name'] . ' (' . $p['period'] . ')',
            'source_type' => 'payroll_accrual', 'source_id' => $payrollId,
            'lines' => $lines,
        ]);
        Database::query("UPDATE payrolls SET accrual_entry_id = ? WHERE id = ?", [$entryId, $payrollId]);
    }

    /** إثبات صرف سلفة للموظف (نقد/بنك) */
    public static function postAdvance(int $advanceId): void
    {
        $a = Database::query(
            "SELECT a.*, e.full_name, ba.account_id AS bank_gl_account_id, acc.code AS bank_account_code
             FROM salary_advances a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN bank_accounts ba ON ba.id = a.bank_account_id
             LEFT JOIN accounts acc ON acc.id = ba.account_id
             WHERE a.id = ?",
            [$advanceId]
        )->fetch();
        if (!$a || $a['status'] !== 'paid') { return; }
        $advanceAcc = self::accountId(Settings::get('hr_account_advances', '1150'));
        if (!$advanceAcc) {
            throw new \RuntimeException('حساب سلف الموظفين (hr_account_advances) غير مكون');
        }
        // اختيار حساب الدائن حسب طريقة الدفع المسجلة
        if ($a['payment_method'] === 'bank') {
            if (empty($a['bank_account_code'])) {
                throw new \RuntimeException('الحساب البنكي المختار لا يملك حساباً في دفتر الأستاذ؛ راجع إعدادات البنوك');
            }
            $creditAcc = self::accountId((string)$a['bank_account_code']);
            if (!$creditAcc) {
                throw new \RuntimeException('حساب الأستاذ المرتبط بالحساب البنكي غير مفعل أو غير موجود');
            }
        } else {
            $creditAcc = self::accountId(Settings::get('hr_account_cash', '1000'));
            if (!$creditAcc) {
                throw new \RuntimeException('حساب الصندوق (hr_account_cash) غير مكون؛ راجع إعدادات المحاسبة');
            }
        }
        $entryId = self::post([
            'entry_date' => date('Y-m-d', strtotime($a['paid_at'] ?? 'now')),
            'description' => 'صرف سلفة - ' . $a['full_name'] . ' (' . $advanceId . ') — ' . ($a['payment_method'] === 'bank' ? 'بنك' : 'نقد'),
            'source_type' => 'salary_advance', 'source_id' => $advanceId,
            'lines' => [
                ['account_id' => $advanceAcc, 'debit' => (float)$a['amount'], 'credit' => 0],
                ['account_id' => $creditAcc, 'debit' => 0, 'credit' => (float)$a['amount']],
            ],
        ]);
        Database::query("UPDATE salary_advances SET journal_entry_id = ? WHERE id = ?", [$entryId, $advanceId]);
    }

    /** إثبات تسليم عهدة للموظف: ذمم الموظف / مخزن */
    public static function postCustodyIssue(int $custodyId): void
    {
        $c = Database::query(
            "SELECT ec.*, e.full_name, i.name AS item_name FROM employee_custody ec
             JOIN employees e ON e.id = ec.employee_id
             JOIN items i ON i.id = ec.item_id
             WHERE ec.id = ?",
            [$custodyId]
        )->fetch();
        if (!$c) { return; }
        $receivableAcc = self::accountId(Settings::get('hr_account_custody_receivable', '1160'));
        $inventoryAcc = self::accountId(Settings::get('hr_account_inventory', '1100'));
        if (!$receivableAcc || !$inventoryAcc) { return; }
        $entryId = self::post([
            'entry_date' => date('Y-m-d', strtotime($c['issued_at'] ?? 'now')),
            'description' => 'تسليم عهدة - ' . $c['full_name'] . ' - ' . $c['item_name'] . ' (' . $custodyId . ')',
            'source_type' => 'employee_custody', 'source_id' => $custodyId,
            'lines' => [
                ['account_id' => $receivableAcc, 'debit' => (float)$c['total_value'], 'credit' => 0],
                ['account_id' => $inventoryAcc, 'debit' => 0, 'credit' => (float)$c['total_value']],
            ],
        ]);
        Database::query("UPDATE employee_custody SET journal_entry_id = ? WHERE id = ?", [$entryId, $custodyId]);
    }

    /** إثبات إرجاع عهدة سليمة: مخزن / ذمم الموظف */
    public static function postCustodyReturn(int $custodyId): void
    {
        $c = Database::query(
            "SELECT ec.*, e.full_name, i.name AS item_name FROM employee_custody ec
             JOIN employees e ON e.id = ec.employee_id
             JOIN items i ON i.id = ec.item_id
             WHERE ec.id = ? AND ec.journal_entry_id IS NOT NULL",
            [$custodyId]
        )->fetch();
        if (!$c) { return; }
        $receivableAcc = self::accountId(Settings::get('hr_account_custody_receivable', '1160'));
        $inventoryAcc = self::accountId(Settings::get('hr_account_inventory', '1100'));
        if (!$receivableAcc || !$inventoryAcc) { return; }
        $entryId = self::post([
            'entry_date' => date('Y-m-d', strtotime($c['returned_at'] ?? 'now')),
            'description' => 'إرجاع عهدة - ' . $c['full_name'] . ' - ' . $c['item_name'] . ' (' . $custodyId . ')',
            'source_type' => 'employee_custody_return', 'source_id' => $custodyId,
            'lines' => [
                ['account_id' => $inventoryAcc, 'debit' => (float)$c['total_value'], 'credit' => 0],
                ['account_id' => $receivableAcc, 'debit' => 0, 'credit' => (float)$c['total_value']],
            ],
        ]);
        Database::query("UPDATE employee_custody SET return_entry_id = ? WHERE id = ?", [$entryId, $custodyId]);
    }

    /** إثبات تلف/فقد للعهدة: غرامة / ذمة الموظف */
    public static function postCustodyPenalty(int $custodyId, float $amount): void
    {
        $c = Database::query(
            "SELECT ec.*, e.full_name, i.name AS item_name FROM employee_custody ec
             JOIN employees e ON e.id = ec.employee_id
             JOIN items i ON i.id = ec.item_id
             WHERE ec.id = ?",
            [$custodyId]
        )->fetch();
        if (!$c || $amount <= 0) { return; }
        $receivableAcc = self::accountId(Settings::get('hr_account_custody_receivable', '1160'));
        $penaltyAcc = self::accountId(Settings::get('hr_account_custody_penalty', '5130'));
        if (!$receivableAcc || !$penaltyAcc) { return; }
        $entryId = self::post([
            'entry_date' => date('Y-m-d', strtotime($c['returned_at'] ?? 'now')),
            'description' => 'غرامة عهدة (تلف/فقد) - ' . $c['full_name'] . ' - ' . $c['item_name'] . ' (' . $custodyId . ')',
            'source_type' => 'employee_custody_penalty', 'source_id' => $custodyId,
            'lines' => [
                ['account_id' => $penaltyAcc, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $receivableAcc, 'debit' => 0, 'credit' => $amount],
            ],
        ]);
        Database::query("UPDATE employee_custody SET return_entry_id = ? WHERE id = ?", [$entryId, $custodyId]);
    }

    /** إحتساب إهلاك أصل لفترة */
    public static function depreciate(int $assetId, string $period): float
    {
        $asset = Database::query("SELECT * FROM fixed_assets WHERE id = ? AND status='active'", [$assetId])->fetch();
        if (!$asset) { return 0; }
        $exists = Database::query("SELECT id FROM depreciation_entries WHERE asset_id = ? AND period = ?", [$assetId, $period])->fetch();
        if ($exists) { return 0; }
        $cost = (float)$asset['purchase_cost'];
        $salvage = (float)$asset['salvage_value'];
        $life = (int)$asset['life_years'];
        if ($life <= 0) { return 0; }
        $amount = 0;
        if ($asset['depreciation_method'] === 'straight_line') {
            $amount = ($cost - $salvage) / ($life * 12);
        } else {
            $accum = (float) Database::query("SELECT COALESCE(SUM(amount),0) AS s FROM depreciation_entries WHERE asset_id = ?", [$assetId])->fetch()['s'];
            $rate = 2 / $life;
            $amount = (($cost - $accum) * $rate) / 12;
        }
        $amount = max(0, round($amount, 2));
        if ($amount <= 0) { return 0; }
        $expAcc = self::accountId('5300');
        $accumAcc = self::accountId('1210');
        if (!$expAcc || !$accumAcc) { return 0; }
        $entryId = self::post([
            'entry_date' => $period . '-01',
            'description' => 'إهلاك أصل - ' . $asset['asset_no'] . ' (' . $period . ')',
            'source_type' => 'depreciation', 'source_id' => $assetId,
            'lines' => [
                ['account_id' => $expAcc, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $accumAcc, 'debit' => 0, 'credit' => $amount],
            ],
        ]);
        Database::query("INSERT INTO depreciation_entries (asset_id, period, amount, journal_entry_id) VALUES (?,?,?,?)", [$assetId, $period, $amount, $entryId]);
        return $amount;
    }

    /** إحلال كامل للأصول في فترة */
    public static function depreciateAll(string $period): array
    {
        $assets = Database::query("SELECT id FROM fixed_assets WHERE status='active'")->fetchAll();
        $out = [];
        foreach ($assets as $a) {
            $amount = self::depreciate((int)$a['id'], $period);
            if ($amount > 0) { $out[] = ['asset_id' => $a['id'], 'amount' => $amount]; }
        }
        return $out;
    }

    /** تحديد نوع الحساب بناءً على الكود في مخطط TOP */
    private static function coaType(string $code): string
    {
        return match ($code[0] ?? '') {
            '1', '7' => 'asset',
            '2' => 'liability',
            '4', '6' => 'revenue',
            '3', '5' => 'expense',
            default => 'expense',
        };
    }

    /**
     * استيراد شجرة حسابات من ملف Excel بالتنسيق العربي (الحساب | الحساب الرئيسي | ختامي)
     * @return array{created:int, updated:int, errors:array}
     */
    public static function importCoa(string $tmpPath, int $createdBy): array
    {
        $read = ImportHelper::readFile($tmpPath);
        if ($read['errors']) {
            return ['created' => 0, 'updated' => 0, 'errors' => $read['errors']];
        }
        $rows = $read['rows'];
        if (!$rows) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['الملف لا يحتوي على بيانات']];
        }
        // تحديد أعمدة الملف العربي
        $colAccount = null;
        $colParent = null;
        $colFinal = null;
        foreach (array_keys($rows[0]) as $key) {
            $norm = strtolower(trim((string)$key));
            if ($norm === 'الحساب') { $colAccount = $key; }
            if ($norm === 'الحساب الرئيسي') { $colParent = $key; }
            if ($norm === 'ختامي') { $colFinal = $key; }
        }
        if (!$colAccount) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['الملف يجب أن يحتوي على العمود "الحساب"']];
        }
        $parsed = [];
        $errors = [];
        foreach ($rows as $idx => $row) {
            $account = trim((string)($row[$colAccount] ?? ''));
            if ($account === '') {
                continue;
            }
            if (!preg_match('/^([0-9]+)-(.+)$/u', $account, $m)) {
                $errors[] = 'الصف ' . ($idx + 2) . ': تنسيق الحساب غير صالح (المطلوب: كود-اسم)';
                continue;
            }
            $code = $m[1];
            $name = trim($m[2]);
            $parent = $colParent ? trim((string)($row[$colParent] ?? '')) : '';
            $final = $colFinal ? trim((string)($row[$colFinal] ?? '')) : '';
            $parsed[] = [
                'line' => $idx + 2,
                'code' => $code,
                'name' => $name,
                'parent' => $parent,
                'final' => $final,
                'type' => self::coaType($code),
            ];
        }
        if ($errors) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors];
        }
        if (!$parsed) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['لا توجد صفوف صالحة للاستيراد']];
        }
        // تحقق من عدم تكرار الكود داخل الملف
        $seen = [];
        foreach ($parsed as $p) {
            if (isset($seen[$p['code']])) {
                $errors[] = 'الكود ' . $p['code'] . ' مكرر في الملف';
            }
            $seen[$p['code']] = true;
        }
        if ($errors) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors];
        }
        Database::beginTransaction();
        try {
            $nameMap = [];
            $codeMap = [];
            $childCount = [];
            $created = 0;
            $updated = 0;
            foreach ($parsed as $p) {
                $parentId = null;
                if ($p['parent'] !== '') {
                    if (!isset($nameMap[$p['parent']])) {
                        throw new \RuntimeException("الصف {$p['line']}: الحساب الرئيسي '{$p['parent']}' غير موجود في الملف");
                    }
                    $parentId = $nameMap[$p['parent']];
                    $childCount[$parentId] = ($childCount[$parentId] ?? 0) + 1;
                }
                Database::query(
                    "INSERT INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        type = VALUES(type),
                        parent_id = VALUES(parent_id),
                        account_group = VALUES(account_group),
                        is_leaf = VALUES(is_leaf)",
                    [$p['code'], $p['name'], $p['type'], $parentId, $p['final'], 1, 0.0]
                );
                $newId = (int) Database::lastInsertId();
                if ($newId > 0) {
                    $created++;
                } else {
                    $existing = Database::query("SELECT id FROM accounts WHERE code = ?", [$p['code']])->fetch();
                    $newId = (int) ($existing['id'] ?? 0);
                    $updated++;
                }
                if ($newId > 0) {
                    $nameMap[$p['name']] = $newId;
                    $codeMap[$p['code']] = $newId;
                }
            }
            // تحديث حالة is_leaf للحسابات المستوردة
            foreach ($codeMap as $code => $id) {
                $isLeaf = isset($childCount[$id]) ? 0 : 1;
                Database::query("UPDATE accounts SET is_leaf = ? WHERE id = ?", [$isLeaf, $id]);
            }
            Database::commit();
            Helpers::audit('استيراد شجرة حسابات', 'accounts', 0, "created:$created updated:$updated");
            return ['created' => $created, 'updated' => $updated, 'errors' => []];
        } catch (\Throwable $e) {
            Database::rollBack();
            return ['created' => 0, 'updated' => 0, 'errors' => ['خطأ أثناء الحفظ: ' . $e->getMessage()]];
        }
    }

    /** تحديث قيد يومي موجود (حذف الأسطر القديمة وإعادة إدراجها) */
    public static function update(int $entryId, array $data): void
    {
        $entry = Database::query("SELECT * FROM journal_entries WHERE id = ?", [$entryId])->fetch();
        if (!$entry) {
            throw new \RuntimeException('القيد غير موجود');
        }
        if ($entry['status'] === 'reversed') {
            throw new \RuntimeException('لا يمكن تعديل قيد معكوس');
        }
        $lines = $data['lines'] ?? [];
        if (count($lines) < 2) {
            throw new \RuntimeException('القيد يحتاج بندين على الأقل');
        }
        $totalDr = array_sum(array_column($lines, 'debit'));
        $totalCr = array_sum(array_column($lines, 'credit'));
        if (abs($totalDr - $totalCr) > 0.001) {
            throw new \RuntimeException('مجموع المدين لا يساوي مجموع الدائن');
        }
        if ($totalDr <= 0) {
            throw new \RuntimeException('القيد فارغ المبالغ');
        }

        $inTrx = Database::connect()->inTransaction();
        if (!$inTrx) { Database::beginTransaction(); }
        try {
            Database::query(
                "UPDATE journal_entries SET entry_date = ?, description = ?, cost_center_id = ? WHERE id = ?",
                [$data['entry_date'] ?? $entry['entry_date'], $data['description'] ?? $entry['description'], $data['cost_center_id'] ?? $entry['cost_center_id'], $entryId]
            );
            Database::query("DELETE FROM journal_lines WHERE entry_id = ?", [$entryId]);
            foreach ($lines as $line) {
                if (empty($line['account_id']) || ((float)($line['debit'] ?? 0) == 0 && (float)($line['credit'] ?? 0) == 0)) { continue; }
                Database::query(
                    "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)",
                    [$entryId, (int)$line['account_id'], (float)($line['debit'] ?? 0), (float)($line['credit'] ?? 0), $line['description'] ?? '']
                );
            }
            if (!$inTrx) { Database::commit(); }
            Helpers::audit('تعديل قيد محاسبي', 'journal_entries', $entryId, $entry['entry_no']);
        } catch (\Throwable $e) {
            if (!$inTrx) { Database::rollBack(); }
            throw $e;
        }
    }

    /** استيراد عدة قيود من ملف Excel */
    public static function importJournal(string $tmpPath): array
    {
        $read = ImportHelper::readFile($tmpPath);
        if (!empty($read['errors'])) {
            return ['created' => 0, 'errors' => $read['errors']];
        }
        $rows = $read['rows'];
        if (empty($rows)) {
            return ['created' => 0, 'errors' => ['الملف لا يحتوي على بيانات']];
        }

        $headers = array_keys($rows[0]);
        $normMap = [];
        foreach ($headers as $h) {
            $norm = str_replace([' ', '_', '-'], '', strtolower(trim((string)$h)));
            $normMap[$norm] = $h;
        }
        $findCol = function (array $candidates) use ($normMap) {
            foreach ($candidates as $c) {
                $norm = str_replace([' ', '_', '-'], '', strtolower(trim((string)$c)));
                if (isset($normMap[$norm])) { return $normMap[$norm]; }
            }
            return null;
        };

        $entryNoCol = $findCol(['رقم القيد', 'entryno', 'entry_no', 'رقم']);
        $dateCol = $findCol(['التاريخ', 'date', 'entrydate']);
        $descCol = $findCol(['الوصف', 'description']);
        $ccCol = $findCol(['مركز التكلفة', 'costcenter', 'cost_center']);
        $accCodeCol = $findCol(['كود الحساب', 'accountcode', 'account_code', 'كود']);
        $accNameCol = $findCol(['اسم الحساب', 'accountname', 'account_name']);
        $debitCol = $findCol(['مدين', 'debit']);
        $creditCol = $findCol(['دائن', 'credit']);
        $lineDescCol = $findCol(['البيان', 'linedescription', 'line_description', 'line_desc', 'بيان']);

        if (!$accCodeCol && !$accNameCol) {
            return ['created' => 0, 'errors' => ['يجب أن يحتوي الملف على عمود كود الحساب أو اسم الحساب']];
        }
        if (!$debitCol || !$creditCol) {
            return ['created' => 0, 'errors' => ['يجب أن يحتوي الملف على عمودي المدين والدائن']];
        }

        $accounts = Database::query("SELECT id, code, name FROM accounts WHERE is_active = 1")->fetchAll();
        $accByCode = [];
        $accByName = [];
        foreach ($accounts as $a) {
            $code = trim((string)$a['code']);
            $name = trim((string)$a['name']);
            $accByCode[$code] = $a;
            $accByCode[preg_replace('/\s+/', '', $code)] = $a;
            $accByName[$name] = $a;
            $accByName[preg_replace('/\s+/', '', $name)] = $a;
        }
        $ccRows = Database::query("SELECT id, name FROM cost_centers WHERE is_active = 1")->fetchAll();
        $ccByName = [];
        foreach ($ccRows as $cc) {
            $name = trim((string)$cc['name']);
            $ccByName[$name] = (int)$cc['id'];
            $ccByName[preg_replace('/\s+/', '', $name)] = (int)$cc['id'];
        }

        $findAccount = function ($code, $name) use ($accByCode, $accByName) {
            $code = trim((string)$code);
            $name = trim((string)$name);
            if ($code !== '') {
                if (isset($accByCode[$code])) { return $accByCode[$code]; }
                $norm = preg_replace('/\s+/', '', $code);
                if (isset($accByCode[$norm])) { return $accByCode[$norm]; }
            }
            if ($name !== '') {
                if (isset($accByName[$name])) { return $accByName[$name]; }
                $norm = preg_replace('/\s+/', '', $name);
                if (isset($accByName[$norm])) { return $accByName[$norm]; }
            }
            return null;
        };
        $findCostCenter = function ($name) use ($ccByName) {
            $name = trim((string)$name);
            if ($name === '') { return null; }
            if (isset($ccByName[$name])) { return $ccByName[$name]; }
            $norm = preg_replace('/\s+/', '', $name);
            return $ccByName[$norm] ?? null;
        };
        $parseAmt = function ($v) {
            if ($v === null || $v === '') { return 0.0; }
            $s = str_replace([',', ' '], '', (string)$v);
            $s = str_replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $s);
            $s = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $s);
            if (!is_numeric($s)) { return null; }
            return (float)$s;
        };

        $groups = [];
        $currentKey = 'group_1';
        foreach ($rows as $idx => $row) {
            $key = $entryNoCol ? trim((string)($row[$entryNoCol] ?? '')) : '';
            if ($key === '') {
                $key = $currentKey;
            } else {
                $currentKey = $key;
            }
            if (!isset($groups[$key])) { $groups[$key] = []; }
            $groups[$key][] = ['idx' => $idx, 'row' => $row];
        }

        $entries = [];
        $errors = [];
        foreach ($groups as $groupKey => $groupRows) {
            $entryDate = null;
            $entryDesc = '';
            $costCenterName = '';
            $lines = [];
            foreach ($groupRows as $item) {
                $row = $item['row'];
                $lineNo = $item['idx'] + 2;
                $date = $dateCol ? ImportHelper::parseExcelDate($row[$dateCol] ?? null) : null;
                if ($date && !$entryDate) { $entryDate = $date; }
                $desc = $descCol ? trim((string)($row[$descCol] ?? '')) : '';
                if ($desc !== '' && $entryDesc === '') { $entryDesc = $desc; }
                $ccName = $ccCol ? trim((string)($row[$ccCol] ?? '')) : '';
                if ($ccName !== '' && $costCenterName === '') { $costCenterName = $ccName; }

                $code = $accCodeCol ? ($row[$accCodeCol] ?? '') : '';
                $name = $accNameCol ? ($row[$accNameCol] ?? '') : '';
                $acc = $findAccount($code, $name);
                if (!$acc) {
                    $errors[] = 'الصف ' . $lineNo . ': الحساب غير موجود' . ($code ? " (كود: $code)" : ($name ? " (اسم: $name)" : ''));
                    continue;
                }
                $dr = $parseAmt($row[$debitCol] ?? 0);
                $cr = $parseAmt($row[$creditCol] ?? 0);
                if ($dr === null || $cr === null) {
                    $errors[] = "الصف $lineNo: المدين/الدائن غير رقمي";
                    continue;
                }
                if ($dr == 0 && $cr == 0) { continue; }
                $lineDesc = $lineDescCol ? trim((string)($row[$lineDescCol] ?? '')) : '';
                $lines[] = [
                    'account_id' => (int)$acc['id'],
                    'debit' => $dr,
                    'credit' => $cr,
                    'description' => $lineDesc,
                ];
            }
            if (empty($lines)) { continue; }
            $totalDr = array_sum(array_column($lines, 'debit'));
            $totalCr = array_sum(array_column($lines, 'credit'));
            if (abs($totalDr - $totalCr) > 0.001) {
                $errors[] = "مجموعة $groupKey: القيد غير متزن (مدين $totalDr / دائن $totalCr)";
                continue;
            }
            $entries[] = [
                'entry_date' => $entryDate ?: date('Y-m-d'),
                'description' => $entryDesc,
                'cost_center_id' => $findCostCenter($costCenterName),
                'lines' => $lines,
            ];
        }

        if ($errors) {
            return ['created' => 0, 'errors' => $errors];
        }
        if (empty($entries)) {
            return ['created' => 0, 'errors' => ['لا توجد قيود صالحة في الملف']];
        }

        $inTrx = Database::connect()->inTransaction();
        if (!$inTrx) { Database::beginTransaction(); }
        try {
            $created = 0;
            foreach ($entries as $e) {
                self::post($e);
                $created++;
            }
            if (!$inTrx) { Database::commit(); }
            Helpers::audit('استيراد قيود من Excel', 'journal_entries', 0, "created:$created");
            return ['created' => $created, 'errors' => []];
        } catch (\Throwable $e) {
            if (!$inTrx) { Database::rollBack(); }
            return ['created' => 0, 'errors' => ['خطأ أثناء الحفظ: ' . $e->getMessage()]];
        }
    }

    /** تنزيل نموذج Excel لاستيراد القيود */
    public static function downloadJournalTemplate(): void
    {
        $headers = ['رقم القيد', 'التاريخ', 'الوصف', 'مركز التكلفة', 'كود الحساب', 'اسم الحساب', 'مدين', 'دائن', 'البيان'];
        $sample = ['1001', date('Y-m-d'), 'قيد افتراضي', 'الإدارة', '1000', 'الصندوق', '1000', '0', ''];
        ImportHelper::downloadTemplate($headers, 'journal_entries_template.xlsx', $sample);
    }
}
