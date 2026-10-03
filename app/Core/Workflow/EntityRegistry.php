<?php
namespace App\Core\Workflow;

use App\Core\{Database, HrService, StockService, MaterialRequestService};

/**
 * سجل الوحدات (Entity Registry) — يربط أي كيان في النظام بمحرك Workflow.
 *
 * الوحدة تسجّل نفسها مرة واحدة:
 *   EntityRegistry::register('maintenance_request', [
 *       'table' => 'maintenance_requests', 'label' => 'طلب صيانة',
 *       'on_outcome' => function ($request, $status, $actorId, $notes) { ... },
 *   ]);
 *
 * ثم تبدأ المسار برمجياً:
 *   Engine::startForEntity('maintenance_request', $id, $userId, ['amount' => 500]);
 */
final class EntityRegistry
{
    /** @var array<string, array> */
    private static array $adapters = [];
    private static bool $booted = false;

    public static function register(string $entityType, array $adapter): void
    {
        self::$adapters[$entityType] = $adapter + ['table' => null, 'label' => $entityType, 'on_outcome' => null];
    }

    public static function adapter(string $entityType): ?array
    {
        self::bootDefaults();
        return self::$adapters[$entityType] ?? null;
    }

    /** ربط كيان بطلب */
    public static function bind(string $entityType, int $entityId, int $requestId): void
    {
        Database::query(
            "INSERT INTO workflow_entity_links (entity_type, entity_id, request_id) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE request_id = VALUES(request_id)",
            [$entityType, $entityId, $requestId]
        );
    }

    /** الكيان المرتبط بطلب (من جدول الربط أو من data_json للتوافق مع HR) */
    public static function requestLink(int $requestId): ?array
    {
        $row = Database::query(
            "SELECT entity_type, entity_id FROM workflow_entity_links WHERE request_id = ? LIMIT 1",
            [$requestId]
        )->fetch();
        if ($row) return $row;
        $data = Database::query("SELECT data_json FROM requests WHERE id = ?", [$requestId])->fetchColumn();
        $payload = json_decode((string)$data, true) ?: [];
        if (!empty($payload['entity_type']) && !empty($payload['entity_id'])) {
            return ['entity_type' => $payload['entity_type'], 'entity_id' => (int)$payload['entity_id']];
        }
        return null;
    }

    public static function workflowRequestId(string $entityType, int $entityId): ?int
    {
        $id = Database::query(
            "SELECT request_id FROM workflow_entity_links WHERE entity_type = ? AND entity_id = ?",
            [$entityType, $entityId]
        )->fetchColumn();
        return $id ? (int)$id : null;
    }

    /** صف الكيان المرتبط (لسياق الشروط entity.*) */
    public static function entityRow(string $entityType, int $entityId): ?array
    {
        $adapter = self::adapter($entityType);
        if (!$adapter || empty($adapter['table'])) return null;
        return Database::query(
            "SELECT * FROM `{$adapter['table']}` WHERE id = ?",
            [$entityId]
        )->fetch() ?: null;
    }

    /** إطلاق حدث النتيجة على الوحدة المالكة للكيان */
    public static function fireOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $link = self::requestLink((int)$request['id']);
        if (!$link) return;
        $adapter = self::adapter($link['entity_type']);
        if (!$adapter || !is_callable($adapter['on_outcome'])) return;
        try {
            ($adapter['on_outcome'])($request, $status, (int)$link['entity_id'], $actorId, $notes);
        } catch (\Throwable $e) {
            error_log('EntityRegistry outcome failed: ' . $e->getMessage());
        }
    }

    /** كتالوج حقول الكيان للشروط: entity.<column> */
    public static function fieldCatalog(string $entityType): array
    {
        $adapter = self::adapter($entityType);
        if (!$adapter || empty($adapter['table'])) return [];
        try {
            $cols = Database::query("SHOW COLUMNS FROM `{$adapter['table']}`")->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($cols as $c) {
            $type = 'text';
            if (preg_match('/int|decimal|float|double/i', $c['Type'])) $type = 'number';
            elseif (preg_match('/date|time/i', $c['Type'])) $type = 'date';
            $out[] = ['name' => 'entity.' . $c['Field'], 'label' => $adapter['label'] . ': ' . $c['Field'], 'type' => $type];
        }
        return $out;
    }

    /** التسجيلات المدمجة للوحدات الحالية */
    private static function bootDefaults(): void
    {
        if (self::$booted) return;
        self::$booted = true;

        // كيانات HR: النتائج تُدار عبر HrWorkflowService (تفادي الإطلاق المزدوج) — هنا للكتالوج والسياق فقط
        self::register('leave_request',   ['table' => 'leave_requests',  'label' => 'طلب إجازة']);
        self::register('salary_advance',  ['table' => 'salary_advances', 'label' => 'طلب سلفة']);
        self::register('hr_adjustment',   ['table' => 'hr_adjustments',  'label' => 'طلب تسوية']);

        // تحويل مخزني
        self::register('stock_transfer', [
            'table' => 'stock_transfers', 'label' => 'تحويل مخزني',
            'on_outcome' => function (array $request, string $status, int $entityId, int $actorId): void {
                Database::query("UPDATE stock_transfers SET status = ?, approved_by = ? WHERE id = ?", [$status, $actorId, $entityId]);
                if ($status === 'approved') StockService::deliverTransfer($entityId);
            },
        ]);

        // مناقلة طلب مواد (مخازن)
        self::register('material_request_transfer', [
            'table' => 'stock_transfers', 'label' => 'مناقلة طلب مواد',
            'on_outcome' => function (array $request, string $status, int $entityId, int $actorId): void {
                Database::query("UPDATE stock_transfers SET status = ?, approved_by = ? WHERE id = ?", [$status, $actorId, $entityId]);
                if ($status === 'approved') MaterialRequestService::onTransferApproved($entityId);
            },
        ]);

        // طلب شراء
        self::register('purchase_request', [
            'table' => 'purchase_requests', 'label' => 'طلب شراء',
            'on_outcome' => function (array $request, string $status, int $entityId, int $actorId): void {
                Database::query("UPDATE purchase_requests SET status = ?, approved_by = ? WHERE id = ?", [$status, $actorId, $entityId]);
                if ($status === 'approved') MaterialRequestService::onPurchaseRequestApproved($entityId);
            },
        ]);

        // مصروف
        self::register('expense', [
            'table' => 'expenses', 'label' => 'مصروف',
            'on_outcome' => function (array $request, string $status, int $entityId, int $actorId): void {
                Database::query("UPDATE expenses SET status = ?, approved_by = ? WHERE id = ?", [$status, $actorId, $entityId]);
            },
        ]);

        // عرض سعر (حالات خاصة)
        self::register('quotation', [
            'table' => 'quotations', 'label' => 'عرض سعر',
            'on_outcome' => function (array $request, string $status, int $entityId, int $actorId): void {
                $st = $status === 'approved' ? 'accepted' : 'rejected';
                Database::query("UPDATE quotations SET status = ?, approved_by = ? WHERE id = ?", [$st, $actorId, $entityId]);
            },
        ]);
    }
}
