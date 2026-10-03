<?php
namespace App\Core;

/**
 * إدارة قوالب سير العمل والخطوات
 */
class WorkflowTemplateService
{
    public const FIELD_TYPES = ['text', 'number', 'date', 'select', 'textarea', 'checkbox', 'file', 'items_list'];

    public static function getTemplatesForUser(int $roleId): array
    {
        // القوالب المتاحة للمستخدم:
        // 1. الدور يملك صراحةً can_create=1 في template_allowed_roles
        // 2. أو الدور إداري (مستوى >= 60 أو اسم الدور يحتوي على "مدير").
        //    بعض قواعد البيانات القديمة تمنح المدير صلاحية الموافقة فقط ولا
        //    تحتوي على صف can_create لكل قالب، لذلك لا يجب أن تعيد الشاشة قائمة فارغة.
        // 3. أو يملك المستخدم صلاحية الموبايل/الإنشاء المعلنة في واجهة التطبيق.
        return Database::query(
            "SELECT DISTINCT t.* FROM approval_templates t
             LEFT JOIN template_allowed_roles tar ON tar.template_id = t.id AND tar.role_id = ?
             LEFT JOIN roles r ON r.id = ?
             WHERE t.is_active = 1
               AND (
                 tar.can_create = 1
                 OR (
                   (
                     COALESCE(r.level, 0) >= 60
                     OR r.name LIKE '%مدير%'
                     OR EXISTS (
                       SELECT 1 FROM role_permissions rp
                       JOIN permissions p ON p.id = rp.permission_id
                       WHERE rp.role_id = ? AND p.code IN ('workflow.create', 'mobile.approvals')
                     )
                   )
                 )
               )
             ORDER BY t.name",
            [$roleId, $roleId, $roleId]
        )->fetchAll();
    }

    public static function getTemplate(int $id): ?array
    {
        $t = Database::query("SELECT * FROM approval_templates WHERE id = ?", [$id])->fetch();
        if (!$t) return null;
        $t['fields'] = json_decode($t['fields_json'] ?? '[]', true) ?: [];
        return $t;
    }

    public static function getTemplateByCode(string $code): ?array
    {
        $t = Database::query("SELECT * FROM approval_templates WHERE code = ?", [$code])->fetch();
        if (!$t) return null;
        $t['fields'] = json_decode($t['fields_json'] ?? '[]', true) ?: [];
        return $t;
    }

    public static function getAllTemplates(): array
    {
        return Database::query("SELECT * FROM approval_templates ORDER BY name")->fetchAll();
    }

    public static function createTemplate(array $data, int $createdBy): int
    {
        $code = Helpers::e((string)($data['code'] ?? ''));
        if ($code === '') $code = 'tpl_' . time();
        Database::query(
            "INSERT INTO approval_templates (code, name, description, icon, fields_json, amount_field, created_by)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), icon=VALUES(icon), fields_json=VALUES(fields_json), amount_field=VALUES(amount_field), created_by=VALUES(created_by)",
            [
                $code, (string)($data['name'] ?? ''),
                (string)($data['description'] ?? ''),
                (string)($data['icon'] ?? '<i class="fa-solid fa-check"></i>'),
                json_encode($data['fields'] ?? [], JSON_UNESCAPED_UNICODE),
                ($data['amount_field'] ?? '') ?: null,
                $createdBy
            ]
        );
        $id = (int) Database::lastInsertId();
        if ($id === 0) {
            $existing = Database::query("SELECT id FROM approval_templates WHERE code = ?", [$code])->fetch();
            $id = (int)($existing['id'] ?? 0);
        }
        self::saveAllowedRoles($id, $data['allowed_roles'] ?? []);
        return $id;
    }

    public static function updateTemplate(int $id, array $data): bool
    {
        $t = self::getTemplate($id);
        if (!$t) return false;
        Database::query(
            "UPDATE approval_templates SET name = ?, description = ?, icon = ?, fields_json = ?, amount_field = ? WHERE id = ?",
            [
                (string)($data['name'] ?? $t['name']),
                (string)($data['description'] ?? $t['description']),
                (string)($data['icon'] ?? $t['icon']),
                json_encode($data['fields'] ?? $t['fields'], JSON_UNESCAPED_UNICODE),
                ($data['amount_field'] ?? '') ?: null,
                $id
            ]
        );
        self::saveAllowedRoles($id, $data['allowed_roles'] ?? []);
        return true;
    }

    public static function deleteTemplate(int $id): void
    {
        Database::query("DELETE FROM approval_templates WHERE id = ?", [$id]);
    }

    public static function getAllowedRoles(int $templateId): array
    {
        return Database::query(
            "SELECT tar.*, r.name AS role_name FROM template_allowed_roles tar
             JOIN roles r ON r.id = tar.role_id WHERE tar.template_id = ?",
            [$templateId]
        )->fetchAll();
    }

    public static function saveAllowedRoles(int $templateId, array $roles): void
    {
        Database::query("DELETE FROM template_allowed_roles WHERE template_id = ?", [$templateId]);
        foreach ($roles as $r) {
            $roleId = (int)($r['role_id'] ?? 0);
            if (!$roleId) continue;
            Database::query(
                "INSERT INTO template_allowed_roles (template_id, role_id, can_create, can_view_all) VALUES (?,?,?,?)",
                [$templateId, $roleId, !empty($r['can_create']) ? 1 : 0, !empty($r['can_view_all']) ? 1 : 0]
            );
        }
    }

    public static function getSteps(int $templateId): array
    {
        return Database::query(
            "SELECT s.*, p.title AS job_title, r.name AS role_name
             FROM approval_steps s
             LEFT JOIN org_positions p ON p.id = s.job_title_id
             LEFT JOIN roles r ON r.id = s.role_id
             WHERE s.template_id = ? ORDER BY s.step_order",
            [$templateId]
        )->fetchAll();
    }

    public static function getStep(int $id): ?array
    {
        return Database::query("SELECT * FROM approval_steps WHERE id = ?", [$id])->fetch() ?: null;
    }

    public static function addStep(int $templateId, array $data): int
    {
        $max = (int) Database::query(
            "SELECT COALESCE(MAX(step_order), 0) AS m FROM approval_steps WHERE template_id = ?",
            [$templateId]
        )->fetch()['m'];
        $approverType = self::normaliseApproverType($data['approver_type'] ?? 'position');
        Database::query(
            "INSERT INTO approval_steps (template_id, step_order, title, approver_type, description, job_title_id, role_id, min_amount, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $templateId, $max + 1,
                (string)($data['title'] ?? ''),
                $approverType,
                (string)($data['description'] ?? ''),
                $approverType === 'position' ? ($data['job_title_id'] ?: null) : null,
                $approverType === 'role' ? ($data['role_id'] ?: null) : null,
                (float)($data['min_amount'] ?? 0),
                json_encode($data['condition'] ?? [], JSON_UNESCAPED_UNICODE),
                !empty($data['can_return_for_edit']) ? 1 : 0,
                !empty($data['is_final']) ? 1 : 0,
                in_array($data['step_type'] ?? '', ['sequential', 'parallel'], true) ? ($data['step_type'] ?? 'sequential') : 'sequential',
                (int)($data['required_count'] ?? 1),
                json_encode($data['notify_channels'] ?? ['push' => 1], JSON_UNESCAPED_UNICODE),
            ]
        );
        return (int) Database::lastInsertId();
    }

    public static function updateStep(int $id, array $data): bool
    {
        $s = self::getStep($id);
        if (!$s) return false;
        $channels = $data['notify_channels'] ?? (json_decode($s['notify_channels'] ?? '{}', true) ?: ['push' => 1]);
        if (empty($channels)) {
            $channels = ['push' => 1];
        }
        $approverType = self::normaliseApproverType($data['approver_type'] ?? ($s['approver_type'] ?? 'position'));
        Database::query(
            "UPDATE approval_steps SET title = ?, approver_type = ?, description = ?, job_title_id = ?, role_id = ?, min_amount = ?, condition_json = ?, can_return_for_edit = ?, is_final = ?, step_type = ?, required_count = ?, notify_channels = ? WHERE id = ?",
            [
                (string)($data['title'] ?? $s['title']),
                $approverType,
                (string)($data['description'] ?? $s['description']),
                $approverType === 'position' ? ($data['job_title_id'] ?: null) : null,
                $approverType === 'role' ? ($data['role_id'] ?: null) : null,
                (float)($data['min_amount'] ?? $s['min_amount']),
                json_encode($data['condition'] ?? (json_decode($s['condition_json'] ?? '{}', true) ?: []), JSON_UNESCAPED_UNICODE),
                !empty($data['can_return_for_edit']) ? 1 : 0,
                !empty($data['is_final']) ? 1 : 0,
                in_array($data['step_type'] ?? ($s['step_type'] ?? 'sequential'), ['sequential', 'parallel'], true) ? ($data['step_type'] ?? ($s['step_type'] ?? 'sequential')) : 'sequential',
                (int)($data['required_count'] ?? ($s['required_count'] ?? 1)),
                json_encode($channels, JSON_UNESCAPED_UNICODE),
                $id
            ]
        );
        return true;
    }

    private static function normaliseApproverType(?string $type): string
    {
        return in_array($type, ['position', 'role', 'direct_manager', 'department_manager'], true) ? $type : 'position';
    }

    public static function deleteStep(int $id): void
    {
        $s = self::getStep($id);
        if ($s) {
            Database::query("DELETE FROM approval_steps WHERE id = ?", [$id]);
            Database::query(
                "UPDATE approval_steps SET step_order = step_order - 1
                 WHERE template_id = ? AND step_order > ?",
                [$s['template_id'], $s['step_order']]
            );
        }
    }

    public static function reorderSteps(int $templateId, array $stepIds): void
    {
        $order = 1;
        foreach ($stepIds as $sid) {
            Database::query(
                "UPDATE approval_steps SET step_order = ? WHERE id = ? AND template_id = ?",
                [$order++, (int)$sid, $templateId]
            );
        }
    }
}
