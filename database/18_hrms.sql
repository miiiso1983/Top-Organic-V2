-- =====================================================
-- 18: HRMS - توسيع الموارد البشرية
-- =====================================================
SET NAMES utf8mb4;

-- إضافة نوع إجازة إذن خروج
ALTER TABLE leave_requests
    MODIFY COLUMN leave_type ENUM('annual','sick','unpaid','emergency','hourly','exit') DEFAULT 'annual';

-- إضافة إحداثيات وتوسعات الفرع للجيوفينس
ALTER TABLE branches ADD COLUMN latitude DECIMAL(10,7) NULL;
ALTER TABLE branches ADD COLUMN longitude DECIMAL(10,7) NULL;
ALTER TABLE branches ADD COLUMN allowed_radius_meters INT DEFAULT 200 COMMENT 'المسافة المسموحة للحضور';

-- إضافة بيانات جهاز البصمة
ALTER TABLE attendance ADD COLUMN device_id VARCHAR(60) NULL AFTER source;
ALTER TABLE attendance ADD COLUMN notes VARCHAR(255) NULL AFTER device_id;

-- جدول رصيد الإجازات
CREATE TABLE IF NOT EXISTS leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    year INT NOT NULL,
    leave_type VARCHAR(30) NOT NULL,
    entitled DECIMAL(8,2) DEFAULT 0,
    balance DECIMAL(8,2) DEFAULT 0,
    used DECIMAL(8,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_emp_year_type (employee_id, year, leave_type),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إنشاء أرصدة افتتاحية للموظفين الموجودين
INSERT IGNORE INTO leave_balances (employee_id, year, leave_type, entitled, balance, used)
SELECT id, YEAR(CURDATE()), 'annual', 30, 30, 0 FROM employees WHERE file_status = 'open';
INSERT IGNORE INTO leave_balances (employee_id, year, leave_type, entitled, balance, used)
SELECT id, YEAR(CURDATE()), 'sick', 15, 15, 0 FROM employees WHERE file_status = 'open';
INSERT IGNORE INTO leave_balances (employee_id, year, leave_type, entitled, balance, used)
SELECT id, YEAR(CURDATE()), 'exit', 0, 0, 0 FROM employees WHERE file_status = 'open';

-- صلاحيات HRMS إضافية
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.clock','تسجيل حضور/انصراف','hr'),
('hr.biometric','استيراد بصمات الأجهزة','hr'),
('hr.leave_balance','إدارة رصيد الإجازات','hr');

-- منح الصلاحيات للأدوار ذات الصلة
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code IN ('hr.clock','hr.biometric','hr.leave_balance');

-- مسار اعتماد الإجازات (النظام القديم)
INSERT IGNORE INTO legacy_approval_workflows (entity_type, name) VALUES ('leave_request', 'اعتماد طلبات الإجازات');
SET @wf_id = (SELECT id FROM legacy_approval_workflows WHERE entity_type = 'leave_request');
INSERT IGNORE INTO legacy_approval_steps (workflow_id, step_order, role_id, min_amount)
SELECT @wf_id, 1, r.id, 0 FROM roles r WHERE r.name IN ('مدير النظام','مدير عام') ORDER BY r.level DESC LIMIT 1;
