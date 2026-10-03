-- =====================================================
-- 128: التأمينات الشهرية + تقريب صافي الراتب
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- جدول التأمينات الشهرية للموظفين (ورقة تامينات Excel)
CREATE TABLE IF NOT EXISTS employee_monthly_insurance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_emp_insurance_period (employee_id, period),
    INDEX idx_period (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إضافة عمود التأمين إلى الرواتب
SET @insurance_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'insurance'
);
SET @sql = IF(@insurance_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN insurance DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER security_deposit',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود فرق التقريب إلى الرواتب
SET @rounding_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'rounding_adjustment'
);
SET @sql = IF(@rounding_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN rounding_adjustment DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER insurance',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
