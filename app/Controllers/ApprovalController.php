<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, ApprovalService, WorkflowEngine};

class ApprovalController extends Controller
{
    public function index(): void
    {
        // أي مستخدم لديه صلاحية الموافقات يستطيع رؤية جميع الطلبات المعلقة
        $legacyPending = Auth::can('approvals.decide')
            ? ApprovalService::allPending()
            : ApprovalService::pendingForCurrentUser();

        // دمج طلبات Workflow (الإجازات والسلف والتسويات) مع الموافقات التقليدية
        $workflowPending = [];
        if (Auth::can('approvals.decide') || Auth::can('workflow.decide')) {
            $workflowPending = WorkflowEngine::getPendingForUser((int)Auth::id());
        }

        $pending = [];
        foreach ($legacyPending as $p) {
            $p['source'] = 'legacy';
            $p['request_no'] = '#' . $p['id'];
            $p['title'] = ($p['entity_type'] ?? '') . ' #' . ($p['entity_id'] ?? '');
            $pending[] = $p;
        }
        foreach ($workflowPending as $p) {
            $p['source'] = 'workflow';
            $p['entity_type'] = $p['template_name'] ?? $p['template_code'] ?? 'workflow';
            $p['entity_id'] = $p['id'];
            $p['requester'] = $p['requester_name'] ?? '-';
            $p['step_title'] = $p['step']['title'] ?? '';
            $pending[] = $p;
        }
        // ترتيب حسب الأحدث
        usort($pending, fn (array $a, array $b): int => ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0)));

        [$allRequests, $totalAll, $page, $perPage] = $this->buildAllRequestsQuery();
        $types = Database::query(
            "SELECT DISTINCT entity_type AS code, name AS label FROM legacy_approval_workflows
             UNION
             SELECT DISTINCT code, name FROM approval_templates
             ORDER BY label"
        )->fetchAll();

        $this->view('approvals/index', [
            'title' => 'الموافقات', 'pending' => $pending,
            'allRequests' => $allRequests, 'totalAll' => $totalAll, 'page' => $page, 'perPage' => $perPage,
            'types' => $types,
            'filters' => [
                'q' => (string)($_GET['q'] ?? ''),
                'status' => (string)($_GET['status'] ?? 'all'),
                'type' => (string)($_GET['type'] ?? ''),
                'from' => (string)($_GET['from'] ?? ''),
                'to' => (string)($_GET['to'] ?? ''),
                'sort' => (string)($_GET['sort'] ?? 'created_at'),
                'dir' => strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
            ],
        ]);
    }

    /**
     * بناء استعلام موحّد (UNION) لجميع طلبات الموافقة (التقليدية ونظام Workflow) بجميع حالاتها
     * مع دعم التصفية والفرز والبحث الشامل والترقيم.
     * @return array{0:array,1:int,2:int,3:int}
     */
    private function buildAllRequestsQuery(): array
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $status = (string)($_GET['status'] ?? 'all');
        $type = (string)($_GET['type'] ?? '');
        $from = (string)($_GET['from'] ?? '');
        $to = (string)($_GET['to'] ?? '');
        $sort = (string)($_GET['sort'] ?? 'created_at');
        $dir = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        $sortMap = [
            'created_at' => 't.created_at', 'status' => 't.status',
            'requester' => 't.requester', 'type_label' => 't.type_label',
        ];
        $sortCol = $sortMap[$sort] ?? 't.created_at';

        $base = "SELECT 'legacy' AS source, ar.id AS id, ar.entity_type AS type_code, aw.name AS type_label,
                        ar.entity_id AS entity_id, ar.amount AS amount, ar.status AS status, ar.current_step AS current_step,
                        u.full_name AS requester, ar.created_at AS created_at, CONCAT('#', ar.id) AS request_no
                 FROM legacy_approval_requests ar
                 JOIN legacy_approval_workflows aw ON aw.id = ar.workflow_id
                 LEFT JOIN users u ON u.id = ar.requested_by
                 UNION ALL
                 SELECT 'workflow' AS source, r.id AS id, at.code AS type_code, at.name AS type_label,
                        r.id AS entity_id, r.amount AS amount, r.status AS status, r.current_step AS current_step,
                        u2.full_name AS requester, r.created_at AS created_at, r.request_no AS request_no
                 FROM requests r
                 JOIN approval_templates at ON at.id = r.template_id
                 LEFT JOIN users u2 ON u2.id = r.requester_id
                 WHERE r.status <> 'draft'";

        $where = ['1=1'];
        $params = [];
        if ($status !== 'all' && $status !== '') {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if ($type !== '') {
            $where[] = 't.type_code = ?';
            $params[] = $type;
        }
        if ($q !== '') {
            $where[] = '(t.requester LIKE ? OR t.type_label LIKE ? OR t.request_no LIKE ?)';
            $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
        }
        if ($from !== '') {
            $where[] = 'DATE(t.created_at) >= ?';
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = 'DATE(t.created_at) <= ?';
            $params[] = $to;
        }
        $whereSql = implode(' AND ', $where);

        $total = (int)Database::query(
            "SELECT COUNT(*) AS c FROM ($base) t WHERE $whereSql",
            $params
        )->fetch()['c'];

        $rows = Database::query(
            "SELECT * FROM ($base) t WHERE $whereSql ORDER BY $sortCol $dir LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        )->fetchAll();

        return [$rows, $total, $page, $perPage];
    }

    public function decide(string $id): void
    {
        $decision = $this->input('decision') === 'approved' ? 'approved' : 'rejected';
        $result = ApprovalService::decide((int)$id, $decision, (string)$this->input('notes'));

        if ($result === 'unauthorized') {
            $this->flash('danger', 'لا يحق لك اتخاذ قرار على هذا الطلب في مستواه الحالي.');
            Response::redirect('/approvals/' . (int)$id);
            return;
        }

        $labels = ['approved' => 'تمت الموافقة النهائية', 'rejected' => 'تم الرفض',
                   'pending' => 'تمت موافقتك — الطلب انتقل للمستوى التالي'];
        $this->flash($result === 'rejected' ? 'danger' : 'success', $labels[$result] ?? $result);
        Response::redirect('/approvals');
    }

    public function show(string $id): void
    {
        $request = Database::query(
            "SELECT ar.*, aw.name AS workflow_name, u.full_name AS requester
             FROM legacy_approval_requests ar
             JOIN legacy_approval_workflows aw ON aw.id = ar.workflow_id
             LEFT JOIN users u ON u.id = ar.requested_by
             WHERE ar.id = ?",
            [(int)$id]
        )->fetch();
        if (!$request) { Response::abort(404, 'طلب الموافقة غير موجود'); }

        $steps = Database::query(
            "SELECT s.*, r.name AS role_name
             FROM legacy_approval_steps s
             JOIN roles r ON r.id = s.role_id
             WHERE s.workflow_id = ?
             ORDER BY s.step_order",
            [(int)$request['workflow_id']]
        )->fetchAll();

        $actions = Database::query(
            "SELECT a.*, u.full_name AS decider
             FROM legacy_approval_actions a
             LEFT JOIN users u ON u.id = a.decided_by
             WHERE a.request_id = ?
             ORDER BY a.id",
            [(int)$id]
        )->fetchAll();

        $this->view('approvals/show', [
            'title'   => 'عرض طلب موافقة #' . $id,
            'request' => $request,
            'steps'   => $steps,
            'actions' => $actions,
        ]);
    }
}
