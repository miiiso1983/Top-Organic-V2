-- =====================================================
-- 19: HRMS Batch 2 - التنبيهات والسلف والتبليغات الإدارية
-- =====================================================
SET NAMES utf8mb4;

-- أوقات دخول وخروج للموظف
ALTER TABLE employees ADD COLUMN IF NOT EXISTS work_start_time TIME NULL COMMENT 'بداية وقت العمل';
ALTER TABLE employees ADD COLUMN IF NOT EXISTS work_end_time TIME NULL COMMENT 'نهاية وقت العمل';

-- إعدادات موقت عمل افتراضي
INSERT IGNORE INTO settings (skey, svalue) VALUES ('default_work_start_time', '08:00');
INSERT IGNORE INTO settings (skey, svalue) VALUES ('default_work_end_time', '17:00');
INSERT IGNORE INTO settings (skey, svalue) VALUES ('missing_clockin_alert_delay_minutes', '15');

-- تطبيق أوقات افتتاحية للموظفين الموجودين
UPDATE employees SET work_start_time = '08:00:00' WHERE work_start_time IS NULL;
UPDATE employees SET work_end_time = '17:00:00' WHERE work_end_time IS NULL;

-- جدول تتبع التنبيهات لتجنب الإعادة
CREATE TABLE IF NOT EXISTS attendance_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    alert_date DATE NOT NULL,
    alert_type VARCHAR(40) NOT NULL COMMENT 'missing_clockin, late_clockin',
    status ENUM('new','acknowledged','resolved') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_emp_date_type (employee_id, alert_date, alert_type),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- قواعد التبليغات الإدارية حسب المستوى والقسم
CREATE TABLE IF NOT EXISTS notification_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(60) NOT NULL COMMENT 'missing_clockin, leave_request, advance_request, task_assigned',
    min_admin_level INT DEFAULT 0,
    branch_specific TINYINT(1) DEFAULT 0,
    department_specific TINYINT(1) DEFAULT 0,
    role_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إدراج قواعد افتراضية
INSERT IGNORE INTO notification_rules (event_type, min_admin_level, branch_specific, department_specific)
VALUES ('missing_clockin', 1, 1, 0);
INSERT IGNORE INTO notification_rules (event_type, min_admin_level, branch_specific, department_specific)
VALUES ('leave_request', 1, 1, 1);
INSERT IGNORE INTO notification_rules (event_type, min_admin_level, branch_specific, department_specific)
VALUES ('advance_request', 1, 1, 1);
INSERT IGNORE INTO notification_rules (event_type, min_admin_level, branch_specific, department_specific)
VALUES ('task_assigned', 0, 0, 0);

-- مصدر طلب السلفة
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS request_source VARCHAR(20) DEFAULT 'web' COMMENT 'web or mobile';
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS mobile_device_id VARCHAR(60) NULL;

-- صلاحيات جديدة
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.alerts.manage', 'إدارة تنبيهات الحضور', 'hr'),
('hr.notification_rules', 'إدارة قواعد التبليغات', 'hr');

-- منح الصلاحيات للمديرين
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم') AND p.code IN ('hr.alerts.manage','hr.notification_rules');
