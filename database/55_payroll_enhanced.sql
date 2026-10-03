-- =====================================================
-- 55: تحسين نظام Payroll — بدلات، إضافي، ضرائب، اعتماد، قيود محاسبية
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. أعمدة جدول الرواتب
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS allowances DECIMAL(12,2) DEFAULT 0;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS overtime DECIMAL(12,2) DEFAULT 0;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(12,2) DEFAULT 0;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS total_earnings DECIMAL(12,2) DEFAULT 0;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS total_deductions DECIMAL(12,2) DEFAULT 0;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS approved_by INT NULL;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS approved TINYINT(1) DEFAULT 0;
ALTER TABLE payrolls MODIFY COLUMN status ENUM('draft','approved','paid') DEFAULT 'draft';
ALTER TABLE payrolls ADD FOREIGN KEY IF NOT EXISTS (approved_by) REFERENCES users(id) ON DELETE SET NULL;

-- 2. بدلات الموظفين (ثابتة شهريًا)
CREATE TABLE IF NOT EXISTS employee_allowances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    allowance_type VARCHAR(60) NOT NULL COMMENT 'housing, transport, food, etc.',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY uq_emp_allowance (employee_id, allowance_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. بدلات مسير الراتب (تفصيلية)
CREATE TABLE IF NOT EXISTS payroll_allowances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_id INT NOT NULL,
    allowance_type VARCHAR(60) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. ساعات إضافي لمسير الراتب
CREATE TABLE IF NOT EXISTS payroll_overtime (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_id INT NOT NULL,
    hours DECIMAL(8,2) NOT NULL DEFAULT 0,
    hourly_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.5,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255),
    FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. شرائح الضريبة
CREATE TABLE IF NOT EXISTS tax_brackets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    min_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_amount DECIMAL(12,2) DEFAULT NULL COMMENT 'NULL means no upper limit',
    rate DECIMAL(5,4) NOT NULL DEFAULT 0 COMMENT '0.05 = 5%',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. حساب ضريبي للمورد البشري
INSERT IGNORE INTO accounts (code, name, type) VALUES
('2200', 'الضريبة المستحقة', 'liability');

-- 7. إعدادات ضريبية افتراضية (مثال: 5% على ما يزيد عن 3000)
INSERT IGNORE INTO tax_brackets (min_amount, max_amount, rate) VALUES
(0, 3000, 0),
(3000, NULL, 0.05);

-- 8. صلاحيات
INSERT IGNORE INTO permissions (code, name, module) VALUES
('payroll.approve', 'اعتماد الرواتب', 'hr'),
('payroll.overtime', 'إدارة ساعات الإضافي', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مالي') AND p.code IN ('payroll.approve','payroll.overtime');

SET FOREIGN_KEY_CHECKS=1;
