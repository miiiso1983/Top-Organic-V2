<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Auth, Helpers, ImageUpload, NotificationService};

/**
 * نظام طلبات الصيانة — الويب + API
 */
class MaintenanceController extends Controller
{
    private const STATUSES = [
        'pending' => 'معلق',
        'assigned' => 'معين',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'منتهي',
        'confirmed' => 'تم التأكيد',
        'cancelled' => 'ملغى',
    ];

    private const PRIORITIES = [
        'low' => 'منخفضة',
        'normal' => 'عادية',
        'high' => 'عالية',
        'urgent' => 'عاجلة',
    ];

    private const WORK_STATUSES = [
        'parts_requested' => 'تم طلب قطع الغيار',
        'in_maintenance' => 'قيد الصيانة',
        'external_maintenance' => 'صيانة خارجية',
        'completed' => 'إتمام الصيانة',
    ];

    public function index(): void
    {
        $userId = Auth::id();
        $employee = Database::query("SELECT * FROM employees WHERE user_id = ?", [$userId])->fetch();
        $isMaintManager = $this->isMaintenanceManager($employee);
        $empId = is_array($employee) ? (int)$employee['id'] : 0;
        $where = $isMaintManager ? '1=1' : 'r.requester_id = ? OR r.assigned_to = ?';
        $params = $isMaintManager ? [] : [$empId, $empId];
        try {
            $rows = Database::query(
                "SELECT r.*, req.full_name AS requester_name, a.full_name AS assigned_name
                 FROM maintenance_requests r
                 LEFT JOIN employees req ON req.id = r.requester_id
                 LEFT JOIN employees a ON a.id = r.assigned_to
                 WHERE $where
                 ORDER BY r.created_at DESC LIMIT 500",
                $params
            )->fetchAll();
        } catch (\Throwable $e) {
            $rows = [];
            $this->flash('danger', 'تعذر تحميل طلبات الصيانة. تأكد من تشغيل migration قاعدة البيانات.');
        }
        $this->view('maintenance/index', [
            'title' => 'طلبات الصيانة',
            'rows' => $rows,
            'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
            'isMaintManager' => $isMaintManager,
            'employees' => $this->maintenanceEmployees(),
        ]);
    }

    public function create(): void
    {
        $employee = Database::query(
            "SELECT e.full_name, j.title AS job_title, d.name AS department_name
             FROM employees e
             LEFT JOIN jobs j ON j.id = e.job_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.user_id = ?",
            [Auth::id()]
        )->fetch();
        $this->view('maintenance/create', [
            'title' => 'طلب صيانة جديد',
            'employee' => $employee,
            'priorities' => self::PRIORITIES,
        ]);
    }

    public function store(): void
    {
        try {
            $userId = (int)Auth::id();
            $employee = Database::query("SELECT * FROM employees WHERE user_id = ?", [$userId])->fetch();
            if (!$employee) {
                throw new \RuntimeException('لا يوجد ملف موظف مرتبط بحسابك');
            }
            $title = trim((string)$this->input('title'));
            $device = trim((string)$this->input('device_name'));
            if ($title === '' || $device === '') {
                throw new \RuntimeException('اسم العطل واسم الجهاز إجباريان');
            }
            $priority = (string)$this->input('priority');
            if (!isset(self::PRIORITIES[$priority])) $priority = 'normal';
            $image = ImageUpload::fromMultipart('image', 'maintenance');
            if (!$image) {
                throw new \RuntimeException('يجب إرفاق صورة من الكاميرا أو المعرض');
            }
            $maintDept = $this->maintenanceDepartment();
            $number = $this->generateNumber();
            Database::query(
                "INSERT INTO maintenance_requests
                 (request_number, requester_id, requester_user_id, title, device_name, description, priority, image_path, status, maintenance_department_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$number, (int)$employee['id'], $userId, $title, $device,
                 (string)$this->input('description') ?: null, $priority, $image, 'pending', $maintDept['id'] ?? null]
            );
            $id = (int)Database::lastInsertId();
            $this->logStatus($id, null, 'pending', 'تم إنشاء الطلب');
            $this->notifyNewRequest($id, $title, $maintDept['id'] ?? null);
            $this->flash('success', 'تم إرسال طلب الصيانة برقم ' . $number);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/maintenance');
    }

    public function show(string $id): void
    {
        $row = $this->findRequest($id);
        if (!$row) Response::redirect('/maintenance');
        $emp = Database::query("SELECT id, department_id FROM employees WHERE user_id = ?", [Auth::id()])->fetch();
        $isMaintManager = $this->isMaintenanceManager($emp);
        $isOwner = (int)$row['requester_user_id'] === (int)Auth::id();
        $isAssigned = $emp && (int)$row['assigned_to'] === (int)$emp['id'];
        if (!$isMaintManager && !$isOwner && !$isAssigned) {
            Response::redirect('/maintenance');
        }
        $logs = Database::query(
            "SELECT l.*, u.full_name AS changer_name
             FROM maintenance_status_logs l
             LEFT JOIN users u ON u.id = l.changed_by
             WHERE l.maintenance_request_id = ? ORDER BY l.id DESC",
            [(int)$id]
        )->fetchAll();
        $this->view('maintenance/show', [
            'title' => 'تفاصيل طلب الصيانة ' . $row['request_number'],
            'row' => $row, 'logs' => $logs, 'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
            'workStatuses' => self::WORK_STATUSES,
            'employees' => $this->maintenanceEmployees(),
            'isMaintManager' => $this->isMaintenanceManager(),
            'isAssignedToMe' => $this->isAssignedToMe($row),
            'isRequester' => $this->isRequester($row),
        ]);
    }

    public function assign(string $id): void
    {
        try {
            if (!$this->isMaintenanceManager()) {
                throw new \RuntimeException('ليس لديك صلاحية تعيين مهام الصيانة');
            }
            $assignedTo = (int)$this->input('assigned_to');
            if (!$assignedTo) throw new \RuntimeException('يجب اختيار موظف الصيانة');
            $row = $this->findRequest($id);
            if (!$row || $row['status'] !== 'pending') {
                throw new \RuntimeException('الطلب غير موجود أو سبق تعيينه');
            }
            $maintDept = $this->maintenanceDepartment();
            $target = Database::query("SELECT department_id FROM employees WHERE id = ?", [$assignedTo])->fetch();
            if (!$maintDept || !$target || (int)$target['department_id'] !== (int)$maintDept['id']) {
                throw new \RuntimeException('يجب اختيار موظف من قسم الصيانة');
            }
            $managerEmp = Database::query("SELECT id FROM employees WHERE user_id = ?", [Auth::id()])->fetch();
            Database::query(
                "UPDATE maintenance_requests
                 SET assigned_to = ?, assigned_by = ?, assigned_at = NOW(), status = 'assigned', updated_at = NOW()
                 WHERE id = ?",
                [$assignedTo, $managerEmp['id'] ?? null, (int)$id]
            );
            $this->logStatus((int)$id, 'pending', 'assigned', 'تم تعيين الموظف المسؤول');
            $this->notifyAssigned((int)$id, $assignedTo);
            $this->flash('success', 'تم تعيين المهمة للموظف');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/maintenance/' . $id);
    }

    public function start(string $id): void
    {
        try {
            $row = $this->findRequest($id);
            if (!$row || $row['status'] !== 'assigned') {
                throw new \RuntimeException('الطلب غير متاح للبدء');
            }
            $this->ensureAssignedToMe($row);
            Database::query(
                "UPDATE maintenance_requests SET status = 'in_progress', started_at = NOW(), updated_at = NOW() WHERE id = ?",
                [(int)$id]
            );
            $this->logStatus((int)$id, 'assigned', 'in_progress', 'بدء التنفيذ');
            $this->notifyStatusChange((int)$id, 'in_progress');
            $this->flash('success', 'تم بدء المهمة');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/maintenance/' . $id);
    }

    public function finish(string $id): void
    {
        try {
            $row = $this->findRequest($id);
            if (!$row || $row['status'] !== 'in_progress') {
                throw new \RuntimeException('الطلب غير متاح للتحديث');
            }
            $this->ensureAssignedToMe($row);
            $workStatus = (string)$this->input('work_status');
            if (!isset(self::WORK_STATUSES[$workStatus])) {
                throw new \RuntimeException('يجب اختيار حالة صحيحة');
            }
            $notes = trim((string)$this->input('resolution_notes'));
            $costInput = $this->input('cost');
            $cost = ($costInput !== null && $costInput !== '') ? (float)$costInput : null;
            $isDone = $workStatus === 'completed';
            Database::query(
                "UPDATE maintenance_requests
                 SET work_status = ?, resolution_notes = ?, cost = ?,
                     status = ?, finished_at = ?, updated_at = NOW()
                 WHERE id = ?",
                [$workStatus, $notes !== '' ? $notes : null, $cost,
                 $isDone ? 'completed' : $row['status'], $isDone ? date('Y-m-d H:i:s') : $row['finished_at'], (int)$id]
            );
            $this->logStatus((int)$id, $row['status'], $isDone ? 'completed' : $workStatus, self::WORK_STATUSES[$workStatus]);
            $this->notifyStatusChange((int)$id, $isDone ? 'completed' : $workStatus);
            $this->flash('success', $isDone ? 'تم إنهاء المهمة، بانتظار تأكيد صاحب الطلب' : 'تم تحديث حالة الصيانة');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/maintenance/' . $id);
    }

    public function confirm(string $id): void
    {
        try {
            $row = $this->findRequest($id);
            if (!$row || $row['status'] !== 'completed') {
                throw new \RuntimeException('الطلب غير متاح للتأكيد');
            }
            $this->ensureRequester($row);
            Database::query(
                "UPDATE maintenance_requests SET status = 'confirmed', confirmed_at = NOW(), updated_at = NOW() WHERE id = ?",
                [(int)$id]
            );
            $this->logStatus((int)$id, 'completed', 'confirmed', 'تم التأكيد من صاحب الطلب');
            $this->notifyStatusChange((int)$id, 'confirmed');
            $this->flash('success', 'تم تأكيد إنجاز العمل');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/maintenance/' . $id);
    }

    private function findRequest($id): ?array
    {
        try {
            return Database::query(
                "SELECT r.*, req.full_name AS requester_name, a.full_name AS assigned_name, a.user_id AS assigned_user_id
                 FROM maintenance_requests r
                 LEFT JOIN employees req ON req.id = r.requester_id
                 LEFT JOIN employees a ON a.id = r.assigned_to
                 WHERE r.id = ?",
                [(int)$id]
            )->fetch() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function generateNumber(): string
    {
        $prefix = 'MAINT-' . date('Ymd') . '-';
        $last = Database::query(
            "SELECT request_number FROM maintenance_requests WHERE request_number LIKE ? ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        )->fetch();
        $n = 1;
        if ($last) {
            $parts = explode('-', (string)$last['request_number']);
            $n = ((int)end($parts)) + 1;
        }
        return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
    }

    private function maintenanceDepartment(): ?array
    {
        return Database::query(
            "SELECT * FROM departments WHERE name LIKE '%صيانة%' OR name LIKE '%maintenance%' OR code = 'MAINT' LIMIT 1"
        )->fetch() ?: null;
    }

    private function maintenanceEmployees(): array
    {
        $dept = $this->maintenanceDepartment();
        if (!$dept) return [];
        return Database::query(
            "SELECT e.id, e.full_name, e.emp_code
             FROM employees e
             WHERE e.department_id = ? AND e.status = 'active' AND e.file_status = 'open'
             ORDER BY e.full_name",
            [(int)$dept['id']]
        )->fetchAll();
    }

    private function isMaintenanceManager($employee = null): bool
    {
        if (Auth::role() === 'مدير النظام') return true;
        $emp = is_array($employee)
            ? $employee
            : (Database::query("SELECT * FROM employees WHERE user_id = ?", [Auth::id()])->fetch() ?: null);
        $dept = $this->maintenanceDepartment();
        if (!$dept || !$emp || (int)($emp['department_id'] ?? 0) !== (int)$dept['id']) return false;
        $managerIds = array_map('intval', array_column($this->maintenanceManagers((int)$dept['id']), 'id'));
        return in_array((int)$emp['id'], $managerIds, true);
    }

    /** موظفو قسم الصيانة الذين يُعتبرون "مدير الصيانة" (وظيفة تحتوي "مدير" أو لديهم مرؤوسون ضمن نفس القسم) */
    private function maintenanceManagers(int $deptId): array
    {
        return Database::query(
            "SELECT DISTINCT e.id, e.user_id
             FROM employees e
             LEFT JOIN jobs j ON j.id = e.job_id
             WHERE e.department_id = ? AND e.status = 'active' AND e.file_status = 'open'
               AND (
                 j.title LIKE '%مدير%' OR j.title LIKE '%Manager%'
                 OR e.id IN (SELECT manager_id FROM employees WHERE department_id = ? AND manager_id IS NOT NULL)
               )",
            [$deptId, $deptId]
        )->fetchAll();
    }

    public function isAssignedToMe(array $row): bool
    {
        $emp = Database::query("SELECT id FROM employees WHERE user_id = ?", [Auth::id()])->fetch();
        return $emp && (int)$row['assigned_to'] === (int)$emp['id'];
    }

    public function isRequester(array $row): bool
    {
        return (int)$row['requester_user_id'] === (int)Auth::id();
    }

    private function ensureAssignedToMe(array $row): void
    {
        if (!$this->isAssignedToMe($row)) {
            throw new \RuntimeException('هذه المهمة غير مخصصة لك');
        }
    }

    private function ensureRequester(array $row): void
    {
        if (!$this->isRequester($row)) {
            throw new \RuntimeException('لست صاحب هذا الطلب');
        }
    }

    private function logStatus(int $id, ?string $old, string $new, string $notes): void
    {
        Database::query(
            "INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes)
             VALUES (?,?,?,?,?)",
            [$id, $old, $new, Auth::id(), $notes]
        );
    }

    private function notifyNewRequest(int $id, string $title, ?int $deptId): void
    {
        if (!$deptId) return;
        $userIds = array_values(array_filter(array_map(
            fn($m) => $m['user_id'] ? (int)$m['user_id'] : null,
            $this->maintenanceManagers($deptId)
        )));
        if ($userIds) {
            Helpers::notifyUsers($userIds, 'طلب صيانة جديد', $title, '/maintenance/' . $id, null, true);
        }
    }

    private function notifyAssigned(int $id, int $employeeId): void
    {
        $emp = Database::query("SELECT user_id, full_name FROM employees WHERE id = ?", [$employeeId])->fetch();
        if ($emp && $emp['user_id']) {
            NotificationService::sendToUser(
                (int)$emp['user_id'], 'مهمة صيانة معينة', 'تم تعيين طلب صيانة لك', '/maintenance/' . $id, ['push']
            );
        }
    }

    private function notifyStatusChange(int $id, string $status): void
    {
        $row = $this->findRequest($id);
        if (!$row) return;
        $label = self::STATUSES[$status] ?? self::WORK_STATUSES[$status] ?? $status;
        if ($row['requester_user_id']) {
            NotificationService::sendToUser(
                (int)$row['requester_user_id'], 'تحديث طلب صيانة', 'الحالة: ' . $label, '/maintenance/' . $id, ['push']
            );
        }
        if ($row['assigned_user_id'] && (int)$row['assigned_user_id'] !== (int)$row['requester_user_id']) {
            NotificationService::sendToUser(
                (int)$row['assigned_user_id'], 'تحديث طلب صيانة', 'الحالة: ' . $label, '/maintenance/' . $id, ['push']
            );
        }
    }
}
