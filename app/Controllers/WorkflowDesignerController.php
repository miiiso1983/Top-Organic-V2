<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Auth};
use App\Core\Workflow\{FieldRegistry, ApproverResolver, ActionDispatcher};

/**
 * إدارة تعريفات Workflow + المصمم المرئي (Drag & Drop Builder)
 */
class WorkflowDesignerController extends Controller
{
    // ===== قائمة التعريفات =====
    public function index(): void
    {
        $defs = Database::query(
            "SELECT d.*, t.name AS template_name,
                    (SELECT COUNT(*) FROM workflow_versions v WHERE v.definition_id = d.id AND v.status = 'published') AS published_count
             FROM workflow_definitions d
             LEFT JOIN approval_templates t ON t.id = d.template_id
             ORDER BY d.priority ASC, d.id DESC"
        )->fetchAll();
        $this->view('workflows/designer_list', ['title' => 'محرك سير العمل الديناميكي', 'defs' => $defs]);
    }

    public function create(): void
    {
        $this->view('workflows/designer_form', [
            'title' => 'تعريف Workflow جديد',
            'def' => null,
            'templates' => Database::query("SELECT id, name, code FROM approval_templates WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'branches' => Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(),
        ]);
    }

    public function store(): void
    {
        $data = $this->definitionData();
        Database::query(
            "INSERT INTO workflow_definitions (code, name, description, request_type, template_id, branch_ids, priority, valid_from, valid_to, is_active, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$data['code'], $data['name'], $data['description'], $data['request_type'], $data['template_id'],
             $data['branch_ids'], $data['priority'], $data['valid_from'], $data['valid_to'], $data['is_active'], Auth::id()]
        );
        $id = (int) Database::lastInsertId();
        $this->flash('success', 'تم إنشاء التعريف — صمّم المخطط الآن');
        Response::redirect('/workflow-engine/' . $id . '/design');
    }

    public function update(string $id): void
    {
        $data = $this->definitionData();
        Database::query(
            "UPDATE workflow_definitions SET name=?, description=?, request_type=?, template_id=?, branch_ids=?,
                    priority=?, valid_from=?, valid_to=?, is_active=? WHERE id=?",
            [$data['name'], $data['description'], $data['request_type'], $data['template_id'], $data['branch_ids'],
             $data['priority'], $data['valid_from'], $data['valid_to'], $data['is_active'], (int)$id]
        );
        $this->flash('success', 'تم التحديث');
        Response::redirect('/workflow-engine');
    }

    public function destroy(string $id): void
    {
        Database::query("DELETE FROM workflow_definitions WHERE id = ?", [(int)$id]);
        $this->flash('success', 'تم الحذف');
        Response::redirect('/workflow-engine');
    }

    // ===== المصمم المرئي =====
    public function design(string $id): void
    {
        $def = Database::query("SELECT * FROM workflow_definitions WHERE id = ?", [(int)$id])->fetch();
        if (!$def) Response::abort(404, 'التعريف غير موجود');
        $version = $this->draftVersion((int)$id);
        $template = $def['template_id']
            ? Database::query("SELECT * FROM approval_templates WHERE id = ?", [(int)$def['template_id']])->fetch()
            : null;
        if ($template) $template['fields'] = json_decode($template['fields_json'] ?? '[]', true) ?: [];

        $this->view('workflows/designer', [
            'title' => 'مصمم: ' . $def['name'],
            'def' => $def,
            'version' => $version,
            'published' => Database::query(
                "SELECT * FROM workflow_versions WHERE definition_id = ? AND status = 'published' ORDER BY version_no DESC LIMIT 1",
                [(int)$id]
            )->fetch() ?: null,
        ]);
    }

    // ===== JSON: تحميل المخطط =====
    public function graphJson(string $versionId): void
    {
        $vid = (int)$versionId;
        Response::json(['ok' => true, 'data' => [
            'nodes' => Database::query("SELECT * FROM workflow_nodes WHERE version_id = ? ORDER BY id", [$vid])->fetchAll(),
            'edges' => Database::query("SELECT * FROM workflow_edges WHERE version_id = ? ORDER BY id", [$vid])->fetchAll(),
        ]]);
    }

    // ===== JSON: بيانات المصمم الوصفية (حقول/معتمدون/إجراءات) =====
    public function metaJson(string $id): void
    {
        $def = Database::query("SELECT * FROM workflow_definitions WHERE id = ?", [(int)$id])->fetch();
        if (!$def) { Response::json(['ok' => false, 'error' => 'غير موجود'], 404); return; }
        $template = $def['template_id']
            ? Database::query("SELECT * FROM approval_templates WHERE id = ?", [(int)$def['template_id']])->fetch()
            : null;
        if ($template) $template['fields'] = json_decode($template['fields_json'] ?? '[]', true) ?: [];

        Response::json(['ok' => true, 'data' => [
            'fields'      => FieldRegistry::fieldsFor((string)$def['request_type'], $template),
            'approver_types' => ApproverResolver::TYPES,
            'users'       => Database::query("SELECT id, full_name FROM users ORDER BY full_name LIMIT 300")->fetchAll(),
            'roles'       => Database::query("SELECT id, name FROM roles ORDER BY level DESC")->fetchAll(),
            'positions'   => Database::query("SELECT id, title FROM org_positions WHERE is_active = 1 ORDER BY title")->fetchAll(),
            'permissions' => Database::query("SELECT code, name FROM permissions ORDER BY module, code")->fetchAll(),
            'actions'     => ActionDispatcher::available(),
        ]]);
    }

    // ===== JSON: حفظ المخطط (استبدال كامل داخل النسخة) =====
    public function saveGraph(string $versionId): void
    {
        $vid = (int)$versionId;
        $version = Database::query("SELECT * FROM workflow_versions WHERE id = ?", [$vid])->fetch();
        if (!$version) { Response::json(['ok' => false, 'error' => 'النسخة غير موجودة'], 404); return; }
        if ($version['status'] === 'published') { Response::json(['ok' => false, 'error' => 'لا يمكن تعديل نسخة منشورة'], 422); return; }

        $payload = $this->jsonInput();
        $nodes = (array)($payload['nodes'] ?? []);
        $edges = (array)($payload['edges'] ?? []);

        $hasStart = false;
        foreach ($nodes as $n) { if (($n['node_type'] ?? '') === 'start') { $hasStart = true; break; } }
        if (!$hasStart) { Response::json(['ok' => false, 'error' => 'المخطط يحتاج عقدة بداية (Start)'], 422); return; }

        Database::query("DELETE FROM workflow_edges WHERE version_id = ?", [$vid]);
        Database::query("DELETE FROM workflow_nodes WHERE version_id = ?", [$vid]);

        $idMap = [];
        foreach ($nodes as $n) {
            $key = substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($n['node_key'] ?? '')), 0, 60);
            if ($key === '') $key = 'n' . (++$idMap['_seq'] ?? 1);
            Database::query(
                "INSERT INTO workflow_nodes (version_id, node_key, node_type, title, config_json, pos_x, pos_y)
                 VALUES (?,?,?,?,?,?,?)",
                [$vid, $key, (string)$n['node_type'], mb_substr((string)($n['title'] ?? $n['node_type']), 0, 150),
                 json_encode($n['config'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE),
                 (int)($n['pos_x'] ?? 0), (int)($n['pos_y'] ?? 0)]
            );
            $idMap[(string)($n['client_id'] ?? $key)] = (int) Database::lastInsertId();
        }
        foreach ($edges as $e) {
            $from = $idMap[(string)($e['from'] ?? '')] ?? null;
            $to = $idMap[(string)($e['to'] ?? '')] ?? null;
            if (!$from || !$to) continue;
            Database::query(
                "INSERT INTO workflow_edges (version_id, from_node_id, to_node_id, handle, label)
                 VALUES (?,?,?,?,?)",
                [$vid, $from, $to, in_array($e['handle'] ?? '', ['yes','no'], true) ? $e['handle'] : 'default',
                 mb_substr((string)($e['label'] ?? ''), 0, 80)]
            );
        }
        Response::json(['ok' => true, 'map' => $idMap]);
    }

    // ===== JSON: نشر النسخة =====
    public function publish(string $versionId): void
    {
        $vid = (int)$versionId;
        $version = Database::query("SELECT * FROM workflow_versions WHERE id = ?", [$vid])->fetch();
        if (!$version) { Response::json(['ok' => false, 'error' => 'النسخة غير موجودة'], 404); return; }
        $hasNodes = Database::query("SELECT 1 FROM workflow_nodes WHERE version_id = ? LIMIT 1", [$vid])->fetch();
        if (!$hasNodes) { Response::json(['ok' => false, 'error' => 'احفظ المخطط قبل النشر'], 422); return; }

        Database::query(
            "UPDATE workflow_versions SET status = 'archived' WHERE definition_id = ? AND status = 'published'",
            [(int)$version['definition_id']]
        );
        Database::query(
            "UPDATE workflow_versions SET status = 'published', published_at = NOW(), published_by = ? WHERE id = ?",
            [Auth::id(), $vid]
        );
        // نسخة مسودة جديدة للتعديلات المستقبلية
        $nextNo = (int)Database::query(
            "SELECT COALESCE(MAX(version_no), 0) + 1 FROM workflow_versions WHERE definition_id = ?",
            [(int)$version['definition_id']]
        )->fetchColumn();
        Database::query(
            "INSERT INTO workflow_versions (definition_id, version_no, status) VALUES (?,?,'draft')",
            [(int)$version['definition_id'], $nextNo]
        );
        Response::json(['ok' => true, 'published_version' => (int)$version['version_no'], 'new_draft' => (int)Database::lastInsertId()]);
    }

    // ===== cron: معالجة SLA والتصعيد (مسار عام محمي بتوكن) =====
    public function cronEscalate(): void
    {
        $stored = (string)\App\Core\Settings::get('workflow_cron_token', '');
        if ($stored === '') {
            $stored = bin2hex(random_bytes(20));
            \App\Core\Settings::set('workflow_cron_token', $stored);
        }
        $token = (string)($_GET['token'] ?? '');
        if (!hash_equals($stored, $token)) {
            Response::abort(403, 'توكن غير صالح');
        }
        $result = \App\Core\Workflow\EscalationService::run();
        // صيانة PTT ضمن نفس النداء الدوري (احتفاظ + تفريغ تلقائي)
        try {
            $result['ptt_purged'] = \App\Core\Ptt\PttService::purgeOld();
            $result['ptt_transcribed'] = \App\Core\Ptt\PttAiService::cronAutoTranscribe(5);
        } catch (\Throwable $e) {
            $result['ptt_error'] = $e->getMessage();
        }
        // إرسال إشعارات Push المعلقة
        try {
            $result['push'] = \App\Core\Push\PushSender::processQueue(50);
        } catch (\Throwable $e) {
            $result['push_error'] = $e->getMessage();
        }
        Response::json(['ok' => true, 'result' => $result]);
    }

    // ===== إدارة التفويضات (البديل أثناء الإجازة) =====
    public function delegations(): void
    {
        $this->view('workflows/delegations', [
            'title' => 'التفويضات والبدلاء',
            'delegations' => Database::query(
                "SELECT d.*, uf.full_name AS from_name, ut.full_name AS to_name
                 FROM approval_delegations d
                 JOIN users uf ON uf.id = d.from_user_id
                 JOIN users ut ON ut.id = d.to_user_id
                 ORDER BY d.id DESC"
            )->fetchAll(),
            'users' => Database::query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll(),
            'definitions' => Database::query("SELECT request_type FROM workflow_definitions WHERE is_active = 1")->fetchAll(),
        ]);
    }

    public function storeDelegation(): void
    {
        $from = (int)$this->input('from_user_id');
        $to = (int)$this->input('to_user_id');
        if (!$from || !$to || $from === $to) {
            $this->flash('danger', 'اختر مستخدمَين مختلفَين');
            Response::redirect('/workflow-engine/delegations');
        }
        Database::query(
            "INSERT INTO approval_delegations (from_user_id, to_user_id, scope, valid_from, valid_to, is_active, created_by)
             VALUES (?,?,?,?,?,1,?)",
            [$from, $to, (string)$this->input('scope', 'all') ?: 'all',
             (string)$this->input('valid_from'), (string)$this->input('valid_to'), Auth::id()]
        );
        $this->flash('success', 'تم إنشاء التفويض');
        Response::redirect('/workflow-engine/delegations');
    }

    public function toggleDelegation(string $id): void
    {
        Database::query("UPDATE approval_delegations SET is_active = 1 - is_active WHERE id = ?", [(int)$id]);
        $this->flash('success', 'تم تحديث الحالة');
        Response::redirect('/workflow-engine/delegations');
    }

    public function destroyDelegation(string $id): void
    {
        Database::query("DELETE FROM approval_delegations WHERE id = ?", [(int)$id]);
        $this->flash('success', 'تم حذف التفويض');
        Response::redirect('/workflow-engine/delegations');
    }

    // ===== أدوات خاصة =====
    private function draftVersion(int $definitionId): array
    {
        $v = Database::query(
            "SELECT * FROM workflow_versions WHERE definition_id = ? AND status = 'draft' ORDER BY version_no DESC LIMIT 1",
            [$definitionId]
        )->fetch();
        if ($v) return $v;
        Database::query("INSERT INTO workflow_versions (definition_id, version_no, status) VALUES (?,1,'draft')", [$definitionId]);
        return Database::query("SELECT * FROM workflow_versions WHERE id = ?", [(int)Database::lastInsertId()])->fetch();
    }

    private function definitionData(): array
    {
        $templateId = (int)$this->input('template_id', 0);
        $requestType = (string)$this->input('request_type', '');
        if ($requestType === '' && $templateId) {
            $requestType = (string)Database::query("SELECT code FROM approval_templates WHERE id = ?", [$templateId])->fetchColumn();
        }
        $branchIds = array_values(array_map('intval', (array)$this->input('branch_ids', [])));
        return [
            'code' => preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$this->input('code')),
            'name' => (string)$this->input('name'),
            'description' => (string)$this->input('description', ''),
            'request_type' => $requestType ?: 'generic',
            'template_id' => $templateId ?: null,
            'branch_ids' => $branchIds ? json_encode($branchIds) : null,
            'priority' => (int)$this->input('priority', 100),
            'valid_from' => $this->input('valid_from') ?: null,
            'valid_to' => $this->input('valid_to') ?: null,
            'is_active' => $this->input('is_active') ? 1 : 0,
        ];
    }
}
