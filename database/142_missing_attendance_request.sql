-- =====================================================
-- 142: طلب نسيان بصمة حضور/انصراف (Missing Attendance Request)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- جدول طلبات نسيان البصمة
CREATE TABLE IF NOT EXISTS missing_attendance_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    request_type ENUM('in','out','both') NOT NULL DEFAULT 'in',
    clock_in_at DATETIME NULL,
    clock_out_at DATETIME NULL,
    reason VARCHAR(255) NULL,
    status ENUM('pending','manager_approved','fully_approved','rejected') DEFAULT 'pending',
    workflow_request_id INT NULL,
    manager_approved_by INT NULL,
    manager_approved_at DATETIME NULL,
    grand_manager_approved_by INT NULL,
    grand_manager_approved_at DATETIME NULL,
    rejected_by INT NULL,
    rejected_at DATETIME NULL,
    rejected_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_status (employee_id, status),
    INDEX idx_dates (clock_in_at, clock_out_at),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (workflow_request_id) REFERENCES requests(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (grand_manager_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إضافة مصدر 'request' لجدول attendance إن لم يكن موجوداً
ALTER TABLE attendance
    MODIFY COLUMN source ENUM('mobile','web','device','request') DEFAULT 'mobile';

-- صلاحيات
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.missing_attendance.request', 'طلب نسيان بصمة', 'hr'),
('hr.missing_attendance.approve', 'اعتماد طلب نسيان بصمة', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE p.code = 'hr.missing_attendance.request' AND r.name IN ('موظف','كاشير','كابتن','نادل','مضيف','مدير صالة','مدير مطعم','مدير عام','مدير النظام');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE p.code = 'hr.missing_attendance.approve' AND r.name IN ('مدير صالة','مدير مطعم','مدير عام','مدير النظام');

-- قالب سير العمل
INSERT INTO approval_templates (code, name, description, icon, fields_json, amount_field, is_active, created_by)
VALUES ('hr_missing_attendance', 'طلب نسيان بصمة', 'طلب تسجيل بصمة دخول/خروج مفقودة من الموبايل', '🕓',
'[{"name":"employee_id","type":"number","label":"الموظف","required":true},
{"name":"employee_name","type":"text","label":"اسم الموظف","required":true},
{"name":"request_type","type":"select","label":"نسيان","required":true,"options":[{"value":"in","label":"بصمة دخول"},{"value":"out","label":"بصمة خروج"}]},
{"name":"clock_in_at","type":"datetime","label":"تاريخ ووقت بصمة الدخول","required":false},
{"name":"clock_out_at","type":"datetime","label":"تاريخ ووقت بصمة الخروج","required":false},
{"name":"reason","type":"textarea","label":"السبب / الملاحظات","required":true}]',
NULL, 1, NULL)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), icon=VALUES(icon),
fields_json=VALUES(fields_json), amount_field=VALUES(amount_field);

SET @tpl_id = (SELECT id FROM approval_templates WHERE code = 'hr_missing_attendance' LIMIT 1);

DELETE FROM approval_steps WHERE template_id = @tpl_id;
INSERT INTO approval_steps
    (template_id, step_order, title, approver_type, description, job_title_id, role_id, min_amount, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
VALUES
(@tpl_id, 1, 'موافقة المدير المباشر', 'direct_manager', NULL, NULL, NULL, 0, '{}', 1, 0, 'sequential', 1, '{"push":1}'),
(@tpl_id, 2, 'موافقة مدير المدير', 'department_manager', NULL, NULL, NULL, 0, '{}', 1, 1, 'sequential', 1, '{"push":1}');

INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT @tpl_id, r.id, 1, 0 FROM roles r
WHERE r.name IN ('موظف','كاشير','كابتن','نادل','مضيف','مدير صالة','مدير مطعم','مدير عام','مدير النظام');

SET FOREIGN_KEY_CHECKS=1;
