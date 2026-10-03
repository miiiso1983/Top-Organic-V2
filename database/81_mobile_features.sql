-- =====================================================
-- 81: ميزات تطبيق الموبايل — حضور بالموقع، مهام، موافقات
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1) إحداثيات الفروع ونطاق الجيوفينس
ALTER TABLE branches
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS allowed_radius_meters INT DEFAULT 200 COMMENT 'نطاق المسافة المسموح بالحضور منه';

-- 2) توسيع جدول المهام بأولوية ومراكز الكلفة والفروع
ALTER TABLE tasks
    ADD COLUMN IF NOT EXISTS priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    ADD COLUMN IF NOT EXISTS cost_center_id INT NULL,
    ADD COLUMN IF NOT EXISTS branch_id INT NULL,
    ADD COLUMN IF NOT EXISTS status_updated_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS status_updated_by INT NULL;

ALTER TABLE purchase_requests
    ADD COLUMN IF NOT EXISTS workflow_request_id INT NULL AFTER cost_center_id;

ALTER TABLE tasks
    ADD CONSTRAINT fk_task_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_task_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_task_status_updater FOREIGN KEY (status_updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- 3) إعداد الجيوفينس الصارم
INSERT INTO settings (skey, svalue) VALUES ('attendance_strict_geofence', '1')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- 4) قوالب سير عمل للطلبات الإدارية (Material Request & Purchase Request)
INSERT IGNORE INTO approval_templates (code, name, description, icon, fields_json, amount_field, is_active)
VALUES
('material_request', 'طلب مواد', 'طلب مواد من المخزون أو الشراء للقسم/التصنيع', '📦', '[]', 'total_amount', 1),
('purchase_request', 'طلب شراء', 'طلب شراء مواد/أصول من الموردين', '🛒', '[]', 'total_amount', 1);

-- 5) خطوات الموافقة التسلسلية: المدير المباشر ثم المدير العام
SET @mr_tpl = (SELECT id FROM approval_templates WHERE code = 'material_request' LIMIT 1);
SET @pr_tpl = (SELECT id FROM approval_templates WHERE code = 'purchase_request' LIMIT 1);

-- نستخدم المسمى الوظيفي "مدير" إن وجد، وإلا نستخدم دور "مدير مطعم"
SET @manager_pos = (SELECT id FROM org_positions WHERE title LIKE '%مدير%' ORDER BY id LIMIT 1);
SET @manager_role = (SELECT id FROM roles WHERE name = 'مدير مطعم' LIMIT 1);

INSERT IGNORE INTO approval_steps (template_id, step_order, title, description, job_title_id, role_id, min_amount, is_final)
VALUES
(@mr_tpl, 1, 'موافقة المدير المباشر', 'المدير المباشر في الهيكل الإداري', @manager_pos, @manager_role, 0, 0),
(@mr_tpl, 2, 'موافقة الإدارة العليا', 'المدير العام للفرع', @manager_pos, @manager_role, 500000, 1),
(@pr_tpl, 1, 'موافقة المدير المباشر', 'المدير المباشر في الهيكل الإداري', @manager_pos, @manager_role, 0, 0),
(@pr_tpl, 2, 'موافقة الإدارة العليا', 'المدير العام للفرع', @manager_pos, @manager_role, 500000, 1);

-- 6) صلاحيات الموبايل
INSERT IGNORE INTO permissions (code, name, module) VALUES
('mobile.attendance','تسجيل الحضور من الموبايل','mobile'),
('mobile.tasks','إدارة المهام من الموبايل','mobile'),
('mobile.approvals','الموافقات من الموبايل','mobile'),
('mobile.requests','إنشاء الطلبات من الموبايل','mobile'),
('mobile.employees','عرض الموظفين من الموبايل','mobile');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE p.code IN ('mobile.attendance','mobile.tasks','mobile.approvals','mobile.requests','mobile.employees');

SET FOREIGN_KEY_CHECKS=1;
