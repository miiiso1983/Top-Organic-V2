-- =====================================================
-- 11: صلاحيات الأدوار الافتراضية + صلاحيات الخدمة الذاتية
-- =====================================================
SET NAMES utf8mb4;

-- صلاحيات ذاتية لكل موظف بغض النظر عن دوره (حضور/انصراف، إجازات، سلف، مهامه الخاصة)
INSERT IGNORE INTO permissions (code, name, module) VALUES
('attendance.self', 'الحضور والانصراف الشخصي', 'self'),
('tasks.self', 'مهامي الشخصية', 'self');

-- مدير النظام: كل الصلاحيات (بما فيها أي صلاحيات جديدة تُضاف مستقبلاً)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'مدير النظام';

-- مدير عام: كل الأقسام التشغيلية عدا إعدادات وإدارة النظام
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'مدير عام' AND p.code IN (
    'sales.view','sales.create','sales.return','pos.use','reservations.manage',
    'kitchen.view','kitchen.manage','recipes.manage','production.manage',
    'inventory.view','inventory.manage','inventory.transfer','inventory.stocktake','custody.manage',
    'purchasing.view','purchasing.manage','suppliers.manage',
    'finance.view','finance.manage','hr.view','hr.manage','payroll.manage',
    'approvals.decide','reports.view','attendance.self','tasks.self'
);

-- مدير مالي
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'مدير مالي' AND p.code IN (
    'finance.view','finance.manage','purchasing.view','purchasing.manage','suppliers.manage',
    'reports.view','approvals.decide','payroll.manage','hr.view','attendance.self','tasks.self'
);

-- مدير مطعم (مدير الفرع)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'مدير مطعم' AND p.code IN (
    'sales.view','sales.create','sales.return','pos.use','reservations.manage',
    'kitchen.view','kitchen.manage','recipes.manage','production.manage',
    'inventory.view','inventory.manage','inventory.transfer','inventory.stocktake','custody.manage',
    'purchasing.view','hr.view','hr.manage','approvals.decide','reports.view',
    'attendance.self','tasks.self'
);

-- محاسب
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'محاسب' AND p.code IN (
    'finance.view','finance.manage','reports.view','purchasing.view','attendance.self','tasks.self'
);

-- مسؤول مشتريات
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'مسؤول مشتريات' AND p.code IN (
    'purchasing.view','purchasing.manage','suppliers.manage','inventory.view',
    'attendance.self','tasks.self'
);

-- أمين مخزن
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'أمين مخزن' AND p.code IN (
    'inventory.view','inventory.manage','inventory.transfer','inventory.stocktake','custody.manage',
    'attendance.self','tasks.self'
);

-- كاشير
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'كاشير' AND p.code IN (
    'sales.view','sales.create','sales.return','pos.use','reservations.manage','attendance.self','tasks.self'
);

-- شيف
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'شيف' AND p.code IN (
    'kitchen.view','kitchen.manage','recipes.manage','production.manage','attendance.self','tasks.self'
);

-- موظف (وظيفة عامة): صلاحيات الخدمة الذاتية فقط
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'موظف' AND p.code IN ('attendance.self','tasks.self');
