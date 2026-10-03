-- =====================================================
-- 54: إصلاحي للسلف — تأمين وجود الأعمدة والجداول والصلاحيات
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. أعمدة جدول السلف
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS is_long_term TINYINT(1) DEFAULT 0;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS remaining_amount DECIMAL(12,2) DEFAULT 0;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS paid_amount DECIMAL(12,2) DEFAULT 0;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS journal_entry_id BIGINT NULL;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS approved_by INT NULL;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP NULL;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS request_source VARCHAR(20) DEFAULT 'web' COMMENT 'web or mobile';
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS mobile_device_id VARCHAR(60) NULL;

ALTER TABLE salary_advances ADD FOREIGN KEY IF NOT EXISTS (approved_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE salary_advances ADD FOREIGN KEY IF NOT EXISTS (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL;

-- 2. جدول الأقساط
CREATE TABLE IF NOT EXISTS advance_repayment_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advance_id INT NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','paid') DEFAULT 'pending',
    payroll_id INT NULL,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (advance_id) REFERENCES salary_advances(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE SET NULL,
    UNIQUE KEY uq_advance_period (advance_id, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. أعمدة المرتبات (للربط المحاسبي)
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS journal_entry_id BIGINT NULL;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS accrual_entry_id BIGINT NULL;
ALTER TABLE payrolls ADD FOREIGN KEY IF NOT EXISTS (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL;
ALTER TABLE payrolls ADD FOREIGN KEY IF NOT EXISTS (accrual_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL;

-- 4. حسابات محاسبية للمورد البشري
INSERT IGNORE INTO accounts (code, name, type) VALUES
('1150', 'سلف الموظفين', 'asset'),
('5110', 'المكافآت والحوافز', 'expense'),
('5120', 'الخصومات والجزاءات', 'expense');

-- 5. صلاحيات وإعدادات السلف
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.advance.approve', 'الموافقة على السلف', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code = 'hr.advance.approve';

INSERT IGNORE INTO settings (skey, svalue) VALUES
('hr_account_salary', '5100'),
('hr_account_cash', '1000'),
('hr_account_advances', '1150'),
('hr_account_bonuses', '5110'),
('hr_account_deductions', '5120');

-- 6. تعديل status ليدعم rejected إذا لم يكن مدعوماً
ALTER TABLE salary_advances MODIFY COLUMN status ENUM('pending','approved','rejected','paid','settled') DEFAULT 'pending';

SET FOREIGN_KEY_CHECKS=1;
