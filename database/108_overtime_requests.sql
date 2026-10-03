-- =====================================================
-- 108: طلبات العمل الإضافي (Overtime Requests)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS overtime_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    request_date DATE NOT NULL,
    hours DECIMAL(4,2) NOT NULL,
    reason VARCHAR(255),
    status ENUM('pending','manager_approved','fully_approved','rejected') DEFAULT 'pending',
    workflow_request_id INT NULL,
    manager_approved_by INT NULL,
    manager_approved_at DATETIME NULL,
    grand_manager_approved_by INT NULL,
    grand_manager_approved_at DATETIME NULL,
    rejected_by INT NULL,
    rejected_at DATETIME NULL,
    rejected_notes TEXT NULL,
    biometric_match TINYINT(1) DEFAULT 0,
    biometric_hours DECIMAL(5,2) DEFAULT 0,
    amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_date (employee_id, request_date),
    INDEX idx_status (status),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (workflow_request_id) REFERENCES requests(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (grand_manager_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول ساعات الإضافي في مسير الراتب (إذا لم يكن موجوداً)
CREATE TABLE IF NOT EXISTS payroll_overtime (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_id INT NOT NULL,
    overtime_request_id INT NULL,
    hours DECIMAL(8,2) NOT NULL DEFAULT 0,
    hourly_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.5,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255),
    FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE CASCADE,
    INDEX idx_overtime_request (overtime_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ربط ساعات الإضافي بمسير الراتب لتجنب التكرار
SET @dbname = DATABASE();
SET @tablename = 'payroll_overtime';
SET @columnname = 'overtime_request_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) = 0,
  'ALTER TABLE payroll_overtime ADD COLUMN overtime_request_id INT NULL AFTER id, ADD INDEX idx_overtime_request (overtime_request_id);',
  'SELECT 1;'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- صلاحيات
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.overtime.request', 'طلب عمل إضافي', 'hr'),
('hr.overtime.approve', 'اعتماد طلب عمل إضافي', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE p.code = 'hr.overtime.request' AND r.name IN ('موظف','كاشير','كابتن','نادل','مضيف','مدير صالة','مدير مطعم','مدير عام','مدير النظام');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE p.code = 'hr.overtime.approve' AND r.name IN ('مدير صالة','مدير مطعم','مدير عام','مدير النظام');

-- قالب سير العمل
INSERT INTO approval_templates (code, name, description, icon, fields_json, amount_field, is_active, created_by)
VALUES ('hr_overtime', 'طلب عمل إضافي', 'طلب ساعات عمل إضافي مع التحقق من سجلات البصمة', '⏱️',
'[{"name":"employee_id","type":"number","label":"الموظف","required":true},
{"name":"employee_name","type":"text","label":"اسم الموظف","required":true},
{"name":"request_date","type":"date","label":"تاريخ العمل الإضافي","required":true},
{"name":"hours","type":"number","label":"عدد الساعات","required":true},
{"name":"reason","type":"textarea","label":"السبب","required":false}]',
'amount', 1, NULL)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), icon=VALUES(icon),
fields_json=VALUES(fields_json), amount_field=VALUES(amount_field);

SET @tpl_id = (SELECT id FROM approval_templates WHERE code = 'hr_overtime' LIMIT 1);

INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT @tpl_id, r.id, 1, 0 FROM roles r
WHERE r.name IN ('موظف','كاشير','كابتن','نادل','مضيف','مدير صالة','مدير مطعم','مدير عام','مدير النظام');

-- خطوات الموافقة: مدير مباشر -> مدير المدير
DELETE FROM approval_steps WHERE template_id = @tpl_id;
INSERT INTO approval_steps
    (template_id, step_order, title, approver_type, description, job_title_id, role_id, min_amount, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
VALUES
(@tpl_id, 1, 'موافقة المدير المباشر', 'direct_manager', NULL, NULL, NULL, 0, '{}', 1, 0, 'sequential', 1, '{"push":1}'),
(@tpl_id, 2, 'موافقة مدير المدير', 'department_manager', NULL, NULL, NULL, 0, '{}', 1, 1, 'sequential', 1, '{"push":1}');

SET FOREIGN_KEY_CHECKS=1;
