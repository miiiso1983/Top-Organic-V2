-- =====================================================
-- 60: تحسين نظام التقارير — صلاحيات متعددة للتقارير
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.reports.payroll', 'تقرير الرواتب', 'hr'),
('hr.reports.adjustments', 'تقرير المكافآت والخصومات والزيادات', 'hr'),
('hr.reports.advances', 'تقرير السلف', 'hr'),
('hr.reports.attendance', 'تقرير الحضور والتأخير', 'hr'),
('hr.reports.leaves', 'تقرير الإجازات والأذونات', 'hr'),
('hr.reports.evaluations', 'تقرير تقييم الموظفين', 'hr'),
('hr.reports.penalties', 'تقرير الجزاءات', 'hr'),
('hr.reports.location', 'تقرير مراقبة المواقع', 'hr'),
('hr.reports.custom', 'تقارير مخصصة', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code LIKE 'hr.reports.%';

SET FOREIGN_KEY_CHECKS=1;
