<?php
namespace App\Core\Workflow;

use App\Core\{Database, Helpers, Auth, PurchasingService, AccountingService};

/**
 * موجّه الإجراءات — تنفيذ إجراءات تلقائية عند عقد action أو بعد الموافقة/الرفض.
 * إضافة إجراء جديد = تسجيل handler واحد:
 *   ActionDispatcher::register('my.action', fn($params, $ctx) => ...);
 */
final class ActionDispatcher
{
    /** @var array<string, callable> */
    private static array $actions = [];
    private static bool $booted = false;

    public static function register(string $name, callable $handler): void
    {
        self::$actions[$name] = $handler;
    }

    public static function available(): array
    {
        self::bootDefaults();
        return array_keys(self::$actions);
    }

    public static function run(string $name, array $params, array $context): void
    {
        self::bootDefaults();
        $handler = self::$actions[$name] ?? null;
        if (!$handler) {
            throw new \RuntimeException('إجراء غير معروف: ' . $name);
        }
        $handler($params, $context);
    }

    private static function bootDefaults(): void
    {
        if (self::$booted) return;
        self::$booted = true;

        // إشعار داخل التطبيق
        self::register('notify.in_app', function (array $p, array $ctx): void {
            $targets = ApproverResolver::resolve((array)($p['targets'] ?? []), $ctx['request'] ?? []);
            foreach ($targets as $uid) {
                Helpers::notify($uid, (string)($p['title'] ?? 'إشعار'), (string)($p['body'] ?? ''), $p['link'] ?? null);
            }
        });

        // إشعار بريد/واتساب/ SMS عبر طابور الإشعارات
        foreach (['notify.email' => 'email', 'notify.whatsapp' => 'whatsapp', 'notify.sms' => 'sms'] as $name => $channel) {
            self::register($name, function (array $p, array $ctx) use ($channel): void {
                $targets = ApproverResolver::resolve((array)($p['targets'] ?? []), $ctx['request'] ?? []);
                foreach ($targets as $uid) {
                    $contact = Database::query(
                        "SELECT COALESCE(u.email, e.phone, '') AS contact FROM users u
                         LEFT JOIN employees e ON e.user_id = u.id WHERE u.id = ?",
                        [$uid]
                    )->fetchColumn();
                    if (!$contact) continue;
                    Database::query(
                        "INSERT INTO notification_queue (channel, user_id, recipient, subject, body)
                         VALUES (?,?,?,?,?)",
                        [$channel, $uid, $contact, (string)($p['title'] ?? ''), (string)($p['body'] ?? '')]
                    );
                }
            });
        }

        // استدعاء Webhook خارجي
        self::register('webhook.call', function (array $p, array $ctx): void {
            $url = (string)($p['url'] ?? '');
            if ($url === '' || !function_exists('curl_init')) return;
            $payload = json_encode([
                'request_id' => $ctx['request']['id'] ?? null,
                'status'     => $ctx['request']['status'] ?? null,
                'data'       => $ctx['data'] ?? [],
                'params'     => $p,
            ], JSON_UNESCAPED_UNICODE);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 5,
            ]);
            curl_exec($ch);
            curl_close($ch);
        });

        // تحديث حالة الطلب مباشرة
        self::register('request.update_status', function (array $p, array $ctx): void {
            $status = (string)($p['status'] ?? '');
            $id = (int)($ctx['request']['id'] ?? 0);
            if ($id && in_array($status, ['pending','approved','rejected','returned_for_edit','cancelled'], true)) {
                Database::query("UPDATE requests SET status = ?, updated_at = NOW() WHERE id = ?", [$status, $id]);
            }
        });

        // تحديث حالة الكيان المرتبط (عمود قابل للتخصيص)
        self::register('entity.update_status', function (array $p, array $ctx): void {
            $link = EntityRegistry::requestLink((int)($ctx['request']['id'] ?? 0));
            $adapter = $link ? EntityRegistry::adapter($link['entity_type']) : null;
            if (!$adapter || empty($adapter['table'])) return;
            $status = (string)($p['status'] ?? '');
            $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($p['column'] ?? 'status'));
            if ($status !== '' && $column !== '') {
                Database::query(
                    "UPDATE `{$adapter['table']}` SET `$column` = ? WHERE id = ?",
                    [$status, (int)$link['entity_id']]
                );
            }
        });

        // إنشاء أمر شراء من طلب الشراء المرتبط
        self::register('purchase_order.create', function (array $p, array $ctx): void {
            $link = EntityRegistry::requestLink((int)($ctx['request']['id'] ?? 0));
            if (!$link || $link['entity_type'] !== 'purchase_request') {
                throw new \RuntimeException('purchase_order.create: الطلب غير مرتبط بكيان طلب شراء');
            }
            $supplierId = (int)($p['supplier_id'] ?? ($ctx['data']['supplier_id'] ?? 0));
            if (!$supplierId) throw new \RuntimeException('purchase_order.create: supplier_id مطلوب');
            $userId = (int)($p['user_id'] ?? (Auth::id() ?: ($ctx['request']['requester_id'] ?? 0)));
            $poId = PurchasingService::createPoFromPurchaseRequest((int)$link['entity_id'], $supplierId, $userId);
            Helpers::notify($userId, 'تم إنشاء أمر شراء', 'PO #' . $poId . ' من طلب ' . ($ctx['request']['title'] ?? ''), '/purchasing/orders/' . $poId);
        });

        // إنشاء تحويل مخزني مسودة
        self::register('stock_transfer.create', function (array $p, array $ctx): void {
            $from = (int)($p['from_warehouse_id'] ?? 0);
            $to = (int)($p['to_warehouse_id'] ?? 0);
            if (!$from || !$to) throw new \RuntimeException('stock_transfer.create: from_warehouse_id و to_warehouse_id مطلوبان');
            $userId = (int)($p['user_id'] ?? (Auth::id() ?: ($ctx['request']['requester_id'] ?? 0)));
            $no = Helpers::docNumber('TR', 'stock_transfers', 'transfer_no');
            Database::query(
                "INSERT INTO stock_transfers (transfer_no, from_warehouse_id, to_warehouse_id, status, requested_by, notes)
                 VALUES (?,?,?,'pending',?,?)",
                [$no, $from, $to, $userId, (string)($p['notes'] ?? ('من طلب ' . ($ctx['request']['title'] ?? '')))]
            );
            $transferId = (int)Database::lastInsertId();
            foreach ((array)($p['items'] ?? []) as $it) {
                if (empty($it['item_id']) || empty($it['qty'])) continue;
                Database::query(
                    "INSERT INTO stock_transfer_items (transfer_id, item_id, qty, unit_id) VALUES (?,?,?,?)",
                    [$transferId, (int)$it['item_id'], (float)$it['qty'], !empty($it['unit_id']) ? (int)$it['unit_id'] : null]
                );
            }
            Helpers::notify($userId, 'تم إنشاء تحويل مخزني', $no, '/inventory/transfers/' . $transferId);
        });

        // إنشاء قيد محاسبي (يدعم =field لسحب القيم من سياق الطلب)
        self::register('journal_entry.create', function (array $p, array $ctx): void {
            $resolve = function ($v) use ($ctx): float {
                if (is_string($v) && str_starts_with($v, '=')) {
                    return (float)RuleEvaluator::resolveField(substr($v, 1), $ctx);
                }
                return (float)$v;
            };
            $lines = [];
            foreach ((array)($p['lines'] ?? []) as $l) {
                $accountId = !empty($l['account_id']) ? (int)$l['account_id']
                    : (!empty($l['account_code']) ? (int)AccountingService::accountId((string)$l['account_code']) : 0);
                if (!$accountId) continue;
                $lines[] = ['account_id' => $accountId, 'debit' => $resolve($l['debit'] ?? 0), 'credit' => $resolve($l['credit'] ?? 0)];
            }
            $entryId = AccountingService::post([
                'description' => (string)($p['description'] ?? ('قيد تلقائي: ' . ($ctx['request']['title'] ?? ''))),
                'cost_center_id' => $p['cost_center_id'] ?? null,
                'source_type' => 'workflow_request',
                'source_id' => (int)($ctx['request']['id'] ?? 0),
                'lines' => $lines,
            ]);
            Database::query(
                "UPDATE requests SET notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ?",
                ['\n[قيد #' . $entryId . ']', (int)($ctx['request']['id'] ?? 0)]
            );
        });
    }
}
