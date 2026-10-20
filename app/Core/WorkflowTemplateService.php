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
        // القوالب (أنواع الطلبات) المتاحة للمستخدم مرتبطة حصراً بصلاحية دوره:
        // 1. الدور يملك صراحةً can_create=1 لهذا القالب تحديداً في template_allowed_roles
        //    (يُدار من شاشة "تعديل الدور" بلوحة التحكم، وهو المصدر الوحيد لظهور
        //    أو اختفاء نوع الطلب لهذا الدور بلا أي استثناء عام مخفي).
        // 2. أو المستخدم "مدير النظام" (صلاحية كاملة بلا شرط، مثل بقية صلاحيات النظام).
        // 3. طلب الإجازة (hr_leave) وطلب السلفة (hr_advance) متاحان دائماً لكل موظف بغض
        //    النظر عن إعدادات القالب، لأنهما حق ذاتي (Self-Service) وليسا مرتبطين بصلاحية دور محددة.
        return Database::query(
            "SELECT DISTINCT t.* FROM approval_templates t
             LEFT JOIN template_allowed_roles tar ON tar.template_id = t.id AND tar.role_id = ?
             LEFT JOIN roles r ON r.id = ?
             WHERE t.is_active = 1
               AND (
                 tar.can_create = 1
                 OR r.name = 'مدير النظام'
                 OR t.code IN ('hr_leave', 'hr_advance')
               )
             ORDER BY t.name",
            [$roleId, $roleId]
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

    private static function nextStepOrder(int $templateId): int
    {
        return 1 + (int) Database::query(
            "SELECT COALESCE(MAX(step_order), 0) AS m FROM approval_steps WHERE template_id = ?",
            [$templateId]
        )->fetch()['m'];
    }

    /**
     * إضافة خطوة/صف جديد. إذا مرر $data['step_order'] بقيمة مرحلة قائمة فعلاً لهذا القالب
     * (أي أنها تشارك خطوة أخرى موجودة بنفس الترتيب)، يُضاف الصف كبديل ضمن نفس المرحلة
     * (مثال: شرطان مختلفان لنفس المرحلة حسب نوع الطلب — نمط التفرع الشرطي المستخدم فعلاً
     * في hr_leave: أكثر من صف بنفس step_order بشرط condition_json مختلف). وإلا يُضاف كمرحلة جديدة بعد الأخيرة.
     */
    public static function addStep(int $templateId, array $data): int
    {
        $explicitOrder = (int)($data['step_order'] ?? 0);
        $order = ($explicitOrder > 0 && (int) Database::query(
            "SELECT COUNT(*) FROM approval_steps WHERE template_id = ? AND step_order = ?",
            [$templateId, $explicitOrder]
        )->fetchColumn() > 0) ? $explicitOrder : self::nextStepOrder($templateId);
        $approverType = self::normaliseApproverType($data['approver_type'] ?? 'position');
        Database::query(
            "INSERT INTO approval_steps (template_id, step_order, title, approver_type, description, job_title_id, role_id, min_amount, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $templateId, $order,
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

    /**
     * إعادة ترتيب مراحل الموافقة (السحب والإفلات). $groups مصفوفة من مصفوفات معرّفات
     * خطوة مرتّبة حسب المرحلة الجديدة المطلوبة مثل [[24,35],[25,26],[27]] — جميع معرّفات
     * المجموعة الواحدة (مرحلة واحدة ذات بدائل متعددة حسب شرط مختلف، نمط hr_leave) تأخذ
     * نفس step_order الجديد معاً ليبقى تجميعها كمرحلة واحدة بعد السحب، بدلاً من تفكيكها إلى
     * مراحل منفصلة متتالية (ما كان سيكسر شرط "أي من هؤلاء المعتمدين" لنفس المرحلة).
     */
    public static function reorderSteps(int $templateId, array $groups): void
    {
        $order = 1;
        foreach ($groups as $group) {
            foreach ((array)$group as $sid) {
                Database::query(
                    "UPDATE approval_steps SET step_order = ? WHERE id = ? AND template_id = ?",
                    [$order, (int)$sid, $templateId]
                );
            }
            $order++;
        }
    }

    /** نسخ خطوة موجودة وإضافتها في نهاية التسلسل — يسرّع بناء سلاسل موافقات متشابهة (مثل عدة مستويات إدارية متتالية) دون إعادة إدخال كل الحقول يدوياً */
    public static function duplicateStep(int $id): int
    {
        $s = self::getStep($id);
        if (!$s) return 0;
        $max = (int) Database::query(
            "SELECT COALESCE(MAX(step_order), 0) AS m FROM approval_steps WHERE template_id = ?",
            [$s['template_id']]
        )->fetch()['m'];
        Database::query(
            "INSERT INTO approval_steps (template_id, step_order, title, approver_type, description, job_title_id, role_id, min_amount, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $s['template_id'], $max + 1,
                (string)$s['title'] . ' (نسخة)',
                $s['approver_type'], $s['description'], $s['job_title_id'], $s['role_id'], $s['min_amount'],
                $s['condition_json'], $s['can_return_for_edit'], $s['is_final'], $s['step_type'], $s['required_count'], $s['notify_channels'],
            ]
        );
        return (int) Database::lastInsertId();
    }
}
