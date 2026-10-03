-- Migration 138: تعديل صلاحيات دور المحاسب في وحدة الموارد البشرية
-- الهدف: السماح للمحاسب بإنشاء ملفات موظفين جدد، مع حجب حقول الرواتب
-- ومنع الدخول إلى التقارير المالية وكشوفات الرواتب.
SET NAMES utf8mb4;

-- 1. إضافة الصلاحيات الجديدة
INSERT IGNORE INTO permissions (code, name) VALUES
('hr.employee.create', 'إنشاء ملف موظف'),
('hr.employee.salary.view', 'عرض حقول الرواتب للموظف');

-- 2. منح المحاسب صلاحية إنشاء الموظفين وعرض الملف الشخصي (بدون تعديل)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'محاسب' AND p.code IN ('hr.employee.create', 'hr.employee.profile');

-- 3. منح صلاحية عرض الرواتب لكل من يملك hr.view باستثناء المحاسب والموظف العادي
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE p.code = 'hr.employee.salary.view'
AND r.id IN (
    SELECT DISTINCT rp.role_id
    FROM role_permissions rp
    JOIN permissions p2 ON p2.id = rp.permission_id
    WHERE p2.code = 'hr.view'
)
AND r.name NOT IN ('محاسب', 'موظف');

-- 4. سحب التقارير المالية وكشوفات الرواتب من المحاسب
DELETE rp FROM role_permissions rp
INNER JOIN roles r ON r.id = rp.role_id
INNER JOIN permissions p ON p.id = rp.permission_id
WHERE r.name = 'محاسب' AND p.code IN ('hr.payslip.view', 'reports.view', 'accounting.reports');
