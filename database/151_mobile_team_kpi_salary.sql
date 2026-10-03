-- =====================================================
-- 151: صلاحية تقييم أداء الفريق من الموبايل (الهيكل الإداري)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

INSERT IGNORE INTO permissions (code, name, module) VALUES
('mobile.evaluations', 'تقييم أداء الفريق من الموبايل', 'mobile');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE p.code = 'mobile.evaluations';

SET FOREIGN_KEY_CHECKS=1;
