-- =====================================================
-- 127: تأمين المستحقات (Security Deposit) — استقطاع 25% لموظفين جدد
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- جدول سجل استقطاعات التأمين المستحقة لكل موظف
CREATE TABLE IF NOT EXISTS employee_security_deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_id INT NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('held','refunded') DEFAULT 'held',
    refunded_at TIMESTAMP NULL,
    refunded_by INT NULL,
    settlement_id INT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE CASCADE,
    FOREIGN KEY (refunded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_emp_payroll (employee_id, payroll_id),
    INDEX idx_employee_status (employee_id, status),
    INDEX idx_period (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إضافة عمود التأمين إلى جدول الرواتب (بطريقة متوافقة مع MySQL 8.0)
SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'security_deposit'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN security_deposit DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER advances',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
