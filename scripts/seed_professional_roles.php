<?php
// بذور احترافية للأدوار والصلاحيات بناءً على الأقسام والوظائف الحالية.
// تشغيل: php scripts/seed_professional_roles.php
// آمن للتشغيل المتكرر — يستخدم INSERT IGNORE ولا يحذف مستخدمين أو موظفين.
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) require $file;
    }
});

use App\Core\{App, Database};

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

@session_start();
App::boot();

$roles = [
    'مدير قسم' => [60, 'إدارة قسم عملياتي'],
    'مشرف قسم' => [50, 'إشراف على فريق العمل'],
    'مدير موارد بشرية' => [60, 'إدارة قسم الموارد البشرية'],
    'موظف موارد بشرية' => [40, 'موظف موارد بشرية'],
    'موظف استقبال' => [20, 'موظف استقبال'],
    'موظف كول سنتر' => [20, 'موظف مركز الاتصال'],
    'باريستا' => [20, 'باريستا'],
    'ويتر' => [20, 'ويتر'],
    'حارس' => [20, 'حارس أمن'],
    'فني صيانة' => [20, 'فني صيانة'],
];

foreach ($roles as $name => [$level, $desc]) {
    Database::query("INSERT IGNORE INTO roles (name, level, description) VALUES (?,?,?)", [$name, $level, $desc]);
}

$patterns = [
    ['مدير مالي', 'مدير مالي'],
    ['مدير مطعم', 'مدير مطعم'],
    ['مدير قسم الموارد البشرية', 'مدير موارد بشرية'],
    ['موظف موارد بشرية', 'موظف موارد بشرية'],
    ['مدير قسم', 'مدير قسم'],
    ['مشرف قسم', 'مشرف قسم'],
    ['شيف', 'شيف'],
    ['كابتن', 'كابتن'],
    ['محاسب', 'محاسب'],
    ['كاشير', 'كاشير'],
    ['موظف مشتريات', 'مسؤول مشتريات'],
    ['سيطرة مخزنية', 'أمين مخزن'],
    ['موظف استقبال', 'موظف استقبال'],
    ['موظف كول سنتر', 'موظف كول سنتر'],
    ['باريستا', 'باريستا'],
    ['ويتر', 'ويتر'],
    ['حارس', 'حارس'],
    ['صيانة', 'فني صيانة'],
    ['', 'موظف'], // fallback
];

$jobs = Database::query("SELECT id, title FROM jobs WHERE is_active = 1")->fetchAll();
foreach ($jobs as $j) {
    foreach ($patterns as $pat) {
        if ($pat[0] === '') {
            Database::query("UPDATE jobs SET role_id = (SELECT id FROM roles WHERE name = ?) WHERE id = ? AND role_id IS NULL", [$pat[1], $j['id']]);
            break;
        }
        if (mb_strpos($j['title'], $pat[0]) !== false) {
            Database::query("UPDATE jobs SET role_id = (SELECT id FROM roles WHERE name = ?) WHERE id = ?", [$pat[1], $j['id']]);
            break;
        }
    }
}

$common = ['ptt.use', 'attendance.self'];

// Ensure new RBAC permissions exist so they can be assigned below
$permissionsToEnsure = [
    'hr.employee.create' => 'إنشاء ملف موظف',
    'hr.employee.salary.view' => 'عرض حقول الرواتب للموظف',
];
foreach ($permissionsToEnsure as $code => $name) {
    Database::query("INSERT IGNORE INTO permissions (code, name) VALUES (?, ?)", [$code, $name]);
}

$matrix = [
    'موظف' => array_merge($common, ['tasks.self', 'hr.leave.request']),
    'حارس' => array_merge($common, ['tasks.self']),
    'فني صيانة' => array_merge($common, ['tasks.self']),
    'باريستا' => array_merge($common, ['sales.view', 'sales.create']),
    'ويتر' => array_merge($common, ['sales.view', 'sales.create', 'pos.use']),
    'كابتن' => array_merge($common, ['sales.view', 'sales.create', 'pos.use', 'reservations.view', 'floor_plan.view']),
    'موظف استقبال' => array_merge($common, ['reception.manage', 'reservations.view', 'sales.view']),
    'موظف كول سنتر' => array_merge($common, ['sales.view', 'reservations.view', 'ai_assistant.view']),
    'شيف' => array_merge($common, ['kitchen.view', 'kitchen.manage', 'recipes.manage', 'production.manage', 'inventory.view', 'production.mobile']),
    'كاشير' => array_merge($common, ['pos.use', 'sales.create', 'sales.view', 'hr.payslip.view']),
    'محاسب' => array_merge($common, ['accounting.view', 'finance.view', 'hr.view', 'hr.employee.create', 'hr.employee.profile', 'sales.view']),
    'أمين مخزن' => array_merge($common, ['inventory.view', 'inventory.manage', 'inventory.transfer', 'inventory.stocktake', 'material_request.view', 'material_request.fulfill', 'custody.manage', 'inventory.mobile', 'custody.mobile', 'production.mobile']),
    'مسؤول مشتريات' => array_merge($common, ['purchasing.manage', 'suppliers.manage', 'procurement.view', 'material_request.view', 'material_request.manage', 'material_request.approve', 'material_request.view_all', 'inventory.view']),
    'موظف موارد بشرية' => array_merge($common, ['tasks.self', 'hr.view', 'hr.employee.profile', 'hr.leave.request', 'hr.leave.approve', 'hr.reports.attendance', 'hr.reports.leaves', 'hr.evaluation.view']),
    'مشرف قسم' => array_merge($common, ['tasks.self', 'sales.view', 'sales.create', 'pos.use', 'kitchen.view', 'inventory.view', 'procurement.view', 'material_request.view', 'reservations.view', 'hr.view', 'hr.employee.profile', 'reports.view']),
    'مدير قسم' => array_merge($common, ['tasks.self', 'sales.view', 'sales.create', 'pos.use', 'kitchen.view', 'inventory.view', 'procurement.view', 'material_request.view', 'material_request.manage', 'purchasing.view', 'reservations.view', 'hr.view', 'hr.leave.approve', 'hr.employee.profile', 'reports.view']),
    'مدير موارد بشرية' => array_merge($common, ['tasks.self', 'hr.view', 'hr.manage', 'hr.payroll.manage', 'hr.adjustments.manage', 'hr.leave_balance', 'hr.leave.approve', 'hr.reports.view', 'hr.reports.payroll', 'hr.reports.attendance', 'hr.reports.leaves', 'hr.evaluation.manage', 'hr.evaluation.view', 'hr.goals.manage', 'hr.employee.profile', 'hr.custody.view', 'hr.workflow.view', 'hr.workflow.manage', 'hr.notification_rules', 'hr.notifications.history', 'hr.alerts.manage', 'hr.accounting.settings', 'approvals.decide', 'workflow.manage', 'workflow.decide', 'reports.view']),
    'مدير مالي' => array_merge($common, ['finance.view', 'finance.manage', 'accounting.view', 'accounting.manage', 'accounting.reports', 'sales.view', 'purchasing.view', 'procurement.view', 'hr.view', 'hr.payroll.manage', 'hr.adjustments.manage', 'hr.reports.payroll', 'hr.reports.adjustments', 'hr.reports.advances', 'hr.payslip.view', 'reports.view']),
    'مدير مطعم' => array_merge($common, ['tasks.self', 'sales.view', 'sales.create', 'sales.return', 'pos.use', 'reservations.manage', 'reception.manage', 'kitchen.view', 'kitchen.manage', 'inventory.view', 'inventory.transfer', 'inventory.stocktake', 'procurement.view', 'material_request.view', 'material_request.manage', 'purchasing.view', 'hr.view', 'hr.employee.profile', 'hr.leave.approve', 'reports.view', 'ptt.manage', 'ptt.sync_departments', 'workflow.manage', 'workflow.decide', 'approvals.decide']),
];

// Grant salary-field visibility to every HR-enabled role except the basic employee and accountant
foreach ($matrix as $roleName => $codes) {
    if (in_array('hr.view', $codes, true) && !in_array($roleName, ['موظف', 'محاسب'], true)) {
        $codes[] = 'hr.employee.salary.view';
        $matrix[$roleName] = array_values(array_unique($codes));
    }
}

$allCodes = Database::query("SELECT code FROM permissions")->fetchAll(\PDO::FETCH_COLUMN);
$matrix['مدير عام'] = $allCodes;
$matrix['مدير النظام'] = $allCodes;

foreach ($matrix as $roleName => $codes) {
    $roleId = (int)Database::query("SELECT id FROM roles WHERE name = ?", [$roleName])->fetchColumn();
    if (!$roleId) continue;
    Database::query("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
    foreach (array_unique($codes) as $code) {
        Database::query("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE code = ?", [$roleId, $code]);
    }
}

// تصحيح أسماء الأقسام في الموظفين لضمان عمل المزامنة التلقائية
$depts = Database::query("SELECT id, name FROM departments WHERE is_active = 1")->fetchAll();
foreach ($depts as $d) {
    Database::query("UPDATE employees SET department = ? WHERE department_id = ? AND (department IS NULL OR department = '')", [$d['name'], $d['id']]);
}

echo "تم تحديث الأدوار والصلاحيات والربط بالوظائف ✔\n";
exit(0);
