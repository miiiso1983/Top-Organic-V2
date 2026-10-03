<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Auth, Helpers, WorkflowEngine, WorkflowTemplateService, HrService};
use App\Core\Workflow\EntityRegistry;

class WorkflowController extends Controller
{
    public function index(): void
    {
        if (!Auth::can('workflow.create') && !Auth::can('workflow.decide')) {
            Response::abort(403, 'ليس لديك صلاحية الوصول لسير العمل');
        }
        $userId = (int) Auth::id();
        $this->view('workflows/index', [
            'title' => 'سير العمل والموافقات',
            'templates' => WorkflowTemplateService::getTemplatesForUser((int)($_SESSION['role_id'] ?? 0)),
            'myRequests' => WorkflowEngine::getMyRequests($userId),
            'pending' => WorkflowEngine::getPendingForUser($userId),
            'visible' => WorkflowEngine::getVisibleRequests($userId),
        ]);
    }

    /** صندوق وارد المعتمد — واجهة سريعة للقرار بضغطة واحدة */
    public function inbox(): void
    {
        $this->view('workflows/inbox', [
            'title' => 'صندوق الموافقات',
            'inbox' => \App\Core\Workflow\InboxService::forUser((int) Auth::id()),
        ]);
    }

    // ===== إدارة القوالب (Admin) =====
    public function templates(): void
    {
        $this->view('workflows/templates', [
            'title' => 'قوالب سير العمل',
            'templates' => WorkflowTemplateService::getAllTemplates(),
            'roles' => Database::query("SELECT * FROM roles ORDER BY level DESC")->fetchAll(),
        ]);
    }

    public function storeTemplate(): void
    {
        $id = WorkflowTemplateService::createTemplate($this->templateData(), (int) Auth::id());
        $this->flash('success', 'تم حفظ القالب');
        Response::redirect('/workflows/templates/' . $id . '/steps');
    }

    public function updateTemplate(string $id): void
    {
        WorkflowTemplateService::updateTemplate((int)$id, $this->templateData());
        $this->flash('success', 'تم تحديث القالب');
        Response::redirect('/workflows/templates');
    }

    public function destroyTemplate(string $id): void
    {
        WorkflowTemplateService::deleteTemplate((int)$id);
        $this->flash('success', 'تم حذف القالب');
        Response::redirect('/workflows/templates');
    }

    public function steps(string $id): void
    {
        $template = WorkflowTemplateService::getTemplate((int)$id);
        if (!$template) Response::abort(404, 'القالب غير موجود');
        $this->view('workflows/steps', [
            'title' => 'خطوات الموافقة: ' . $template['name'],
            'template' => $template,
            'steps' => WorkflowTemplateService::getSteps((int)$id),
            'roles' => Database::query("SELECT * FROM roles ORDER BY level DESC")->fetchAll(),
            'positions' => Database::query("SELECT id, title, parent_id FROM org_positions WHERE is_active = 1 ORDER BY title")->fetchAll(),
        ]);
    }

    public function storeStep(string $id): void
    {
        WorkflowTemplateService::addStep((int)$id, $this->stepData());
        $this->flash('success', 'تم إضافة الخطوة');
        Response::redirect('/workflows/templates/' . $id . '/steps');
    }

    public function updateStep(string $id, string $stepId): void
    {
        WorkflowTemplateService::updateStep((int)$stepId, $this->stepData());
        $this->flash('success', 'تم تحديث الخطوة');
        Response::redirect('/workflows/templates/' . $id . '/steps');
    }

    public function destroyStep(string $id, string $stepId): void
    {
        WorkflowTemplateService::deleteStep((int)$stepId);
        $this->flash('success', 'تم حذف الخطوة');
        Response::redirect('/workflows/templates/' . $id . '/steps');
    }

    // ===== الطلبات =====
    public function newRequest(): void
    {
        $this->view('workflows/new_request', [
            'title' => 'طلب جديد',
            'templates' => WorkflowTemplateService::getTemplatesForUser((int)($_SESSION['role_id'] ?? 0)),
        ]);
    }

    public function createRequestForm(string $templateId): void
    {
        $template = WorkflowTemplateService::getTemplate((int)$templateId);
        if (!$template || !WorkflowEngine::canCreate((int)$templateId, (int)($_SESSION['role_id'] ?? 0))) {
            Response::abort(403, 'لا تملك صلاحية إنشاء هذا الطلب');
        }
        $this->view('workflows/request_form', [
            'title' => 'إنشاء ' . $template['name'],
            'template' => $template,
            'request' => null,
            'employees' => $this->employeeOptionsForTemplate($template['code'] ?? ''),
            'departments' => $this->departmentOptions(),
            'purchaseItems' => $this->purchaseRequestItems(),
            'advanceLimit' => $template['code'] === 'hr_advance' ? WorkflowEngine::advanceLimitForUser((int) Auth::id()) : null,
        ]);
    }

    public function storeRequest(): void
    {
        $templateId = (int) $this->input('template_id');
        try {
            $template = WorkflowTemplateService::getTemplate($templateId);
            $data = $this->mergeUploadedFiles($_POST, $template['fields'] ?? []);
            $adjId = null;

            if (($template['code'] ?? '') === 'deduction_request') {
                $adjId = HrService::createAdjustmentFromWorkflowData($data, (int)Auth::id(), 'deduction');
                if (!$adjId) {
                    $this->flash('danger', 'بيانات الخصم غير صالحة: يجب تحديد موظف ومبلغ');
                    Response::redirect('/workflows/create/' . $templateId);
                    return;
                }
                $data['entity_id'] = $adjId;
                $data['entity_type'] = 'hr_adjustment';
            }

            $id = WorkflowEngine::createRequest($templateId, (int) Auth::id(), $data);
            WorkflowEngine::submitRequest($id, (int) Auth::id());

            if ($adjId) {
                EntityRegistry::bind('hr_adjustment', $adjId, $id);
            }

            if (($template['code'] ?? '') === 'purchase_request') {
                $this->createPurchaseRequestFromWeb($id, $data);
            }

            $this->flash('success', 'تم إنشاء وإرسال الطلب بنجاح');
            Response::redirect('/workflows/requests/' . $id);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            Response::redirect('/workflows/create/' . $templateId);
        }
    }

    private function purchaseRequestItems(): array
    {
        return Database::query(
            "SELECT i.id, i.name, u.name AS unit_name
             FROM items i
             LEFT JOIN units u ON u.id = i.unit_id
             WHERE i.is_active = 1 AND i.available_for_purchase_request = 1 ORDER BY i.name"
        )->fetchAll();
    }

    private function createPurchaseRequestFromWeb(int $requestId, array $data): void
    {
        $items = json_decode($data['items'] ?? '[]', true) ?: [];
        if (!is_array($items) || empty($items)) {
            throw new \RuntimeException('يجب إضافة صنف واحد على الأقل في طلب الشراء');
        }
        $departmentId = (int)($data['department_id'] ?? 0);
        $departmentName = $departmentId
            ? Database::query("SELECT name FROM departments WHERE id = ?", [$departmentId])->fetchColumn()
            : '';
        $no = Helpers::docNumber('PRQ', 'purchase_requests', 'request_no');
        Database::query(
            "INSERT INTO purchase_requests (request_no, department, notes, requested_by, workflow_request_id) VALUES (?,?,?,?,?)",
            [$no, (string)$departmentName, (string)($data['reason'] ?? ''), (int)Auth::id(), $requestId]
        );
        $prId = (int)Database::lastInsertId();
        foreach ($items as $it) {
            $itemId = (int)($it['item_id'] ?? 0);
            $qty = (float)($it['qty'] ?? 0);
            $notes = (string)($it['reason'] ?? '');
            if ($itemId <= 0 || $qty <= 0) continue;
            $itemName = Database::query("SELECT name FROM items WHERE id = ?", [$itemId])->fetchColumn();
            Database::query(
                "INSERT INTO purchase_request_items (request_id, item_id, new_item_name, qty, notes) VALUES (?,?,?,?,?)",
                [$prId, $itemId, $itemName, $qty, $notes]
            );
        }
        Database::query(
            "UPDATE requests SET data_json = ? WHERE id = ?",
            [json_encode(array_merge($data, ['purchase_request_id' => $prId, 'pr_no' => $no, 'department' => $departmentName]), JSON_UNESCAPED_UNICODE), $requestId]
        );
    }

    public function requests(): void
    {
        if (!Auth::can('workflow.create') && !Auth::can('workflow.decide')) {
            Response::abort(403, 'ليس لديك صلاحية عرض الطلبات');
        }
        $userId = (int) Auth::id();
        $this->view('workflows/requests', [
            'title' => 'الطلبات',
            'myRequests' => WorkflowEngine::getMyRequests($userId),
            'pending' => WorkflowEngine::getPendingForUser($userId),
            'visible' => WorkflowEngine::getVisibleRequests($userId),
        ]);
    }

    public function showRequest(string $id): void
    {
        $req = WorkflowEngine::getRequest((int)$id);
        if (!$req) Response::abort(404, 'الطلب غير موجود');
        $visible = WorkflowEngine::getVisibleRequests((int) Auth::id());
        $ids = array_column($visible, 'id');
        if (!in_array((int)$id, $ids, true)) {
            Response::abort(403, 'ليس لديك صلاحية عرض هذا الطلب');
        }
        $isGraph = !empty($req['workflow_version_id']);
        $steps = (!$isGraph && $req['status'] === 'pending')
            ? WorkflowEngine::resolveSteps((int)$req['template_id'], (int)$req['current_step'], (int)$id)
            : [];
        $canDecide = false;
        if ($isGraph && $req['status'] === 'pending') {
            $canDecide = \App\Core\Workflow\Engine::userHasPendingTask((int)$id, (int) Auth::id());
        } else {
            foreach ($steps as $s) {
                if (WorkflowEngine::userCanDecideStep($s, $req)) {
                    $canDecide = true; break;
                }
            }
        }
        $this->view('workflows/request_view', [
            'title' => $req['title'],
            'request' => $req,
            'history' => WorkflowEngine::getHistory((int)$id),
            'steps' => $steps,
            'graph' => $isGraph ? \App\Core\Workflow\Engine::graphForRequest((int)$id) : null,
            'canDecide' => $canDecide,
            'canEdit' => WorkflowEngine::canEditRequest((int)$id, (int) Auth::id()),
            'canCancel' => (int)$req['requester_id'] === (int) Auth::id() && in_array($req['status'], ['draft', 'pending', 'returned_for_edit'], true),
        ]);
    }

    public function editRequestForm(string $id): void
    {
        $req = WorkflowEngine::getRequest((int)$id);
        if (!$req) Response::abort(404, 'الطلب غير موجود');
        if (!WorkflowEngine::canEditRequest((int)$id, (int) Auth::id())) {
            Response::abort(403, 'لا يمكن تعديل هذا الطلب');
        }
        $advanceLimit = null;
        if ($req['template_code'] === 'hr_advance') {
            $advanceLimit = WorkflowEngine::advanceLimitForUser((int) Auth::id());
        } elseif ($req['template_code'] === 'hr_exceptional_advance') {
            $employeeId = (int)($req['data']['employee_id'] ?? 0);
            if ($employeeId) {
                $advanceLimit = WorkflowEngine::advanceLimitForEmployee($employeeId);
            }
        }
        $this->view('workflows/request_form', [
            'title' => 'تعديل ' . $req['template_name'],
            'template' => $req,
            'request' => $req,
            'employees' => $this->employeeOptionsForTemplate($req['template_code'] ?? ''),
            'departments' => $this->departmentOptions(),
            'purchaseItems' => $this->purchaseRequestItems(),
            'advanceLimit' => $advanceLimit,
        ]);
    }

    private function employeeOptions(): array
    {
        return Database::query(
            "SELECT id, full_name, emp_code FROM employees WHERE status = 'active' ORDER BY full_name"
        )->fetchAll();
    }

    private function employeeOptionsForTemplate(string $templateCode): array
    {
        if ($templateCode === 'deduction_request' || $templateCode === 'suspension_request') {
            return WorkflowEngine::subordinateEmployees((int) Auth::id());
        }
        if ($templateCode === 'hr_advance') {
            $userId = (int) Auth::id();
            return Database::query(
                "SELECT id, full_name, emp_code FROM employees WHERE user_id = ? AND status = 'active' ORDER BY full_name",
                [$userId]
            )->fetchAll();
        }
        return $this->employeeOptions();
    }

    private function departmentOptions(): array
    {
        return Database::query(
            "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name"
        )->fetchAll();
    }

    public function employeeAdvanceLimit(string $id): void
    {
        $employeeId = (int)$id;
        if (!$employeeId) {
            Response::json(['ok' => false, 'error' => 'معرّف الموظف مطلوب'], 422);
            return;
        }
        $employee = Database::query("SELECT id FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$employee) {
            Response::json(['ok' => false, 'error' => 'الموظف غير موجود'], 404);
            return;
        }
        Response::json(['ok' => true, 'data' => WorkflowEngine::advanceLimitForEmployee($employeeId)]);
    }

    public function submitRequest(string $id): void
    {
        try {
            $req = WorkflowEngine::getRequest((int)$id);
            if ($req && $req['status'] === 'draft') {
                $data = $this->mergeUploadedFiles($_POST, $req['fields']);
                WorkflowEngine::saveDraftData((int)$id, $data, (int) Auth::id());
            }
            WorkflowEngine::submitRequest((int)$id, (int) Auth::id());
            $this->flash('success', 'تم إرسال الطلب');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/workflows/requests/' . $id);
    }

    public function decideRequest(string $id): void
    {
        try {
            WorkflowEngine::decide((int)$id, (string) $this->input('decision'), (string) $this->input('notes'), (int) Auth::id());
            $this->flash('success', 'تم تسجيل القرار');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/workflows/requests/' . $id);
    }

    public function resubmitRequest(string $id): void
    {
        try {
            $req = WorkflowEngine::getRequest((int)$id);
            $fields = $req ? $req['fields'] : [];
            $data = $this->mergeUploadedFiles($_POST, $fields);
            WorkflowEngine::resubmit((int)$id, $data, (int) Auth::id());
            $this->flash('success', 'تم إعادة إرسال الطلب');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/workflows/requests/' . $id);
    }

    public function commentRequest(string $id): void
    {
        WorkflowEngine::addComment((int)$id, (string) $this->input('notes'), (int) Auth::id());
        $this->flash('success', 'تم إضافة التعليق');
        Response::redirect('/workflows/requests/' . $id);
    }

    public function cancelRequest(string $id): void
    {
        try {
            WorkflowEngine::cancelRequest((int)$id, (int) Auth::id());
            $this->flash('success', 'تم إلغاء الطلب');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/workflows/requests/' . $id);
    }

    public function updateRequest(string $id): void
    {
        try {
            $req = WorkflowEngine::getRequest((int)$id);
            $fields = $req ? $req['fields'] : [];
            $data = $this->mergeUploadedFiles($_POST, $fields);
            $data['notes'] = (string)($this->input('notes') ?? '');
            WorkflowEngine::editRequest((int)$id, $data, (int) Auth::id());
            $this->flash('success', 'تم حفظ التعديل');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/workflows/requests/' . $id);
    }

    private function templateData(): array
    {
        $fields = [];
        $names = (array) ($_POST['field_name'] ?? []);
        $labels = (array) ($_POST['field_label'] ?? []);
        $types = (array) ($_POST['field_type'] ?? []);
        $required = (array) ($_POST['field_required'] ?? []);
        $options = (array) ($_POST['field_options'] ?? []);
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name === '') continue;
            $fields[] = [
                'name' => $name,
                'label' => (string)($labels[$i] ?? $name),
                'type' => in_array($types[$i] ?? 'text', WorkflowTemplateService::FIELD_TYPES, true) ? ($types[$i] ?? 'text') : 'text',
                'required' => !empty($required[$i]),
                'options' => array_values(array_filter(array_map('trim', explode(',', $options[$i] ?? '')))),
            ];
        }
        return [
            'code' => (string) $this->input('code'),
            'name' => (string) $this->input('name'),
            'description' => (string) $this->input('description'),
            'icon' => (string) $this->input('icon', '<i class="fa-solid fa-check"></i>'),
            'fields' => $fields,
            'amount_field' => (string) $this->input('amount_field'),
            'allowed_roles' => (array) ($_POST['allowed_roles'] ?? []),
        ];
    }

    private function stepData(): array
    {
        return [
            'title' => (string) $this->input('title'),
            'description' => (string) $this->input('description'),
            'approver_type' => (string) $this->input('approver_type', 'position'),
            'job_title_id' => (int) $this->input('job_title_id', 0),
            'role_id' => (int) $this->input('role_id', 0),
            'min_amount' => (float) $this->input('min_amount', 0),
            'condition' => json_decode((string) $this->input('condition', '{}'), true) ?: [],
            'can_return_for_edit' => !empty($this->input('can_return_for_edit')),
            'is_final' => !empty($this->input('is_final')),
            'step_type' => (string) $this->input('step_type', 'sequential'),
            'required_count' => (int) $this->input('required_count', 1),
            'notify_channels' => [
                'push' => !empty($this->input('notify_push')) ? 1 : 0,
                'email' => !empty($this->input('notify_email')) ? 1 : 0,
                'whatsapp' => !empty($this->input('notify_whatsapp')) ? 1 : 0,
            ],
        ];
    }

    private function mergeUploadedFiles(array $post, array $fields): array
    {
        $data = $post;
        foreach ($fields as $f) {
            if (($f['type'] ?? '') !== 'file') continue;
            $name = $f['name'];
            if (!empty($_FILES[$name]['tmp_name'])) {
                $ext = pathinfo($_FILES[$name]['name'], PATHINFO_EXTENSION);
                $filename = 'workflow_' . time() . '_' . uniqid() . ($ext ? '.' . $ext : '');
                $dest = BASE_PATH . '/public/uploads/' . $filename;
                if (move_uploaded_file($_FILES[$name]['tmp_name'], $dest)) {
                    $data[$name] = $filename;
                }
            }
        }
        return $data;
    }
}
