-- =====================================================
-- 59: الملف الإلكتروني للموظف — عقود + صلاحية عرض الملف
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. جدول عقود الموظف
CREATE TABLE IF NOT EXISTS employee_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    contract_type ENUM('fixed','open','project','part_time') DEFAULT 'fixed',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    position VARCHAR(100) NULL,
    benefits TEXT NULL,
    file_path VARCHAR(255) NULL,
    status ENUM('active','expired','terminated') DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee (employee_id),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. صلاحية عرض الملف الشامل
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.employee.profile', 'عرض الملف الإلكتروني للموظف', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code IN ('hr.employee.profile','hr.employee.documents','hr.employee.audit');

SET FOREIGN_KEY_CHECKS=1;
