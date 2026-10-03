-- =====================================================
-- 24: Fix cashier role permissions - add sales.view
-- =====================================================
SET NAMES utf8mb4;

-- إضافة صلاحيات المبيعات الناقصة لدور الكاشير
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'كاشير' AND p.code IN (
    'sales.view',
    'sales.create',
    'sales.return',
    'pos.use',
    'reservations.manage',
    'attendance.self',
    'tasks.self'
);
