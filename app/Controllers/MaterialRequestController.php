<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Database, MaterialRequestService, NotificationService, Response, WorkflowEngine, CostCenterService};

class MaterialRequestController extends Controller
{
    public function index(): void
    {
        $filters = [
            'status' => $this->input('status'),
            'branch_id' => $this->input('branch_id'),
            'priority' => $this->input('priority'),
            'q' => $this->input('q'),
        ];
        if (!Auth::can('material_request.view_all')) {
            $filters['requested_by'] = Auth::id();
        }
        $this->view('material_requests/index', [
            'title' => 'طلبات المواد والمشتريات',
            'requests' => MaterialRequestService::list(array_filter($filters)),
            'branches' => Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->view('material_requests/form', [
            'title' => 'طلب مواد جديد',
            'request' => null,
            'items' => [],
            'branches' => Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'departments' => Database::query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'costCenters' => CostCenterService::dropdown(),
            'productionOrders' => Database::query("SELECT id, production_no, item_id FROM production_orders WHERE status IN ('planned','in_progress') ORDER BY id DESC")->fetchAll(),
            'kitchenSections' => Database::query("SELECT id, name FROM kitchen_sections WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'warehouses' => Database::query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'itemsList' => Database::query("SELECT id, barcode, name, unit_id FROM items WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'units' => Database::query("SELECT id, name FROM units ORDER BY name")->fetchAll(),
            'itemCategories' => Database::query("SELECT id, name, type FROM item_categories ORDER BY name")->fetchAll(),
        ]);
    }

    public function store(): void
    {
        try {
            $id = MaterialRequestService::create((int)Auth::id(), $_POST);
            $mr = MaterialRequestService::find($id);
            NotificationService::mrCreated($id, (int)Auth::id(), $mr['request_no'] ?? '#');
            $this->flash('success', 'تم إنشاء طلب المواد');
            Response::redirect('/material-requests/' . $id);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            Response::redirect('/material-requests/create');
        }
    }

    public function show(string $id): void
    {
        $mr = MaterialRequestService::find((int)$id);
        if (!$mr) Response::abort(404);
        $items = MaterialRequestService::items((int)$id);
        $fulfillment = MaterialRequestService::fulfillment((int)$id);
        $workflow = $mr['workflow_request_id'] ? WorkflowEngine::getRequest((int)$mr['workflow_request_id']) : null;
        $history = $mr['workflow_request_id'] ? WorkflowEngine::getHistory((int)$mr['workflow_request_id']) : [];
        $audit = Database::query("SELECT * FROM material_request_audit_logs WHERE material_request_id = ? ORDER BY id DESC", [(int)$id])->fetchAll();
        $purchaseRequest = $mr['purchase_request_id']
            ? Database::query("SELECT * FROM purchase_requests WHERE id = ?", [(int)$mr['purchase_request_id']])->fetch()
            : null;
        $purchaseRequests = Database::query(
            "SELECT pr.* FROM purchase_requests pr WHERE pr.material_request_id = ? ORDER BY pr.id DESC",
            [(int)$id]
        )->fetchAll();
        $transfers = Database::query(
            "SELECT t.*, fw.name AS from_warehouse, tw.name AS to_warehouse
             FROM stock_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             WHERE t.material_request_id = ? ORDER BY t.id DESC",
            [(int)$id]
        )->fetchAll();
        $this->view('material_requests/show', [
            'title' => 'طلب مواد ' . $mr['request_no'],
            'mr' => $mr, 'items' => $items, 'fulfillment' => $fulfillment,
            'workflow' => $workflow, 'history' => $history, 'audit' => $audit,
            'purchaseRequest' => $purchaseRequest, 'purchaseRequests' => $purchaseRequests, 'transfers' => $transfers,
        ]);
    }

    public function edit(string $id): void
    {
        $mr = MaterialRequestService::find((int)$id);
        if (!$mr || $mr['status'] !== 'draft') Response::abort(404);
        $this->view('material_requests/form', [
            'title' => 'تعديل طلب مواد',
            'request' => $mr,
            'items' => MaterialRequestService::items((int)$id),
            'branches' => Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'departments' => Database::query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'costCenters' => CostCenterService::dropdown(),
            'productionOrders' => Database::query("SELECT id, production_no, item_id FROM production_orders WHERE status IN ('planned','in_progress') ORDER BY id DESC")->fetchAll(),
            'kitchenSections' => Database::query("SELECT id, name FROM kitchen_sections WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'warehouses' => Database::query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'itemsList' => Database::query("SELECT id, barcode, name, unit_id FROM items WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'units' => Database::query("SELECT id, name FROM units ORDER BY name")->fetchAll(),
            'itemCategories' => Database::query("SELECT id, name, type FROM item_categories ORDER BY name")->fetchAll(),
        ]);
    }

    public function update(string $id): void
    {
        try {
            MaterialRequestService::update((int)$id, $_POST);
            $this->flash('success', 'تم تحديث الطلب');
            Response::redirect('/material-requests/' . $id);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            Response::redirect('/material-requests/' . $id . '/edit');
        }
    }

    public function submit(string $id): void
    {
        try {
            MaterialRequestService::submit((int)$id, (int)Auth::id());
            $this->flash('success', 'تم إرسال الطلب للموافقة');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/material-requests/' . $id);
    }

    public function cancel(string $id): void
    {
        try {
            MaterialRequestService::cancel((int)$id, (int)Auth::id());
            $this->flash('success', 'تم إلغاء الطلب');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/material-requests/' . $id);
    }

    public function fulfillmentPlan(string $id): void
    {
        $mr = MaterialRequestService::find((int)$id);
        if (!$mr || $mr['status'] !== 'approved') Response::abort(404);
        $this->view('material_requests/fulfillment', [
            'title' => 'خطة تلبية طلب ' . $mr['request_no'],
            'mr' => $mr,
            'plan' => MaterialRequestService::fulfillmentPlan((int)$id),
            'warehouses' => Database::query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name")->fetchAll(),
        ]);
    }

    public function executeFulfillment(string $id): void
    {
        try {
            $plan = $_POST['plan'] ?? [];
            MaterialRequestService::executeFulfillment((int)$id, $plan, (int)Auth::id());
            $this->flash('success', 'تم تنفيذ التلبية');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/material-requests/' . $id);
    }

    public function transferSignature(string $transferId): void
    {
        $t = Database::query(
            "SELECT t.*, fw.name AS from_warehouse, tw.name AS to_warehouse, mr.request_no, mr.id AS mr_id
             FROM stock_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             LEFT JOIN material_requests mr ON mr.id = t.material_request_id
             WHERE t.id = ?",
            [(int)$transferId]
        )->fetch();
        if (!$t || !in_array($t['status'], ['approved','prepared'], true)) Response::abort(404);
        $items = Database::query(
            "SELECT sti.*, i.name AS item_name, u.name AS unit_name
             FROM stock_transfer_items sti
             JOIN items i ON i.id = sti.item_id
             LEFT JOIN units u ON u.id = sti.unit_id
             WHERE sti.transfer_id = ?",
            [(int)$transferId]
        )->fetchAll();
        $this->view('material_requests/transfer_signature', [
            'title' => 'تسليم مناقلة ' . $t['transfer_no'],
            'transfer' => $t, 'items' => $items,
        ]);
    }

    public function prepareTransfer(string $transferId): void
    {
        try {
            MaterialRequestService::prepareTransfer((int)$transferId, (int)Auth::id());
            $this->flash('success', 'تم تجهيز المناقلة');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/material-requests/transfer/' . $transferId . '/signature');
    }

    public function deliverTransfer(string $transferId): void
    {
        try {
            $recipient = (string) $this->input('recipient_name');
            $signature = (string) $this->input('recipient_signature');
            if (empty($recipient) || empty($signature)) {
                throw new \RuntimeException('اسم المستلم والتوقيع مطلوبان');
            }
            MaterialRequestService::deliverTransfer((int)$transferId, $recipient, $signature, (int)Auth::id());
            $this->flash('success', 'تم تسليم المناقلة وتوقيع المستلم');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/material-requests');
    }

    public function apiList(): void
    {
        $filters = [
            'status' => $this->input('status'),
            'branch_id' => $this->input('branch_id'),
            'priority' => $this->input('priority'),
        ];
        if (!Auth::can('material_request.view_all')) {
            $filters['requested_by'] = Auth::id();
        }
        Response::json(['ok' => true, 'data' => MaterialRequestService::list(array_filter($filters))]);
    }

    public function apiShow(string $id): void
    {
        $mr = MaterialRequestService::find((int)$id);
        if (!$mr) {
            Response::json(['ok' => false, 'error' => 'Not found'], 404);
            return;
        }
        Response::json(['ok' => true, 'data' => [
            'request' => $mr,
            'items' => MaterialRequestService::items((int)$id),
            'fulfillment' => MaterialRequestService::fulfillment((int)$id),
        ]]);
    }

    public function apiStore(): void
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = MaterialRequestService::create((int)Auth::id(), $body);
            Response::json(['ok' => true, 'id' => $id, 'data' => MaterialRequestService::find($id)]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function apiSubmit(string $id): void
    {
        try {
            MaterialRequestService::submit((int)$id, (int)Auth::id());
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function apiPlan(string $id): void
    {
        Response::json(['ok' => true, 'data' => MaterialRequestService::fulfillmentPlan((int)$id)]);
    }

    public function apiExecuteFulfillment(string $id): void
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            MaterialRequestService::executeFulfillment((int)$id, $body['plan'] ?? [], (int)Auth::id());
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
