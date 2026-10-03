-- =====================================================
-- 31: دور الكابتن (نادل الطاولات) + صلاحياته
-- =====================================================
SET NAMES utf8mb4;

-- إضافة الدور إذا لم يكن موجوداً
INSERT IGNORE INTO roles (name, level, description) VALUES
('كابتن', 30, 'نادل يأخذ طلبات الطاولات ويبيع من المنيو');

-- صلاحيات الكابتن: رؤية وإنشاء المبيعات، استخدام نقطة البيع، الحضور، مهامه
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'كابتن' AND p.code IN (
    'sales.view','sales.create','sales.return','pos.use','reservations.manage',
    'attendance.self','tasks.self'
);
