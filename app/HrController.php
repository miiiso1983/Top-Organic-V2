<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, WorkflowEngine, HrWorkflowService, AccountingService, ExportHelper, ImportHelper, HrService, BiometricService, EmployeeAuditService, DocumentUpload, HrReportService, NotificationService, CostCenterService, AnnouncementReminderService};
use App\Core\Ptt\PttService;

class HrController extends Controller
{
    public function employees(): void
    {
        $search = trim((string)($_GET['search'] ?? ''));
        $departmentId = $_GET['department_id'] ?? '';
        $jobId = $_GET['job_id'] ?? '';
        $status = $_GET['status'] ?? '';
        $fileStatus = $_GET['file_status'] ?? '';
        $branchId = $_GET['branch_id'] ?? '';
        $managerId = $_GET['manager_id'] ?? '';
        $sortBy = in_array($_GET['sort_by'] ?? '', ['full_name','emp_code','job_title','department_name','manager_name','base_salary','hire_date','status','file_status','branch_name','username'], true) ? ($_GET['sort_by'] ?? 'full_name') : 'full_name';
        $sortDir = strtolower($_GET['sort_dir'] ?? '') === 'desc' ? 'DESC' : 'ASC';

        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = "(e.full_name LIKE ? OR e.emp_code LIKE ? OR e.phone LIKE ? OR e.position LIKE ? OR e.department LIKE ?)";
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }
        if ($departmentId !== '') {
            $where[] = "e.department_id = ?";
            $params[] = (int)$departmentId;
        }
        if ($jobId !== '') {
            $where[] = "e.job_id = ?";
            $params[] = (int)$jobId;
        }
        if ($status !== '') {
            $where[] = "e.status = ?";
            $params[] = $status;
        } else {
            // إخفاء موظفي منتهي الخدمة والمستقيلين من القائمة الافتراضية
            $where[] = "e.status NOT IN ('terminated','resigned')";
        }
        if ($fileStatus !== '') {
            $where[] = "e.file_status = ?";
            $params[] = $fileStatus;
        }
        if ($branchId !== '') {
            $where[] = "e.branch_id = ?";
            $params[] = (int)$branchId;
        }
        if ($managerId !== '') {
            $where[] = "e.manager_id = ?";
            $params[] = (int)$managerId;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $count = (int)Database::query(
            "SELECT COUNT(*) AS c FROM employees e $whereSql",
            $params
        )->fetch()['c'];
        $orderMap = [
            'full_name' => 'e.full_name', 'emp_code' => 'e.emp_code', 'job_title' => 'j.title',
            'department_name' => 'd.name', 'manager_name' => 'm.full_name', 'base_salary' => 'e.base_salary',
            'hire_date' => 'e.hire_date', 'status' => 'e.status', 'file_status' => 'e.file_status',
            'branch_name' => 'b.name', 'username' => 'u.username',
        ];
        $orderColumn = $orderMap[$sortBy] ?? 'e.full_name';
        $employees = Database::query(
            "SELECT e.*, b.name AS branch_name, u.username AS username,
                    d.name AS department_name, j.title AS job_title, m.full_name AS manager_name
             FROM employees e
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN users u ON u.id = e.user_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN jobs j ON j.id = e.job_id
             LEFT JOIN employees m ON m.id = e.manager_id
             $whereSql
             ORDER BY $orderColumn $sortDir
             LIMIT 1000",
            $params
        )->fetchAll();

        $canViewSalary = Auth::can('hr.employee.salary.view');
        if (!$canViewSalary) {
            foreach ($employees as &$e) {
                $e['base_salary'] = null;
            }
            unset($e);
        }

        $branches = Database::query("SELECT * FROM branches WHERE is_active=1")->fetchAll();
        $roles = Database::query("SELECT id, name, level FROM roles WHERE level > 0 ORDER BY level DESC")->fetchAll();
        $departments = Database::query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
        $jobs = Database::query(
            "SELECT j.*, d.name AS department_name FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE j.is_active=1 ORDER BY j.title"
        )->fetchAll();
        $managers = Database::query(
            "SELECT id, full_name, emp_code, department_id FROM employees
             WHERE file_status = 'open' AND status = 'active' ORDER BY full_name"
        )->fetchAll();
        $employeeOptions = Database::query(
            "SELECT id, full_name, emp_code FROM employees ORDER BY full_name"
        )->fetchAll();
        $costCenters = CostCenterService::dropdown();
        $accounts = Database::query("SELECT id, code, name FROM accounts ORDER BY name")->fetchAll();

        $filters = [
            'search' => $search, 'department_id' => $departmentId, 'job_id' => $jobId,
            'status' => $status, 'file_status' => $fileStatus, 'branch_id' => $branchId,
            'manager_id' => $managerId, 'sort_by' => $sortBy, 'sort_dir' => $sortDir,
        ];

        $this->view('hr/employees', [
            'title' => 'الموظفون', 'employees' => $employees, 'branches' => $branches, 'roles' => $roles,
            'departments' => $departments, 'jobs' => $jobs, 'managers' => $managers,
            'employeeOptions' => $employeeOptions, 'costCenters' => $costCenters, 'accounts' => $accounts,
            'filters' => $filters, 'total' => $count, 'canViewSalary' => $canViewSalary,
        ]);
    }

    public function createEmployeeForm(): void
    {
        $branches = Database::query("SELECT * FROM branches WHERE is_active=1")->fetchAll();
        $roles = Database::query("SELECT id, name, level FROM roles WHERE level > 0 ORDER BY level DESC")->fetchAll();
        $departments = Database::query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
        $jobs = Database::query(
            "SELECT j.*, d.name AS department_name FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE j.is_active=1 ORDER BY j.title"
        )->fetchAll();
        $managers = Database::query(
            "SELECT id, full_name, emp_code, department_id FROM employees
             WHERE file_status = 'open' AND status = 'active' ORDER BY full_name"
        )->fetchAll();
        $costCenters = CostCenterService::dropdown();
        $pttChannels = PttService::allChannels();
        $shifts = HrService::getShifts();
        $permissions = Database::query("SELECT * FROM permissions ORDER BY module, id")->fetchAll();
        $rolePerms = [];
        foreach (Database::query("SELECT role_id, permission_id FROM role_permissions")->fetchAll() as $rp) {
            $rolePerms[$rp['role_id']][] = (int)$rp['permission_id'];
        }

        $this->view('hr/employees_create', [
            'title' => 'فتح ملف موظف جديد',
            'branches' => $branches, 'roles' => $roles, 'departments' => $departments,
            'jobs' => $jobs, 'managers' => $managers, 'costCenters' => $costCenters,
            'pttChannels' => $pttChannels, 'shifts' => $shifts,
            'permissions' => $permissions, 'rolePerms' => $rolePerms,
        ]);
    }

    public function storeEmployee(): void
    {
        try {
            $hasManage = Auth::can('hr.manage');
            $canViewSalary = Auth::can('hr.employee.salary.view');
            $roleId = $this->input('role_id') ?: null;
            $adminLevel = 0;
            if (!$hasManage) {
                // Restrict non-managers (e.g. accountant) to the default employee role only
                $roleId = $this->defaultEmployeeRoleId();
                $adminLevel = 0;
            }
            if (!$roleId) {
                $roleId = $this->defaultEmployeeRoleId();
            }
            if ($roleId) {
                $role = Database::query(
                    "SELECT id, level, name FROM roles WHERE id = ?",
                    [(int)$roleId]
                )->fetch();
                if (!$role) {
                    throw new \RuntimeException('الدور المختار غير موجود');
                }
                $roleId = (int)$role['id'];
                $adminLevel = (int)$role['level'];
            }
            if (!$roleId) {
                throw new \RuntimeException('لم يتم تحديد دور للموظف ولا يوجد دور افتراضي (موظف) في النظام');
            }
            // Enforce salary field privacy for non-HR managers
            $baseSalary = $hasManage || $canViewSalary ? (float)$this->input('base_salary', 0) : 0.0;

            $code = Helpers::docNumber('EMP', 'employees', 'emp_code');
            $departmentId = $this->input('department_id') ?: null;
            $jobId = $this->input('job_id') ?: null;
            $managerId = $this->input('manager_id') ?: null;
            $costCenterId = $this->input('cost_center_id') ?: null;
            $status = (string)$this->input('status', 'active');
            if (!in_array($status, ['active', 'vacation', 'suspended', 'terminated', 'resigned'], true)) {
                $status = 'active';
            }
            $attendanceType = (string)$this->input('attendance_type', 'shift');
            if (!in_array($attendanceType, ['shift', 'flexible'], true)) {
                $attendanceType = 'shift';
            }
            $requiredHours = $attendanceType === 'flexible' ? (float)$this->input('required_hours', 8.0) : 0.0;
            $shiftId = $this->input('shift_id') ?: null;
            $weeklyOffDay = $this->input('weekly_off_day');
            $weeklyOffDay = ($weeklyOffDay !== '' && $weeklyOffDay !== null) ? (int)$weeklyOffDay : null;
            if ($weeklyOffDay !== null && ($weeklyOffDay < 0 || $weeklyOffDay > 6)) {
                $weeklyOffDay = null;
            }

            $departmentName = '';
            $jobTitle = '';
            if ($departmentId) {
                $d = Database::query("SELECT name FROM departments WHERE id = ?", [(int)$departmentId])->fetch();
                $departmentName = $d['name'] ?? '';
            }
            if ($jobId) {
                $j = Database::query("SELECT title FROM jobs WHERE id = ?", [(int)$jobId])->fetch();
                $jobTitle = $j['title'] ?? '';
            }

            Database::beginTransaction();

            // 1. تخليق الحساب المالي للموظف تحت 1210
            $accountData = $this->createEmployeeAccount((string)$this->input('full_name'));
            $accountId = $accountData['id'];
            $accountCode = $accountData['code'];

            // 2. اسم المستخدم يدوي وكلمة المرور (تلقائية إن لم تُدخل)
            $username = trim((string)$this->input('username'));
            if ($username === '') {
                throw new \RuntimeException('اسم المستخدم مطلوب');
            }
            if (Database::query("SELECT 1 FROM users WHERE username = ?", [$username])->fetch()) {
                throw new \RuntimeException('اسم المستخدم "' . $username . '" مستخدم مسبقاً');
            }
            $password = $this->input('password') ?: $this->generateRandomPassword(10);

            Database::query(
                "INSERT INTO employees (emp_code, full_name, phone, nationality, residence, position, department, department_id, job_id, manager_id,
                 admin_level, base_salary, hire_date, work_start_time, work_end_time, shift_id, branch_id, cost_center_id, account_id, status, attendance_type, required_hours, weekly_off_day)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$code, (string)$this->input('full_name'), (string)$this->input('phone'),
                 (string)$this->input('nationality') ?: null, (string)$this->input('residence') ?: null,
                 $jobTitle, $departmentName,
                 $departmentId, $jobId, $managerId,
                 $adminLevel, $baseSalary,
                 $this->input('hire_date') ?: date('Y-m-d'),
                 $this->input('work_start_time') ?: null, $this->input('work_end_time') ?: null,
                 $shiftId,
                 $this->input('branch_id') ?: null,
                 $costCenterId, $accountId, $status,
                 $attendanceType, $requiredHours, $weeklyOffDay]
            );
            $id = (int)Database::lastInsertId();

            // 3. إنشاء حساب دخول للنظام وربطه بالموظف
            $userId = null;
            Database::query(
                "INSERT INTO users (username, password_hash, full_name, role_id, branch_id, is_active)
                 VALUES (?,?,?,?,?,?)",
                [
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    (string)$this->input('full_name'),
                    $roleId ?: null,
                    $this->input('branch_id') ?: null,
                    1,
                ]
            );
            $userId = (int)Database::lastInsertId();
            Database::query("UPDATE employees SET user_id = ? WHERE id = ?", [$userId, $id]);

            HrService::initLeaveBalances($id);
            PttService::syncEmployeeChannels($id);
            if ($userId) {
                $pttChannelIds = array_map('intval', (array)$this->input('ptt_channel_ids', []));
                PttService::setManualChannels($userId, $pttChannelIds);
            }
            EmployeeAuditService::log($id, 'insert', 'emp_code', null, $code, Auth::id());
            Helpers::queueSync('employees', $id, 'insert', [
                'emp_code' => $code, 'full_name' => (string)$this->input('full_name'),
            ]);

            Database::commit();

            $this->flash(
                'success',
                "تم فتح ملف الموظف $code وإنشاء حساب المستخدم $username والحساب المالي $accountCode (كلمة المرور: $password)"
            );
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/hr/employees');
    }

    public function editEmployee(string $id): void
    {
        if (!Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية تعديل بيانات الموظفين');
            Response::redirect('/hr/employees');
            return;
        }
        try {
        $employee = Database::query(
            "SELECT e.*, u.username AS user_username, u.role_id AS user_role_id FROM employees e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.id = ?",
            [(int)$id]
        )->fetch();
        if (!$employee) {
            Response::redirect('/hr/employees');
        }
        $branches = Database::query("SELECT * FROM branches WHERE is_active=1")->fetchAll();
        $roles = Database::query("SELECT id, name, level FROM roles WHERE level > 0 ORDER BY level DESC")->fetchAll();
        $departments = Database::query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
        $jobs = Database::query(
            "SELECT j.*, d.name AS department_name FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE j.is_active=1 ORDER BY j.title"
        )->fetchAll();
        $managers = Database::query(
            "SELECT id, full_name, emp_code, department_id FROM employees
             WHERE file_status = 'open' AND status = 'active' AND id != ? ORDER BY full_name",
            [(int)$id]
        )->fetchAll();
        $costCenters = CostCenterService::dropdown();
        $accounts = Database::query("SELECT id, code, name FROM accounts ORDER BY name")->fetchAll();
        $users = Database::query(
            "SELECT u.id, u.username, u.full_name, r.name AS role_name FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             ORDER BY u.full_name, u.username"
        )->fetchAll();
        $documents = HrService::getEmployeeDocuments((int)$id);
        $auditLogs = EmployeeAuditService::getByEmployee((int)$id);
        $custody = HrService::getEmployeeCustody((int)$id, 'active');
        $pttChannels = PttService::allChannels();
        $selectedPttChannels = $employee['user_id'] ? PttService::manualChannelsForUser((int)$employee['user_id']) : [];
        $shifts = HrService::getShifts();
        $resignation = Database::query(
            "SELECT * FROM resignations WHERE employee_id = ? ORDER BY id DESC LIMIT 1",
            [(int)$id]
        )->fetch();
        $workedDays = HrService::getEmployeeWorkedDaysInMonth((int)$id, (int)date('Y'), (int)date('m'));
        try {
            $breakdown = $this->calculateResignationBreakdown((int)$id, (float)$employee['base_salary'], (float)$workedDays);
        } catch (\Throwable $e) {
            error_log('editEmployee breakdown failed: ' . $e->getMessage());
            $breakdown = [
                'salary_amount' => (float)$employee['base_salary'],
                'absence_days' => 0,
                'absence_deduction' => 0.0,
                'overtime_amount' => 0.0,
                'advances_amount' => 0.0,
                'housing_deduction' => 0.0,
                'other_deductions' => 0.0,
                'total_due' => (float)$employee['base_salary'],
            ];
        }
        if (!empty($resignation['calculation_breakdown_json'])) {
            $savedBreakdown = json_decode($resignation['calculation_breakdown_json'], true) ?? [];
            $breakdown = array_merge($breakdown, array_intersect_key($savedBreakdown, $breakdown));
        }
        $permissions = Database::query("SELECT * FROM permissions ORDER BY module, id")->fetchAll();
        $rolePerms = [];
        foreach (Database::query("SELECT role_id, permission_id FROM role_permissions")->fetchAll() as $rp) {
            $rolePerms[$rp['role_id']][] = (int)$rp['permission_id'];
        }

        $this->view('hr/employees_edit', [
            'title' => 'تعديل بيانات الموظف',
            'employee' => $employee, 'branches' => $branches, 'roles' => $roles,
            'departments' => $departments, 'jobs' => $jobs, 'managers' => $managers,
            'costCenters' => $costCenters, 'accounts' => $accounts, 'users' => $users,
            'documents' => $documents, 'auditLogs' => $auditLogs,
            'custody' => $custody, 'pttChannels' => $pttChannels, 'selectedPttChannels' => $selectedPttChannels,
            'shifts' => $shifts, 'resignation' => $resignation, 'workedDays' => $workedDays,
            'breakdown' => $breakdown,
            'permissions' => $permissions, 'rolePerms' => $rolePerms,
        ]);
        } catch (\Throwable $e) {
            error_log('editEmployee 500: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            header('Content-Type: text/html; charset=utf-8');
            http_response_code(500);
            echo '<h1>خطأ في تحميل صفحة التعديل</h1>';
            echo '<p><strong>الرسالة:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>الملف:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
            echo '<p>يرجى إرسال هذه المعلومات للدعم.</p>';
            exit;
        }
    }

    public function updateEmployee(string $id): void
    {
        if (!Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية تعديل بيانات الموظفين');
            Response::redirect('/hr/employees');
            return;
        }
        try {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$id])->fetch();
        if (!$employee) {
            Response::redirect('/hr/employees');
        }
        $old = $employee;
        $roleId = $this->input('role_id') ?: null;
        $roleId = $roleId ? (int)$roleId : null;
        $adminLevel = (int)$employee['admin_level'];
        if ($roleId) {
            $role = Database::query("SELECT id, level, name FROM roles WHERE id = ?", [$roleId])->fetch();
            if ($role) {
                $adminLevel = (int)$role['level'];
            } else {
                $roleId = null;
            }
        }
        $selectedUserId = $this->input('user_id');
        $userId = $selectedUserId !== '' && $selectedUserId !== null ? (int)$selectedUserId : null;
        $username = trim((string)$this->input('username'));
        $password = $this->input('password') ?: null;
        // عند تحديث اسم المستخدم/كلمة المرور بدون تغيير الدور: نحتفظ بالدور الحالي للحساب المرتبط
        $hasCredentials = ($username !== '' || $password !== null);
        if ($hasCredentials && $roleId === null && $userId) {
            $existingUserRole = Database::query("SELECT role_id FROM users WHERE id = ?", [$userId])->fetch();
            if ($existingUserRole && $existingUserRole['role_id']) {
                $role = Database::query("SELECT id, level FROM roles WHERE id = ?", [(int)$existingUserRole['role_id']])->fetch();
                if ($role) {
                    $roleId = (int)$role['id'];
                    $adminLevel = (int)$role['level'];
                }
            }
        }

        if ($username !== '') {
            $existingUser = Database::query(
                "SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1",
                [$username, $userId ?? 0]
            )->fetch();
            if ($existingUser) {
                $this->flash('danger', 'اسم المستخدم مُستخدم مسبقاً');
                Response::redirect('/hr/employees/' . $id . '/edit');
                return;
            }
        }

        $departmentId = $this->input('department_id') ?: $employee['department_id'] ?: null;
        $jobId = $this->input('job_id') ?: $employee['job_id'] ?: null;
        $managerId = $this->input('manager_id') ?: $employee['manager_id'] ?: null;
        $costCenterId = $this->input('cost_center_id') ?: $employee['cost_center_id'] ?: null;
        $accountId = $this->input('account_id') ?: $employee['account_id'] ?: null;
        $status = (string)$this->input('status', $employee['status'] ?: 'active');
        if (!in_array($status, ['active', 'vacation', 'suspended', 'terminated', 'resigned'], true)) {
            $status = $employee['status'] ?: 'active';
        }
        $attendanceType = (string)$this->input('attendance_type', $employee['attendance_type'] ?: 'shift');
        if (!in_array($attendanceType, ['shift', 'flexible'], true)) {
            $attendanceType = $employee['attendance_type'] ?: 'shift';
        }
        $requiredHours = $attendanceType === 'flexible'
            ? (float)$this->input('required_hours', $employee['required_hours'] ?? 8.0)
            : 0.0;

        $departmentName = $employee['department'] ?? '';
        $jobTitle = $employee['position'] ?? '';
        if ($departmentId) {
            $d = Database::query("SELECT name FROM departments WHERE id = ?", [(int)$departmentId])->fetch();
            $departmentName = $d['name'] ?? $departmentName;
        }
        if ($jobId) {
            $j = Database::query("SELECT title FROM jobs WHERE id = ?", [(int)$jobId])->fetch();
            $jobTitle = $j['title'] ?? $jobTitle;
        }

        $referencePhoto = $employee['reference_photo'] ?? null;
        try {
            $uploaded = DocumentUpload::fromMultipart('reference_photo', 'reference');
            if ($uploaded) $referencePhoto = $uploaded;
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            Response::redirect('/hr/employees/' . $id . '/edit');
            return;
        }

        $shiftId = $this->input('shift_id');
        $shiftId = $shiftId !== '' && $shiftId !== null ? (int)$shiftId : null;
        $weeklyOffDay = $this->input('weekly_off_day');
        $weeklyOffDay = ($weeklyOffDay !== '' && $weeklyOffDay !== null) ? (int)$weeklyOffDay : null;
        if ($weeklyOffDay !== null && ($weeklyOffDay < 0 || $weeklyOffDay > 6)) {
            $weeklyOffDay = null;
        }

        $housingType = (string)$this->input('housing_type', '');
        if (!in_array($housingType, ['company', 'allowance', 'own'], true)) {
            $housingType = null;
        }
        $housingMonthlyDeduction = (float)$this->input('housing_monthly_deduction', 0);
        $insurancePercentage = (float)$this->input('insurance_percentage', 0);
        $insuranceOccurrences = (int)$this->input('insurance_occurrences', 0);

        Database::query(
            "UPDATE employees SET full_name = ?, phone = ?, nationality = ?, residence = ?, position = ?, department = ?, department_id = ?, job_id = ?, manager_id = ?,
             user_id = ?, admin_level = ?, base_salary = ?, hire_date = ?, work_start_time = ?, work_end_time = ?, shift_id = ?, branch_id = ?, cost_center_id = ?, account_id = ?, status = ?, reference_photo = ?, attendance_type = ?, required_hours = ?, weekly_off_day = ?,
             housing_type = ?, housing_monthly_deduction = ?, insurance_percentage = ?, insurance_occurrences = ?
             WHERE id = ?",
            [(string)$this->input('full_name'), (string)$this->input('phone'),
             (string)$this->input('nationality') ?: $employee['nationality'] ?: null,
             (string)$this->input('residence') ?: $employee['residence'] ?: null,
             $jobTitle, $departmentName,
             $departmentId, $jobId, $managerId,
             $userId, $adminLevel, (float)$this->input('base_salary', 0),
             $this->input('hire_date') ?: $employee['hire_date'],
             $this->input('work_start_time') ?: $employee['work_start_time'] ?: null,
             $this->input('work_end_time') ?: $employee['work_end_time'] ?: null,
             $shiftId,
             $this->input('branch_id') ?: $employee['branch_id'] ?: null,
             $costCenterId, $accountId, $status, $referencePhoto,
             $attendanceType, $requiredHours, $weeklyOffDay,
             $housingType, $housingMonthlyDeduction, $insurancePercentage, $insuranceOccurrences, (int)$id]
        );

        $new = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$id])->fetch();
        $auditFields = ['full_name','phone','nationality','residence','position','department','department_id','job_id','manager_id','user_id','admin_level','base_salary','hire_date','work_start_time','work_end_time','shift_id','branch_id','cost_center_id','account_id','status','reference_photo','attendance_type','required_hours','weekly_off_day','housing_type','housing_monthly_deduction','insurance_percentage','insurance_occurrences'];
        EmployeeAuditService::logSnapshot((int)$id, $old, $new, $auditFields, Auth::id());

        if ($status === 'resigned') {
            $this->saveResignationRecord((int)$id, $employee['base_salary'] ?? 0);
        }

        if ($userId) {
            HrService::syncExistingUserAccount(
                $userId, $adminLevel, (string)$this->input('full_name'),
                $this->input('branch_id') ?: $employee['branch_id'] ?: null,
                $roleId,
                $username !== '' ? $username : null,
                $password
            );
        } elseif ($adminLevel > 0) {
            HrService::syncEmployeeUserAccount(
                (int)$id, $adminLevel, (string)$this->input('full_name'),
                (string)$employee['emp_code'], $this->input('branch_id') ?: $employee['branch_id'] ?: null,
                $roleId,
                $username !== '' ? $username : null,
                $password
            );
        }
        PttService::syncEmployeeChannels((int)$id, $old['department'] ?? null);

        $new = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$id])->fetch();
        $oldUserId = (int)($old['user_id'] ?? 0);
        $newUserId = (int)($new['user_id'] ?? 0);
        $pttChannelIds = array_map('intval', (array)$this->input('ptt_channel_ids', []));
        if ($oldUserId && $oldUserId !== $newUserId) {
            PttService::setManualChannels($oldUserId, []);
        }
        if ($newUserId) {
            PttService::setManualChannels($newUserId, $pttChannelIds);
        }

        Helpers::queueSync('employees', (int)$id, 'update', [
            'full_name' => (string)$this->input('full_name'), 'admin_level' => $adminLevel,
        ]);
        $this->flash('success', 'تم حفظ بيانات الموظف بنجاح');
        if (!empty($_GET['modal'])) {
            // في النافذة المنبثقة: البقاء داخل نفس النافذة على صفحة ملف الموظف دون إغلاقها
            Response::redirect('/hr/employees/' . $id . '?modal=1');
        } else {
            Response::redirect('/hr/employees/' . $id . '/edit');
        }
        } catch (\Throwable $e) {
            error_log('updateEmployee failed: ' . $e->getMessage());
            $this->flash('danger', 'فشل حفظ التعديلات: ' . $e->getMessage());
            $modalSuffix = !empty($_GET['modal']) ? '?modal=1' : '';
            Response::redirect('/hr/employees/' . $id . '/edit' . $modalSuffix);
        }
    }

    public function deleteReferencePhoto(string $id): void
    {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$id])->fetch();
        if (!$employee) {
            Response::redirect('/hr/employees');
            return;
        }
        $oldPath = $employee['reference_photo'] ?? null;
        if ($oldPath) {
            $file = BASE_PATH . '/public' . $oldPath;
            if (is_file($file)) {
                @unlink($file);
            }
            Database::query("UPDATE employees SET reference_photo = NULL WHERE id = ?", [(int)$id]);
            EmployeeAuditService::log((int)$id, 'delete_reference_photo', 'reference_photo', $oldPath, null);
        }
        $this->flash('success', 'تم حذف الصورة المرجعية؛ سيطلب التطبيق من الموظف رفع صورة جديدة');
        $modalSuffix = !empty($_GET['modal']) ? '?modal=1' : '';
        Response::redirect('/hr/employees/' . $id . $modalSuffix);
    }

    public function toggleFile(string $id): void
    {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$id])->fetch();
        if (!$employee) {
            Response::redirect('/hr/employees');
        }
        $newStatus = (string)$this->input('file_status', ($employee['file_status'] === 'open' ? 'closed' : 'open'));
        $reason = (string)$this->input('reason', '');
        if (!in_array($newStatus, ['open', 'closed', 'archived'], true)) {
            $newStatus = $employee['file_status'] === 'open' ? 'closed' : 'open';
        }
        $modalSuffix = !empty($_GET['modal']) ? '?modal=1' : '';
        if (in_array($newStatus, ['closed', 'archived'], true) && HrService::hasOutstandingCustody((int)$id)) {
            $this->flash('danger', 'لا يمكن إغلاق أو أرشفة الملف: يوجد عهد مفتوحة للموظف. يجب إرجاعها أو تسويتها أولاً');
            Response::redirect('/hr/employees/' . $id . $modalSuffix);
        }
        if ($newStatus === 'archived') {
            if (HrService::archiveEmployee((int)$id, Auth::id())) {
                $this->flash('success', 'تم أرشفة ملف الموظف');
            } else {
                $this->flash('danger', 'فشل في أرشفة الملف');
            }
        } else {
            if (HrService::changeFileStatus((int)$id, $newStatus, $reason, Auth::id())) {
                $this->flash('success', 'تم تحديث حالة الملف إلى ' . $newStatus);
            } else {
                $this->flash('danger', 'فشل في تحديث حالة الملف');
            }
        }
        Response::redirect('/hr/employees/' . $id . $modalSuffix);
    }

    public function deleteEmployee(string $id): void
    {
        if (!Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية حذف الموظفين');
            Response::redirect('/hr/employees');
            return;
        }
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$id])->fetch();
        if (!$employee) {
            Response::redirect('/hr/employees');
            return;
        }

        Database::beginTransaction();
        try {
            $empId = (int)$id;
            // نحذف من الجدول إن وجد، ونتجاوز الجداول غير الموجودة في البيئة الحالية
            $del = function (string $sql, array $params) {
                try {
                    Database::query($sql, $params);
                } catch (\Throwable $e) {
                    if (strpos($e->getMessage(), 'Base table or view not found') === false) {
                        throw $e;
                    }
                }
            };

            // نقل الموظفين الذين يُبلّغون إليه إلى بدون مدير
            Database::query("UPDATE employees SET manager_id = NULL WHERE manager_id = ?", [$empId]);

            // حذف مرفقات الإجازات ثم الإجازات
            $del("DELETE FROM leave_request_attachments WHERE request_id IN (SELECT id FROM leave_requests WHERE employee_id = ?)", [$empId]);
            $del("DELETE FROM leave_requests WHERE employee_id = ?", [$empId]);

            // حذف السلف والرواتب
            $del("DELETE FROM salary_advances WHERE employee_id = ?", [$empId]);
            $del("DELETE FROM payrolls WHERE employee_id = ?", [$empId]);

            // حذف عناصر التقييم ثم التقييمات
            $del("DELETE FROM evaluation_items WHERE evaluation_id IN (SELECT id FROM evaluations WHERE employee_id = ?)", [$empId]);
            $del("DELETE FROM evaluation_goals WHERE evaluation_id IN (SELECT id FROM evaluations WHERE employee_id = ?)", [$empId]);
            $del("DELETE FROM evaluations WHERE employee_id = ?", [$empId]);

            // حذف المهام المُكلَّفة للموظف
            $del("DELETE FROM tasks WHERE assigned_to = ?", [$empId]);

            // حذف أهداف الموظف
            $del("DELETE FROM employee_goals WHERE employee_id = ?", [$empId]);

            // فصل الموظف عن المواقع التنظيمية (لا نحذف الهيكل)
            $del("UPDATE org_positions SET employee_id = NULL WHERE employee_id = ?", [$empId]);

            // حذف سجلات حالة الملف والتدقيق
            $del("DELETE FROM employee_file_status_logs WHERE employee_id = ?", [$empId]);
            $del("DELETE FROM employee_audit_logs WHERE employee_id = ?", [$empId]);

            // حذف المستندات والعقود
            $del("DELETE FROM employee_documents WHERE employee_id = ?", [$empId]);
            $del("DELETE FROM employee_contracts WHERE employee_id = ?", [$empId]);

            // حذف تنبيهات العهدة ثم العهدة
            $del("DELETE FROM employee_custody_alerts WHERE custody_id IN (SELECT id FROM employee_custody WHERE employee_id = ?)", [$empId]);
            $del("DELETE FROM employee_custody WHERE employee_id = ?", [$empId]);

            // حذف الحضور
            $del("DELETE FROM attendance WHERE employee_id = ?", [$empId]);

            // حذف طلبات سير العمل وما يتعلق بها
            $requestIds = array_column(
                Database::query("SELECT id FROM requests WHERE requester_employee_id = ?", [$empId])->fetchAll(),
                'id'
            );
            if ($requestIds) {
                $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
                $del("UPDATE material_requests SET workflow_request_id = NULL WHERE workflow_request_id IN ($placeholders)", $requestIds);
                $del("DELETE FROM request_participants WHERE request_id IN ($placeholders)", $requestIds);
                $del("DELETE FROM request_history WHERE request_id IN ($placeholders)", $requestIds);
                $del("DELETE FROM request_step_decisions WHERE request_id IN ($placeholders)", $requestIds);
                $del("DELETE FROM workflow_tasks WHERE request_id IN ($placeholders)", $requestIds);
                $del("DELETE FROM hr_workflow_links WHERE request_id IN ($placeholders)", $requestIds);
                $del("DELETE FROM workflow_entity_links WHERE request_id IN ($placeholders)", $requestIds);
                $del("DELETE FROM requests WHERE id IN ($placeholders)", $requestIds);
            }

            // تعطيل حساب الدخول المرتبط (بدلاً من حذفه حفاظاً على السجلات الأخرى)
            $userId = $employee['user_id'] ? (int)$employee['user_id'] : null;
            if ($userId) {
                Database::query("UPDATE users SET is_active = 0 WHERE id = ?", [$userId]);
            }

            // حذف الموظف
            Database::query("DELETE FROM employees WHERE id = ?", [$empId]);
            Helpers::audit('حذف موظف نهائي', 'employees', $empId, $employee['full_name']);
            Database::commit();
            $this->flash('success', 'تم حذف الموظف ' . $employee['full_name'] . ' وجميع البيانات المرتبطة به نهائياً');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'فشل حذف الموظف: ' . $e->getMessage());
        }
        $modalSuffix = !empty($_GET['modal']) ? '?iframe_close=1' : '';
        Response::redirect('/hr/employees' . $modalSuffix);
    }

    public function employeeDocuments(string $id): void
    {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$id])->fetch();
        if (!$employee) {
            Response::redirect('/hr/employees');
        }
        $documents = HrService::getEmployeeDocuments((int)$id);
        $this->view('hr/employee_documents', [
            'title' => 'مستندات الموظف: ' . $employee['full_name'],
            'employee' => $employee,
            'documents' => $documents,
        ]);
    }

    public function storeEmployeeDocument(string $id): void
    {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$id])->fetch();
        if (!$employee) {
            Response::redirect('/hr/employees');
        }
        $data = [
            'doc_type' => (string)$this->input('doc_type', 'other'),
            'title' => (string)$this->input('title'),
            'issue_date' => (string)$this->input('issue_date'),
            'expiry_date' => (string)$this->input('expiry_date'),
            'notes' => (string)$this->input('notes'),
        ];
        $filePath = null;
        if (!empty($_FILES['file']['tmp_name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $filePath = DocumentUpload::fromMultipart('file', 'employee_documents');
        }
        HrService::storeEmployeeDocument((int)$id, $data, $filePath, Auth::id());
        $this->flash('success', 'تم إضافة المستند');
        $modalSuffix = !empty($_GET['modal']) ? '?modal=1' : '';
        Response::redirect('/hr/employees/' . $id . '/documents' . $modalSuffix);
    }

    public function deleteEmployeeDocument(string $docId): void
    {
        $doc = Database::query("SELECT * FROM employee_documents WHERE id = ?", [(int)$docId])->fetch();
        if (!$doc) {
            Response::redirect('/hr/employees');
        }
        HrService::deleteEmployeeDocument((int)$docId, (int)$doc['employee_id'], Auth::id());
        $this->flash('success', 'تم حذف المستند');
        $modalSuffix = !empty($_GET['modal']) ? '?modal=1' : '';
        Response::redirect('/hr/employees/' . $doc['employee_id'] . '/documents' . $modalSuffix);
    }

    public function employeeStatusLogs(): void
    {
        $statusLogs = Database::query(
            "SELECT l.*, e.full_name, u.full_name AS changed_by_name
             FROM employee_file_status_logs l
             JOIN employees e ON e.id = l.employee_id
             LEFT JOIN users u ON u.id = l.changed_by
             ORDER BY l.id DESC LIMIT 50"
        )->fetchAll();
        $this->view('hr/employees_logs', [
            'title' => 'سجل تغييرات حالة الملف',
            'statusLogs' => $statusLogs,
        ]);
    }

    public function employeeAudit(string $id): void
    {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$id])->fetch();
        if (!$employee) {
            Response::redirect('/hr/employees');
        }
        $logs = EmployeeAuditService::getByEmployee((int)$id);
        $this->view('hr/employee_audit', [
            'title' => 'سجل التدقيق: ' . $employee['full_name'],
            'employee' => $employee,
            'logs' => $logs,
        ]);
    }

    public function showEmployeeProfile(string $id): void
    {
        if (!Auth::can('hr.employee.profile') && !Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/employees');
        }
        $employee = Database::query(
            "SELECT e.*, b.name AS branch_name, d.name AS department_name, j.title AS job_title,
                m.full_name AS manager_name, u.full_name AS user_full_name, u.username
             FROM employees e
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN jobs j ON j.id = e.job_id
             LEFT JOIN employees m ON m.id = e.manager_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.id = ?",
            [(int)$id]
        )->fetch();
        if (!$employee) {
            Response::redirect('/hr/employees');
        }
        $departments = Database::query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
        $resignation = Database::query(
            "SELECT * FROM resignations WHERE employee_id = ? ORDER BY id DESC LIMIT 1",
            [(int)$id]
        )->fetch();

        $canViewSalary = Auth::can('hr.employee.salary.view');
        if (!$canViewSalary) {
            $employee['base_salary'] = null;
            $resignation = null;
        }

        $contracts = HrService::getEmployeeContracts((int)$id);
        if (!$canViewSalary) {
            foreach ($contracts as &$c) {
                $c['salary'] = null;
            }
            unset($c);
        }

        $payrolls = HrService::getEmployeePayrolls((int)$id);
        if (!Auth::can('hr.payslip.view')) {
            $payrolls = [];
        }

        $this->view('hr/employee_profile', [
            'title' => 'الملف الإلكتروني: ' . $employee['full_name'],
            'employee' => $employee,
            'departments' => $departments,
            'contracts' => $contracts,
            'documents' => HrService::getEmployeeDocuments((int)$id),
            'attendance' => HrService::getEmployeeAttendance((int)$id),
            'leaves' => HrService::getEmployeeLeaves((int)$id),
            'advances' => HrService::getEmployeeAdvances((int)$id),
            'payrolls' => $payrolls,
            'securityDeposits' => HrService::getEmployeeSecurityDeposits((int)$id),
            'securityDepositBalance' => HrService::getSecurityDepositBalance((int)$id),
            'adjustments' => HrService::getEmployeeAdjustments((int)$id),
            'travelTickets' => HrService::getEmployeeTravelTickets((int)$id),
            'loadings' => HrService::getEmployeeLoadings((int)$id),
            'tips' => HrService::getEmployeeMonthlyTipsRecords((int)$id),
            'socialSecurity' => HrService::getEmployeeMonthlySocialSecurityRecords((int)$id),
            'warnings' => HrService::getEmployeeWarnings((int)$id),
            'evaluations' => HrService::getEmployeeEvaluations((int)$id),
            'auditLogs' => $this->filterSalaryAuditLogs(EmployeeAuditService::getByEmployee((int)$id), $canViewSalary),
            'resignation' => $resignation,
            'canViewSalary' => $canViewSalary,
        ]);
    }

    public function storeEmployeeContract(string $id): void
    {
        if (!Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/employees/' . $id);
        }
        $filePath = null;
        if (!empty($_FILES['file']['tmp_name'])) {
            $filePath = DocumentUpload::fromMultipart('file', 'employee_contracts');
        }
        HrService::storeEmployeeContract((int)$id, [
            'contract_type' => (string)$this->input('contract_type'),
            'start_date' => (string)$this->input('start_date'),
            'end_date' => (string)$this->input('end_date'),
            'salary' => (float)$this->input('salary', 0),
            'position' => (string)$this->input('position'),
            'department_id' => (int)$this->input('department_id', 0) ?: null,
            'benefits' => (string)$this->input('benefits'),
        ], $filePath, Auth::id());
        $this->flash('success', 'تم إضافة العقد');
        $modalSuffix = !empty($_GET['modal']) ? '?modal=1' : '';
        Response::redirect('/hr/employees/' . $id . $modalSuffix . '#contracts');
    }

    public function deleteEmployeeContract(string $id): void
    {
        if (!Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/employees');
        }
        HrService::deleteEmployeeContract((int)$id);
        $this->flash('success', 'تم حذف العقد');
        Response::redirect('/hr/employees');
    }

    public function departments(): void
    {
        $departments = Database::query("SELECT * FROM departments ORDER BY name")->fetchAll();
        $this->view('hr/departments', [
            'title' => 'الأقسام',
            'departments' => $departments,
        ]);
    }

    public function storeDepartment(): void
    {
        Database::query(
            "INSERT INTO departments (name, code) VALUES (?, ?)",
            [(string)$this->input('name'), (string)$this->input('code')]
        );
        $this->flash('success', 'تم إضافة القسم');
        Response::redirect('/hr/departments');
    }

    public function updateDepartment(string $id): void
    {
        Database::query(
            "UPDATE departments SET name = ?, code = ?, is_active = ? WHERE id = ?",
            [(string)$this->input('name'), (string)$this->input('code'), (int)$this->input('is_active', 1), (int)$id]
        );
        $this->flash('success', 'تم تحديث القسم');
        Response::redirect('/hr/departments');
    }

    public function deleteDepartment(string $id): void
    {
        $department = Database::query("SELECT * FROM departments WHERE id = ?", [(int)$id])->fetch();
        if (!$department) {
            Response::redirect('/hr/departments');
            return;
        }
        Database::query("DELETE FROM departments WHERE id = ?", [(int)$id]);
        Helpers::audit('حذف قسم', 'departments', (int)$id, $department['name']);
        $this->flash('success', 'تم حذف القسم');
        Response::redirect('/hr/departments');
    }

    public function departmentsTemplate(): void
    {
        ImportHelper::downloadTemplate(
            ['name', 'code'],
            'departments_template.xlsx',
            ['المطبخ', 'KITCHEN']
        );
    }

    public function importDepartments(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/hr/departments');
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/hr/departments');
        }
        $rows = $read['rows'];
        $errors = array_merge(
            ImportHelper::validateRequired($rows, ['name']),
            ImportHelper::validateUnique($rows, 'name'),
            ImportHelper::validateNotInDb($rows, 'name', 'departments', 'name')
        );
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/hr/departments');
        }
        Database::beginTransaction();
        try {
            $count = 0;
            foreach ($rows as $row) {
                Database::query(
                    "INSERT INTO departments (name, code, is_active) VALUES (?, ?, 1)",
                    [trim((string)$row['name']), trim((string)($row['code'] ?? ''))]
                );
                $count++;
            }
            Database::commit();
            Helpers::audit('استيراد أقسام', 'departments', 0, "created:$count");
            $this->flash('success', "تم استيراد $count قسم");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء الحفظ: ' . $e->getMessage());
        }
        Response::redirect('/hr/departments');
    }

    public function jobs(): void
    {
        $period = (string)$this->input('period', date('Y-m'));
        $jobs = Database::query(
            "SELECT j.*, d.name AS department_name, r.name AS role_name, r.level AS role_level,
                    (SELECT COUNT(*) FROM employees e WHERE e.file_status = 'open' AND e.job_id = j.id) AS actual_count,
                    (SELECT COALESCE(SUM(h.planned_count),0) FROM hr_headcount_plans h WHERE h.job_id = j.id AND h.period = ?) AS planned_count
             FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN roles r ON r.id = j.role_id
             ORDER BY j.title",
            [$period]
        )->fetchAll();
        $departments = Database::query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
        $roles = Database::query("SELECT id, name, level FROM roles ORDER BY level DESC")->fetchAll();
        $permissions = Database::query("SELECT * FROM permissions ORDER BY module, id")->fetchAll();
        $rolePerms = [];
        foreach (Database::query("SELECT role_id, permission_id FROM role_permissions")->fetchAll() as $rp) {
            $rolePerms[$rp['role_id']][] = (int)$rp['permission_id'];
        }
        $workflowTemplates = Database::query("SELECT * FROM approval_templates WHERE is_active = 1 ORDER BY name")->fetchAll();
        $templateRolePerms = [];
        foreach (Database::query("SELECT * FROM template_allowed_roles")->fetchAll() as $tr) {
            $templateRolePerms[$tr['role_id']][$tr['template_id']] = $tr;
        }
        $this->view('hr/jobs', [
            'title' => 'الوظائف',
            'jobs' => $jobs,
            'departments' => $departments,
            'roles' => $roles,
            'period' => $period,
            'permissions' => $permissions,
            'rolePerms' => $rolePerms,
            'workflowTemplates' => $workflowTemplates,
            'templateRolePerms' => $templateRolePerms,
        ]);
    }

    public function storeJob(): void
    {
        Database::query(
            "INSERT INTO jobs (title, department_id, role_id, code) VALUES (?, ?, ?, ?)",
            [(string)$this->input('title'), $this->input('department_id') ?: null, $this->input('role_id') ?: null, (string)$this->input('code')]
        );
        $this->flash('success', 'تم إضافة الوظيفة');
        Response::redirect('/hr/jobs');
    }

    public function updateJob(string $id): void
    {
        Database::query(
            "UPDATE jobs SET title = ?, department_id = ?, role_id = ?, code = ?, is_active = ? WHERE id = ?",
            [(string)$this->input('title'), $this->input('department_id') ?: null, $this->input('role_id') ?: null, (string)$this->input('code'), (int)$this->input('is_active', 1), (int)$id]
        );
        $this->flash('success', 'تم تحديث الوظيفة');
        Response::redirect('/hr/jobs');
    }

    public function deleteJob(string $id): void
    {
        $job = Database::query("SELECT * FROM jobs WHERE id = ?", [(int)$id])->fetch();
        if (!$job) {
            Response::redirect('/hr/jobs');
            return;
        }
        Database::query("DELETE FROM jobs WHERE id = ?", [(int)$id]);
        Helpers::audit('حذف وظيفة', 'jobs', (int)$id, $job['title']);
        $this->flash('success', 'تم حذف الوظيفة');
        Response::redirect('/hr/jobs');
    }

    public function jobsTemplate(): void
    {
        ImportHelper::downloadTemplate(
            ['department', 'job'],
            'jobs_template.xlsx',
            ['المطبخ', 'شيف عام']
        );
    }

    public function importJobs(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/hr/jobs');
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name'], true);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/hr/jobs');
        }
        $rows = $read['rows'];

        $departments = Database::query("SELECT id, name FROM departments")->fetchAll();
        $deptMap = [];
        $normalizedMap = [];
        foreach ($departments as $d) {
            $name = trim($d['name']);
            $deptMap[$name] = (int)$d['id'];
            $normalized = mb_strtolower($name, 'UTF-8');
            $normalizedMap[$normalized] = (int)$d['id'];
            $withoutPrefix = mb_strtolower(preg_replace('/^\s*قسم\s+/u', '', $name), 'UTF-8');
            $normalizedMap[$withoutPrefix] = (int)$d['id'];
        }

        $errors = [];
        $created = 0;
        $skipped = 0;

        Database::beginTransaction();
        try {
            foreach ($rows as $row) {
                $values = array_values($row);
                $deptName = trim((string)($values[0] ?? ''));
                if (!$deptName) {
                    continue;
                }
                $deptId = null;
                if (isset($deptMap[$deptName])) {
                    $deptId = $deptMap[$deptName];
                } else {
                    $search = mb_strtolower($deptName, 'UTF-8');
                    $deptId = $normalizedMap[$search] ?? null;
                    if (!$deptId) {
                        $withoutPrefix = mb_strtolower(preg_replace('/^\s*قسم\s+/u', '', $deptName), 'UTF-8');
                        $deptId = $normalizedMap[$withoutPrefix] ?? null;
                    }
                    if (!$deptId) {
                        $withPrefix = mb_strtolower('قسم ' . $deptName, 'UTF-8');
                        $deptId = $normalizedMap[$withPrefix] ?? null;
                    }
                }
                if (!$deptId) {
                    $errors[] = "القسم '{$deptName}' غير موجود في الأقسام";
                    continue;
                }

                for ($i = 1; $i < count($values); $i++) {
                    $title = trim((string)$values[$i]);
                    if ($title === '' || $title === 'nan') {
                        continue;
                    }
                    $exists = Database::query(
                        "SELECT id FROM jobs WHERE title = ? AND department_id = ?",
                        [$title, $deptId]
                    )->fetch();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                    Database::query(
                        "INSERT INTO jobs (title, department_id, code, is_active) VALUES (?, ?, '', 1)",
                        [$title, $deptId]
                    );
                    $created++;
                }
            }
            Database::commit();
            if ($errors) {
                ImportHelper::flashErrors($errors);
            }
            Helpers::audit('استيراد وظائف', 'jobs', 0, "created:{$created} skipped:{$skipped}");
            $this->flash('success', "تم استيراد {$created} وظيفة جديدة" . ($skipped ? " (تجاوز {$skipped} مكررة)" : ""));
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء الحفظ: ' . $e->getMessage());
        }
        Response::redirect('/hr/jobs');
    }

    public function attendance(): void
    {
        $date = (string)($_GET['date'] ?? date('Y-m-d'));
        // يوم العمل = التاريخ التقويمي للبصمة
        $records = $this->getAttendanceDayRecords($date);

        $this->view('hr/attendance', [
            'title' => 'الحضور والانصراف', 'records' => $records, 'date' => $date,
        ]);
    }

    /**
     * جلب بصمات يوم العمل (النظام الهجين) مفصلة لكل موظف مع تزويج كل دخول/خروج متتالي.
     */
    private function getAttendanceDayRecords(string $date): array
    {
        $from = date('Y-m-d', strtotime($date . ' -1 day'));
        $to = date('Y-m-d', strtotime($date . ' +1 day'));
        $raw = Database::query(
            "SELECT
                e.id AS employee_id,
                e.emp_code,
                e.full_name,
                e.department,
                a.id,
                a.check_type,
                a.checked_at,
                a.source
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             WHERE DATE(a.checked_at) BETWEEN ? AND ?
             ORDER BY e.full_name ASC, a.checked_at ASC",
            [$from, $to]
        )->fetchAll();

        $employeeIds = array_unique(array_map(fn ($r) => (int)$r['employee_id'], $raw));
        $shiftMap = HrService::loadShiftMap($employeeIds, $from, $to);
        return HrService::pairAndGroupAttendance($raw, $shiftMap, $date);
    }

    public function attendanceAlerts(): void
    {
        $date = (string)($_GET['date'] ?? date('Y-m-d'));
        $created = [];
        $error = '';
        if (isset($_GET['run'])) {
            try {
                $created = HrService::checkMissingClockIns($date);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $alerts = Database::query(
            "SELECT aa.*, e.full_name, e.department, e.work_start_time FROM attendance_alerts aa
             JOIN employees e ON e.id = aa.employee_id
             WHERE aa.alert_date = ? ORDER BY aa.created_at DESC",
            [$date]
        )->fetchAll();

        $this->view('hr/attendance_alerts', [
            'title' => 'تنبيهات الحضور', 'alerts' => $alerts, 'date' => $date, 'created' => $created,
            'error' => $error,
        ]);
    }

    public function updateAlertStatus(string $id): void
    {
        $status = (string)$this->input('status', 'acknowledged');
        if (in_array($status, ['acknowledged', 'resolved'], true)) {
            Database::query("UPDATE attendance_alerts SET status = ? WHERE id = ?", [$status, (int)$id]);
            $this->flash('success', 'تم تحديث حالة التنبيه');
        }
        Response::redirect('/hr/attendance/alerts');
    }

    public function runMissingClockInAlerts(): void
    {
        $created = HrService::checkMissingClockIns(date('Y-m-d'));
        Response::json(['ok' => true, 'created' => count($created), 'alerts' => $created]);
    }

    public function notificationRules(): void
    {
        $rules = Database::query(
            "SELECT nr.*, r.name AS role_name, u.full_name AS user_name FROM notification_rules nr
             LEFT JOIN roles r ON r.id = nr.role_id
             LEFT JOIN users u ON u.id = nr.user_id
             ORDER BY nr.event_type"
        )->fetchAll();
        $roles = Database::query("SELECT * FROM roles ORDER BY level DESC")->fetchAll();
        $users = Database::query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
        $this->view('hr/notification_rules', [
            'title' => 'قواعد التبليغات', 'rules' => $rules, 'roles' => $roles, 'users' => $users,
        ]);
    }

    public function storeNotificationRule(): void
    {
        $recipientType = (string) $this->input('recipient_type', 'level');
        $roleId = $recipientType === 'role' ? ($this->input('role_id') ?: null) : null;
        $userId = $recipientType === 'user' ? ($this->input('user_id') ?: null) : null;

        Database::query(
            "INSERT INTO notification_rules (event_type, min_admin_level, branch_specific, department_specific, role_id, user_id)
             VALUES (?,?,?,?,?,?)",
            [(string)$this->input('event_type'), (int)$this->input('min_admin_level', 0),
             !empty($this->input('branch_specific')) ? 1 : 0,
             !empty($this->input('department_specific')) ? 1 : 0,
             $roleId, $userId]
        );
        $this->flash('success', 'تم إضافة القاعدة');
        Response::redirect('/hr/notification-rules');
    }

    public function toggleNotificationRule(string $id): void
    {
        Database::query(
            "UPDATE notification_rules SET is_active = NOT is_active WHERE id = ?",
            [(int)$id]
        );
        $this->flash('success', 'تم تحديث الحالة');
        Response::redirect('/hr/notification-rules');
    }

    /** بيانات الشريط الجانبي المشترك (سجل التعميمات + التذكيرات) لعرض الصفحة على شكل بريد إلكتروني */
    private function announcementsSidebarData(): array
    {
        $history = Database::query(
            "SELECT n.id, n.title, n.target_type, n.sender_id, n.created_at, n.broadcast_id,
                s.full_name AS sender_name,
                COUNT(DISTINCT n2.user_id) AS recipient_count
             FROM notifications n
             LEFT JOIN notifications n2 ON n2.broadcast_id = n.broadcast_id OR (n.broadcast_id IS NULL AND n2.id = n.id)
             LEFT JOIN users s ON s.id = n.sender_id
             WHERE n.is_announcement = 1
               AND (n.broadcast_id IS NULL OR n.id = n.broadcast_id)
             GROUP BY n.id, n.title, n.target_type, n.sender_id, n.created_at, n.broadcast_id, s.full_name
             ORDER BY n.id DESC
             LIMIT 30"
        )->fetchAll();
        $reminders = Auth::can('hr.notifications.reminders') ? AnnouncementReminderService::listAll() : [];
        return ['historyItems' => $history, 'reminders' => $reminders];
    }

    public function announcements(): void
    {
        if (!Auth::can('hr.notifications.send') && !Auth::can('hr.notifications.view')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/dashboard');
        }
        $roles = Database::query("SELECT id, name FROM roles ORDER BY level DESC")->fetchAll();
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();
        $departments = Database::query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
        $users = Database::query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
        $this->view('hr/announcements', array_merge([
            'title' => 'التعميمات والتبليغات', 'roles' => $roles, 'branches' => $branches,
            'departments' => $departments, 'users' => $users, 'activeId' => null,
        ], $this->announcementsSidebarData()));
    }

    public function sendAnnouncement(): void
    {
        if (!Auth::can('hr.notifications.send')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/announcements');
        }
        $title = trim((string)$this->input('title'));
        $body = trim((string)$this->input('body'));
        $link = trim((string)$this->input('link')) ?: null;
        $targetType = (string)$this->input('target_type', 'all');
        if (!$title || !$body) {
            $this->flash('danger', 'العنوان والنص مطلوبان');
            Response::redirect('/hr/announcements');
        }
        $senderId = Auth::id();
        // إشعار Web + إشعار Push (تطبيق الموبايل/المتصفح) معًا لكل مستلمي التعميم
        switch ($targetType) {
            case 'all':
                Helpers::broadcastAnnouncement($title, $body, $link, $senderId, true);
                break;
            case 'level':
                Helpers::notifyByLevel((int)$this->input('target_level', 1), $title, $body, $link, $senderId, true);
                break;
            case 'role':
                $roleId = (int)$this->input('target_role_id', 0);
                if ($roleId) {
                    Helpers::notifyRole($roleId, $title, $body, $link, $senderId, true);
                }
                break;
            case 'branch':
                $branchId = (int)$this->input('target_branch_id', 0);
                if ($branchId) {
                    Helpers::notifyByBranch($branchId, $title, $body, $link, $senderId, true);
                }
                break;
            case 'department':
                $departmentId = (int)$this->input('target_department_id', 0);
                if ($departmentId) {
                    Helpers::notifyByDepartment($departmentId, $title, $body, $link, $senderId, true);
                }
                break;
            case 'user':
                $userIds = $this->input('target_user_id', []);
                if (!is_array($userIds)) {
                    $userIds = $userIds !== '' && $userIds !== null ? [$userIds] : [];
                }
                $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
                if ($userIds) {
                    Helpers::notifyUsers($userIds, $title, $body, $link, $senderId, true);
                }
                break;
        }
        Helpers::audit('notification_broadcast', 'announcement', 0, "target=$targetType title=$title", $senderId);

        // جدولة تذكير (لمرة واحدة أو روتيني) بنفس بيانات التعميم إن طُلب ذلك
        if ($this->input('enable_reminder') && Auth::can('hr.notifications.reminders')) {
            $remindAt = trim((string)$this->input('remind_at'));
            $recurrenceType = in_array($this->input('recurrence_type'), ['once', 'daily', 'weekly', 'monthly'], true)
                ? $this->input('recurrence_type') : 'once';
            $ts = $remindAt !== '' ? strtotime($remindAt) : false;
            if ($ts) {
                $reminderUserIds = $targetType === 'user' ? ($userIds ?? []) : [];
                AnnouncementReminderService::create([
                    'created_by' => $senderId,
                    'title' => $title, 'body' => $body, 'link' => $link,
                    'target_type' => $targetType,
                    'target_level' => $targetType === 'level' ? (int)$this->input('target_level', 1) : null,
                    'target_role_id' => $targetType === 'role' ? (int)$this->input('target_role_id', 0) : null,
                    'target_branch_id' => $targetType === 'branch' ? (int)$this->input('target_branch_id', 0) : null,
                    'target_department_id' => $targetType === 'department' ? (int)$this->input('target_department_id', 0) : null,
                    'target_user_ids' => $reminderUserIds,
                    'recurrence_type' => $recurrenceType,
                    'next_run_at' => date('Y-m-d H:i:s', $ts),
                ]);
                $this->flash('success', 'تم إرسال التبليغ وجدولة التذكير');
                Response::redirect('/hr/announcements');
            }
        }

        $this->flash('success', 'تم إرسال التبليغ');
        Response::redirect('/hr/announcements');
    }

    public function toggleAnnouncementReminder(string $id): void
    {
        if (!Auth::can('hr.notifications.reminders')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/announcements');
        }
        AnnouncementReminderService::toggle((int)$id);
        $this->flash('success', 'تم تحديث حالة التذكير');
        Response::redirect('/hr/announcements');
    }

    public function deleteAnnouncementReminder(string $id): void
    {
        if (!Auth::can('hr.notifications.reminders')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/announcements');
        }
        AnnouncementReminderService::delete((int)$id);
        $this->flash('success', 'تم حذف التذكير');
        Response::redirect('/hr/announcements');
    }

    public function notificationHistory(): void
    {
        if (!Auth::can('hr.notifications.history') && !Auth::can('hr.notifications.send')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/dashboard');
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $q = trim((string)($_GET['q'] ?? ''));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $searchSql = '';
        $searchParams = [];
        if ($q !== '') {
            $searchSql = ' AND n.title LIKE ?';
            $searchParams[] = '%' . $q . '%';
        }
        $total = (int)Database::query(
            "SELECT COUNT(DISTINCT COALESCE(broadcast_id, id)) AS c FROM notifications n WHERE n.is_announcement = 1" . $searchSql,
            $searchParams
        )->fetch()['c'];
        $items = Database::query(
            "SELECT n.id, n.title, n.target_type, n.sender_id, n.created_at, n.broadcast_id,
                s.full_name AS sender_name,
                COUNT(DISTINCT n2.user_id) AS recipient_count,
                COUNT(DISTINCT nr.user_id) AS read_count
             FROM notifications n
             LEFT JOIN notifications n2 ON n2.broadcast_id = n.broadcast_id OR (n.broadcast_id IS NULL AND n2.id = n.id)
             LEFT JOIN users s ON s.id = n.sender_id
             LEFT JOIN notification_read_receipts nr ON nr.notification_id = n2.id
             WHERE n.is_announcement = 1
               AND (n.broadcast_id IS NULL OR n.id = n.broadcast_id)" . $searchSql . "
             GROUP BY n.id, n.title, n.target_type, n.sender_id, n.created_at, n.broadcast_id, s.full_name
             ORDER BY n.id DESC
             LIMIT ? OFFSET ?",
            array_merge($searchParams, [$limit, $offset])
        )->fetchAll();
        $this->view('hr/notifications_history', [
            'title' => 'سجل التبليغات والتعميمات', 'items' => $items,
            'page' => $page, 'totalPages' => (int)ceil($total / $limit), 'total' => $total, 'q' => $q,
        ]);
    }

    public function showNotificationHistory(string $id): void
    {
        if (!Auth::can('hr.notifications.history') && !Auth::can('hr.notifications.send')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/notifications-history');
        }
        $notification = Database::query(
            "SELECT n.*, s.full_name AS sender_name FROM notifications n LEFT JOIN users s ON s.id = n.sender_id WHERE n.id = ?",
            [(int)$id]
        )->fetch();
        if (!$notification) {
            $this->flash('danger', 'التبليغ غير موجود');
            Response::redirect('/hr/notifications-history');
        }
        $broadcastId = $notification['broadcast_id'] ?? $notification['id'];
        $recipients = Database::query(
            "SELECT u.id AS user_id, u.full_name, u.username, n.id AS notification_id, n.is_read, n.read_at, n.read_ip,
                nr.read_at AS receipt_read_at, nr.read_ip AS receipt_read_ip
             FROM notifications n
             JOIN users u ON u.id = n.user_id
             LEFT JOIN notification_read_receipts nr ON nr.notification_id = n.id
             WHERE n.broadcast_id = ? OR n.id = ?
             ORDER BY u.full_name",
            [$broadcastId, $notification['id']]
        )->fetchAll();
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' || !empty($this->input('ajax'));
        if ($isAjax) {
            echo $this->partial('hr/_announcement_detail', [
                'notification' => $notification, 'recipients' => $recipients,
            ]);
            return;
        }
        $this->view('hr/notifications_history_show', array_merge([
            'title' => 'تفاصيل التبليغ', 'notification' => $notification, 'recipients' => $recipients,
            'activeId' => (int)$broadcastId,
            'backPage' => max(1, (int)($_GET['page'] ?? 1)),
            'backQ' => trim((string)($_GET['q'] ?? '')),
        ], $this->announcementsSidebarData()));
    }

    public function markNotificationRead(): void
    {
        $id = (int)$this->input('id');
        Helpers::markNotificationRead($id, Auth::id());
        Response::json(['ok' => true]);
    }

    public function clockPage(): void
    {
        // تسجيل دخول تلقائي من التطبيق باستخدام mobile token
        $mobileToken = (string)($_GET['mobile_token'] ?? '');
        if ($mobileToken && !Auth::check()) {
            $user = \App\Core\ApiAuth::validateToken($mobileToken);
            if ($user) {
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['perms'] = \App\Core\Auth::effectivePermissions((int)$user['role_id'], (string)$user['role_name']);
                \App\Core\Auth::refresh();
            }
        }

        $userId = Auth::id();
        $employee = null;
        if ($userId) {
            $employee = Database::query("SELECT * FROM employees WHERE user_id = ?", [$userId])->fetch();
        }
        $lastRecord = null;
        if ($employee) {
            $lastRecord = Database::query(
                "SELECT * FROM attendance WHERE employee_id = ? ORDER BY id DESC LIMIT 1",
                [(int)$employee['id']]
            )->fetch();
        }
        $branch = null;
        if ($employee && $employee['branch_id']) {
            $branch = Database::query("SELECT * FROM branches WHERE id = ?", [(int)$employee['branch_id']])->fetch();
        }
        $this->view('hr/clock', [
            'title' => 'بصمة الحضور/الانصراف',
            'employee' => $employee, 'lastRecord' => $lastRecord, 'branch' => $branch,
        ]);
    }

    public function clockInOut(): void
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::redirect('/login');
        }
        $employee = Database::query("SELECT * FROM employees WHERE user_id = ?", [$userId])->fetch();
        if (!$employee) {
            $this->flash('danger', 'لا يوجد ملف موظف مرتبط بهذا الحساب');
            Response::redirect('/hr/clock');
        }
        $type = ($this->input('check_type') ?? 'in') === 'out' ? 'out' : 'in';
        $lat = (float)($this->input('latitude') ?? 0);
        $lng = (float)($this->input('longitude') ?? 0);
        $photoPath = null;
        if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/attendance/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $filename = 'att_' . $employee['id'] . '_' . date('Ymd_His') . '_' . uniqid() . '.jpg';
            move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename);
            $photoPath = '/uploads/attendance/' . $filename;
        }
        $result = HrService::recordAttendance((int)$employee['id'], $type, $lat, $lng, $photoPath, 'web');
        $this->flash('success', $type === 'in' ? 'تم تسجيل الحضور' : 'تم تسجيل الانصراف');
        Response::redirect('/hr/clock');
    }

    public function biometricImport(): void
    {
        $deviceId = (string)$this->input('device_id', '');
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/hr/attendance');
        }
        $rows = BiometricService::parseCsv($_FILES['import_file']['tmp_name']);
        if (isset($rows['error'])) {
            $this->flash('danger', $rows['error']);
            Response::redirect('/hr/attendance');
        }
        $count = 0;
        foreach ($rows as $row) {
            $emp = Database::query(
                "SELECT id FROM employees WHERE emp_code = ? OR UPPER(emp_code) = UPPER(?)",
                [$row['emp_code'], $row['emp_code']]
            )->fetch();
            if (!$emp) {
                continue;
            }
            Database::query(
                "INSERT INTO attendance (employee_id, check_type, checked_at, device_id, source, within_geofence)
                 VALUES (?,?,?,?, 'device', 1)",
                [(int)$emp['id'], $row['check_type'], $row['checked_at'], $deviceId]
            );
            Helpers::queueSync('attendance', (int)Database::lastInsertId(), 'insert', [
                'employee_id' => (int)$emp['id'], 'check_type' => $row['check_type'], 'checked_at' => $row['checked_at'],
            ]);
            $count++;
        }
        $this->flash('success', "تم استيراد $count سجل بصمة");
        Response::redirect('/hr/attendance');
    }


    public function leaves(): void
    {
        $type = (string)($_GET['type'] ?? '');
        $status = (string)($_GET['status'] ?? '');
        $employeeId = (int)($_GET['employee_id'] ?? 0);

        $where = ['1=1'];
        $params = [];
        if ($type !== '') {
            $where[] = "l.request_type = ?";
            $params[] = $type;
        }
        if ($status !== '') {
            $where[] = "l.status = ?";
            $params[] = $status;
        }
        if ($employeeId > 0) {
            $where[] = "l.employee_id = ?";
            $params[] = $employeeId;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $requests = Database::query(
            "SELECT l.*, e.full_name, e.emp_code, e.manager_id, m.full_name AS manager_name,
                    u.username AS requester_name,
                    hwl.request_id AS workflow_request_id, req.status AS workflow_status, req.current_step AS workflow_current_step
             FROM leave_requests l
             JOIN employees e ON e.id = l.employee_id
             LEFT JOIN employees m ON m.id = e.manager_id
             LEFT JOIN users u ON u.id = l.requested_by
             LEFT JOIN hr_workflow_links hwl ON hwl.entity_type = 'leave_request' AND hwl.entity_id = l.id
             LEFT JOIN requests req ON req.id = hwl.request_id
             $whereSql
             ORDER BY l.id DESC
             LIMIT 200",
            $params
        )->fetchAll();

        foreach ($requests as &$r) {
            $r['attachments'] = HrService::getLeaveRequestAttachments((int)$r['id']);
        }
        unset($r);

        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();

        $this->view('hr/leaves', [
            'title' => 'طلبات الموارد البشرية',
            'requests' => $requests,
            'employees' => $employees,
            'filters' => ['type' => $type, 'status' => $status, 'employee_id' => $employeeId],
        ]);
    }

    public function storeLeave(): void
    {
        $employeeId = (int)$this->input('employee_id');
        if (!HrService::isEmployeeFileOpen($employeeId)) {
            $this->flash('danger', 'ملف الموظف مغلق أو مأرشف، لا يمكن تقديم طلب');
            Response::redirect('/hr/leaves');
        }

        $requestType = (string)$this->input('request_type', 'leave');
        if (!in_array($requestType, ['leave', 'exit', 'delay', 'work_resumption'], true)) {
            $requestType = 'leave';
        }
        $leaveType = $requestType === 'leave' ? (string)$this->input('leave_type', 'annual') : 'n/a';

        $dateFrom = (string)$this->input('date_from');
        $dateTo = $requestType === 'leave' ? (string)$this->input('date_to') : $dateFrom;
        if (!$dateFrom || !$dateTo) {
            $this->flash('danger', 'التاريخ مطلوب');
            Response::redirect('/hr/leaves');
        }

        $startTime = (string)$this->input('start_time') ?: null;
        $endTime = (string)$this->input('end_time') ?: null;
        $reason = (string)$this->input('reason');
        $notes = (string)$this->input('notes');

        Database::query(
            "INSERT INTO leave_requests (employee_id, requested_by, request_type, leave_type, date_from, date_to, start_time, end_time, reason, notes, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$employeeId, Auth::id(), $requestType, $leaveType, $dateFrom, $dateTo, $startTime, $endTime, $reason, $notes, 'pending']
        );
        $requestId = (int)Database::lastInsertId();

        if (!empty($_FILES['attachments']['tmp_name'])) {
            foreach (array_keys($_FILES['attachments']['tmp_name']) as $i) {
                $file = [
                    'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                    'error' => $_FILES['attachments']['error'][$i],
                    'size' => $_FILES['attachments']['size'][$i],
                    'name' => $_FILES['attachments']['name'][$i],
                ];
                $path = DocumentUpload::fromFilesArray($file, 'leave_requests');
                if ($path) {
                    Database::query(
                        "INSERT INTO leave_request_attachments (request_id, file_path, original_name, mime_type, file_size, uploaded_by)
                         VALUES (?,?,?,?,?,?)",
                        [$requestId, $path, $file['name'], mime_content_type($file['tmp_name']), $file['size'], Auth::id()]
                    );
                }
            }
        }

        Helpers::queueSync('leave_requests', $requestId, 'insert', [
            'employee_id' => $employeeId,
            'request_type' => $requestType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $emp = Database::query("SELECT * FROM employees WHERE id = ?", [$employeeId])->fetch();
        HrWorkflowService::request('leave_request', $requestId, (int)Auth::id(), [
            'employee_id' => $employeeId,
            'employee_name' => $emp['full_name'] ?? '',
            'request_type' => $requestType,
            'leave_type' => $leaveType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reason' => $reason,
            'title' => 'طلب ' . self::requestTypeLabel($requestType) . ' - ' . Helpers::e($emp['full_name'] ?? ''),
        ]);

        $this->flash('success', 'تم تقديم الطلب وأرسل للموافقة');
        Response::redirect('/hr/leaves');
    }

    public function showLeave(string $id): void
    {
        $requestId = (int)$id;
        $request = Database::query(
            "SELECT l.*, e.full_name, e.emp_code, e.branch_id, e.manager_id, e.user_id AS employee_user_id,
                    m.full_name AS manager_name, u.username AS requester_name
             FROM leave_requests l
             JOIN employees e ON e.id = l.employee_id
             LEFT JOIN employees m ON m.id = e.manager_id
             LEFT JOIN users u ON u.id = l.requested_by
             WHERE l.id = ?",
            [$requestId]
        )->fetch();
        if (!$request) {
            Response::redirect('/hr/leaves');
        }

        $attachments = HrService::getLeaveRequestAttachments($requestId);
        $workflowRequestId = HrWorkflowService::workflowRequestId('leave_request', $requestId);
        $workflowRequest = $workflowRequestId ? WorkflowEngine::getRequest($workflowRequestId) : null;
        $workflowHistory = $workflowRequest ? WorkflowEngine::getHistory($workflowRequestId) : [];
        $canDecide = false;
        if ($workflowRequest) {
            foreach (WorkflowEngine::resolveSteps((int)$workflowRequest['template_id'], (int)$workflowRequest['current_step'], $workflowRequestId) as $s) {
                if (WorkflowEngine::userCanDecideStep($s, $workflowRequest)) {
                    $canDecide = true; break;
                }
            }
        }

        $this->view('hr/leaves_show', [
            'title' => 'طلب ' . self::requestTypeLabel($request['request_type']),
            'request' => $request,
            'attachments' => $attachments,
            'approvals' => $workflowHistory,
            'canDecide' => $canDecide,
            'workflowRequestId' => $workflowRequestId,
        ]);
    }

    public function decideLeave(string $id): void
    {
        $requestId = (int)$id;
        $decision = (string)$this->input('decision', 'approved');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            Response::redirect('/hr/leaves');
        }
        if (!Auth::can('approvals.decide') && !Auth::can('hr.leave.approve')) {
            $this->flash('danger', 'ليس لديك صلاحية اعتماد الطلبات');
            Response::redirect('/hr/leaves/' . $requestId);
        }

        $notes = (string)$this->input('notes');
        $finalStatus = null;

        $wfRequestId = HrWorkflowService::workflowRequestId('leave_request', $requestId);
        if ($wfRequestId) {
            try {
                WorkflowEngine::decide($wfRequestId, $decision, $notes, (int)Auth::id());
                $finalStatus = $decision;
            } catch (\Throwable $e) {
                $this->flash('danger', $e->getMessage());
                Response::redirect('/hr/leaves/' . $requestId);
                return;
            }
        } else {
            $request = Database::query("SELECT * FROM leave_requests WHERE id = ?", [$requestId])->fetch();
            if ($request && $request['status'] === 'pending') {
                Database::query(
                    "UPDATE leave_requests SET status = ?, approved_by = ? WHERE id = ?",
                    [$decision, Auth::id(), $requestId]
                );
                if ($decision === 'approved') {
                    HrService::applyLeaveApproval($requestId);
                } else {
                    HrService::revertLeaveApproval($requestId);
                }
                $finalStatus = $decision;
            }
        }

        if ($finalStatus === 'approved' || $finalStatus === 'rejected') {
            $request = Database::query(
                "SELECT l.*, e.user_id FROM leave_requests l JOIN employees e ON e.id = l.employee_id WHERE l.id = ?",
                [$requestId]
            )->fetch();
            if ($request && $request['user_id']) {
                Helpers::notify(
                    (int)$request['user_id'],
                    'نتيجة طلب ' . self::requestTypeLabel($request['request_type']),
                    'تم ' . ($finalStatus === 'approved' ? 'الموافقة' : 'الرفض') . ' على طلبك',
                    '/hr/leaves/' . $requestId
                );
            }
        }

        $this->flash('success', $decision === 'approved' ? 'تم اعتماد الطلب' : 'تم رفض الطلب');
        Response::redirect('/hr/leaves/' . $requestId);
    }

    public function leaveBalances(): void
    {
        $year = (int)($_GET['year'] ?? date('Y'));
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $selected = (int)($_GET['employee_id'] ?? 0);
        $balances = [];
        if ($selected) {
            $balances = Database::query(
                "SELECT * FROM leave_balances WHERE employee_id = ? AND year = ? ORDER BY leave_type",
                [$selected, $year]
            )->fetchAll();
        }
        $this->view('hr/leave_balances', [
            'title' => 'رصيد الإجازات',
            'employees' => $employees, 'balances' => $balances, 'year' => $year, 'selected' => $selected,
        ]);
    }

    public function updateLeaveBalance(): void
    {
        $employeeId = (int)$this->input('employee_id');
        $year = (int)$this->input('year', date('Y'));
        $type = (string)$this->input('leave_type');
        $entitled = (float)$this->input('entitled', 0);
        if (!$employeeId || !$type) {
            Response::redirect('/hr/leave-balances');
        }
        HrService::ensureBalance($employeeId, $year, $type);
        Database::query(
            "UPDATE leave_balances SET entitled = ?, balance = entitled - used
             WHERE employee_id = ? AND year = ? AND leave_type = ?",
            [$entitled, $employeeId, $year, $type]
        );
        $this->flash('success', 'تم تحديث الرصيد');
        Response::redirect('/hr/leave-balances?employee_id=' . $employeeId . '&year=' . $year);
    }


    public function advances(): void
    {
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $status = (string)($_GET['status'] ?? '');
        $scope = (string)($_GET['scope'] ?? 'current');

        $where = ['1=1'];
        $params = [];
        if ($employeeId) {
            $where[] = 'a.employee_id = ?';
            $params[] = $employeeId;
        }
        if ($status) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        } elseif ($scope === 'current') {
            $where[] = "a.status IN ('pending','approved','paid')";
        } elseif ($scope === 'previous') {
            $where[] = "a.status IN ('settled','rejected')";
        }

        $sql = "SELECT a.*, e.full_name, hwl.request_id AS workflow_request_id, req.current_step AS workflow_current_step
                FROM salary_advances a
                JOIN employees e ON e.id = a.employee_id
                LEFT JOIN hr_workflow_links hwl ON hwl.entity_type = 'salary_advance' AND hwl.entity_id = a.id
                LEFT JOIN requests req ON req.id = hwl.request_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.id DESC LIMIT 200";
        $advances = Database::query($sql, $params)->fetchAll();
        $employees = Database::query("SELECT id, full_name, base_salary FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();

        $this->view('hr/advances', [
            'title' => 'السلف', 'advances' => $advances, 'employees' => $employees,
            'employeeId' => $employeeId, 'status' => $status, 'scope' => $scope,
        ]);
    }

    public function storeAdvance(): void
    {
        $employeeId = (int)$this->input('employee_id');
        if (!HrService::isEmployeeFileOpen($employeeId)) {
            $this->flash('danger', 'ملف الموظف مغلق أو مأرشف، لا يمكن تقديم طلب سلفة');
            Response::redirect('/hr/advances');
        }
        $amount = (float)$this->input('amount');
        if ($amount <= 0) {
            $this->flash('danger', 'المبلغ يجب أن يكون أكبر من صفر');
            Response::redirect('/hr/advances');
        }
        $installments = max(1, (int)$this->input('installments', 1));
        $advanceType = (string)$this->input('advance_type', 'short');
        $isLongTerm = $advanceType === 'long' || $installments > 1;

        // طريقة احتساب القسط الشهري للسلف طويلة الأجل: نسبة من الراتب أو مبلغ ثابت مستقطع
        $installmentMethod = (string)$this->input('installment_method', 'fixed');
        if (!in_array($installmentMethod, ['fixed', 'percentage'], true)) {
            $installmentMethod = 'fixed';
        }
        $installmentPercentage = null;
        $installmentAmount = null;
        if ($isLongTerm) {
            if ($installmentMethod === 'percentage') {
                $installmentPercentage = (float)$this->input('installment_percentage', 0);
                if ($installmentPercentage <= 0 || $installmentPercentage > 100) {
                    $this->flash('danger', 'يجب إدخال نسبة قسط شهري صحيحة (بين 1 و100)');
                    Response::redirect('/hr/advances');
                }
                $emp = Database::query("SELECT base_salary FROM employees WHERE id = ?", [$employeeId])->fetch();
                $installmentAmount = round((float)($emp['base_salary'] ?? 0) * $installmentPercentage / 100, 2);
            } elseif ((float)$this->input('installment_amount', 0) > 0) {
                $installmentAmount = round((float)$this->input('installment_amount'), 2);
            }
            if ($installmentAmount !== null && $installmentAmount > 0) {
                $installments = max(2, (int)ceil($amount / $installmentAmount));
            }
            if ($installments < 2) {
                $this->flash('danger', 'السلف طويلة الأجل تتطلب عدد أقساط لا يقل عن 2');
                Response::redirect('/hr/advances');
            }
        }
        Database::query(
            "INSERT INTO salary_advances (employee_id, amount, advance_type, installments, reason, request_source, is_long_term, remaining_amount, installment_method, installment_percentage, installment_amount)
             VALUES (?,?,?,?,?,'web',?,?,?,?,?)",
            [$employeeId, $amount, $advanceType, $installments,
             (string)$this->input('reason'), $isLongTerm ? 1 : 0, $amount,
             $installmentMethod, $installmentPercentage, $installmentAmount]
        );
        $advanceId = (int)Database::lastInsertId();
        Helpers::queueSync('salary_advances', $advanceId, 'insert', [
            'employee_id' => $employeeId, 'amount' => $amount,
        ]);
        $emp = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$this->input('employee_id')])->fetch();
        $workflowId = HrWorkflowService::request('salary_advance', $advanceId, (int)Auth::id(), [
            'employee_id' => (int)$this->input('employee_id'),
            'employee_name' => $emp['full_name'] ?? '',
            'amount' => $amount,
            'advance_type' => $advanceType,
            'installments' => $installments,
            'reason' => (string)$this->input('reason'),
            'title' => 'طلب سلفة - ' . Helpers::e($emp['full_name'] ?? '') . ' - ' . Helpers::money($amount),
        ]);
        if ($workflowId) {
            $this->flash('success', 'تم تقديم طلب السلفة وأرسل للموافقة');
        } else {
            $this->flash('warning', 'تم تقديم طلب السلفة لكن لا يوجد مسار موافقات نشط لهذا النوع');
        }
        Response::redirect('/hr/advances');
    }

    public function showAdvance(string $id): void
    {
        $advanceId = (int)$id;
        $advance = Database::query(
            "SELECT a.*, e.full_name, e.emp_code, e.department, e.base_salary,
                    u.full_name AS approved_by_name, j.entry_no
             FROM salary_advances a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN users u ON u.id = a.approved_by
             LEFT JOIN journal_entries j ON j.id = a.journal_entry_id
             WHERE a.id = ?",
            [$advanceId]
        )->fetch();
        if (!$advance) {
            Response::redirect('/hr/advances');
        }
        $schedule = Database::query(
            "SELECT s.*, p.period AS payroll_period FROM advance_repayment_schedule s
             LEFT JOIN payrolls p ON p.id = s.payroll_id
             WHERE s.advance_id = ? ORDER BY s.period",
            [$advanceId]
        )->fetchAll();
        $workflowRequestId = HrWorkflowService::workflowRequestId('salary_advance', $advanceId);
        $workflowRequest = $workflowRequestId ? WorkflowEngine::getRequest($workflowRequestId) : null;
        $workflowHistory = $workflowRequest ? WorkflowEngine::getHistory($workflowRequestId) : [];
        $canDecide = false;
        if ($workflowRequest) {
            foreach (WorkflowEngine::resolveSteps((int)$workflowRequest['template_id'], (int)$workflowRequest['current_step'], $workflowRequestId) as $s) {
                if (WorkflowEngine::userCanDecideStep($s, $workflowRequest)) {
                    $canDecide = true; break;
                }
            }
        }

        $bankAccounts = Database::query("SELECT id, name, bank_name, account_no FROM bank_accounts WHERE is_active = 1 ORDER BY name, bank_name")->fetchAll();
        $cashRegisters = Database::query("SELECT id, name FROM cash_registers WHERE is_active = 1 ORDER BY name")->fetchAll();

        $this->view('hr/advances_show', [
            'title' => 'تفاصيل السلفة',
            'advance' => $advance,
            'schedule' => $schedule,
            'approvals' => $workflowHistory,
            'workflowRequestId' => $workflowRequestId,
            'canDecide' => $canDecide,
            'bankAccounts' => $bankAccounts,
            'cashRegisters' => $cashRegisters,
        ]);
    }

    public function decideAdvance(string $id): void
    {
        $advanceId = (int)$id;
        $advance = Database::query("SELECT * FROM salary_advances WHERE id = ?", [$advanceId])->fetch();
        if (!$advance || $advance['status'] !== 'pending') {
            Response::redirect('/hr/advances');
        }
        if (!Auth::can('hr.advance.approve') && !Auth::can('approvals.decide')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/advances');
        }
        if (!HrService::isEmployeeFileOpen((int)$advance['employee_id'])) {
            $this->flash('danger', 'ملف الموظف مغلق أو مأرشف، لا يمكن اتخاذ قرار');
            Response::redirect('/hr/advances');
        }
        $decision = $this->input('decision') === 'approved' ? 'approved' : 'rejected';
        $notes = (string)$this->input('notes');

        Database::beginTransaction();
        try {
            $wfRequestId = HrWorkflowService::workflowRequestId('salary_advance', $advanceId);
            if ($wfRequestId) {
                WorkflowEngine::decide($wfRequestId, $decision, $notes, (int)Auth::id());
            } else {
                Database::query(
                    "UPDATE salary_advances SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?",
                    [$decision, Auth::id(), $advanceId]
                );
                if ($decision === 'approved') {
                    HrService::generateRepaymentSchedule($advanceId);
                }
            }
            Database::commit();
            $this->flash('success', $decision === 'approved' ? 'تمت الموافقة على السلفة' : 'تم رفض السلفة');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/hr/advances');
    }

    public function payAdvance(string $id): void
    {
        $advanceId = (int)$id;
        $advance = Database::query("SELECT * FROM salary_advances WHERE id = ?", [$advanceId])->fetch();
        if (!$advance || $advance['status'] !== 'approved' || (float)$advance['paid_amount'] > 0) {
            Response::redirect('/hr/advances');
        }
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية صرف السلف');
            Response::redirect('/hr/advances');
        }
        if (!HrService::isEmployeeFileOpen((int)$advance['employee_id'])) {
            $this->flash('danger', 'ملف الموظف مغلق أو مأرشف، لا يمكن الصرف');
            Response::redirect('/hr/advances');
        }

        $paymentMethod = in_array($this->input('payment_method'), ['cash', 'bank'], true) ? $this->input('payment_method') : 'cash';
        $bankAccountId = $paymentMethod === 'bank' ? (int)$this->input('bank_account_id') : 0;
        $cashRegisterId = $paymentMethod === 'cash' ? (int)($this->input('cash_register_id') ?: 1) : 0;

        $amount = (float)$advance['amount'];
        $now = date('Y-m-d H:i:s');

        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE salary_advances SET status = 'paid', paid_at = ?, payment_method = ?, bank_account_id = ?, cash_register_id = ?, paid_by = ? WHERE id = ?",
                [$now, $paymentMethod, $bankAccountId ?: null, $cashRegisterId ?: null, Auth::id(), $advanceId]
            );

            if ($paymentMethod === 'cash') {
                $register = Database::query("SELECT * FROM cash_registers WHERE id = ?", [$cashRegisterId])->fetch();
                if (!$register) {
                    throw new \RuntimeException('الصندوق المختار غير موجود');
                }
                Database::query(
                    "INSERT INTO cash_transactions (register_id, trx_type, amount, category, reference, description, created_by)
                     VALUES (?, 'out', ?, 'سلف موظف', ?, ?, ?)",
                    [$cashRegisterId, $amount, 'ADV-' . $advanceId, 'صرف سلفة ' . $advanceId, Auth::id()]
                );
                Database::query("UPDATE cash_registers SET balance = balance - ? WHERE id = ?", [$amount, $cashRegisterId]);
            } else {
                $bankAccount = Database::query("SELECT * FROM bank_accounts WHERE id = ?", [$bankAccountId])->fetch();
                if (!$bankAccount) {
                    throw new \RuntimeException('الحساب البنكي المختار غير موجود');
                }
                Database::query(
                    "INSERT INTO bank_transactions (bank_account_id, trx_type, amount, reference, description, trx_date, created_by)
                     VALUES (?, 'withdrawal', ?, ?, ?, ?, ?)",
                    [$bankAccountId, $amount, 'ADV-' . $advanceId, 'صرف سلفة ' . $advanceId, date('Y-m-d'), Auth::id()]
                );
                Database::query("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?", [$amount, $bankAccountId]);
            }

            // إعادة توليد جدول الأقساط بناءً على تاريخ الصرف الفعلي (للسلف المقسطة)
            HrService::generateRepaymentSchedule($advanceId);
            AccountingService::postAdvance($advanceId);
            Database::commit();
            $this->flash('success', 'تم صرف السلفة');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/hr/advances/' . $advanceId);
    }

    public function overtime(): void
    {
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $status = (string)($_GET['status'] ?? '');
        $from = (string)($_GET['from'] ?? '');
        $to = (string)($_GET['to'] ?? '');

        $where = ['1=1'];
        $params = [];
        if ($employeeId) {
            $where[] = 'o.employee_id = ?';
            $params[] = $employeeId;
        }
        if ($status) {
            $where[] = 'o.status = ?';
            $params[] = $status;
        }
        if ($from && $to) {
            $where[] = 'o.request_date BETWEEN ? AND ?';
            $params[] = $from;
            $params[] = $to;
        }

        $sql = "SELECT o.*, e.full_name, e.emp_code, e.base_salary,
                       u1.full_name AS manager_name, u2.full_name AS grand_manager_name, u3.full_name AS rejected_by_name
                FROM overtime_requests o
                JOIN employees e ON e.id = o.employee_id
                LEFT JOIN users u1 ON u1.id = o.manager_approved_by
                LEFT JOIN users u2 ON u2.id = o.grand_manager_approved_by
                LEFT JOIN users u3 ON u3.id = o.rejected_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY o.created_at DESC LIMIT 200";
        $overtime = Database::query($sql, $params)->fetchAll();
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $statusLabels = ['pending'=>'بانتظار المدير المباشر','manager_approved'=>'بانتظار مدير المدير','fully_approved'=>'معتمد نهائياً','rejected'=>'مرفوض','expired'=>'منتهي الصلاحية'];


        $this->view('hr/overtime', [
            'title' => 'طلبات العمل الإضافي',
            'overtime' => $overtime,
            'employees' => $employees,
            'employeeId' => $employeeId,
            'status' => $status,
            'from' => $from,
            'to' => $to,
            'statusLabels' => $statusLabels,
        ]);
    }

    public function storeOvertime(): void
    {
        $employeeId = (int)$this->input('employee_id');
        if (!HrService::isEmployeeFileOpen($employeeId)) {
            $this->flash('danger', 'ملف الموظف مغلق أو مأرشف');
            Response::redirect('/hr/overtime');
        }
        $date = (string)$this->input('request_date');
        $hours = (float)$this->input('hours');
        $reason = (string)$this->input('reason');
        if (!$date || $hours <= 0) {
            $this->flash('danger', 'التاريخ والساعات مطلوبة');
            Response::redirect('/hr/overtime');
        }
        $overtimeId = HrService::createOvertimeRequest($employeeId, $date, $hours, $reason);
        $amount = HrService::calculateOvertimeAmount($employeeId, $hours, date('Y-m', strtotime($date)));
        $emp = Database::query("SELECT * FROM employees WHERE id = ?", [$employeeId])->fetch();
        $workflowId = HrWorkflowService::request('overtime_request', $overtimeId, (int)Auth::id(), [
            'employee_id' => $employeeId,
            'employee_name' => $emp['full_name'] ?? '',
            'request_date' => $date,
            'hours' => $hours,
            'reason' => $reason,
            'amount' => $amount,
            'title' => 'طلب عمل إضافي - ' . Helpers::e($emp['full_name'] ?? '') . ' - ' . $date,
        ]);
        if ($workflowId) {
            Database::query("UPDATE overtime_requests SET workflow_request_id = ? WHERE id = ?", [$workflowId, $overtimeId]);
        }
        $this->flash('success', $workflowId ? 'تم تقديم الطلب وأرسل للموافقة' : 'تم تقديم الطلب لكن لا يوجد مسار موافقات');
        Response::redirect('/hr/overtime');
    }

    public function showOvertime(string $id): void
    {
        $overtimeId = (int)$id;
        HrService::recomputeOvertimeBiometricMatch($overtimeId);
        $overtime = Database::query(
            "SELECT o.*, e.full_name, e.emp_code, e.base_salary,
                    u1.full_name AS manager_name, u2.full_name AS grand_manager_name, u3.full_name AS rejected_by_name
             FROM overtime_requests o
             JOIN employees e ON e.id = o.employee_id
             LEFT JOIN users u1 ON u1.id = o.manager_approved_by
             LEFT JOIN users u2 ON u2.id = o.grand_manager_approved_by
             LEFT JOIN users u3 ON u3.id = o.rejected_by
             WHERE o.id = ?",
            [$overtimeId]
        )->fetch();
        if (!$overtime) {
            Response::redirect('/hr/overtime');
        }
        $attendance = HrService::getOvertimeAttendance((int)$overtime['employee_id'], (string)$overtime['request_date']);
        $biometricHours = HrService::getBiometricOvertimeHours((int)$overtime['employee_id'], (string)$overtime['request_date']);
        $workflowRequest = $overtime['workflow_request_id'] ? WorkflowEngine::getRequest((int)$overtime['workflow_request_id']) : null;
        $workflowHistory = $workflowRequest ? WorkflowEngine::getHistory((int)$overtime['workflow_request_id']) : [];
        $canDecide = false;
        if ($workflowRequest && in_array($overtime['status'], ['pending','manager_approved'])) {
            foreach (WorkflowEngine::resolveSteps((int)$workflowRequest['template_id'], (int)$workflowRequest['current_step'], (int)$overtime['workflow_request_id']) as $s) {
                if (WorkflowEngine::userCanDecideStep($s, $workflowRequest)) {
                    $canDecide = true; break;
                }
            }
        }
        $this->view('hr/overtime_show', [
            'title' => 'تفاصيل طلب العمل الإضافي',
            'overtime' => $overtime,
            'attendance' => $attendance,
            'biometricHours' => $biometricHours,
            'approvals' => $workflowHistory,
            'workflowRequestId' => $overtime['workflow_request_id'],
            'canDecide' => $canDecide,
        ]);
    }

    public function decideOvertime(string $id): void
    {
        $overtimeId = (int)$id;
        $overtime = Database::query("SELECT * FROM overtime_requests WHERE id = ?", [$overtimeId])->fetch();
        if (!$overtime || !in_array($overtime['status'], ['pending','manager_approved'])) {
            Response::redirect('/hr/overtime');
        }
        if (!Auth::can('hr.overtime.approve') && !Auth::can('approvals.decide')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/overtime');
        }
        if (!HrService::isEmployeeFileOpen((int)$overtime['employee_id'])) {
            $this->flash('danger', 'ملف الموظف مغلق أو مأرشف');
            Response::redirect('/hr/overtime');
        }
        $decision = $this->input('decision') === 'approved' ? 'approved' : 'rejected';
        $notes = (string)$this->input('notes');
        Database::beginTransaction();
        try {
            $wfRequestId = (int)$overtime['workflow_request_id'];
            if ($wfRequestId) {
                WorkflowEngine::decide($wfRequestId, $decision, $notes, (int)Auth::id());
            } else {
                Database::query(
                    "UPDATE overtime_requests SET status = ?, rejected_by = ?, rejected_at = NOW() WHERE id = ?",
                    [$decision, Auth::id(), $overtimeId]
                );
            }
            Database::commit();
            $this->flash('success', $decision === 'approved' ? 'تمت الموافقة' : 'تم الرفض');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/hr/overtime/' . $overtimeId);
    }

    public function holidays(): void
    {
        if (!Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/dashboard');
        }
        $holidays = Database::query("SELECT * FROM hr_holidays ORDER BY day_of_week, date, name")->fetchAll();
        $holidayMonths = Database::query("SELECT * FROM hr_holiday_months ORDER BY year DESC, month DESC")->fetchAll();
        $this->view('hr/holidays', ['title' => 'إدارة العطل الرسمية', 'holidays' => $holidays, 'holidayMonths' => $holidayMonths]);
    }

    public function storeHoliday(): void
    {
        if (!Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/holidays');
        }
        $date = (string)$this->input('date');
        $dayOfWeek = $this->input('day_of_week');
        $name = (string)$this->input('name');
        if (!$date && $dayOfWeek === '') {
            $this->flash('danger', 'يجب تحديد تاريخ أو يوم أسبوعي');
            Response::redirect('/hr/holidays');
        }
        try {
            Database::query(
                "INSERT INTO hr_holidays (date, day_of_week, name, is_active) VALUES (?, ?, ?, ?)",
                [$date ?: null, $dayOfWeek !== '' ? (int)$dayOfWeek : null, $name, 1]
            );
            $this->flash('success', 'تم إضافة العطلة');
        } catch (\PDOException $e) {
            $mysqlErr = $e->errorInfo[1] ?? 0;
            if ($mysqlErr == 1062) {
                $this->flash('danger', 'هذه العطلة مسجلة مسبقاً');
            } else {
                $this->flash('danger', 'خطأ: ' . $e->getMessage());
            }
        }
        Response::redirect('/hr/holidays');
    }

    public function deleteHoliday(string $id): void
    {
        if (!Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/holidays');
        }
        Database::query("DELETE FROM hr_holidays WHERE id = ?", [(int)$id]);
        $this->flash('success', 'تم حذف العطلة');
        Response::redirect('/hr/holidays');
    }

    public function storeHolidayMonth(): void
    {
        if (!Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/holidays');
        }
        $year = (int)$this->input('year');
        $month = (int)$this->input('month');
        $days = (int)$this->input('days');
        if ($year < 2000 || $month < 1 || $month > 12 || $days < 0) {
            $this->flash('danger', 'بيانات غير صحيحة');
            Response::redirect('/hr/holidays');
        }
        try {
            Database::query(
                "INSERT INTO hr_holiday_months (year, month, days) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE days = VALUES(days)",
                [$year, $month, $days]
            );
            $this->flash('success', 'تم حفظ عدد أيام العطل للشهر');
        } catch (\PDOException $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/hr/holidays');
    }

    public function deleteHolidayMonth(string $id): void
    {
        if (!Auth::can('hr.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/holidays');
        }
        Database::query("DELETE FROM hr_holiday_months WHERE id = ?", [(int)$id]);
        $this->flash('success', 'تم حذف عدد أيام الشهر');
        Response::redirect('/hr/holidays');
    }




    public function payroll(): void
    {
        $period = (string)($_GET['period'] ?? date('Y-m'));
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $department = (string)($_GET['department'] ?? '');
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $status = (string)($_GET['status'] ?? '');

        // المخالصات (الاستقالات المصفّاة) لهذا الشهر: تُستثنى من كشف الرواتب العادي وتُعرض في قسم منفصل
        $settlements = Database::query(
            "SELECT r.*, e.full_name, e.emp_code, e.department, e.position, e.branch_id,
                    b.name AS branch_name
             FROM resignations r
             JOIN employees e ON e.id = r.employee_id
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE DATE_FORMAT(r.resignation_date, '%Y-%m') = ?
             ORDER BY r.resignation_date DESC",
            [$period]
        )->fetchAll();
        $settledEmployeeIds = array_map(function ($r) { return (int)$r['employee_id']; }, $settlements);
        $settlementsSummary = ['count' => 0, 'earnings' => 0.0, 'deductions' => 0.0, 'net' => 0.0];
        foreach ($settlements as &$s) {
            $b = json_decode($s['calculation_breakdown_json'] ?? '', true) ?: [];
            $s['breakdown'] = $b;
            $earnings = (float)($b['salary_amount'] ?? 0) + (float)($b['overtime_amount'] ?? 0);
            $deductions = (float)($b['absence_deduction'] ?? 0) + (float)($b['advances_amount'] ?? 0)
                + (float)($b['housing_deduction'] ?? 0) + (float)($b['other_deductions'] ?? 0);
            $settlementsSummary['count']++;
            $settlementsSummary['earnings'] += $earnings;
            $settlementsSummary['deductions'] += $deductions;
            $settlementsSummary['net'] += (float)$s['total_due'];
        }
        unset($s);

        $where = 'WHERE p.period = ?';
        $params = [$period];
        if (!empty($settledEmployeeIds)) {
            $placeholders = implode(',', array_fill(0, count($settledEmployeeIds), '?'));
            $where .= " AND p.employee_id NOT IN ($placeholders)";
            $params = array_merge($params, $settledEmployeeIds);
        }
        if ($employeeId) {
            $where .= ' AND p.employee_id = ?';
            $params[] = $employeeId;
        }
        if ($department !== '') {
            $where .= ' AND e.department = ?';
            $params[] = $department;
        }
        if ($branchId) {
            $where .= ' AND e.branch_id = ?';
            $params[] = $branchId;
        }
        if ($status !== '') {
            $where .= " AND p.status = ?";
            $params[] = $status;
        }

        $payrolls = Database::query(
            "SELECT p.*, e.full_name, e.emp_code, e.department, e.position, e.phone,
                    e.branch_id, e.bank_name, e.bank_account_no, e.bank_iban,
                    e.base_salary AS full_base_salary,
                    b.name AS branch_name
             FROM payrolls p
             JOIN employees e ON e.id = p.employee_id
             LEFT JOIN branches b ON b.id = e.branch_id
             $where
             ORDER BY e.full_name",
            $params
        )->fetchAll();

        $format = (string)($_GET['format'] ?? '');
        if ($format === 'bank') {
            $this->exportBankPayroll($payrolls, $period);
            return;
        }
        if ($format === 'excel' || $format === 'pdf') {
            $this->exportPayroll($payrolls, $period, $format);
            return;
        }

        $summary = [
            'count' => 0, 'draft' => 0, 'approved' => 0, 'paid' => 0,
            'gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0, 'advances' => 0.0,
        ];
        foreach ($payrolls as $p) {
            $summary['count']++;
            $summary[$p['status']] = ($summary[$p['status']] ?? 0) + 1;
            $summary['gross'] += (float)$p['total_earnings'];
            $summary['deductions'] += (float)$p['total_deductions'];
            $summary['net'] += (float)$p['net_salary'];
            $summary['advances'] += (float)$p['advances'];
        }

        $advancesByEmployee = [];
        $advanceEmployeeIds = [];
        foreach ($payrolls as $p) {
            if ((float)$p['advances'] > 0) {
                $advanceEmployeeIds[] = (int)$p['employee_id'];
            }
        }
        if (!empty($advanceEmployeeIds)) {
            $placeholders = implode(',', array_fill(0, count($advanceEmployeeIds), '?'));
            $schedules = Database::query(
                "SELECT s.id, s.advance_id, s.period, s.amount, s.status AS schedule_status,
                        a.employee_id, a.amount AS advance_amount, a.advance_type,
                        a.payment_method, a.paid_at, a.installments,
                        ba.name AS bank_account_name
                 FROM advance_repayment_schedule s
                 JOIN salary_advances a ON a.id = s.advance_id
                 LEFT JOIN bank_accounts ba ON ba.id = a.bank_account_id
                 WHERE a.employee_id IN ($placeholders) AND s.period = ?
                 ORDER BY a.employee_id, a.id, s.id",
                [...$advanceEmployeeIds, $period]
            )->fetchAll();
            foreach ($schedules as $s) {
                $advancesByEmployee[(int)$s['employee_id']][] = $s;
            }
        }

        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $departments = Database::query("SELECT DISTINCT department AS name FROM employees WHERE file_status='open' AND department IS NOT NULL AND department != '' ORDER BY department")->fetchAll();
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();

        $this->view('hr/payroll', [
            'title' => 'الرواتب',
            'payrolls' => $payrolls,
            'period' => $period,
            'employees' => $employees,
            'selected_employee' => $employeeId,
            'departments' => $departments,
            'selected_department' => $department,
            'branches' => $branches,
            'selected_branch' => $branchId,
            'selected_status' => $status,
            'summary' => $summary,
            'advancesByEmployee' => $advancesByEmployee,
            'absence_mode' => (string)\App\Core\Settings::get('payroll_absence_mode', 'attendance'),
            'settlements' => $settlements,
            'settlementsSummary' => $settlementsSummary,
        ]);
    }

    private function exportPayroll(array $payrolls, string $period, string $format): void
    {
        $statusLabels = ['draft'=>'مسودة','approved'=>'معتمد','paid'=>'مدفوع'];
        $totalNet = 0; $totalBase = 0; $totalAllowances = 0; $totalOvertime = 0; $totalTax = 0;
        $totalBonuses = 0; $totalDeductions = 0; $totalAdvances = 0; $totalRaises = 0; $totalSecurity = 0;
        $totalInsurance = 0; $totalRounding = 0; $totalTravelTickets = 0; $totalLoadings = 0;
        $totalTips = 0; $totalSocialSecurity = 0; $totalLongTermAdvances = 0;
        $rows = [];
        foreach ($payrolls as $p) {
            $totalNet += (float)$p['net_salary']; $totalBase += (float)$p['base_salary'];
            $totalAllowances += (float)($p['allowances'] ?? 0); $totalOvertime += (float)($p['overtime'] ?? 0);
            $totalTax += (float)($p['tax_amount'] ?? 0); $totalBonuses += (float)$p['bonuses'];
            $totalDeductions += (float)$p['deductions']; $totalAdvances += (float)$p['advances']; $totalRaises += (float)$p['raises'];
            $totalSecurity += (float)($p['security_deposit'] ?? 0); $totalInsurance += (float)($p['insurance'] ?? 0); $totalRounding += (float)($p['rounding_adjustment'] ?? 0);
            $totalTravelTickets += (float)($p['travel_tickets'] ?? 0); $totalLoadings += (float)($p['loadings'] ?? 0);
            $totalTips += (float)($p['tips'] ?? 0); $totalSocialSecurity += (float)($p['social_security'] ?? 0);
            $totalLongTermAdvances += (float)($p['long_term_advances'] ?? 0);
            $expectedDays = (int)($p['expected_days'] ?? 0);
            $rows[] = [
                $p['full_name'], $p['department'] ?? '-', number_format($p['base_salary'], 0), number_format($p['full_base_salary'] ?? $p['base_salary'], 0), number_format($p['raises'], 0),
                number_format($p['allowances'] ?? 0, 0), number_format($p['overtime'] ?? 0, 0), number_format($p['bonuses'], 0),
                number_format($p['travel_tickets'] ?? 0, 0),
                (int)($p['working_days'] ?? 0), (int)($p['leave_days'] ?? 0), (int)($p['absence_days'] ?? 0) . '/' . $expectedDays, number_format($p['absence_deduction'] ?? 0, 0),
                number_format($p['deductions'], 0), number_format($p['tax_amount'] ?? 0, 0),
                number_format($p['advances'], 0), number_format($p['long_term_advances'] ?? 0, 0), number_format($p['security_deposit'] ?? 0, 0), number_format($p['insurance'] ?? 0, 0),
                number_format($p['loadings'] ?? 0, 0), number_format($p['tips'] ?? 0, 0), number_format($p['social_security'] ?? 0, 0),
                number_format($p['rounding_adjustment'] ?? 0, 0),
                number_format($p['net_salary'], 0), $statusLabels[$p['status']] ?? $p['status'],
            ];
        }
        $headers = ['الموظف', 'القسم', 'الأساسي (المستحق حتى اليوم)', 'الراتب الاسمي', 'الزيادات', 'البدلات', 'الإضافي', 'المكافآت', 'تذاكر سفر', 'أيام الدوام', 'إجازات', 'الغياب/المتوقع', 'خصم الغياب', 'الخصومات', 'الضريبة', 'السلف', 'سلف طويلة الأجل', 'تأمين المستحقات', 'التأمينات', 'التحميلات', 'التيبس', 'الضمان الاجتماعي', 'التقريب', 'الصافي', 'الحالة'];
        $totals = ['إجمالي تذاكر السفر' => number_format($totalTravelTickets, 0), 'إجمالي سلف طويلة الأجل' => number_format($totalLongTermAdvances, 0), 'إجمالي التأمين المستحقات' => number_format($totalSecurity, 0), 'إجمالي التأمينات' => number_format($totalInsurance, 0), 'إجمالي التحميلات' => number_format($totalLoadings, 0), 'إجمالي التيبس' => number_format($totalTips, 0), 'إجمالي الضمان الاجتماعي' => number_format($totalSocialSecurity, 0), 'إجمالي التقريب' => number_format($totalRounding, 0), 'إجمالي الصافي' => number_format($totalNet, 0)];
        if ($format === 'excel') {
            ExportHelper::excel('payroll_' . $period . '.xlsx', [[
                'title' => 'كشف الرواتب', 'report_title' => 'كشف الرواتب', 'period' => $period,
                'headers' => $headers, 'rows' => $rows,
                'totals' => $totals,
            ]]);
        }
        ExportHelper::pdf('payroll_' . $period . '.pdf', [
            'title' => 'كشف الرواتب', 'period' => $period,
            'headers' => $headers, 'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    private function exportBankPayroll(array $payrolls, string $period): void
    {
        $rows = [];
        $totalNet = 0.0;
        foreach ($payrolls as $p) {
            if (!in_array($p['status'], ['approved','paid'], true)) {
                continue;
            }
            $totalNet += (float)$p['net_salary'];
            $rows[] = [
                $p['emp_code'] ?? '',
                $p['full_name'],
                $p['bank_name'] ?? '',
                $p['bank_account_no'] ?? '',
                $p['bank_iban'] ?? '',
                number_format((float)$p['net_salary'], 2),
                $p['status'] === 'paid' ? 'تم الصرف' : 'مستحق الصرف',
            ];
        }
        $headers = ['رمز الموظف', 'الاسم', 'البنك', 'رقم الحساب', 'IBAN', 'صافي الراتب', 'ملاحظة'];
        ExportHelper::excel('bank_payroll_' . $period . '.xlsx', [[
            'title' => 'كشف البنك',
            'report_title' => 'كشف بنك الرواتب - ' . $period,
            'headers' => $headers,
            'rows' => $rows,
            'totals' => ['إجمالي صافي المستحق' => number_format($totalNet, 2)],
        ]]);
    }

    /** تبديل وضع احتساب الغياب في الرواتب (من البصمات أو من التنبيهات) */
    public function setPayrollAbsenceMode(): void
    {
        $mode = (string)$this->input('mode', 'attendance');
        if (!in_array($mode, ['attendance', 'alerts'], true)) {
            $mode = 'attendance';
        }
        \App\Core\Settings::set('payroll_absence_mode', $mode);
        Helpers::audit('تغيير وضع احتساب الغياب', 'settings', 0, "payroll_absence_mode:{$mode}");
        $this->flash('success', $mode === 'attendance' ? 'سيُحتسب الغياب من البصمات والورديات والإجازات' : 'سيُحتسب الغياب من تنبيهات عدم البصمة');
        Response::redirect('/hr/payroll?period=' . urlencode((string)$this->input('period', date('Y-m'))));
    }

    public function payrollMessages(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $period = (string)$this->input('period', date('Y-m'));
        $payrolls = Database::query(
            "SELECT p.id, p.employee_id, p.net_salary, p.base_salary, p.total_deductions, e.full_name, e.phone, e.user_id, u.email
             FROM payrolls p
             JOIN employees e ON e.id = p.employee_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE p.period = ? AND e.file_status = 'open'
             ORDER BY e.full_name",
            [$period]
        )->fetchAll();
        $defaultTemplate = "مرحباً {name}، راتب فترة {period} تم إعداده.\\nالأساسي: {base}\\nالاستقطاعات: {deductions}\\nالصافي: {net}\\nشكراً.";
        $this->view('hr/payroll_messages', [
            'title' => 'رسائل كشف الرواتب',
            'period' => $period,
            'payrolls' => $payrolls,
            'template' => $defaultTemplate,
        ]);
    }

    public function sendPayrollMessages(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $period = (string)$this->input('period', date('Y-m'));
        $channel = (string)$this->input('channel', 'whatsapp');
        $template = (string)$this->input('template', '');
        $selected = (array)($_POST['employee_ids'] ?? []);
        if (!in_array($channel, ['whatsapp', 'sms', 'email'], true)) {
            $this->flash('danger', 'قناة إرسال غير صحيحة');
            Response::redirect('/hr/payroll/messages?period=' . urlencode($period));
        }
        if (!$template || !$selected) {
            $this->flash('danger', 'اختر موظفين واكتب نص الرسالة');
            Response::redirect('/hr/payroll/messages?period=' . urlencode($period));
        }
        $stats = ['sent' => 0, 'queued' => 0, 'failed' => 0, 'errors' => []];
        foreach ($selected as $employeeId) {
            $p = Database::query(
                "SELECT p.net_salary, p.base_salary, p.total_deductions, e.full_name, e.phone, e.email AS emp_email, e.user_id, u.email
                 FROM payrolls p
                 JOIN employees e ON e.id = p.employee_id
                 LEFT JOIN users u ON u.id = e.user_id
                 WHERE p.employee_id = ? AND p.period = ?",
                [(int)$employeeId, $period]
            )->fetch();
            if (!$p) continue;
            $message = str_replace(
                ['{name}', '{period}', '{net}', '{base}', '{deductions}'],
                [Helpers::e($p['full_name']), $period, Helpers::money($p['net_salary']), Helpers::money($p['base_salary']), Helpers::money($p['total_deductions'])],
                $template
            );
            $userId = (int)$p['user_id'] ?: Auth::id();
            $r = null;
            if ($channel === 'whatsapp' && $p['phone']) {
                $r = NotificationService::sendWhatsApp($p['phone'], $message, $userId);
            } elseif ($channel === 'sms' && $p['phone']) {
                $r = NotificationService::sendSms($p['phone'], $message, $userId);
            } elseif ($channel === 'email') {
                $email = $p['email'] ?: $p['emp_email'];
                if ($email) {
                    $r = NotificationService::sendEmail($email, 'كشف الراتب', $message, $userId);
                }
            }
            if ($r !== null) {
                if (!empty($r['success'])) {
                    $stats['sent']++;
                } elseif (!empty($r['queued'])) {
                    $stats['queued']++;
                } else {
                    $stats['failed']++;
                }
                if (!empty($r['error'])) {
                    $stats['errors'][] = $r['error'];
                }
            }
        }
        $msg = "نتائج الإرسال عبر $channel: {$stats['sent']} تم الإرسال";
        if ($stats['queued'] > 0) {
            $msg .= "، {$stats['queued']} في طابور الانتظار";
        }
        if ($stats['failed'] > 0) {
            $msg .= "، {$stats['failed']} فشل";
        }
        $type = $stats['failed'] > 0 ? 'warning' : 'success';
        $this->flash($type, $msg);
        Response::redirect('/hr/payroll/messages?period=' . urlencode($period));
    }

    public function headcount(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $period = (string)$this->input('period', date('Y-m'));
        $departments = Database::query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll();
        $jobs = Database::query("SELECT id, title, department_id FROM jobs WHERE is_active = 1 ORDER BY title")->fetchAll();
        $plans = HrService::getHeadcountPlans($period);
        $this->view('hr/headcount', [
            'title' => 'خطة الأعداد',
            'period' => $period,
            'departments' => $departments,
            'jobs' => $jobs,
            'plans' => $plans,
        ]);
    }

    public function saveHeadcount(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $jobId = (int)$this->input('job_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $plannedCount = (int)$this->input('planned_count', 0);
        $budgetSalary = (float)$this->input('budget_salary', 0);
        $notes = (string)$this->input('notes', '');
        if ($jobId && preg_match('/^\d{4}-\d{2}$/', $period)) {
            $ok = HrService::saveHeadcountPlan($jobId, $period, $plannedCount, $budgetSalary, $notes ?: null, Auth::id());
            if ($ok) {
                $this->flash('success', 'تم حفظ خطة الأعداد');
            } else {
                $this->flash('danger', 'الوظيفة المحددة غير مرتبطة بقسم، يرجى تعيين قسم للوظيفة أولاً');
            }
        } else {
            $this->flash('danger', 'بيانات غير صحيحة');
        }
        Response::redirect('/hr/headcount?period=' . urlencode($period));
    }

    public function deleteHeadcount(string $id): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $plan = HrService::getHeadcountPlan((int)$id);
        HrService::deleteHeadcountPlan((int)$id);
        $this->flash('success', 'تم حذف خطة الأعداد');
        Response::redirect('/hr/headcount?period=' . urlencode($plan['period'] ?? date('Y-m')));
    }

    public function payrollImport(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $components = [
            'insurance' => 'التأمينات الشهرية',
            'tips' => 'التيبس',
            'social_security' => 'الضمان الاجتماعي',
            'loadings' => 'التحميلات',
            'travel_tickets' => 'تذاكر السفر',
            'advances' => 'السلف',
        ];
        $this->view('hr/payroll_import', [
            'title' => 'استيراد بيانات الرواتب',
            'components' => $components,
        ]);
    }

    public function payrollImportTemplate(string $component): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        switch ($component) {
            case 'insurance':
                ImportHelper::downloadTemplate(['emp_code', 'period', 'amount', 'notes'], 'insurance_template.xlsx', ['EMP001', '2026-09', 50000, 'تأمين شهر 9']);
                break;
            case 'tips':
                ImportHelper::downloadTemplate(['emp_code', 'period', 'full_amount', 'working_days', 'notes'], 'tips_template.xlsx', ['EMP001', '2026-09', 300000, 30, 'تيبس شهر 9']);
                break;
            case 'social_security':
                ImportHelper::downloadTemplate(['emp_code', 'period', 'amount', 'notes'], 'social_security_template.xlsx', ['EMP001', '2026-09', 75000, 'ضمان شهر 9']);
                break;
            case 'loadings':
                ImportHelper::downloadTemplate(['emp_code', 'period', 'category', 'amount', 'notes'], 'loadings_template.xlsx', ['EMP001', '2026-09', 'تحميلات', 100000, 'تحميلات شهر 9']);
                break;
            case 'travel_tickets':
                ImportHelper::downloadTemplate(['emp_code', 'period', 'amount', 'departure_date', 'return_date', 'travel_method', 'notes'], 'travel_tickets_template.xlsx', ['EMP001', '2026-09', 500000, '2026-09-10', '2026-09-20', 'طيران', 'تذكرة سفر']);
                break;
            case 'advances':
                ImportHelper::downloadTemplate(['emp_code', 'amount', 'installments', 'is_long_term', 'approved_at', 'reason'], 'advances_template.xlsx', ['EMP001', 500000, 5, 0, '2026-01-15', 'سلفة شخصية']);
                break;
            default:
                $this->flash('danger', 'نوع استيراد غير معروف');
                Response::redirect('/hr/payroll/import');
        }
    }

    public function importPayrollComponent(string $component): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/hr/payroll/import');
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/hr/payroll/import');
        }
        $rows = $read['rows'];
        $required = match ($component) {
            'insurance', 'social_security' => ['emp_code', 'period', 'amount'],
            'tips' => ['emp_code', 'period', 'full_amount', 'working_days'],
            'loadings' => ['emp_code', 'period', 'category', 'amount'],
            'travel_tickets' => ['emp_code', 'period', 'amount'],
            'advances' => ['emp_code', 'amount', 'installments'],
            default => []
        };
        if (!$required) {
            $this->flash('danger', 'نوع استيراد غير معروف');
            Response::redirect('/hr/payroll/import');
        }
        $errors = ImportHelper::validateRequired($rows, $required);
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/hr/payroll/import');
        }

        $empCodes = [];
        foreach ($rows as $row) {
            $code = trim((string)($row['emp_code'] ?? ''));
            if ($code) { $empCodes[] = $code; }
        }
        $empMap = [];
        if ($empCodes) {
            $in = implode(',', array_fill(0, count($empCodes), '?'));
            $emps = Database::query("SELECT id, emp_code FROM employees WHERE emp_code IN ($in)", $empCodes)->fetchAll();
            $empMap = array_column($emps, 'id', 'emp_code');
        }
        foreach ($rows as $idx => $row) {
            $code = trim((string)($row['emp_code'] ?? ''));
            if (!isset($empMap[$code])) {
                $errors[] = 'الصف ' . ($idx + 2) . ': كود الموظف ' . $code . ' غير موجود';
            }
        }
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/hr/payroll/import');
        }

        $created = 0;
        Database::beginTransaction();
        try {
            foreach ($rows as $row) {
                $employeeId = (int)$empMap[trim((string)($row['emp_code'] ?? ''))];
                $userId = Auth::id();
                $this->applyPayrollRow($row, $component, $employeeId, $userId);
                $created++;
            }
            Database::commit();
            Helpers::audit('استيراد بيانات رواتب', 'payroll_import', 0, "component:{$component} created:{$created}");
            $this->flash('success', "تم استيراد {$created} سجل في " . $component);
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء الاستيراد: ' . $e->getMessage());
        }
        Response::redirect('/hr/payroll/import');
    }

    /** ==================== استيراد موحد لجميع بنود الرواتب ==================== */

    public function payrollImportUnifiedTemplate(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }

        $headers = [
            'emp_code',
            'type',
            'period',
            'amount',
            'full_amount',
            'working_days',
            'category',
            'departure_date',
            'return_date',
            'travel_method',
            'installments',
            'is_long_term',
            'approved_at',
            'reason',
            'notes',
        ];
        $samples = [
            ['emp_code' => 'EMP001', 'type' => 'insurance', 'period' => '2026-09', 'amount' => 50000, 'notes' => 'تأمين شهر 9'],
            ['emp_code' => 'EMP001', 'type' => 'tips', 'period' => '2026-09', 'full_amount' => 300000, 'working_days' => 30, 'notes' => 'تيبس شهر 9'],
            ['emp_code' => 'EMP001', 'type' => 'social_security', 'period' => '2026-09', 'amount' => 75000, 'notes' => 'ضمان شهر 9'],
            ['emp_code' => 'EMP001', 'type' => 'loadings', 'period' => '2026-09', 'category' => 'تحميلات', 'amount' => 100000, 'notes' => 'تحميلات شهر 9'],
            ['emp_code' => 'EMP001', 'type' => 'travel_tickets', 'period' => '2026-09', 'amount' => 500000, 'departure_date' => '2026-09-10', 'return_date' => '2026-09-20', 'travel_method' => 'طيران', 'notes' => 'تذكرة سفر'],
            ['emp_code' => 'EMP001', 'type' => 'advances', 'amount' => 500000, 'installments' => 5, 'is_long_term' => 0, 'approved_at' => '2026-01-15', 'reason' => 'سلفة شخصية'],
        ];
        $sampleRows = [];
        foreach ($samples as $data) {
            $row = array_fill_keys($headers, '');
            foreach ($data as $key => $value) {
                if (in_array($key, $headers, true)) {
                    $row[$key] = $value;
                }
            }
            $sampleRows[] = array_values($row);
        }
        ImportHelper::downloadTemplateMulti($headers, 'payroll_unified_template.xlsx', $sampleRows);
    }

    public function importPayrollUnified(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/hr/payroll/import');
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/hr/payroll/import');
        }
        $headers = $read['headers'];
        if (!in_array('type', $headers, true) || !in_array('emp_code', $headers, true)) {
            $this->flash('danger', 'الملف يجب أن يحتوي على عمودي emp_code و type');
            Response::redirect('/hr/payroll/import');
        }
        $rows = $read['rows'];
        $errors = [];
        foreach ($rows as $idx => $row) {
            $line = $idx + 2;
            $component = $this->resolvePayrollComponent($row);
            if (!$component) {
                $errors[] = "الصف $line: نوع السجل (type) غير معروف";
                continue;
            }
            foreach ($this->payrollRequiredColumns($component) as $col) {
                if (empty($row[$col]) && !is_numeric($row[$col] ?? null)) {
                    $errors[] = "الصف $line: العمود $col مطلوب لنوع $component";
                }
            }
        }
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/hr/payroll/import');
        }

        $empCodes = [];
        foreach ($rows as $row) {
            $code = trim((string)($row['emp_code'] ?? ''));
            if ($code) { $empCodes[] = $code; }
        }
        $empMap = [];
        if ($empCodes) {
            $in = implode(',', array_fill(0, count($empCodes), '?'));
            $emps = Database::query("SELECT id, emp_code FROM employees WHERE emp_code IN ($in)", $empCodes)->fetchAll();
            $empMap = array_column($emps, 'id', 'emp_code');
        }
        foreach ($rows as $idx => $row) {
            $code = trim((string)($row['emp_code'] ?? ''));
            if (!isset($empMap[$code])) {
                $errors[] = 'الصف ' . ($idx + 2) . ': كود الموظف ' . $code . ' غير موجود';
            }
        }
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/hr/payroll/import');
        }

        $created = 0;
        Database::beginTransaction();
        try {
            foreach ($rows as $row) {
                $component = $this->resolvePayrollComponent($row);
                $employeeId = (int)$empMap[trim((string)($row['emp_code'] ?? ''))];
                $userId = Auth::id();
                $this->applyPayrollRow($row, $component, $employeeId, $userId);
                $created++;
            }
            Database::commit();
            Helpers::audit('استيراد بيانات رواتب موحدة', 'payroll_import', 0, "created:{$created}");
            $this->flash('success', "تم استيراد {$created} سجل بنجاح");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء الاستيراد: ' . $e->getMessage());
        }
        Response::redirect('/hr/payroll/import');
    }

    /** تحديد نوع السجل من عمود type */
    private function resolvePayrollComponent(array $row): ?string
    {
        $type = strtolower(trim((string)($row['type'] ?? '')));
        $map = [
            'insurance' => ['insurance', 'تأمين', 'التأمين', 'التأمينات الشهرية'],
            'tips' => ['tips', 'tip', 'تيبس'],
            'social_security' => ['social_security', 'ضمان', 'الضمان', 'الضمان الاجتماعي'],
            'loadings' => ['loadings', 'loading', 'تحميلات', 'التحميلات'],
            'travel_tickets' => ['travel_tickets', 'تذاكر', 'تذاكر السفر'],
            'advances' => ['advances', 'advance', 'سلف', 'سلفة', 'السلف'],
        ];
        foreach ($map as $component => $aliases) {
            foreach ($aliases as $alias) {
                if (strtolower(trim($alias)) === $type) {
                    return $component;
                }
            }
        }
        return null;
    }

    /** الأعمدة المطلوبة لكل نوع سجل */
    private function payrollRequiredColumns(string $component): array
    {
        return match ($component) {
            'insurance', 'social_security' => ['emp_code', 'period', 'amount'],
            'tips' => ['emp_code', 'period', 'full_amount', 'working_days'],
            'loadings' => ['emp_code', 'period', 'category', 'amount'],
            'travel_tickets' => ['emp_code', 'period', 'amount'],
            'advances' => ['emp_code', 'amount', 'installments'],
            default => [],
        };
    }

    /** تطبيق صف واحد من بيانات الرواتب */
    private function applyPayrollRow(array $row, string $component, int $employeeId, int $userId): void
    {
        switch ($component) {
            case 'insurance':
                HrService::setEmployeeMonthlyInsurance($employeeId, (string)$row['period'], (float)$row['amount'], (string)($row['notes'] ?? ''), $userId);
                break;
            case 'tips':
                HrService::setEmployeeMonthlyTips($employeeId, (string)$row['period'], (float)$row['full_amount'], (int)$row['working_days'], (string)($row['notes'] ?? ''), $userId);
                break;
            case 'social_security':
                HrService::setEmployeeMonthlySocialSecurity($employeeId, (string)$row['period'], (float)$row['amount'], (string)($row['notes'] ?? ''), $userId);
                break;
            case 'loadings':
                HrService::setEmployeeMonthlyLoading($employeeId, (string)$row['period'], (string)$row['category'], (float)$row['amount'], (string)($row['notes'] ?? ''), $userId);
                break;
            case 'travel_tickets':
                HrService::addEmployeeTravelTicket($employeeId, (string)$row['period'], (float)$row['amount'], (string)($row['departure_date'] ?? ''), (string)($row['return_date'] ?? ''), (string)($row['travel_method'] ?? ''), (string)($row['notes'] ?? ''), $userId);
                break;
            case 'advances':
                $isLong = (int)($row['is_long_term'] ?? 0) === 1 ? 'long' : 'short';
                $approvedAt = !empty($row['approved_at']) ? ImportHelper::parseExcelDate($row['approved_at']) : date('Y-m-d');
                Database::query(
                    "INSERT INTO salary_advances (employee_id, amount, advance_type, installments, reason, status, approved_by, approved_at) VALUES (?,?,?,?,?,?,?,?)",
                    [$employeeId, (float)$row['amount'], $isLong, (int)($row['installments'] ?? 1), (string)($row['reason'] ?? ''), 'approved', $userId, $approvedAt]
                );
                break;
        }
    }


    /** ==================== استيراد رواتب تاريخية كاملة لفترات سابقة ==================== */

    public function payrollImportHistoricalTemplate(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        ImportHelper::downloadTemplate(
            ['emp_code', 'emp_name', 'period', 'base_salary', 'allowances', 'deductions', 'net_salary'],
            'payroll_historical_template.xlsx',
            ['EMP001', 'اسم الموظف', '2026-07', 1000000, 100000, 50000, 1050000]
        );
    }

    /**
     * استيراد رواتب تاريخية كاملة لفترات سابقة (مثل يوليو أو أغسطس) دفعة واحدة.
     * يُنشئ سجل راتب بحالة "مسودة" لكل صف، ويحترم نفس قواعد ترحيل العجز إلى سلفة
     * (migration 144) عند اعتماد الراتب لاحقاً عبر approvePayroll/bulkApprovePayroll
     * التي تستدعي AccountingService::postPayrollAccrual دون أي تعديل.
     */
    public function importPayrollHistorical(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/hr/payroll/import');
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/hr/payroll/import');
        }
        $rows = $read['rows'];
        if (!$rows) {
            $this->flash('danger', 'الملف لا يحتوي على بيانات');
            Response::redirect('/hr/payroll/import');
        }

        $required = ['emp_code', 'period', 'base_salary', 'net_salary'];
        $errors = ImportHelper::validateRequired($rows, $required);

        foreach ($rows as $idx => $row) {
            $period = trim((string)($row['period'] ?? ''));
            if ($period !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                $errors[] = 'الصف ' . ($idx + 2) . ': صيغة الفترة (period) يجب أن تكون YYYY-MM';
            }
        }

        $empCodes = [];
        foreach ($rows as $row) {
            $code = trim((string)($row['emp_code'] ?? ''));
            if ($code) { $empCodes[] = $code; }
        }
        $empMap = [];
        if ($empCodes) {
            $in = implode(',', array_fill(0, count($empCodes), '?'));
            $emps = Database::query("SELECT id, emp_code FROM employees WHERE emp_code IN ($in)", $empCodes)->fetchAll();
            $empMap = array_column($emps, 'id', 'emp_code');
        }
        foreach ($rows as $idx => $row) {
            $code = trim((string)($row['emp_code'] ?? ''));
            if ($code !== '' && !isset($empMap[$code])) {
                $errors[] = 'الصف ' . ($idx + 2) . ': كود الموظف ' . $code . ' غير موجود';
            }
        }

        // منع الكتابة فوق راتب سبق اعتماده أو صرفه لنفس الموظف/الفترة
        $existingMap = [];
        foreach ($rows as $idx => $row) {
            $code = trim((string)($row['emp_code'] ?? ''));
            $period = trim((string)($row['period'] ?? ''));
            if (!isset($empMap[$code]) || $period === '') { continue; }
            $employeeId = (int)$empMap[$code];
            $key = $employeeId . '|' . $period;
            if (!array_key_exists($key, $existingMap)) {
                $existingMap[$key] = Database::query(
                    "SELECT id, status FROM payrolls WHERE employee_id = ? AND period = ?",
                    [$employeeId, $period]
                )->fetch();
            }
            $existing = $existingMap[$key];
            if ($existing && $existing['status'] !== 'draft') {
                $statusLabel = $existing['status'] === 'approved' ? 'معتمد' : 'مصروف';
                $errors[] = 'الصف ' . ($idx + 2) . ': يوجد راتب ' . $statusLabel . ' لهذا الموظف في هذه الفترة، لا يمكن استبداله بالاستيراد';
            }
        }

        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/hr/payroll/import');
        }

        $created = 0;
        $updated = 0;
        $userId = Auth::id();
        Database::beginTransaction();
        try {
            foreach ($rows as $row) {
                $code = trim((string)($row['emp_code'] ?? ''));
                $employeeId = (int)$empMap[$code];
                $period = trim((string)($row['period'] ?? ''));
                $base = (float)($row['base_salary'] ?? 0);
                $allowances = (float)($row['allowances'] ?? 0);
                $deductions = (float)($row['deductions'] ?? 0);
                $desiredNet = (float)($row['net_salary'] ?? 0);

                // نفس منطق calculatePayrollComponents: نعدّل استقطاعاً تسووياً (rounding_adjustment)
                // ليطابق صافي الراتب المستورد تماماً، ثم نطبّق قاعدة ترحيل العجز إلى سلفة
                // إن كان الصافي المستورد سالباً — بما يُبقي قيد المحاسبة متوازناً تماماً.
                $totalEarnings = $base + $allowances;
                $computedNet = $totalEarnings - $deductions;
                $roundingAdjustment = round($desiredNet - $computedNet, 2);
                $totalDeductions = $deductions - $roundingAdjustment;
                $net = $desiredNet;
                $deficitAmount = 0.0;
                if ($net < 0) {
                    $deficitAmount = -$net;
                    $totalDeductions -= $deficitAmount;
                    $net = 0.0;
                }

                $existing = $existingMap[$employeeId . '|' . $period] ?? null;
                if ($existing) {
                    $payrollId = (int)$existing['id'];
                    Database::query(
                        "UPDATE payrolls SET base_salary = ?, allowances = ?, deductions = ?, rounding_adjustment = ?,
                            total_earnings = ?, total_deductions = ?, net_salary = ?, deficit_amount = ?, generated_by = ?,
                            raises = 0, bonuses = 0, overtime = 0, penalties = 0, advances = 0, security_deposit = 0,
                            insurance = 0, travel_tickets = 0, loadings = 0, tips = 0, social_security = 0,
                            absence_days = 0, absence_deduction = 0, tax_amount = 0
                         WHERE id = ?",
                        [$base, $allowances, $deductions, $roundingAdjustment, $totalEarnings, $totalDeductions, $net, $deficitAmount, $userId, $payrollId]
                    );
                    $updated++;
                } else {
                    Database::query(
                        "INSERT INTO payrolls (employee_id, period, base_salary, allowances, deductions, rounding_adjustment, total_earnings, total_deductions, net_salary, deficit_amount, status, generated_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)",
                        [$employeeId, $period, $base, $allowances, $deductions, $roundingAdjustment, $totalEarnings, $totalDeductions, $net, $deficitAmount, $userId]
                    );
                    $payrollId = (int)Database::lastInsertId();
                    $created++;
                }

                HrService::syncPayrollDeficitAdvance($payrollId, $employeeId, $period, $deficitAmount);
            }
            Database::commit();
            Helpers::audit('استيراد رواتب تاريخية', 'payroll_import', 0, "created:{$created} updated:{$updated}");
            $this->flash('success', "تم استيراد الرواتب التاريخية بنجاح: {$created} جديد، {$updated} محدث (بحالة مسودة بانتظار الاعتماد).");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء الاستيراد: ' . $e->getMessage());
        }
        Response::redirect('/hr/payroll/import');
    }

    /** ========== المباشرات والإغلاقات ========== */

    public function movements(): void
    {
        $period = (string)$this->input('period', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = date('Y-m');
        }
        $this->view('hr/movements', [
            'title' => 'المباشرات والإغلاقات',
            'period' => $period,
            'starters' => HrService::getStarters($period),
            'closures' => HrService::getClosures($period),
            'importErrors' => ImportHelper::getErrors(),
        ]);
    }

    public function exportMovements(): void
    {
        $type = (string)($_GET['type'] ?? 'starters');
        $period = (string)($_GET['period'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = date('Y-m');
        }
        $format = strtolower($_GET['format'] ?? 'excel');
        $fileStatusLabels = ['open' => 'مفتوح', 'closed' => 'مغلق', 'archived' => 'مأرشف'];
        $statusLabels = ['active' => 'نشط', 'vacation' => 'إجازة', 'suspended' => 'موقوف', 'terminated' => 'منتهي الخدمة', 'resigned' => 'مستقيل'];

        if ($type === 'closures') {
            $headers = ['الكود', 'الاسم', 'الهاتف', 'القسم', 'الوظيفة', 'الفرع', 'تاريخ المباشرة', 'تاريخ الإغلاق', 'حالة الملف', 'الحالة', 'السبب', 'بواسطة'];
            $rows = [];
            foreach (HrService::getClosures($period) as $c) {
                $rows[] = [
                    $c['emp_code'], $c['full_name'], $c['phone'] ?? '', $c['department'] ?? '', $c['position'] ?? '',
                    $c['branch_name'] ?? '', $c['hire_date'] ?? '', $c['close_date'],
                    $fileStatusLabels[$c['new_status']] ?? $c['new_status'], $statusLabels[$c['status']] ?? $c['status'],
                    $c['reason'] ?? '', $c['changed_by_name'] ?? '',
                ];
            }
            $title = 'قائمة الإغلاقات';
            $name = "closures_$period";
        } else {
            $headers = ['الكود', 'الاسم', 'الهاتف', 'الجنسية', 'السكن', 'القسم', 'الوظيفة', 'الفرع', 'تاريخ المباشرة', 'الراتب الأساسي', 'حالة الملف'];
            $rows = [];
            foreach (HrService::getStarters($period) as $s) {
                $rows[] = [
                    $s['emp_code'], $s['full_name'], $s['phone'] ?? '', $s['nationality'] ?? '', $s['residence'] ?? '',
                    $s['department'] ?? '', $s['position'] ?? '', $s['branch_name'] ?? '', $s['hire_date'],
                    $s['base_salary'], $fileStatusLabels[$s['file_status']] ?? $s['file_status'],
                ];
            }
            $title = 'قائمة المباشرات';
            $name = "starters_$period";
        }
        if ($format === 'pdf') {
            ExportHelper::pdf("$name.pdf", ['title' => $title, 'period' => "الفترة: $period", 'headers' => $headers, 'rows' => $rows]);
        } else {
            ExportHelper::excel("$name.xlsx", [['title' => $title, 'report_title' => $title, 'period' => "الفترة: $period", 'headers' => $headers, 'rows' => $rows]]);
        }
    }

    public function movementsTemplate(string $type): void
    {
        if ($type === 'closures') {
            ImportHelper::downloadTemplate(
                ['emp_code', 'close_date', 'reason', 'file_status', 'status'],
                'closures_template.xlsx',
                ['EMP001', '2026-09-15', 'استقالة', 'closed', 'resigned']
            );
        } else {
            ImportHelper::downloadTemplate(
                ['emp_code', 'full_name', 'phone', 'hire_date', 'nationality', 'residence', 'department', 'job', 'branch_name', 'base_salary'],
                'starters_template.xlsx',
                ['EMP001', 'اسم الموظف', '07700000000', '2026-09-01', 'عراقي', 'بغداد', 'المطبخ', 'طباخ', '', 500000]
            );
        }
    }

    public function importMovements(string $type): void
    {
        $redirect = '/hr/movements';
        if (!in_array($type, ['starters', 'closures'], true)) {
            $this->flash('danger', 'نوع استيراد غير معروف');
            Response::redirect($redirect);
        }
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect($redirect);
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect($redirect);
        }
        $rows = $read['rows'];
        $required = $type === 'closures' ? ['emp_code'] : ['full_name', 'hire_date'];
        $errors = ImportHelper::validateRequired($rows, $required);
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect($redirect);
        }

        $userId = Auth::id();
        $counts = ['created' => 0, 'updated' => 0, 'closed' => 0, 'skipped' => 0];
        if ($type === 'starters') {
            Database::beginTransaction();
            try {
                foreach ($rows as $idx => $row) {
                    try {
                        $result = HrService::importStarterRow($row, $userId);
                        $counts[$result]++;
                    } catch (\RuntimeException $e) {
                        $errors[] = 'الصف ' . ($idx + 2) . ': ' . $e->getMessage();
                    }
                }
                if ($errors) {
                    Database::rollBack();
                    ImportHelper::flashErrors($errors);
                    $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف، لم يتم استيراد أي صف');
                    Response::redirect($redirect);
                }
                Database::commit();
            } catch (\Throwable $e) {
                Database::rollBack();
                $this->flash('danger', 'خطأ أثناء الاستيراد: ' . $e->getMessage());
                Response::redirect($redirect);
            }
            Helpers::audit('استيراد مباشرات', 'employees', 0, "created:{$counts['created']} updated:{$counts['updated']}");
            $this->flash('success', "تم استيراد المباشرات: {$counts['created']} جديد، {$counts['updated']} محدّث");
        } else {
            // إغلاق كل صف بمعاملة مستقلة (changeFileStatus يدير معاملته الخاصة)
            foreach ($rows as $idx => $row) {
                try {
                    $result = HrService::importClosureRow($row, $userId);
                    $counts[$result]++;
                } catch (\Throwable $e) {
                    $errors[] = 'الصف ' . ($idx + 2) . ': ' . $e->getMessage();
                }
            }
            Helpers::audit('استيراد إغلاقات', 'employees', 0, "closed:{$counts['closed']} skipped:{$counts['skipped']} errors:" . count($errors));
            if ($errors) {
                ImportHelper::flashErrors($errors);
                $this->flash('warning', "تم إغلاق {$counts['closed']} ملف، تجاوز {$counts['skipped']}، مع " . count($errors) . ' خطأ');
            } else {
                $this->flash('success', "تم إغلاق {$counts['closed']} ملف، تجاوز {$counts['skipped']} (مغلق مسبقًا)");
            }
        }
        Response::redirect($redirect);
    }

    public function generatePayroll(): void
    {
        $period = (string)$this->input('period', date('Y-m'));
        $employees = Database::query("SELECT * FROM employees WHERE file_status='open'")->fetchAll();
        $count = 0;

        Database::beginTransaction();
        try {
            foreach ($employees as $e) {
                HrService::generatePayroll((int)$e['id'], $period, Auth::id());
                $count++;
            }
            Database::commit();
            $this->flash('success', "تم توليد رواتب $count موظف لفترة $period");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/hr/payroll?period=' . urlencode($period));
    }

    public function approvePayroll(string $id): void
    {
        $result = $this->approvePayrollById((int)$id);
        if ($result['status'] === 'ok') {
            $this->flash('success', 'تم اعتماد الراتب وإنشاء قيد الاستحقاق');
        } else {
            $this->flash('danger', 'خطأ: ' . $result['message']);
        }
        Response::redirect('/hr/payroll?period=' . urlencode($result['period'] ?? $this->input('period', date('Y-m'))));
    }

    public function bulkApprovePayroll(): void
    {
        if (!Auth::can('payroll.approve')) {
            $this->flash('danger', 'ليس لديك صلاحية اعتماد الرواتب');
            Response::redirect('/hr/payroll');
        }
        $period = (string)$this->input('period', date('Y-m'));
        $ids = (array)($_POST['payroll_ids'] ?? []);
        if (!$ids) {
            $this->flash('warning', 'لم يُختر أي راتب للاعتماد');
            Response::redirect('/hr/payroll?period=' . urlencode($period));
        }
        $success = 0;
        $errors = [];
        foreach ($ids as $id) {
            $result = $this->approvePayrollById((int)$id);
            if ($result['status'] === 'ok') {
                $success++;
            } else {
                $errors[] = 'راتب #' . (int)$id . ': ' . $result['message'];
            }
        }
        if ($success) {
            $this->flash('success', "تم اعتماد $success راتب(s) وإنشاء قيود الاستحقاق");
        }
        if ($errors) {
            $this->flash('danger', 'أخطاء أثناء الاعتماد:<br>' . implode('<br>', $errors));
        }
        Response::redirect('/hr/payroll?period=' . urlencode($period));
    }

    private function approvePayrollById(int $payrollId): array
    {
        $p = Database::query(
            "SELECT p.*, e.user_id FROM payrolls p JOIN employees e ON e.id = p.employee_id WHERE p.id = ?",
            [$payrollId]
        )->fetch();
        if (!$p || $p['status'] !== 'draft') {
            return ['status' => 'error', 'message' => 'غير موجود أو غير مسودة', 'period' => $p['period'] ?? date('Y-m')];
        }
        if (!Auth::can('payroll.approve')) {
            return ['status' => 'error', 'message' => 'ليس لديك صلاحية اعتماد الرواتب', 'period' => $p['period']];
        }
        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE payrolls SET status='approved', approved=1, approved_by=?, approved_at=NOW() WHERE id = ?",
                [Auth::id(), $payrollId]
            );
            AccountingService::postPayrollAccrual($payrollId);
            Helpers::queueSync('payrolls', $payrollId, 'update', ['status' => 'approved']);
            if ($p['user_id']) {
                Helpers::notify((int)$p['user_id'], 'تم اعتماد الراتب', 'تم اعتماد راتبك للفترة ' . $p['period'], '/hr/payroll/' . $payrollId . '/slip');
            }
            Database::commit();
            return ['status' => 'ok', 'period' => $p['period']];
        } catch (\Throwable $e) {
            Database::rollBack();
            return ['status' => 'error', 'message' => $e->getMessage(), 'period' => $p['period']];
        }
    }

    public function addOvertime(string $id): void
    {
        $payrollId = (int)$id;
        $p = Database::query("SELECT * FROM payrolls WHERE id = ?", [$payrollId])->fetch();
        if (!$p || $p['status'] === 'paid') {
            Response::redirect('/hr/payroll?period=' . urlencode($p['period'] ?? date('Y-m')));
        }
        if (!Auth::can('payroll.overtime') && !Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll?period=' . urlencode($p['period']));
        }
        $hours = (float)$this->input('hours', 0);
        $rate = (float)$this->input('hourly_rate', 0);
        $multiplier = (float)$this->input('multiplier', 1.0);
        $amount = round($hours * $rate * $multiplier, 2);
        if ($hours > 0 && $rate > 0) {
            Database::query(
                "INSERT INTO payroll_overtime (payroll_id, hours, hourly_rate, multiplier, amount, notes) VALUES (?,?,?,?,?,?)",
                [$payrollId, $hours, $rate, $multiplier, $amount, (string)$this->input('notes')]
            );
            HrService::generatePayroll((int)$p['employee_id'], $p['period'], Auth::id());
            $this->flash('success', 'تم إضافة الإضافي وإعادة حساب الراتب');
        }
        Response::redirect('/hr/payroll?period=' . urlencode($p['period']));
    }

    public function employeeAllowances(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $employeeId) {
            Database::query(
                "INSERT INTO employee_allowances (employee_id, allowance_type, amount) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE amount = VALUES(amount)",
                [$employeeId, (string)$this->input('allowance_type'), (float)$this->input('amount')]
            );
            $this->flash('success', 'تم حفظ البدل');
            Response::redirect('/hr/payroll/allowances?employee_id=' . $employeeId);
        }
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $allowances = [];
        if ($employeeId) {
            $allowances = Database::query(
                "SELECT * FROM employee_allowances WHERE employee_id = ? ORDER BY allowance_type",
                [$employeeId]
            )->fetchAll();
        }
        $this->view('hr/employee_allowances', [
            'title' => 'بدلات الموظفين', 'employees' => $employees, 'allowances' => $allowances,
            'employeeId' => $employeeId,
        ]);
    }

    public function monthlyInsurance(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $records = [];
        if ($employeeId) {
            $records = HrService::getEmployeeInsuranceRecords($employeeId);
        }
        $this->view('hr/monthly_insurance', [
            'title' => 'التأمينات الشهرية', 'employees' => $employees, 'records' => $records,
            'employeeId' => $employeeId, 'period' => $period,
        ]);
    }

    public function storeMonthlyInsurance(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $amount = (float)$this->input('amount', 0);
        $notes = (string)$this->input('notes', '');
        if ($employeeId && preg_match('/^\d{4}-\d{2}$/', $period)) {
            HrService::setEmployeeMonthlyInsurance($employeeId, $period, $amount, $notes, Auth::id());
            $this->flash('success', 'تم حفظ التأمين');
        } else {
            $this->flash('danger', 'بيانات غير صحيحة');
        }
        Response::redirect('/hr/payroll/insurance?employee_id=' . $employeeId . '&period=' . urlencode($period));
    }

    public function deleteMonthlyInsurance(string $id): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        HrService::deleteEmployeeMonthlyInsurance((int)$id);
        $this->flash('success', 'تم حذف التأمين');
        Response::redirect('/hr/payroll/insurance');
    }

    public function monthlyTips(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $records = [];
        if ($employeeId) {
            $records = HrService::getEmployeeMonthlyTipsRecords($employeeId);
        }
        $this->view('hr/monthly_tips', [
            'title' => 'التيبس الشهري', 'employees' => $employees, 'records' => $records,
            'employeeId' => $employeeId, 'period' => $period,
        ]);
    }

    public function storeMonthlyTips(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $fullAmount = (float)$this->input('full_amount', 0);
        $workingDays = (int)$this->input('working_days', 30);
        $notes = (string)$this->input('notes', '');
        if ($employeeId && preg_match('/^\d{4}-\d{2}$/', $period)) {
            HrService::setEmployeeMonthlyTips($employeeId, $period, $fullAmount, $workingDays, $notes ?: null, Auth::id());
            $this->flash('success', 'تم حفظ التيبس');
        } else {
            $this->flash('danger', 'بيانات غير صحيحة');
        }
        Response::redirect('/hr/payroll/tips?employee_id=' . $employeeId . '&period=' . urlencode($period));
    }

    public function deleteMonthlyTips(string $id): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        HrService::deleteEmployeeMonthlyTip((int)$id);
        $this->flash('success', 'تم حذف التيبس');
        Response::redirect('/hr/payroll/tips');
    }

    public function monthlySocialSecurity(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $records = [];
        if ($employeeId) {
            $records = HrService::getEmployeeMonthlySocialSecurityRecords($employeeId);
        }
        $this->view('hr/monthly_social_security', [
            'title' => 'الضمان الاجتماعي الشهري', 'employees' => $employees, 'records' => $records,
            'employeeId' => $employeeId, 'period' => $period,
        ]);
    }

    public function storeMonthlySocialSecurity(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $amount = (float)$this->input('amount', 0);
        $notes = (string)$this->input('notes', '');
        if ($employeeId && preg_match('/^\d{4}-\d{2}$/', $period)) {
            HrService::setEmployeeMonthlySocialSecurity($employeeId, $period, $amount, $notes ?: null, Auth::id());
            $this->flash('success', 'تم حفظ الضمان الاجتماعي');
        } else {
            $this->flash('danger', 'بيانات غير صحيحة');
        }
        Response::redirect('/hr/payroll/social-security?employee_id=' . $employeeId . '&period=' . urlencode($period));
    }

    public function deleteMonthlySocialSecurity(string $id): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        HrService::deleteEmployeeMonthlySocialSecurity((int)$id);
        $this->flash('success', 'تم حذف الضمان الاجتماعي');
        Response::redirect('/hr/payroll/social-security');
    }


    public function travelTickets(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $records = [];
        if ($employeeId) {
            $records = HrService::getEmployeeTravelTickets($employeeId);
        }
        $this->view('hr/travel_tickets', [
            'title' => 'تذاكر سفر الموظفين', 'employees' => $employees, 'records' => $records,
            'employeeId' => $employeeId, 'period' => $period,
        ]);
    }

    public function storeTravelTicket(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $amount = (float)$this->input('amount', 0);
        $departureDate = (string)$this->input('departure_date', '');
        $returnDate = (string)$this->input('return_date', '');
        $travelMethod = (string)$this->input('travel_method', '');
        $notes = (string)$this->input('notes', '');
        if ($employeeId && preg_match('/^\d{4}-\d{2}$/', $period) && $amount > 0) {
            HrService::addEmployeeTravelTicket(
                $employeeId, $period, $amount,
                $departureDate ?: null, $returnDate ?: null,
                $travelMethod ?: null, $notes ?: null, Auth::id()
            );
            $this->flash('success', 'تم حفظ تذكرة السفر');
        } else {
            $this->flash('danger', 'بيانات غير صحيحة');
        }
        Response::redirect('/hr/payroll/travel-tickets?employee_id=' . $employeeId . '&period=' . urlencode($period));
    }

    public function deleteTravelTicket(string $id): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        HrService::deleteEmployeeTravelTicket((int)$id);
        $this->flash('success', 'تم حذف تذكرة السفر');
        Response::redirect('/hr/payroll/travel-tickets');
    }

    public function monthlyLoadings(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $records = [];
        if ($employeeId) {
            $records = HrService::getEmployeeLoadings($employeeId);
        }
        $this->view('hr/loadings', [
            'title' => 'التحميلات الشهرية', 'employees' => $employees, 'records' => $records,
            'employeeId' => $employeeId, 'period' => $period,
        ]);
    }

    public function storeMonthlyLoading(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $employeeId = (int)$this->input('employee_id', 0);
        $period = (string)$this->input('period', date('Y-m'));
        $category = (string)$this->input('category', 'تحميلات');
        $amount = (float)$this->input('amount', 0);
        $notes = (string)$this->input('notes', '');
        if ($employeeId && preg_match('/^\d{4}-\d{2}$/', $period) && $category !== '') {
            HrService::setEmployeeMonthlyLoading($employeeId, $period, $category, $amount, $notes ?: null, Auth::id());
            $this->flash('success', 'تم حفظ التحميل');
        } else {
            $this->flash('danger', 'بيانات غير صحيحة');
        }
        Response::redirect('/hr/payroll/loadings?employee_id=' . $employeeId . '&period=' . urlencode($period));
    }

    public function deleteMonthlyLoading(string $id): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        HrService::deleteEmployeeMonthlyLoading((int)$id);
        $this->flash('success', 'تم حذف التحميل');
        Response::redirect('/hr/payroll/loadings');
    }

    /** إعدادات شرائح الضريبة */
    public function taxBrackets(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $brackets = Database::query("SELECT * FROM tax_brackets ORDER BY min_amount")->fetchAll();
        $this->view('hr/tax_brackets', ['title' => 'شرائح الضريبة', 'brackets' => $brackets]);
    }

    public function storeTaxBracket(): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        $minAmount = (float)$this->input('min_amount', 0);
        $maxAmountRaw = trim((string)$this->input('max_amount', ''));
        $maxAmount = $maxAmountRaw === '' ? null : (float)$maxAmountRaw;
        $ratePercent = (float)$this->input('rate', 0);
        if ($minAmount >= 0 && $ratePercent >= 0 && ($maxAmount === null || $maxAmount > $minAmount)) {
            Database::query(
                "INSERT INTO tax_brackets (min_amount, max_amount, rate) VALUES (?,?,?)",
                [$minAmount, $maxAmount, $ratePercent / 100]
            );
            $this->flash('success', 'تم إضافة شريحة الضريبة');
        } else {
            $this->flash('danger', 'بيانات غير صحيحة');
        }
        Response::redirect('/hr/payroll/tax-brackets');
    }

    public function toggleTaxBracket(string $id): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        Database::query("UPDATE tax_brackets SET is_active = NOT is_active WHERE id = ?", [(int)$id]);
        $this->flash('success', 'تم تحديث حالة الشريحة');
        Response::redirect('/hr/payroll/tax-brackets');
    }

    public function deleteTaxBracket(string $id): void
    {
        if (!Auth::can('payroll.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        Database::query("DELETE FROM tax_brackets WHERE id = ?", [(int)$id]);
        $this->flash('success', 'تم حذف شريحة الضريبة');
        Response::redirect('/hr/payroll/tax-brackets');
    }


    public function payPayroll(string $id): void
    {
        $p = Database::query("SELECT * FROM payrolls WHERE id = ?", [(int)$id])->fetch();
        if ($p && $p['status'] !== 'paid') {
            if ($p['status'] !== 'approved') {
                $this->flash('danger', 'يجب اعتماد الراتب قبل الصرف');
                Response::redirect('/hr/payroll?period=' . urlencode($p['period'] ?? date('Y-m')));
            }
            if (!HrService::isEmployeeFileOpen((int)$p['employee_id'])) {
                $this->flash('danger', 'ملف الموظف مغلق أو مأرشف، لا يمكن صرف الراتب');
                Response::redirect('/hr/payroll?period=' . urlencode($p['period'] ?? date('Y-m')));
            }
            Database::beginTransaction();
            try {
                Database::query("UPDATE payrolls SET status='paid', paid_at=NOW() WHERE id = ?", [(int)$id]);
                Database::query(
                    "INSERT INTO cash_transactions (register_id, trx_type, amount, category, reference, description, created_by)
                     VALUES (1,'out',?,?,?,?,?)",
                    [(float)$p['net_salary'], 'رواتب', 'PAYROLL-' . $p['id'],
                     'راتب فترة ' . $p['period'], Auth::id()]
                );
                Database::query("UPDATE cash_registers SET balance = balance - ? WHERE id = 1", [(float)$p['net_salary']]);
                AccountingService::postPayroll((int)$id);
                Helpers::queueSync('payrolls', (int)$id, 'update', ['status' => 'paid', 'net_salary' => (float)$p['net_salary']]);
                Database::commit();
                $this->flash('success', 'تم صرف الراتب وخصمه من الصندوق');
            } catch (\Throwable $e) {
                Database::rollBack();
                $this->flash('danger', 'خطأ: ' . $e->getMessage());
            }
        }
        Response::redirect('/hr/payroll?period=' . urlencode($p['period'] ?? date('Y-m')));
    }

    public function paySlip(string $id): void
    {
        $p = Database::query(
            "SELECT p.*, e.full_name, e.department, e.position, e.phone, e.emp_code FROM payrolls p
             JOIN employees e ON e.id = p.employee_id WHERE p.id = ?",
            [(int)$id]
        )->fetch();
        if (!$p) { Response::redirect('/hr/payroll'); return; }

        $overtime = Database::query(
            "SELECT * FROM payroll_overtime WHERE payroll_id = ? ORDER BY id",
            [(int)$id]
        )->fetchAll();
        $allowances = Database::query(
            "SELECT * FROM payroll_allowances WHERE payroll_id = ? ORDER BY allowance_type",
            [(int)$id]
        )->fetchAll();

        $format = (string)($_GET['format'] ?? '');
        if ($format === 'pdf') {
            $rows = [
                ['الراتب الأساسي', Helpers::money($p['base_salary'])],
                ['الزيادات', Helpers::money($p['raises'])],
            ];
            foreach ($allowances as $a) {
                $rows[] = [Helpers::e($a['allowance_type']), Helpers::money($a['amount'])];
            }
            $rows[] = ['المكافآت', Helpers::money($p['bonuses'])];
            $rows[] = ['تذاكر سفر', Helpers::money($p['travel_tickets'] ?? 0)];
            foreach ($overtime as $o) {
                $rows[] = ['إضافي ' . $o['hours'] . ' ساعة × ' . $o['multiplier'], Helpers::money($o['amount'])];
            }
            $rows[] = ['إجمالي الاستحقاقات', Helpers::money($p['total_earnings'])];
            $rows[] = ['الخصومات', Helpers::money($p['deductions'])];
            $rows[] = ['الجزاءات', Helpers::money($p['penalties'])];
            $rows[] = ['أقساط السلف', Helpers::money($p['advances'])];
            $rows[] = ['تأمين المستحقات', Helpers::money($p['security_deposit'] ?? 0)];
            $rows[] = ['التأمينات الشهرية', Helpers::money($p['insurance'] ?? 0)];
            $rows[] = ['التحميلات الشهرية', Helpers::money($p['loadings'] ?? 0)];
            $rows[] = ['التيبس', Helpers::money($p['tips'] ?? 0)];
            $rows[] = ['الضمان الاجتماعي', Helpers::money($p['social_security'] ?? 0)];
            $rows[] = ['فرق التقريب', Helpers::money($p['rounding_adjustment'] ?? 0)];
            $rows[] = ['غيابات (' . $p['absence_days'] . ' يوم)', Helpers::money($p['absence_deduction'])];
            $rows[] = ['الضريبة', Helpers::money($p['tax_amount'])];
            $rows[] = ['إجمالي الاستقطاعات', Helpers::money($p['total_deductions'])];
            $rows[] = ['الراتب الصافي', Helpers::money($p['net_salary'])];
            ExportHelper::pdf('payslip_' . $p['id'] . '.pdf', [
                'title' => 'ورقة دفع الراتب - ' . $p['full_name'],
                'period' => $p['period'],
                'headers' => ['البند', 'المبلغ'],
                'rows' => $rows,
            ]);
        }
        $this->view('hr/pay_slip', [
            'title' => 'ورقة دفع الراتب', 'payroll' => $p,
            'overtime' => $overtime, 'allowances' => $allowances,
        ]);
    }

    public function adjustments(): void
    {
        $status = (string)($_GET['status'] ?? 'all');
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $where = ['1=1'];
        $params = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where[] = "a.status = ?";
            $params[] = $status;
        }
        if ($employeeId) {
            $where[] = "a.employee_id = ?";
            $params[] = $employeeId;
        }
        $sql = "SELECT a.*, e.full_name, e.department, req.full_name AS requested_name, app.full_name AS approved_name,
                        hwl.request_id AS workflow_request_id, wr.current_step AS workflow_current_step
                FROM hr_adjustments a
                JOIN employees e ON e.id = a.employee_id
                LEFT JOIN users req ON req.id = a.requested_by
                LEFT JOIN users app ON app.id = a.approved_by
                LEFT JOIN hr_workflow_links hwl ON hwl.entity_type = 'hr_adjustment' AND hwl.entity_id = a.id
                LEFT JOIN requests wr ON wr.id = hwl.request_id
                WHERE " . implode(' AND ', $where) . " ORDER BY a.id DESC LIMIT 100";
        $adjustments = Database::query($sql, $params)->fetchAll();
        $employees = Database::query("SELECT id, full_name, base_salary, admin_level FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $warnings = [];
        if ($employeeId) {
            $warnings = HrService::getEmployeeWarnings($employeeId, 50);
        }
        $this->view('hr/adjustments', [
            'title' => 'التسويات المالية والإنذارات', 'adjustments' => $adjustments, 'employees' => $employees,
            'status' => $status, 'employeeId' => $employeeId, 'warnings' => $warnings,
        ]);
    }

    public function showAdjustment(string $id): void
    {
        $adjId = (int)$id;
        $adjustment = Database::query(
            "SELECT a.*, e.full_name, e.department, e.emp_code, req.full_name AS requested_name, app.full_name AS approved_name
             FROM hr_adjustments a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN users req ON req.id = a.requested_by
             LEFT JOIN users app ON app.id = a.approved_by
             WHERE a.id = ?",
            [$adjId]
        )->fetch();
        if (!$adjustment) {
            Response::redirect('/hr/adjustments');
        }
        $workflowRequestId = HrWorkflowService::workflowRequestId('hr_adjustment', $adjId);
        $workflowHistory = $workflowRequestId ? WorkflowEngine::getHistory($workflowRequestId) : [];
        $this->view('hr/adjustments_show', [
            'title' => 'تفاصيل التسوية', 'adjustment' => $adjustment, 'history' => $workflowHistory,
            'workflowRequestId' => $workflowRequestId,
        ]);
    }

    public function storeAdjustment(): void
    {
        $employeeId = (int)$this->input('employee_id');
        if (!HrService::isEmployeeFileOpen($employeeId)) {
            $this->flash('danger', 'ملف الموظف مغلق أو مأرشف، لا يمكن إضافة تسوية');
            Response::redirect('/hr/adjustments');
        }
        $type = (string)$this->input('adjustment_type');
        $period = (string)$this->input('period');
        if (!$employeeId || !in_array($type, ['bonus','deduction','raise','penalty','tips_addition'], true)) {
            $this->flash('danger', 'بيانات تسوية غير صالحة');
            Response::redirect('/hr/adjustments');
        }
        // الخصومات والجزاءات تُستقطع بعدد أيام من الراتب (وليس مبلغاً مقطوعاً)
        $days = null;
        if (in_array($type, ['deduction', 'penalty'], true)) {
            $days = (float)$this->input('days', 0);
            if ($days <= 0) {
                $this->flash('danger', 'يجب إدخال عدد أيام الاستقطاع');
                Response::redirect('/hr/adjustments');
            }
            $emp = Database::query("SELECT base_salary FROM employees WHERE id = ?", [$employeeId])->fetch();
            $amount = round(((float)($emp['base_salary'] ?? 0) / 30) * $days, 2);
        } else {
            $amount = (float)$this->input('amount');
        }
        if (!$amount) {
            $this->flash('danger', 'بيانات تسوية غير صالحة');
            Response::redirect('/hr/adjustments');
        }
        $effective = (string)$this->input('effective_date') ?: ($period ? $period . '-01' : date('Y-m-d'));
        Database::query(
            "INSERT INTO hr_adjustments (employee_id, adjustment_type, amount, days, period, effective_date, reason, requested_by, admin_level_required)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [$employeeId, $type, $amount, $days, $period ?: null, $effective, (string)$this->input('reason'), Auth::id(), (int)$this->input('admin_level_required', 1)]
        );
        $adjId = (int)Database::lastInsertId();
        Helpers::queueSync('hr_adjustments', $adjId, 'insert', [
            'employee_id' => $employeeId, 'type' => $type, 'amount' => $amount,
        ]);
        $emp = Database::query("SELECT * FROM employees WHERE id = ?", [$employeeId])->fetch();
        HrWorkflowService::request('hr_adjustment', $adjId, (int)Auth::id(), [
            'employee_id' => $employeeId,
            'employee_name' => $emp['full_name'] ?? '',
            'amount' => $amount,
            'adjustment_type' => $type,
            'period' => $period,
            'effective_date' => $effective,
            'reason' => (string)$this->input('reason'),
            'title' => 'طلب تسوية - ' . Helpers::e($emp['full_name'] ?? '') . ' - ' . Helpers::money($amount),
        ]);
        $this->flash('success', 'تم تقديم التسوية وإرسالها للموافقة');
        Response::redirect('/hr/adjustments');
    }

    public function decideAdjustment(string $id): void
    {
        $adjId = (int)$id;
        $decision = (string)$this->input('decision');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            Response::json(['ok' => false, 'error' => 'قرار غير صالح'], 422); return;
        }
        if (!HrService::canDecideAdjustment($adjId, Auth::id())) {
            Response::json(['ok' => false, 'error' => 'ليس لديك الصلاحية للموافقة'], 403); return;
        }
        $wfRequestId = HrWorkflowService::workflowRequestId('hr_adjustment', $adjId);
        if ($wfRequestId) {
            try {
                WorkflowEngine::decide($wfRequestId, $decision, '', (int)Auth::id());
            } catch (\Throwable $e) {
                Response::json(['ok' => false, 'error' => $e->getMessage()], 422); return;
            }
        } else {
            HrService::decideAdjustment($adjId, $decision, Auth::id());
        }
        Response::json(['ok' => true, 'status' => $decision]);
    }

    public function storeWarning(): void
    {
        $employeeId = (int)$this->input('employee_id');
        if (!HrService::isEmployeeFileOpen($employeeId)) {
            $this->flash('danger', 'ملف الموظف مغلق أو مأرشف');
            Response::redirect('/hr/adjustments');
        }
        $type = (string)$this->input('warning_type');
        $reason = (string)$this->input('reason');
        $date = (string)$this->input('warning_date') ?: date('Y-m-d');
        if (!$employeeId || !$reason || !in_array($type, ['verbal','written','final'], true)) {
            $this->flash('danger', 'بيانات إنذار غير صالحة');
            Response::redirect('/hr/adjustments');
        }
        HrService::createWarning($employeeId, $type, $reason, $date, Auth::id());
        $this->flash('success', 'تم تسجيل الإنذار');
        Response::redirect('/hr/adjustments' . ($employeeId ? '?employee_id=' . $employeeId : ''));
    }

    public function resolveWarning(string $id): void
    {
        $warningId = (int)$id;
        if (!Auth::can('hr.warnings.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/adjustments');
        }
        HrService::updateWarningStatus($warningId, 'resolved');
        $this->flash('success', 'تم تحديث حالة الإنذار');
        Response::redirect('/hr/adjustments');
    }

    public function regularityReport(): void
    {
        $_GET['type'] = 'regularity';
        $this->reports();
    }

    public function reports(): void
    {
        if (!Auth::can('hr.reports.view')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/dashboard');
        }
        $type = (string)($_GET['type'] ?? 'payroll');
        $filters = [
            'period' => (string)($_GET['period'] ?? date('Y-m')),
            'date_from' => (string)($_GET['date_from'] ?? ''),
            'date_to' => (string)($_GET['date_to'] ?? ''),
            'employee_id' => (int)($_GET['employee_id'] ?? 0),
            'branch_id' => (int)($_GET['branch_id'] ?? 0),
            'department_id' => (int)($_GET['department_id'] ?? 0),
            'status' => (string)($_GET['status'] ?? ''),
        ];
        $report = HrReportService::getReport($type, $filters);
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $branches = Database::query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
        $departments = Database::query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

        $this->view('hr/reports', [
            'title' => $report['title'],
            'type' => $type, 'filters' => $filters,
            'report' => $report,
            'employees' => $employees, 'branches' => $branches, 'departments' => $departments,
        ]);
    }

    public function exportReports(): void
    {
        if (!Auth::can('hr.reports.view')) {
            Response::json(['error' => 'unauthorized']);
            return;
        }
        $type = (string)($_GET['type'] ?? 'payroll');
        $format = (string)($_GET['format'] ?? 'excel');
        $filters = [
            'period' => (string)($_GET['period'] ?? date('Y-m')),
            'date_from' => (string)($_GET['date_from'] ?? ''),
            'date_to' => (string)($_GET['date_to'] ?? ''),
            'employee_id' => (int)($_GET['employee_id'] ?? 0),
            'branch_id' => (int)($_GET['branch_id'] ?? 0),
            'department_id' => (int)($_GET['department_id'] ?? 0),
            'status' => (string)($_GET['status'] ?? ''),
        ];
        $report = HrReportService::getReport($type, $filters);
        $columns = $report['columns'] ?? [];
        $headers = $report['headers'];
        $moneyCols = $report['money'] ?? [];
        $sheetRows = [];
        foreach ($report['rows'] as $r) {
            $row = [];
            foreach ($columns as $idx => $key) {
                $val = $r[$key] ?? '';
                if (in_array($key, $moneyCols, true)) {
                    $val = Helpers::money($val);
                }
                $row[] = $val;
            }
            $sheetRows[] = $row;
        }
        $sheet = [
            'title' => $type, 'report_title' => $report['title'], 'period' => $filters['period'],
            'headers' => $headers, 'rows' => $sheetRows,
        ];
        if ($format === 'pdf') {
            ExportHelper::pdf('hr_' . $type . '_' . date('Ymd') . '.pdf', ['title' => $report['title'], 'period' => $filters['period'], 'headers' => $headers, 'rows' => $sheetRows]);
        } else {
            ExportHelper::excel('hr_' . $type . '_' . date('Ymd') . '.xlsx', [$sheet]);
        }
    }

    public function tasks(): void
    {
        $tasks = Database::query(
            "SELECT t.*, e.full_name AS assignee, u.full_name AS creator FROM tasks t
             JOIN employees e ON e.id = t.assigned_to
             LEFT JOIN users u ON u.id = t.created_by
             ORDER BY t.id DESC LIMIT 100"
        )->fetchAll();
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();

        $this->view('hr/tasks', [
            'title' => 'المهام', 'tasks' => $tasks, 'employees' => $employees,
        ]);
    }

    public function storeTask(): void
    {
        Database::query(
            "INSERT INTO tasks (title, description, assigned_to, due_date, created_by) VALUES (?,?,?,?,?)",
            [(string)$this->input('title'), (string)$this->input('description'),
             (int)$this->input('assigned_to'), $this->input('due_date') ?: null, Auth::id()]
        );
        $this->flash('success', 'تم إسناد المهمة');
        Response::redirect('/hr/tasks');
    }

    public function updateTaskStatus(string $id): void
    {
        $status = (string)$this->input('status', 'open');
        if (in_array($status, ['open', 'in_progress', 'done', 'cancelled'], true)) {
            Database::query("UPDATE tasks SET status = ? WHERE id = ?", [$status, (int)$id]);
            $this->flash('success', 'تم تحديث حالة المهمة');
        }
        Response::redirect('/hr/tasks');
    }

    public function employeesTemplate(): void
    {
        ImportHelper::downloadTemplate(
            ['emp_code', 'full_name', 'phone', 'nationality', 'residence', 'department', 'job', 'role', 'manager_id',
             'base_salary', 'hire_date', 'work_start_time', 'work_end_time', 'weekly_off_day', 'branch_id', 'branch_name',
             'cost_center_id', 'account_id', 'status', 'username', 'password'],
            'employees_template.xlsx',
            ['EMP001', 'علي حسن', '07700000000', 'عراقي', 'بغداد', 'المطبخ', 'شيف عام', 'شيف', 5,
             500000, '2026-01-01', '08:00', '17:00', 5, 1, 'الفرع الرئيسي',
             1, 1, 'active', 'ali.hassan', '123456']
        );
    }

    public function exportEmployeeIds(): void
    {
        $employees = Database::query(
            "SELECT e.id, e.full_name, e.emp_code, e.status,
                    d.name AS department, j.title AS job
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN jobs j ON j.id = e.job_id
             ORDER BY e.full_name"
        )->fetchAll();

        $rows = [];
        foreach ($employees as $e) {
            $rows[] = [
                $e['id'],
                $e['full_name'],
                $e['emp_code'],
                $e['department'] ?? '-',
                $e['job'] ?? '-',
                $e['status'],
            ];
        }

        ExportHelper::excel('employees_ids.xlsx', [
            [
                'title' => 'Employees IDs',
                'headers' => ['id', 'full_name', 'emp_code', 'department', 'job', 'status'],
                'rows' => $rows,
            ],
        ]);
    }

    public function importEmployees(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/hr/employees');
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/hr/employees');
        }
        $rows = $read['rows'];

        $errors = [];

        $departments = Database::query("SELECT id, name FROM departments WHERE is_active = 1")->fetchAll();
        $deptMap = [];
        $deptNormalized = [];
        foreach ($departments as $d) {
            $name = trim($d['name']);
            $deptMap[$name] = (int)$d['id'];
            $deptNormalized[mb_strtolower($name, 'UTF-8')] = (int)$d['id'];
            $deptNormalized[mb_strtolower(preg_replace('/^\s*قسم\s+/u', '', $name), 'UTF-8')] = (int)$d['id'];
        }

        $jobs = Database::query(
            "SELECT j.id, j.title, j.department_id, j.role_id FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE j.is_active = 1"
        )->fetchAll();
        $jobMap = [];
        foreach ($jobs as $j) {
            $key = mb_strtolower(trim($j['title']) . '|' . (int)$j['department_id'], 'UTF-8');
            $jobMap[$key] = (int)$j['id'];
        }

        $roles = Database::query("SELECT id, name, level FROM roles")->fetchAll();
        $roleMap = [];
        foreach ($roles as $r) {
            $roleMap[trim($r['name'])] = (int)$r['id'];
            $roleMap[mb_strtolower(trim($r['name']), 'UTF-8')] = (int)$r['id'];
        }

        $branches = Database::query("SELECT id, name FROM branches WHERE is_active = 1")->fetchAll();
        $branchMap = [];
        $branchNormalized = [];
        foreach ($branches as $b) {
            $name = trim($b['name']);
            $branchMap[$name] = (int)$b['id'];
            $branchNormalized[mb_strtolower($name, 'UTF-8')] = (int)$b['id'];
        }

        $existingManagers = array_column(Database::query("SELECT id FROM employees")->fetchAll(), 'id');
        $existingBranches = array_column($branches, 'id');
        $existingCostCenters = array_column(Database::query("SELECT id FROM cost_centers")->fetchAll(), 'id');
        $existingAccounts = array_column(Database::query("SELECT id FROM accounts")->fetchAll(), 'id');

        $processedRows = [];
        foreach ($rows as $idx => $row) {
            $line = $idx + 2;

            // مطابقة الموظف الموجود مسبقاً بواسطة كود الموظف أو الهاتف
            $existing = null;
            if (!empty($row['emp_code'])) {
                $existing = Database::query(
                    "SELECT * FROM employees WHERE emp_code = ? LIMIT 1",
                    [trim((string)$row['emp_code'])]
                )->fetch();
            }
            if (!$existing && !empty($row['phone'])) {
                $existing = Database::query(
                    "SELECT * FROM employees WHERE phone = ? LIMIT 1",
                    [trim((string)$row['phone'])]
                )->fetch();
            }

            // تحويل اسم الفرع إلى branch_id
            $branchId = null;
            if (!empty($row['branch_id'])) {
                $branchId = (int)$row['branch_id'];
            }
            if (!empty($row['branch_name'])) {
                $bName = trim((string)$row['branch_name']);
                $branchId = $branchMap[$bName] ?? $branchNormalized[mb_strtolower($bName, 'UTF-8')] ?? null;
                if (!$branchId) {
                    $errors[] = "الصف $line: الفرع '{$row['branch_name']}' غير موجود";
                }
            }
            if (!empty($row['branch_id']) && !in_array((int)$row['branch_id'], $existingBranches, true)) {
                $errors[] = "الصف $line: الفرع '{$row['branch_id']}' غير موجود";
            }

            if ($existing) {
                // التحقق من صحة الدور المُدخل للموظف الموجود
                if (!empty($row['role_id']) && !in_array((int)$row['role_id'], array_column($roles, 'id'), true)) {
                    $errors[] = "الصف $line: الدور '{$row['role_id']}' غير موجود";
                }
                if (!empty($row['role']) && !isset($roleMap[trim((string)$row['role'])]) && !isset($roleMap[mb_strtolower(trim((string)$row['role']), 'UTF-8')])) {
                    $errors[] = "الصف $line: الدور '{$row['role']}' غير موجود";
                }
                $processedRows[] = ['line' => $line, 'row' => $row, 'existing' => $existing, 'branchId' => $branchId];
                continue;
            }

            // الموظف غير موجود: يجب توفر الاسم والهاتف
            if (empty($row['full_name'])) {
                $errors[] = "الصف $line: الاسم الكامل مطلوب";
            }
            if (empty($row['phone'])) {
                $errors[] = "الصف $line: الهاتف مطلوب";
            }

            $departmentId = null;
            if (!empty($row['department_id'])) {
                $departmentId = (int)$row['department_id'];
            } elseif (!empty($row['department'])) {
                $deptName = trim((string)$row['department']);
                $departmentId = $deptMap[$deptName] ?? null;
                if (!$departmentId) {
                    $search = mb_strtolower($deptName, 'UTF-8');
                    $departmentId = $deptNormalized[$search] ?? null;
                    if (!$departmentId) {
                        $withoutPrefix = mb_strtolower(preg_replace('/^\s*قسم\s+/u', '', $deptName), 'UTF-8');
                        $departmentId = $deptNormalized[$withoutPrefix] ?? null;
                    }
                    if (!$departmentId) {
                        $withPrefix = mb_strtolower('قسم ' . $deptName, 'UTF-8');
                        $departmentId = $deptNormalized[$withPrefix] ?? null;
                    }
                }
            }
            if (!empty($row['department']) && !$departmentId) {
                $errors[] = "الصف $line: القسم '{$row['department']}' غير موجود";
            }

            $jobId = null;
            if (!empty($row['job_id'])) {
                $jobId = (int)$row['job_id'];
            } elseif (!empty($row['job'])) {
                if ($departmentId) {
                    $key = mb_strtolower(trim((string)$row['job']) . '|' . $departmentId, 'UTF-8');
                    $jobId = $jobMap[$key] ?? null;
                }
                if (!$jobId) {
                    $jobTitle = mb_strtolower(trim((string)$row['job']), 'UTF-8');
                    foreach ($jobs as $j) {
                        if (mb_strtolower(trim($j['title']), 'UTF-8') === $jobTitle) {
                            $jobId = (int)$j['id'];
                            break;
                        }
                    }
                }
            }
            if (!empty($row['job']) && !$jobId) {
                $errors[] = "الصف $line: الوظيفة '{$row['job']}' غير موجودة" . ($departmentId ? ' لهذا القسم' : '');
            }

            $roleId = null;
            if (!empty($row['role_id'])) {
                $roleId = (int)$row['role_id'];
            } elseif (!empty($row['role'])) {
                $roleName = trim((string)$row['role']);
                $roleId = $roleMap[$roleName] ?? $roleMap[mb_strtolower($roleName, 'UTF-8')] ?? null;
            } elseif ($jobId) {
                foreach ($jobs as $j) {
                    if ((int)$j['id'] === $jobId) {
                        $roleId = $j['role_id'] ? (int)$j['role_id'] : null;
                        break;
                    }
                }
            }
            if (!empty($row['role']) && !$roleId) {
                $errors[] = "الصف $line: الدور '{$row['role']}' غير موجود";
            }

            if (!empty($row['manager_id']) && !in_array((int)$row['manager_id'], $existingManagers, true)) {
                $errors[] = "الصف $line: المدير '{$row['manager_id']}' غير موجود";
            }
            if (!empty($row['cost_center_id']) && !in_array((int)$row['cost_center_id'], $existingCostCenters, true)) {
                $errors[] = "الصف $line: مركز الكلفة '{$row['cost_center_id']}' غير موجود";
            }
            if (!empty($row['account_id']) && !in_array((int)$row['account_id'], $existingAccounts, true)) {
                // لا نُوقف الاستيراد؛ سيتم تجاهله لاحقاً
            }

            $processedRows[] = [
                'line' => $line, 'row' => $row, 'existing' => null, 'branchId' => $branchId,
                'departmentId' => $departmentId, 'jobId' => $jobId, 'roleId' => $roleId,
            ];
        }

        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/hr/employees');
        }

        Database::beginTransaction();
        try {
            $createdCount = 0;
            $updatedCount = 0;
            foreach ($processedRows as $item) {
                $row = $item['row'];
                $branchId = $item['branchId'];

                if ($item['existing']) {
                    $existing = $item['existing'];
                    $employeeId = (int)$existing['id'];

                    $updateFields = [];
                    $updateParams = [];

                    // تحديث فرع الموظف
                    if ($branchId) {
                        $updateFields[] = 'branch_id = ?';
                        $updateParams[] = $branchId;
                    }

                    // تحديث الدور إذا مُدخل
                    $roleId = null;
                    $adminLevel = (int)$existing['admin_level'];
                    if (!empty($row['role_id'])) {
                        $roleId = (int)$row['role_id'];
                    } elseif (!empty($row['role'])) {
                        $roleName = trim((string)$row['role']);
                        $roleId = $roleMap[$roleName] ?? $roleMap[mb_strtolower($roleName, 'UTF-8')] ?? null;
                    }
                    if (!$roleId && !empty($row['job'])) {
                        $departmentId = null;
                        if (!empty($row['department_id'])) {
                            $departmentId = (int)$row['department_id'];
                        } elseif (!empty($row['department'])) {
                            $deptName = trim((string)$row['department']);
                            $departmentId = $deptMap[$deptName] ?? $deptNormalized[mb_strtolower($deptName, 'UTF-8')] ?? null;
                        }
                        if (!$departmentId && $existing['department_id']) {
                            $departmentId = (int)$existing['department_id'];
                        }
                        $jobId = null;
                        if ($departmentId) {
                            $key = mb_strtolower(trim((string)$row['job']) . '|' . $departmentId, 'UTF-8');
                            $jobId = $jobMap[$key] ?? null;
                        }
                        if (!$jobId) {
                            $jobTitle = mb_strtolower(trim((string)$row['job']), 'UTF-8');
                            foreach ($jobs as $j) {
                                if (mb_strtolower(trim($j['title']), 'UTF-8') === $jobTitle) {
                                    $jobId = (int)$j['id'];
                                    break;
                                }
                            }
                        }
                        if ($jobId) {
                            foreach ($jobs as $j) {
                                if ((int)$j['id'] === $jobId) {
                                    $roleId = $j['role_id'] ? (int)$j['role_id'] : null;
                                    break;
                                }
                            }
                        }
                    }
                    if ($roleId) {
                        $role = Database::query("SELECT level FROM roles WHERE id = ?", [$roleId])->fetch();
                        if ($role) {
                            $adminLevel = (int)($role['level'] ?? 0);
                            $updateFields[] = 'admin_level = ?';
                            $updateParams[] = $adminLevel;
                        }
                    }

                    // تحديث يوم العطلة الأسبوعية إذا مُدخل
                    if (isset($row['weekly_off_day']) && $row['weekly_off_day'] !== '') {
                        $wod = (int)$row['weekly_off_day'];
                        if ($wod >= 0 && $wod <= 6) {
                            $updateFields[] = 'weekly_off_day = ?';
                            $updateParams[] = $wod;
                        }
                    }

                    if ($updateFields) {
                        $updateParams[] = $employeeId;
                        Database::query(
                            "UPDATE employees SET " . implode(', ', $updateFields) . " WHERE id = ?",
                            $updateParams
                        );
                    }

                    // تحديث/إنشاء حساب الدخول
                    $username = !empty($row['username']) ? trim((string)$row['username']) : null;
                    $password = !empty($row['password']) ? trim((string)$row['password']) : null;
                    if ($username || $password || $roleId) {
                        if ((int)$existing['user_id'] > 0) {
                            HrService::syncExistingUserAccount(
                                (int)$existing['user_id'],
                                $adminLevel,
                                $existing['full_name'],
                                $branchId ?: $existing['branch_id'],
                                $roleId,
                                $username,
                                $password
                            );
                        } else {
                            HrService::syncEmployeeUserAccount(
                                $employeeId,
                                $adminLevel,
                                $existing['full_name'],
                                $existing['emp_code'],
                                $branchId ?: $existing['branch_id'],
                                $roleId,
                                $username,
                                $password
                            );
                        }
                    }

                    Helpers::queueSync('employees', $employeeId, 'update', [
                        'emp_code' => $existing['emp_code'], 'full_name' => $existing['full_name'],
                    ]);
                    $updatedCount++;
                    continue;
                }

                // إنشاء موظف جديد
                $code = !empty($row['emp_code']) ? trim((string)$row['emp_code']) : Helpers::docNumber('EMP', 'employees', 'emp_code');

                $departmentId = $item['departmentId'];
                $jobId = $item['jobId'];
                $roleId = $item['roleId'];

                $managerId = !empty($row['manager_id']) ? (int)$row['manager_id'] : null;
                $costCenterId = !empty($row['cost_center_id']) ? (int)$row['cost_center_id'] : null;
                $accountId = !empty($row['account_id']) && in_array((int)$row['account_id'], $existingAccounts, true) ? (int)$row['account_id'] : null;
                $status = trim((string)($row['status'] ?? 'active'));
                if (!in_array($status, ['active', 'vacation', 'suspended', 'terminated', 'resigned'], true)) {
                    $status = 'active';
                }

                $departmentName = '';
                $jobTitle = '';
                if ($departmentId) {
                    $d = Database::query("SELECT name FROM departments WHERE id = ?", [$departmentId])->fetch();
                    $departmentName = $d['name'] ?? '';
                }
                if ($jobId) {
                    $j = Database::query("SELECT title FROM jobs WHERE id = ?", [$jobId])->fetch();
                    $jobTitle = $j['title'] ?? '';
                }

                $adminLevel = 0;
                if ($roleId) {
                    $role = Database::query("SELECT level FROM roles WHERE id = ?", [$roleId])->fetch();
                    $adminLevel = (int)($role['level'] ?? 0);
                }

                $nationality = !empty($row['nationality']) ? trim((string)$row['nationality']) : null;
                $residence = !empty($row['residence']) ? trim((string)$row['residence']) : null;

                $hireDate = ImportHelper::parseExcelDate($row['hire_date'] ?? null) ?: date('Y-m-d');
                $workStartTime = ImportHelper::parseExcelTime($row['work_start_time'] ?? null) ?: null;
                $workEndTime = ImportHelper::parseExcelTime($row['work_end_time'] ?? null) ?: null;
                $weeklyOffDay = (isset($row['weekly_off_day']) && $row['weekly_off_day'] !== '') ? (int)$row['weekly_off_day'] : null;
                if ($weeklyOffDay !== null && ($weeklyOffDay < 0 || $weeklyOffDay > 6)) {
                    $weeklyOffDay = null;
                }

                Database::query(
                    "INSERT INTO employees (emp_code, full_name, phone, nationality, residence, position, department, department_id, job_id, manager_id,
                     admin_level, base_salary, hire_date, work_start_time, work_end_time, weekly_off_day, branch_id, cost_center_id, account_id, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [$code, trim((string)$row['full_name']), trim((string)$row['phone']),
                     $nationality, $residence,
                     $jobTitle, $departmentName,
                     $departmentId, $jobId, $managerId,
                     $adminLevel, (float)($row['base_salary'] ?? 0),
                     $hireDate, $workStartTime, $workEndTime, $weeklyOffDay,
                     $branchId, $costCenterId, $accountId, $status]
                );
                $employeeId = (int)Database::lastInsertId();

                // إنشاء/ربط حساب الدخول للموظف الجديد
                $username = !empty($row['username']) ? trim((string)$row['username']) : null;
                $password = !empty($row['password']) ? trim((string)$row['password']) : null;
                if ($username || $password || $adminLevel > 0) {
                    HrService::syncEmployeeUserAccount(
                        $employeeId, $adminLevel, trim((string)$row['full_name']), $code,
                        $branchId, $roleId, $username, $password
                    );
                }

                HrService::initLeaveBalances($employeeId);
                PttService::syncEmployeeChannels($employeeId);
                Helpers::queueSync('employees', $employeeId, 'insert', [
                    'emp_code' => $code, 'full_name' => trim((string)$row['full_name']),
                ]);
                $createdCount++;
            }
            Database::commit();
            Helpers::audit('استيراد موظفين', 'employees', 0, "created:$createdCount, updated:$updatedCount");
            $this->flash('success', "تم استيراد $createdCount موظف وتحديث $updatedCount موظف");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء الحفظ: ' . $e->getMessage());
        }
        Response::redirect('/hr/employees');
    }

    public function evaluations(): void
    {
        if (!Auth::can('hr.evaluation.view') && !Auth::can('hr.evaluation.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/dashboard');
        }
        $period = (string)($_GET['period'] ?? date('Y-m'));
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $where = '';
        $params = [];
        if ($employeeId) {
            $where = "WHERE e.employee_id = ?";
            $params[] = $employeeId;
        }
        $evaluations = Database::query(
            "SELECT e.*, emp.full_name, emp.department, j.title AS job_title, ev.full_name AS evaluator_name
             FROM evaluations e
             JOIN employees emp ON emp.id = e.employee_id
             LEFT JOIN jobs j ON j.id = emp.job_id
             LEFT JOIN users ev ON ev.id = e.evaluated_by
             $where ORDER BY e.id DESC LIMIT 100",
            $params
        )->fetchAll();
        $employees = Database::query(
            "SELECT e.id, e.full_name, e.job_id, j.title AS job_title
             FROM employees e LEFT JOIN jobs j ON j.id = e.job_id
             WHERE e.file_status='open' ORDER BY e.full_name"
        )->fetchAll();
        $kpis = Database::query(
            "SELECT * FROM (
                SELECT pk.*, GROUP_CONCAT(j.id ORDER BY j.title) AS job_ids,
                        GROUP_CONCAT(j.title ORDER BY j.title SEPARATOR '، ') AS job_titles
                 FROM performance_kpis pk
                 LEFT JOIN performance_kpi_jobs pkj ON pkj.kpi_id = pk.id
                 LEFT JOIN jobs j ON j.id = pkj.job_id
                 WHERE pk.is_active=1
                 GROUP BY pk.id
             ) t
             ORDER BY t.job_titles IS NULL, t.id"
        )->fetchAll();
        $this->view('hr/evaluations', [
            'title' => 'تقييم أداء الموظفين',
            'evaluations' => $evaluations, 'employees' => $employees, 'kpis' => $kpis, 'period' => $period, 'employee_id' => $employeeId,
        ]);
    }

    public function storeEvaluation(): void
    {
        if (!Auth::can('hr.evaluation.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/evaluations');
        }
        $employeeId = (int)$this->input('employee_id');
        if (!HrService::isEmployeeFileOpen($employeeId)) {
            $this->flash('danger', 'ملف الموظف مغلق أو مأرشف، لا يمكن إضافة تقييم');
            Response::redirect('/hr/evaluations');
        }
        $period = (string)$this->input('period', date('Y-m'));
        $type = (string)$this->input('evaluation_type', 'monthly');
        $kpiIds = (array)$this->input('kpi_id', []);
        $scores = (array)$this->input('score', []);
        $weights = (array)$this->input('weight', []);
        $notes = (array)$this->input('kpi_notes', []);
        $applicableKpiIds = HrService::getApplicableKpiIds($employeeId);
        $kpiScores = [];
        foreach ($kpiIds as $i => $kpiId) {
            if (!empty($kpiId) && in_array((int)$kpiId, $applicableKpiIds, true)) {
                $kpiScores[] = [
                    'kpi_id' => (int)$kpiId,
                    'score' => (float)($scores[$i] ?? 0),
                    'weight' => (float)($weights[$i] ?? 0),
                    'notes' => (string)($notes[$i] ?? ''),
                ];
            }
        }
        $goalIds = (array)$this->input('goal_id', []);
        $goalRates = (array)$this->input('goal_rate', []);
        $goalNotes = (array)$this->input('goal_notes', []);
        $goalRatings = [];
        foreach ($goalIds as $i => $goalId) {
            if (!empty($goalId)) {
                $goalRatings[] = [
                    'goal_id' => (int)$goalId,
                    'achievement_rate' => (float)($goalRates[$i] ?? 0),
                    'notes' => (string)($goalNotes[$i] ?? ''),
                ];
            }
        }
        try {
            HrService::createEvaluation(
                $employeeId, $period, $type, $kpiScores,
                (string)$this->input('notes'), Auth::id(),
                $this->input('manager_score') !== '' ? (float)$this->input('manager_score') : null,
                $this->input('admin_score') !== '' ? (float)$this->input('admin_score') : null,
                $this->input('self_score') !== '' ? (float)$this->input('self_score') : null,
                (string)$this->input('manager_notes'), (string)$this->input('admin_notes'),
                $goalRatings
            );
            $this->flash('success', 'تم إنشاء التقييم');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/hr/evaluations');
    }

    public function showEvaluation(string $id): void
    {
        if (!Auth::can('hr.evaluation.view') && !Auth::can('hr.evaluation.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/evaluations');
        }
        $eval = Database::query(
            "SELECT e.*, emp.full_name, emp.department, emp.job_title, emp.salary, ev.full_name AS evaluator_name
             FROM evaluations e
             JOIN employees emp ON emp.id = e.employee_id
             LEFT JOIN users ev ON ev.id = e.evaluated_by
             WHERE e.id = ?",
            [(int)$id]
        )->fetch();
        if (!$eval) {
            $this->flash('danger', 'التقييم غير موجود');
            Response::redirect('/hr/evaluations');
        }
        $items = HrService::getEvaluationItems((int)$id);
        $goals = HrService::getEvaluationGoals((int)$id);
        $this->view('hr/evaluations_show', [
            'title' => 'تفاصيل تقييم الأداء',
            'eval' => $eval, 'items' => $items, 'goals' => $goals,
        ]);
    }

    public function finalizeEvaluation(string $id): void
    {
        if (!Auth::can('hr.evaluation.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/evaluations');
        }
        $bonusAmount = (float)$this->input('bonus_amount', 0);
        $raiseAmount = (float)$this->input('raise_amount', 0);
        $recommendation = (string)$this->input('recommendation', 'none');
        HrService::finalizeEvaluation(
            (int)$id, Auth::id(),
            $bonusAmount > 0 ? $bonusAmount : null,
            $raiseAmount > 0 ? $raiseAmount : null,
            in_array($recommendation, ['none','bonus','raise','promotion','warning'], true) ? $recommendation : 'none'
        );
        $msg = 'تم اعتماد التقييم';
        if ($bonusAmount > 0) { $msg .= ' وإنشاء المكافأة'; }
        if ($raiseAmount > 0) { $msg .= ($bonusAmount > 0 ? ' والزيادة' : ' وإنشاء الزيادة'); }
        $this->flash('success', $msg);
        Response::redirect('/hr/evaluations');
    }

    public function employeeGoals(): void
    {
        if (!Auth::can('hr.goals.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/evaluations');
        }
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $goals = $employeeId ? HrService::getEmployeeGoals($employeeId) : [];
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $this->view('hr/employee_goals', [
            'title' => 'أهداف الموظفين', 'goals' => $goals, 'employees' => $employees, 'employee_id' => $employeeId,
        ]);
    }

    public function storeGoal(): void
    {
        if (!Auth::can('hr.goals.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/goals');
        }
        $employeeId = (int)$this->input('employee_id');
        HrService::createGoal(
            $employeeId,
            trim((string)$this->input('title')),
            (string)$this->input('description'),
            $this->input('target_value') !== '' ? (float)$this->input('target_value') : null,
            (string)$this->input('unit'),
            (string)$this->input('due_date'),
            Auth::id()
        );
        $this->flash('success', 'تم إضافة الهدف');
        Response::redirect('/hr/goals?employee_id=' . $employeeId);
    }

    public function updateGoal(string $id): void
    {
        if (!Auth::can('hr.goals.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/goals');
        }
        $data = [];
        foreach (['title','description','target_value','actual_value','unit','due_date','status'] as $col) {
            if ($this->input($col) !== '') {
                $data[$col] = in_array($col, ['target_value','actual_value']) ? (float)$this->input($col) : (string)$this->input($col);
            }
        }
        HrService::updateGoal((int)$id, $data);
        $this->flash('success', 'تم تحديث الهدف');
        Response::redirect('/hr/goals');
    }

    public function deleteGoal(string $id): void
    {
        if (!Auth::can('hr.goals.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/goals');
        }
        HrService::deleteGoal((int)$id);
        $this->flash('success', 'تم حذف الهدف');
        Response::redirect('/hr/goals');
    }

    public function hrAccountingSettings(): void
    {
        if (!Auth::can('hr.accounting.settings')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/payroll');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $keys = [
                'hr_account_salary', 'hr_account_cash', 'hr_account_advances',
                'hr_account_bonuses', 'hr_account_deductions', 'hr_account_salary_payable',
                'hr_account_tax_payable', 'hr_account_insurance_payable',
                'hr_account_social_security_payable', 'hr_account_loadings_payable',
                'hr_account_tips_payable', 'hr_account_security_deposit_payable',
                'hr_account_travel_tickets', 'hr_account_custody_receivable',
                'hr_account_custody_penalty', 'hr_account_inventory',
                'custody_alert_days_before',
            ];
            foreach ($keys as $k) {
                Database::query(
                    "INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
                    [$k, (string)$this->input($k)]
                );
            }
            $this->flash('success', 'تم حفظ إعدادات المحاسبة');
            Response::redirect('/hr/accounting-settings');
        }
        $accounts = Database::query("SELECT code, name FROM accounts WHERE is_active=1 AND is_leaf=1 ORDER BY code")->fetchAll();
        $settings = [];
        foreach (Database::query("SELECT skey, svalue FROM settings WHERE skey LIKE 'hr_account_%'")->fetchAll() as $s) {
            $settings[$s['skey']] = $s['svalue'];
        }
        $this->view('hr/accounting_settings', [
            'title' => 'إعدادات المحاسبة للمورد البشري', 'accounts' => $accounts, 'settings' => $settings,
        ]);
    }

    public function kpis(): void
    {
        if (!Auth::can('hr.evaluation.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/evaluations');
        }
        $kpis = Database::query(
            "SELECT * FROM (
                SELECT pk.*, GROUP_CONCAT(j.id ORDER BY j.title) AS job_ids,
                        GROUP_CONCAT(j.title ORDER BY j.title SEPARATOR '، ') AS job_titles
                 FROM performance_kpis pk
                 LEFT JOIN performance_kpi_jobs pkj ON pkj.kpi_id = pk.id
                 LEFT JOIN jobs j ON j.id = pkj.job_id
                 GROUP BY pk.id
             ) t
             ORDER BY t.is_active DESC, t.job_titles IS NULL, t.job_titles, t.id DESC"
        )->fetchAll();
        $jobs = Database::query("SELECT id, title, department_id FROM jobs WHERE is_active=1 ORDER BY title")->fetchAll();
        $departments = Database::query("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
        $this->view('hr/kpis', ['title' => 'مؤشرات الأداء', 'kpis' => $kpis, 'jobs' => $jobs, 'departments' => $departments]);
    }

    /** استخراج قائمة معرّفات وظائف صحيحة من مُدخل قد يكون مصفوفة أو قيمة واحدة أو فارغًا */
    private function inputJobIds(): array
    {
        $raw = $this->input('job_id', []);
        if (!is_array($raw)) { $raw = ($raw === '' || $raw === null) ? [] : [$raw]; }
        return array_values(array_unique(array_filter(array_map('intval', $raw))));
    }

    public function storeKpi(): void
    {
        if (!Auth::can('hr.evaluation.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/kpis');
        }
        $name = trim((string)$this->input('name'));
        $weight = max(0, min(100, (float)$this->input('weight', 0)));
        $jobIds = $this->inputJobIds();
        if (!$name) {
            $this->flash('danger', 'اسم المؤشر مطلوب');
            Response::redirect('/hr/kpis');
        }
        Database::query(
            "INSERT INTO performance_kpis (name, description, weight) VALUES (?,?,?)",
            [$name, (string)$this->input('description'), $weight]
        );
        $kpiId = (int)Database::lastInsertId();
        foreach ($jobIds as $jobId) {
            Database::query("INSERT IGNORE INTO performance_kpi_jobs (kpi_id, job_id) VALUES (?,?)", [$kpiId, $jobId]);
        }
        $this->flash('success', 'تم إضافة المؤشر');
        Response::redirect('/hr/kpis');
    }

    public function updateKpi(string $id): void
    {
        if (!Auth::can('hr.evaluation.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/kpis');
        }
        $name = trim((string)$this->input('name'));
        $weight = max(0, min(100, (float)$this->input('weight', 0)));
        $jobIds = $this->inputJobIds();
        if (!$name) {
            $this->flash('danger', 'اسم المؤشر مطلوب');
            Response::redirect('/hr/kpis');
        }
        Database::query(
            "UPDATE performance_kpis SET name = ?, description = ?, weight = ? WHERE id = ?",
            [$name, (string)$this->input('description'), $weight, (int)$id]
        );
        Database::query("DELETE FROM performance_kpi_jobs WHERE kpi_id = ?", [(int)$id]);
        foreach ($jobIds as $jobId) {
            Database::query("INSERT IGNORE INTO performance_kpi_jobs (kpi_id, job_id) VALUES (?,?)", [(int)$id, $jobId]);
        }
        $this->flash('success', 'تم تحديث المؤشر');
        Response::redirect('/hr/kpis');
    }

    public function toggleKpi(string $id): void
    {
        if (!Auth::can('hr.evaluation.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/kpis');
        }
        Database::query(
            "UPDATE performance_kpis SET is_active = NOT is_active WHERE id = ?",
            [(int)$id]
        );
        $this->flash('success', 'تم تحديث حالة المؤشر');
        Response::redirect('/hr/kpis');
    }

    public function custody(): void
    {
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $status = (string)($_GET['status'] ?? 'active');
        $sql = "SELECT ec.*, e.full_name AS employee_name, e.emp_code, i.name AS item_name, i.barcode, w.name AS warehouse_name, u.full_name AS issued_by_name
                FROM employee_custody ec
                JOIN employees e ON e.id = ec.employee_id
                JOIN items i ON i.id = ec.item_id
                LEFT JOIN warehouses w ON w.id = ec.warehouse_id
                LEFT JOIN users u ON u.id = ec.issued_by
                WHERE 1=1";
        $params = [];
        if ($employeeId > 0) {
            $sql .= " AND ec.employee_id = ?";
            $params[] = $employeeId;
        }
        if ($status !== '') {
            $sql .= " AND ec.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY ec.issued_at DESC LIMIT 300";
        $custody = Database::query($sql, $params)->fetchAll();
        // حساب الأيام المتبقية على انتهاء العهدة الاستهلاكية
        foreach ($custody as &$c) {
            $c['days_to_expiry'] = null;
            if (!empty($c['expiry_date']) && $c['status'] === 'active') {
                $today = new \DateTime(date('Y-m-d'));
                $expiry = new \DateTime($c['expiry_date']);
                $c['days_to_expiry'] = (int)$today->diff($expiry)->format('%r%a');
            }
        }
        unset($c);

        $employees = Database::query("SELECT id, full_name, emp_code FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $items = Database::query(
            "SELECT i.id, i.name, i.barcode, c.type AS category_type, i.cost_price, i.unit_id FROM items i
             JOIN item_categories c ON c.id = i.category_id
             WHERE c.type IN ('asset','tool','clothing') AND i.is_active = 1 AND i.custody_status != 'in_custody'
             ORDER BY i.name"
        )->fetchAll();
        $warehouses = Database::query("SELECT * FROM warehouses WHERE is_active=1 ORDER BY name")->fetchAll();

        $this->view('hr/custody', [
            'title' => 'إدارة العهد',
            'custody' => $custody, 'employees' => $employees, 'items' => $items,
            'warehouses' => $warehouses, 'filterEmployee' => $employeeId, 'filterStatus' => $status,
        ]);
    }

    public function employeeCustody(string $id): void
    {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [(int)$id])->fetch();
        if (!$employee) {
            Response::redirect('/hr/employees');
        }
        $custody = HrService::getEmployeeCustody((int)$id);
        $this->view('hr/employee_custody', [
            'title' => 'عهد الموظف: ' . $employee['full_name'],
            'employee' => $employee, 'custody' => $custody,
        ]);
    }

    public function storeCustody(): void
    {
        if (!Auth::can('hr.custody.manage')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/custody');
        }
        try {
            HrService::issueCustody(
                (int)$this->input('employee_id'),
                (int)$this->input('item_id'),
                (int)$this->input('warehouse_id') ?: null,
                (string)$this->input('serial_number'),
                (float)$this->input('qty', 1),
                (float)$this->input('unit_cost', 0),
                (string)$this->input('due_date') ?: null,
                (string)$this->input('type', 'fixed'),
                (string)$this->input('expiry_date') ?: null,
                (string)$this->input('notes'),
                Auth::id()
            );
            $this->flash('success', 'تم تسليم العهدة وتحديث المخزن والحسابات');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/hr/custody');
    }

    public function returnCustody(string $id): void
    {
        if (!Auth::can('hr.custody.manage') && !Auth::can('hr.custody.clearance')) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/hr/custody');
        }
        $ok = HrService::returnCustody(
            (int)$id,
            (string)$this->input('status', 'returned'),
            (string)$this->input('condition'),
            (string)$this->input('notes'),
            Auth::id()
        );
        if ($ok) {
            $this->flash('success', 'تم تحديث حالة العهدة');
        } else {
            $this->flash('danger', 'لم يتم العثور على العهدة أو أنها غير مفتوحة');
        }
        Response::redirect('/hr/custody');
    }

    public function custodyAlerts(): void
    {
        $date = (string)($_GET['date'] ?? date('Y-m-d'));
        $created = [];
        if (isset($_GET['run'])) {
            $created = HrService::checkCustodyAlerts($date);
        }
        $alerts = Database::query(
            "SELECT a.*, e.full_name, i.name AS item_name, ec.due_date, ec.expiry_date, ec.status AS custody_status
             FROM employee_custody_alerts a
             JOIN employee_custody ec ON ec.id = a.custody_id
             JOIN employees e ON e.id = a.employee_id
             JOIN items i ON i.id = ec.item_id
             WHERE a.alert_date = ? ORDER BY a.created_at DESC",
            [$date]
        )->fetchAll();
        $this->view('hr/custody_alerts', [
            'title' => 'تنبيهات العهد', 'alerts' => $alerts, 'date' => $date, 'created' => $created,
        ]);
    }

    public function runCustodyAlerts(): void
    {
        $created = HrService::checkCustodyAlerts(date('Y-m-d'));
        Response::json(['ok' => true, 'created' => count($created), 'alerts' => $created]);
    }

    public function acknowledgeCustodyAlert(string $id): void
    {
        if (!Auth::can('hr.custody.manage')) {
            Response::json(['ok' => false]);
            return;
        }
        Database::query(
            "UPDATE employee_custody_alerts SET is_acknowledged = 1, acknowledged_by = ?, acknowledged_at = NOW() WHERE id = ?",
            [Auth::id(), (int)$id]
        );
        Response::json(['ok' => true]);
    }

    private static function requestTypeLabel(string $type): string
    {
        return [
            'leave' => 'إجازة',
            'exit' => 'إذن خروج',
            'delay' => 'إذن تأخير',
            'work_resumption' => 'مباشرة عمل',
        ][$type] ?? $type;
    }

    private function calculateResignationBreakdown(int $employeeId, float $baseSalary, float $workedDays): array
    {
        $dailySalary = round($baseSalary / 30, 2);
        $absenceDays = max(0, 30 - $workedDays);
        $absenceDeduction = round($absenceDays * $dailySalary, 2);

        $advancesRow = Database::query(
            "SELECT COALESCE(SUM(remaining_amount), 0) AS total FROM salary_advances WHERE employee_id = ? AND status IN ('paid','settled')",
            [$employeeId]
        )->fetch();
        $advancesAmount = (float)($advancesRow['total'] ?? 0);

        $period = date('Y-m');
        $deductionsRow = Database::query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM hr_adjustments WHERE employee_id = ? AND adjustment_type = 'deduction' AND status = 'approved' AND (period = ? OR effective_date BETWEEN ? AND ?)",
            [$employeeId, $period, date('Y-m-01'), date('Y-m-t')]
        )->fetch();
        $otherDeductions = (float)($deductionsRow['total'] ?? 0);

        $salaryAmount = round($baseSalary, 2);
        $overtimeAmount = 0.0;
        $housingDeduction = 0.0;
        $totalDue = round($salaryAmount - $absenceDeduction + $overtimeAmount - $advancesAmount - $housingDeduction - $otherDeductions, 2);

        return [
            'salary_amount' => $salaryAmount,
            'absence_days' => $absenceDays,
            'absence_deduction' => $absenceDeduction,
            'overtime_amount' => $overtimeAmount,
            'advances_amount' => $advancesAmount,
            'housing_deduction' => $housingDeduction,
            'other_deductions' => $otherDeductions,
            'total_due' => $totalDue,
        ];
    }

    private function saveResignationRecord(int $employeeId, float $baseSalary): void
    {
        $resignationDate = $this->input('resignation_date') ?: date('Y-m-d');
        $daysWorked = (float)$this->input('days_worked', 0);
        $dailySalary = (float)$this->input('daily_salary', round($baseSalary / 30, 2));
        $breakdown = [
            'salary_amount' => (float)$this->input('salary_amount', $baseSalary),
            'absence_days' => (float)$this->input('absence_days', 0),
            'absence_deduction' => (float)$this->input('absence_deduction', 0),
            'overtime_amount' => (float)$this->input('overtime_amount', 0),
            'advances_amount' => (float)$this->input('advances_amount', 0),
            'housing_deduction' => (float)$this->input('housing_deduction', 0),
            'other_deductions' => (float)$this->input('other_deductions', 0),
        ];
        $totalDue = round(
            $breakdown['salary_amount'] - $breakdown['absence_deduction'] + $breakdown['overtime_amount']
            - $breakdown['advances_amount'] - $breakdown['housing_deduction'] - $breakdown['other_deductions'],
            2
        );
        $reason = (string)$this->input('resignation_reason');
        $reason = (string)$this->input('resignation_reason');
        $notes = (string)$this->input('resignation_notes');
        $returnedCustodyIds = array_map('intval', (array)$this->input('custody_returned', []));
        $allCustody = HrService::getEmployeeCustody($employeeId, 'active');
        $custodySummary = [];
        $userId = (int)(Auth::id() ?? 0);
        $now = date('Y-m-d H:i:s');

        foreach ($allCustody as $c) {
            $isReturned = in_array((int)$c['id'], $returnedCustodyIds, true);
            $custodySummary[] = [
                'id' => (int)$c['id'],
                'item_name' => $c['item_name'],
                'barcode' => $c['barcode'] ?? null,
                'qty' => $c['qty'],
                'returned' => $isReturned,
            ];
            if ($isReturned) {
                Database::query(
                    "UPDATE employee_custody SET status = 'returned', returned_at = ?, returned_by = ? WHERE id = ?",
                    [$now, $userId, (int)$c['id']]
                );
            }
        }

        $existing = Database::query(
            "SELECT id FROM resignations WHERE employee_id = ? ORDER BY id DESC LIMIT 1",
            [$employeeId]
        )->fetch();
        if ($existing) {
            Database::query(
                "UPDATE resignations SET resignation_date = ?, reason = ?, days_worked = ?, daily_salary = ?, total_due = ?, calculation_breakdown_json = ?, custody_summary_json = ?, notes = ?, created_by = ? WHERE id = ?",
                [$resignationDate, $reason, $daysWorked, $dailySalary, $totalDue, json_encode($breakdown), json_encode($custodySummary), $notes, $userId, (int)$existing['id']]
            );
        } else {
            Database::query(
                "INSERT INTO resignations (employee_id, resignation_date, reason, days_worked, daily_salary, total_due, calculation_breakdown_json, custody_summary_json, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$employeeId, $resignationDate, $reason, $daysWorked, $dailySalary, $totalDue, json_encode($breakdown), json_encode($custodySummary), $notes, $userId]
            );
        }
    }

    /**
     * معرف الموظف المرتبط بالمستخدم الحالي
     */
    private function currentEmployeeId(): ?int
    {
        $userId = (int)(Auth::id() ?? 0);
        if (!$userId) return null;
        $row = Database::query("SELECT id FROM employees WHERE user_id = ? LIMIT 1", [$userId])->fetch();
        return $row ? (int)$row['id'] : null;
    }

    /**
     * مستوى الدور الحالي (role level)
     */
    private function currentRoleLevel(): int
    {
        $roleId = (int)($_SESSION['role_id'] ?? 0);
        if (!$roleId) return 0;
        return (int)(Database::query("SELECT level FROM roles WHERE id = ?", [$roleId])->fetchColumn() ?? 0);
    }

    /**
     * التحقق من أن المستخدم الحالي يملك رتبة مدير قسم فما فوق
     */
    private function requireManager(): void
    {
        if (Auth::role() === 'مدير النظام') return;
        if ($this->currentRoleLevel() < 60) {
            Response::abort(403, 'هذه الميزة متاحة للمدراء فقط.');
        }
    }

    /**
     * هل يحق للمستخدم الحالي البت في طلب تعيين/إيقاف دوام؟
     */
    private function canDecideHrRequest(string $level): bool
    {
        if (Auth::role() === 'مدير النظام') return true;
        $roleLevel = $this->currentRoleLevel();
        if ($level === 'manager') {
            return $roleLevel >= 60;
        }
        if ($level === 'hr') {
            $roleName = Auth::role();
            return $roleLevel >= 60 && (str_contains($roleName, 'موارد بشرية') || str_contains($roleName, 'النظام'));
        }
        if ($level === 'finance') {
            $roleName = Auth::role();
            return $roleLevel >= 60 && (str_contains($roleName, 'مالي') || str_contains($roleName, 'محاسب') || str_contains($roleName, 'النظام'));
        }
        return false;
    }

    /**
     * تحديث حالة الطلب بناءً على موافقات المستويات الثلاثة
     */
    private function syncHrRequestStatus(string $table, int $id): void
    {
        $row = Database::query("SELECT * FROM $table WHERE id = ?", [$id])->fetch();
        if (!$row) return;
        $statuses = [$row['manager_status'], $row['hr_status'], $row['finance_status']];
        if (in_array('rejected', $statuses, true)) {
            $overall = 'rejected';
        } elseif ($statuses === ['approved', 'approved', 'approved']) {
            $overall = 'approved';
        } else {
            $overall = 'pending';
        }
        Database::query("UPDATE $table SET overall_status = ? WHERE id = ?", [$overall, $id]);
    }

    /**
     * عرض لوحة المدير: طلبات التعيين وإيقاف الدوام + عدد التابعين
     */
    public function managerRequests(): void
    {
        $this->requireManager();
        $employeeId = $this->currentEmployeeId();
        if (!$employeeId) {
            Response::abort(403, 'لم يتم ربط حساب المستخدم بملف موظف.');
        }
        $subordinatesCount = HrService::getSubordinateCount($employeeId);
        $subordinates = HrService::getSubordinates($employeeId);
        $hiring = Database::query(
            "SELECT h.*, d.name AS department_name, j.title AS job_title, req.full_name AS requester_name
             FROM hiring_requests h
             LEFT JOIN departments d ON d.id = h.department_id
             LEFT JOIN jobs j ON j.id = h.job_id
             LEFT JOIN employees req ON req.id = h.requester_id
             WHERE h.requester_id = ? ORDER BY h.id DESC",
            [$employeeId]
        )->fetchAll();
        $suspensions = Database::query(
            "SELECT s.*, e.full_name AS employee_name, req.full_name AS requester_name
             FROM suspension_requests s
             LEFT JOIN employees e ON e.id = s.employee_id
             LEFT JOIN employees req ON req.id = s.requester_id
             WHERE s.requester_id = ? ORDER BY s.id DESC",
            [$employeeId]
        )->fetchAll();
        $this->view('hr/manager_requests', [
            'title' => 'طلبات المدير',
            'subordinatesCount' => $subordinatesCount,
            'subordinates' => $subordinates,
            'hiring' => $hiring,
            'suspensions' => $suspensions,
        ]);
    }

    /**
     * نموذج إنشاء طلب تعيين
     */
    public function createHiringRequest(): void
    {
        $this->requireManager();
        $employeeId = $this->currentEmployeeId();
        if (!$employeeId) {
            Response::abort(403, 'لم يتم ربط حساب المستخدم بملف موظف.');
        }
        $departments = Database::query("SELECT * FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll();
        $jobs = Database::query(
            "SELECT j.*, d.name AS department_name FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE j.is_active = 1 ORDER BY j.title"
        )->fetchAll();
        $this->view('hr/hiring_request_form', [
            'title' => 'طلب تعيين موظف جديد',
            'departments' => $departments,
            'jobs' => $jobs,
        ]);
    }

    /**
     * حفظ طلب التعيين
     */
    public function storeHiringRequest(): void
    {
        $this->requireManager();
        $employeeId = $this->currentEmployeeId();
        if (!$employeeId) {
            Response::abort(403, 'لم يتم ربط حساب المستخدم بملف موظف.');
        }
        $departmentId = (int)$this->input('department_id', 0) ?: null;
        $jobId = (int)$this->input('job_id', 0) ?: null;
        $jobTitle = (string)$this->input('job_title');
        $departmentName = '';
        if ($departmentId) {
            $d = Database::query("SELECT name FROM departments WHERE id = ?", [$departmentId])->fetch();
            $departmentName = $d['name'] ?? '';
        }
        if ($jobId && !$jobTitle) {
            $j = Database::query("SELECT title FROM jobs WHERE id = ?", [$jobId])->fetch();
            $jobTitle = $j['title'] ?? '';
        }
        if (!$jobTitle) {
            $this->flash('danger', 'المسمى الوظيفي مطلوب.');
            Response::redirect('/hr/manager-requests/hiring/create');
        }
        $emp = Database::query("SELECT manager_id FROM employees WHERE id = ?", [$employeeId])->fetch();
        $managerId = (int)($emp['manager_id'] ?? 0) ?: null;
        $requestNo = Helpers::docNumber('HIR', 'hiring_requests', 'request_no');
        Database::query(
            "INSERT INTO hiring_requests
             (request_no, requester_id, manager_id, department_id, job_id, job_title, department_name, expected_salary, reason, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $requestNo, $employeeId, $managerId, $departmentId, $jobId, $jobTitle, $departmentName,
                (float)$this->input('expected_salary', 0), (string)$this->input('reason'), (string)$this->input('notes'),
                (int)(Auth::id() ?? 0)
            ]
        );
        $this->flash('success', 'تم إنشاء طلب التعيين بنجاح، ينتظر موافقة المدير المباشر.');
        Response::redirect('/hr/manager-requests');
    }

    /**
     * نموذج إنشاء طلب إيقاف دوام
     */
    public function createSuspensionRequest(): void
    {
        $this->requireManager();
        $employeeId = $this->currentEmployeeId();
        if (!$employeeId) {
            Response::abort(403, 'لم يتم ربط حساب المستخدم بملف موظف.');
        }
        $subordinates = HrService::getSubordinates($employeeId);
        $this->view('hr/suspension_request_form', [
            'title' => 'طلب إيقاف دوام موظف',
            'subordinates' => $subordinates,
        ]);
    }

    /**
     * حفظ طلب إيقاف الدوام
     */
    public function storeSuspensionRequest(): void
    {
        $this->requireManager();
        $employeeId = $this->currentEmployeeId();
        if (!$employeeId) {
            Response::abort(403, 'لم يتم ربط حساب المستخدم بملف موظف.');
        }
        $targetId = (int)$this->input('employee_id', 0);
        if (!$targetId) {
            $this->flash('danger', 'يجب اختيار الموظف.');
            Response::redirect('/hr/manager-requests/suspension/create');
        }
        $allowed = array_column(HrService::getSubordinates($employeeId), 'id');
        if (!in_array($targetId, $allowed, true)) {
            Response::abort(403, 'الموظف المحدد ليس من تابعيك المباشرين.');
        }
        $target = Database::query("SELECT manager_id FROM employees WHERE id = ?", [$targetId])->fetch();
        $managerId = (int)($target['manager_id'] ?? 0) ?: null;
        $requestNo = Helpers::docNumber('SUS', 'suspension_requests', 'request_no');
        Database::query(
            "INSERT INTO suspension_requests
             (request_no, requester_id, manager_id, employee_id, suspension_date, suspension_type, reason, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $requestNo, $employeeId, $managerId, $targetId, (string)$this->input('suspension_date'), (string)$this->input('suspension_type', 'temporary'),
                (string)$this->input('reason'), (string)$this->input('notes'), (int)(Auth::id() ?? 0)
            ]
        );
        $this->flash('success', 'تم إنشاء طلب إيقاف الدوام، ينتظر موافقة المدير المباشر.');
        Response::redirect('/hr/manager-requests');
    }

    /**
     * قائمة طلبات التعيين لـ HR/Finance/المدير العام
     */
    public function hiringRequests(): void
    {
        $this->requireManager();
        $requests = Database::query(
            "SELECT h.*, d.name AS department_name, j.title AS job_title, req.full_name AS requester_name
             FROM hiring_requests h
             LEFT JOIN departments d ON d.id = h.department_id
             LEFT JOIN jobs j ON j.id = h.job_id
             LEFT JOIN employees req ON req.id = h.requester_id
             ORDER BY h.id DESC LIMIT 500"
        )->fetchAll();
        $this->view('hr/hiring_requests', ['title' => 'طلبات التعيين', 'requests' => $requests]);
    }

    /**
     * قائمة طلبات إيقاف الدوام لـ HR/Finance/المدير العام
     */
    public function suspensionRequests(): void
    {
        $this->requireManager();
        $requests = Database::query(
            "SELECT s.*, e.full_name AS employee_name, req.full_name AS requester_name
             FROM suspension_requests s
             LEFT JOIN employees e ON e.id = s.employee_id
             LEFT JOIN employees req ON req.id = s.requester_id
             ORDER BY s.id DESC LIMIT 500"
        )->fetchAll();
        $this->view('hr/suspension_requests', ['title' => 'طلبات إيقاف الدوام', 'requests' => $requests]);
    }

    /**
     * عرض تفاصيل طلب التعيين
     */
    public function showHiringRequest(string $id): void
    {
        $this->requireManager();
        $request = Database::query(
            "SELECT h.*, d.name AS department_name, j.title AS job_title,
                    req.full_name AS requester_name, man.full_name AS manager_name,
                    hu.full_name AS hr_name, fu.full_name AS finance_name
             FROM hiring_requests h
             LEFT JOIN departments d ON d.id = h.department_id
             LEFT JOIN jobs j ON j.id = h.job_id
             LEFT JOIN employees req ON req.id = h.requester_id
             LEFT JOIN employees man ON man.id = h.manager_id
             LEFT JOIN users hu ON hu.id = h.hr_approved_by
             LEFT JOIN users fu ON fu.id = h.finance_approved_by
             WHERE h.id = ?",
            [(int)$id]
        )->fetch();
        if (!$request) {
            Response::redirect('/hr/hiring-requests');
        }
        $this->view('hr/hiring_request_show', ['title' => 'طلب تعيين #' . $request['request_no'], 'request' => $request]);
    }

    /**
     * عرض تفاصيل طلب إيقاف الدوام
     */
    public function showSuspensionRequest(string $id): void
    {
        $this->requireManager();
        $request = Database::query(
            "SELECT s.*, e.full_name AS employee_name, e.emp_code AS employee_code,
                    req.full_name AS requester_name, man.full_name AS manager_name,
                    hu.full_name AS hr_name, fu.full_name AS finance_name
             FROM suspension_requests s
             LEFT JOIN employees e ON e.id = s.employee_id
             LEFT JOIN employees req ON req.id = s.requester_id
             LEFT JOIN employees man ON man.id = s.manager_id
             LEFT JOIN users hu ON hu.id = s.hr_approved_by
             LEFT JOIN users fu ON fu.id = s.finance_approved_by
             WHERE s.id = ?",
            [(int)$id]
        )->fetch();
        if (!$request) {
            Response::redirect('/hr/suspension-requests');
        }
        $this->view('hr/suspension_request_show', ['title' => 'طلب إيقاف دوام #' . $request['request_no'], 'request' => $request]);
    }

    /**
     * اتخاذ قرار على طلب التعيين
     */
    public function decideHiringRequest(string $id): void
    {
        $level = (string)$this->input('level');
        if (!in_array($level, ['manager', 'hr', 'finance'], true)) {
            Response::abort(400, 'مستوى الموافقة غير صحيح.');
        }
        if (!$this->canDecideHrRequest($level)) {
            Response::abort(403, 'ليس لديك صلاحية الموافقة على هذا المستوى.');
        }
        $decision = (string)$this->input('decision');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            Response::abort(400, 'القرار غير صحيح.');
        }
        $request = Database::query("SELECT * FROM hiring_requests WHERE id = ?", [(int)$id])->fetch();
        if (!$request || $request['overall_status'] !== 'pending') {
            Response::abort(400, 'الطلب غير موجود أو تم البت فيه مسبقاً.');
        }
        // الترتيب: manager -> hr -> finance
        if ($level === 'hr' && $request['manager_status'] !== 'approved') {
            $this->flash('danger', 'يجب موافقة المدير المباشر أولاً.');
            Response::redirect('/hr/hiring-requests/' . $id);
        }
        if ($level === 'finance' && $request['hr_status'] !== 'approved') {
            $this->flash('danger', 'يجب موافقة الموارد البشرية أولاً.');
            Response::redirect('/hr/hiring-requests/' . $id);
        }
        $column = $level . '_status';
        $byColumn = $level . '_approved_by';
        $notesColumn = $level . '_notes';
        $atColumn = $level . '_at';
        Database::query(
            "UPDATE hiring_requests SET $column = ?, $byColumn = ?, $notesColumn = ?, $atColumn = NOW() WHERE id = ?",
            [$decision, (int)(Auth::id() ?? 0), (string)$this->input('notes'), (int)$id]
        );
        $this->syncHrRequestStatus('hiring_requests', (int)$id);
        if ($decision === 'approved' && $level === 'finance') {
            $this->flash('success', 'تم اعتماد طلب التعيين نهائياً.');
        } elseif ($decision === 'rejected') {
            $this->flash('danger', 'تم رفض طلب التعيين.');
        } else {
            $this->flash('success', 'تمت الموافقة وإرسال الطلب للمستوى التالي.');
        }
        Response::redirect('/hr/hiring-requests/' . $id);
    }

    /**
     * اتخاذ قرار على طلب إيقاف الدوام
     */
    public function decideSuspensionRequest(string $id): void
    {
        $level = (string)$this->input('level');
        if (!in_array($level, ['manager', 'hr', 'finance'], true)) {
            Response::abort(400, 'مستوى الموافقة غير صحيح.');
        }
        if (!$this->canDecideHrRequest($level)) {
            Response::abort(403, 'ليس لديك صلاحية الموافقة على هذا المستوى.');
        }
        $decision = (string)$this->input('decision');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            Response::abort(400, 'القرار غير صحيح.');
        }
        $request = Database::query("SELECT * FROM suspension_requests WHERE id = ?", [(int)$id])->fetch();
        if (!$request || $request['overall_status'] !== 'pending') {
            Response::abort(400, 'الطلب غير موجود أو تم البت فيه مسبقاً.');
        }
        if ($level === 'hr' && $request['manager_status'] !== 'approved') {
            $this->flash('danger', 'يجب موافقة المدير المباشر أولاً.');
            Response::redirect('/hr/suspension-requests/' . $id);
        }
        if ($level === 'finance' && $request['hr_status'] !== 'approved') {
            $this->flash('danger', 'يجب موافقة الموارد البشرية أولاً.');
            Response::redirect('/hr/suspension-requests/' . $id);
        }
        $column = $level . '_status';
        $byColumn = $level . '_approved_by';
        $notesColumn = $level . '_notes';
        $atColumn = $level . '_at';
        Database::query(
            "UPDATE suspension_requests SET $column = ?, $byColumn = ?, $notesColumn = ?, $atColumn = NOW() WHERE id = ?",
            [$decision, (int)(Auth::id() ?? 0), (string)$this->input('notes'), (int)$id]
        );
        $this->syncHrRequestStatus('suspension_requests', (int)$id);
        if ($decision === 'approved' && $level === 'finance') {
            Database::query("UPDATE employees SET status = 'suspended' WHERE id = ?", [(int)$request['employee_id']]);
            $this->flash('success', 'تم اعتماد طلب الإيقاف وتحديث حالة الموظف إلى موقوف.');
        } elseif ($decision === 'rejected') {
            $this->flash('danger', 'تم رفض طلب الإيقاف.');
        } else {
            $this->flash('success', 'تمت الموافقة وإرسال الطلب للمستوى التالي.');
        }
        Response::redirect('/hr/suspension-requests/' . $id);
    }

    /**
     * إنشاء حساب مالي للموظف تحت الحساب الأب 1210 وتوليد كود فريد
     */
    private function createEmployeeAccount(string $employeeName): array
    {
        $parent = Database::query("SELECT id, code FROM accounts WHERE code = ?", ['1210'])->fetch();
        if (!$parent) {
            throw new \RuntimeException('الحساب الأب 1210 غير موجود في شجرة الحسابات');
        }
        $parentId = (int)$parent['id'];

        $max = Database::query(
            "SELECT MAX(CAST(code AS UNSIGNED)) AS m FROM accounts WHERE code LIKE '1210%' AND code != '1210'"
        )->fetch();
        $nextCode = (string)((int)($max['m'] ?? 1210000) + 1);

        // في حال نفاد التسلسل العددي، نستخدم الطابع الزمني
        $attempts = 0;
        while ($attempts < 5 && Database::query("SELECT 1 FROM accounts WHERE code = ?", [$nextCode])->fetch()) {
            $nextCode = (string)((int)$nextCode + 1);
            $attempts++;
        }
        if (Database::query("SELECT 1 FROM accounts WHERE code = ?", [$nextCode])->fetch()) {
            throw new \RuntimeException('تعذر توليد كود حساب فريد تحت 1210');
        }

        $accountName = mb_substr('سلف ' . $employeeName, 0, 150);
        Database::query(
            "INSERT INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
             VALUES (?, ?, 'asset', ?, 'receivables', 1, 0)",
            [$nextCode, $accountName, $parentId]
        );
        $accountId = (int)Database::lastInsertId();
        return ['id' => $accountId, 'code' => $nextCode];
    }

    /**
     * توليد كلمة مرور عشوائية تحتوي على أحرف وأرقام
     */
    private function generateRandomPassword(int $length = 10): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        // ضمان وجود رقم واحد على الأقل
        if (!preg_match('/\d/', $password)) {
            $password[random_int(0, $length - 1)] = (string)random_int(0, 9);
        }
        return $password;
    }

    // ===== تصدير البيانات HR إلى Excel/PDF =====
    public function exportEmployees(): void
    {
        $search = trim((string)($_GET['search'] ?? ''));
        $departmentId = $_GET['department_id'] ?? '';
        $jobId = $_GET['job_id'] ?? '';
        $status = $_GET['status'] ?? '';
        $fileStatus = $_GET['file_status'] ?? '';
        $branchId = $_GET['branch_id'] ?? '';
        $managerId = $_GET['manager_id'] ?? '';
        $sortBy = in_array($_GET['sort_by'] ?? '', ['full_name','emp_code','job_title','department_name','manager_name','base_salary','hire_date','status','file_status','branch_name','username'], true) ? ($_GET['sort_by'] ?? 'full_name') : 'full_name';
        $sortDir = strtolower($_GET['sort_dir'] ?? '') === 'desc' ? 'DESC' : 'ASC';

        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = "(e.full_name LIKE ? OR e.emp_code LIKE ? OR e.phone LIKE ? OR e.position LIKE ? OR e.department LIKE ?)";
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }
        if ($departmentId !== '') {
            $where[] = "e.department_id = ?";
            $params[] = (int)$departmentId;
        }
        if ($jobId !== '') {
            $where[] = "e.job_id = ?";
            $params[] = (int)$jobId;
        }
        if ($status !== '') {
            $where[] = "e.status = ?";
            $params[] = $status;
        } else {
            $where[] = "e.status NOT IN ('terminated','resigned')";
        }
        if ($fileStatus !== '') {
            $where[] = "e.file_status = ?";
            $params[] = $fileStatus;
        }
        if ($branchId !== '') {
            $where[] = "e.branch_id = ?";
            $params[] = (int)$branchId;
        }
        if ($managerId !== '') {
            $where[] = "e.manager_id = ?";
            $params[] = (int)$managerId;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $orderMap = [
            'full_name' => 'e.full_name', 'emp_code' => 'e.emp_code', 'job_title' => 'j.title',
            'department_name' => 'd.name', 'manager_name' => 'm.full_name', 'base_salary' => 'e.base_salary',
            'hire_date' => 'e.hire_date', 'status' => 'e.status', 'file_status' => 'e.file_status',
            'branch_name' => 'b.name', 'username' => 'u.username',
        ];
        $orderColumn = $orderMap[$sortBy] ?? 'e.full_name';

        $employees = Database::query(
            "SELECT e.emp_code, e.full_name, e.phone, e.position, e.department, e.status, e.file_status, e.base_salary, e.hire_date, e.weekly_off_day,
                    b.name AS branch_name, u.username, d.name AS department_name, j.title AS job_title, m.full_name AS manager_name
             FROM employees e
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN users u ON u.id = e.user_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN jobs j ON j.id = e.job_id
             LEFT JOIN employees m ON m.id = e.manager_id
             $whereSql
             ORDER BY $orderColumn $sortDir
             LIMIT 5000",
            $params
        )->fetchAll();

        $statusLabels = ['active'=>'نشط','vacation'=>'إجازة','suspended'=>'موقوف','terminated'=>'منتهي الخدمة','resigned'=>'مستقيل'];
        $fileStatusLabels = ['open'=>'مفتوح','closed'=>'مغلق','archived'=>'مأرشف'];
        $weekDayLabels = ['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
        $canViewSalary = Auth::can('hr.employee.salary.view');
        $headers = ['الكود', 'الاسم', 'الهاتف', 'القسم', 'الوظيفة', 'المدير', 'الفرع', 'الحالة', 'حالة الملف', 'تاريخ التعيين', 'يوم العطلة', 'اسم المستخدم'];
        if ($canViewSalary) {
            array_splice($headers, 9, 0, 'الراتب الأساسي');
        }
        $rows = [];
        foreach ($employees as $e) {
            $weeklyOffLabel = '';
            if ($e['weekly_off_day'] !== null && $e['weekly_off_day'] >= 0 && $e['weekly_off_day'] <= 6) {
                $weeklyOffLabel = $weekDayLabels[(int)$e['weekly_off_day']] ?? '';
            }
            $row = [
                $e['emp_code'] ?? '', $e['full_name'] ?? '', $e['phone'] ?? '',
                $e['department_name'] ?? ($e['department'] ?? '-'), $e['job_title'] ?? '', $e['manager_name'] ?? '',
                $e['branch_name'] ?? '', $statusLabels[$e['status']] ?? $e['status'],
                $fileStatusLabels[$e['file_status']] ?? $e['file_status'],
                $e['hire_date'] ?? '', $weeklyOffLabel, $e['username'] ?? '',
            ];
            if ($canViewSalary) {
                array_splice($row, 9, 0, $e['base_salary'] ?? '');
            }
            $rows[] = $row;
        }
        $date = date('Y-m-d');
        $format = strtolower($_GET['format'] ?? 'excel');
        if ($format === 'pdf') {
            ExportHelper::pdf("employees_export_$date.pdf", ['title' => 'تقرير الموظفين', 'period' => "تاريخ التصدير: $date", 'headers' => $headers, 'rows' => $rows]);
        } else {
            ExportHelper::excel("employees_export_$date.xlsx", [['title' => 'الموظفون', 'report_title' => 'تقرير الموظفين', 'period' => "تاريخ التصدير: $date", 'headers' => $headers, 'rows' => $rows]]);
        }
    }

    public function exportDepartments(): void
    {
        $departments = Database::query("SELECT * FROM departments ORDER BY name")->fetchAll();
        $rows = [];
        foreach ($departments as $d) {
            $rows[] = [$d['id'], $d['name'], $d['code'] ?? '', $d['is_active'] ? 'نشط' : 'غير نشط', $d['created_at'] ?? ''];
        }
        $headers = ['#', 'الاسم', 'الرمز', 'الحالة', 'تاريخ الإنشاء'];
        $date = date('Y-m-d');
        $format = strtolower($_GET['format'] ?? 'excel');
        if ($format === 'pdf') {
            ExportHelper::pdf("departments_export_$date.pdf", ['title' => 'الأقسام', 'headers' => $headers, 'rows' => $rows]);
        } else {
            ExportHelper::excel("departments_export_$date.xlsx", [['title' => 'الأقسام', 'report_title' => 'الأقسام', 'headers' => $headers, 'rows' => $rows]]);
        }
    }

    public function exportJobs(): void
    {
        $jobs = Database::query(
            "SELECT j.*, d.name AS department_name, r.name AS role_name FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN roles r ON r.id = j.role_id
             ORDER BY j.title"
        )->fetchAll();
        $rows = [];
        foreach ($jobs as $j) {
            $rows[] = [$j['id'], $j['title'], $j['department_name'] ?? '', $j['role_name'] ?? '', $j['code'] ?? '', $j['is_active'] ? 'نشط' : 'غير نشط'];
        }
        $headers = ['#', 'المسمى الوظيفي', 'القسم', 'الدور', 'الرمز', 'الحالة'];
        $date = date('Y-m-d');
        $format = strtolower($_GET['format'] ?? 'excel');
        if ($format === 'pdf') {
            ExportHelper::pdf("jobs_export_$date.pdf", ['title' => 'الوظائف', 'headers' => $headers, 'rows' => $rows]);
        } else {
            ExportHelper::excel("jobs_export_$date.xlsx", [['title' => 'الوظائف', 'report_title' => 'الوظائف', 'headers' => $headers, 'rows' => $rows]]);
        }
    }

    public function exportTasks(): void
    {
        $tasks = Database::query(
            "SELECT t.*, e.full_name AS assignee, u.full_name AS creator FROM tasks t
             JOIN employees e ON e.id = t.assigned_to
             LEFT JOIN users u ON u.id = t.created_by
             ORDER BY t.id DESC LIMIT 100"
        )->fetchAll();
        $statusLabels = ['open'=>'مفتوحة','in_progress'=>'قيد التنفيذ','done'=>'منجزة','cancelled'=>'ملغاة'];
        $rows = [];
        foreach ($tasks as $t) {
            $rows[] = [$t['title'], $t['description'] ?? '', $t['assignee'], $t['due_date'] ?? '', $statusLabels[$t['status']] ?? $t['status'], $t['creator'] ?? ''];
        }
        $headers = ['العنوان', 'الوصف', 'المكلّف', 'الاستحقاق', 'الحالة', 'بواسطة'];
        $date = date('Y-m-d');
        $format = strtolower($_GET['format'] ?? 'excel');
        if ($format === 'pdf') {
            ExportHelper::pdf("tasks_export_$date.pdf", ['title' => 'المهام', 'headers' => $headers, 'rows' => $rows]);
        } else {
            ExportHelper::excel("tasks_export_$date.xlsx", [['title' => 'المهام', 'report_title' => 'المهام', 'headers' => $headers, 'rows' => $rows]]);
        }
    }

    public function exportAttendance(): void
    {
        $date = (string)($_GET['date'] ?? date('Y-m-d'));
        $records = $this->getAttendanceDayRecords($date);
        $rows = [];
        $sourceLabels = ['mobile'=>'موبايل','web'=>'ويب','device'=>'جهاز بصمة'];
        $format = strtolower($_GET['format'] ?? 'excel');
        $pairSeparator = $format === 'pdf' ? ' / ' : "\n";
        foreach ($records as $r) {
            $sourceText = [];
            foreach ($r['sources'] as $s) {
                $sourceText[] = $sourceLabels[$s] ?? $s;
            }
            $inText = [];
            $outText = [];
            foreach ($r['pairs'] as $p) {
                $inText[] = $p['in'];
                $outText[] = $p['out'];
            }
            if (!$inText) {
                $inText[] = '-';
                $outText[] = '-';
            }
            $rows[] = [
                $r['full_name'],
                $r['department'] ?? '-',
                implode($pairSeparator, $inText),
                implode($pairSeparator, $outText),
                $r['total_hours'],
                implode(' + ', $sourceText)
            ];
        }
        $headers = ['الموظف', 'القسم', 'حضور', 'انصراف', 'ساعات العمل', 'المصدر'];
        if ($format === 'pdf') {
            ExportHelper::pdf("attendance_export_$date.pdf", ['title' => 'الحضور والانصراف', 'period' => "تاريخ: $date", 'headers' => $headers, 'rows' => $rows]);
        } else {
            ExportHelper::excel("attendance_export_$date.xlsx", [['title' => 'الحضور', 'report_title' => 'الحضور والانصراف', 'period' => "تاريخ: $date", 'headers' => $headers, 'rows' => $rows]]);
        }
    }

    // ===== cron: إشعارات الورديات الثابتة (محمي بتوكن) =====
    public function cronShiftNotifications(): void
    {
        $stored = (string)\App\Core\Settings::get('shift_cron_token', '');
        $justGenerated = false;
        if ($stored === '') {
            $stored = bin2hex(random_bytes(20));
            \App\Core\Settings::set('shift_cron_token', $stored);
            $justGenerated = true;
        }
        $token = (string)($_GET['token'] ?? '');
        if ($token === '' && $justGenerated) {
            Response::json(['ok' => true, 'message' => 'تم إنشاء التوكن. احفظه وأعد استدعاء الرابط به.', 'token' => $stored]);
            return;
        }
        if (!hash_equals($stored, $token)) {
            Response::abort(403, 'توكن غير صالح');
        }
        $result = \App\Core\ShiftNotificationService::run();
        try {
            $result['push'] = \App\Core\Push\PushSender::processQueue(50);
        } catch (\Throwable $e) {
            $result['push_error'] = $e->getMessage();
        }
        Response::json(['ok' => true, 'result' => $result]);
    }

    private function filterSalaryAuditLogs(array $logs, bool $canViewSalary): array
    {
        if ($canViewSalary) {
            return $logs;
        }
        $salaryFields = ['base_salary', 'salary', 'salary_amount', 'daily_salary', 'net_salary', 'total_due'];
        return array_values(array_filter($logs, function ($log) use ($salaryFields) {
            return !in_array($log['field_name'] ?? '', $salaryFields, true);
        }));
    }

    private function defaultEmployeeRoleId(): ?int
    {
        $role = Database::query("SELECT id FROM roles WHERE name = 'موظف' LIMIT 1")->fetch();
        if ($role) return (int)$role['id'];
        $role = Database::query("SELECT id FROM roles WHERE level > 0 ORDER BY level ASC LIMIT 1")->fetch();
        return $role ? (int)$role['id'] : null;
    }

    public function syncEmployeeAccounts(): void
    {
        if (!Auth::can('hr.manage')) {
            Response::redirect('/hr/employees');
            return;
        }
        try {
            $employees = Database::query(
                "SELECT id, full_name, emp_code FROM employees WHERE account_id IS NULL OR account_id = 0 ORDER BY id"
            )->fetchAll();
            $created = 0;
            foreach ($employees as $e) {
                $accountData = $this->createEmployeeAccount((string)$e['full_name']);
                Database::query(
                    "UPDATE employees SET account_id = ? WHERE id = ?",
                    [(int)$accountData['id'], (int)$e['id']]
                );
                $created++;
            }
            $this->flash('success', "تم إنشاء {$created} حساب مالي مفقود وربطه بالموظفين.");
        } catch (\Throwable $ex) {
            $this->flash('danger', 'فشل مزامنة الحسابات: ' . $ex->getMessage());
        }
        Response::redirect('/hr/employees');
    }
}
