-- صلاحية التحكم بحالة خدمة PTT (إيقاف جزئي / إعادة تشغيل) — حصرية للإدارة العليا
INSERT IGNORE INTO permissions (code, name, module) VALUES
('ptt.service_control','التحكم بحالة خدمة PTT (إيقاف جزئي/إعادة تشغيل)','ptt');

-- تُمنح فقط لأعلى مستويين إداريين في النظام
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام') AND p.code = 'ptt.service_control';
