-- =====================================================
-- 131: تذاكر السفر + التحميلات الشهرية في الرواتب
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- جدول تذاكر السفر للموظفين (ورقة تذاكر سفر Excel)
CREATE TABLE IF NOT EXISTS employee_travel_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    departure_date DATE NULL,
    return_date DATE NULL,
    duration INT NULL COMMENT 'مدة السفر بالأيام',
    travel_method VARCHAR(50) NULL COMMENT 'طريقة السفر',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_emp_period (employee_id, period),
    INDEX idx_period (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول التحميلات/الاستقطاعات الشهرية للموظفين (ورقة تحميلات Excel)
CREATE TABLE IF NOT EXISTS employee_monthly_loadings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    category VARCHAR(50) NOT NULL DEFAULT 'تحميلات' COMMENT 'تحميلات، تيبس، ضمان اجتماعي، سلف، سلف طويلة الاجل، أخرى',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_emp_period_category (employee_id, period, category),
    INDEX idx_period (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إضافة عمود تذاكر السفر إلى الرواتب
SET @tt_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'travel_tickets'
);
SET @sql = IF(@tt_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN travel_tickets DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER net_salary',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود التحميلات إلى الرواتب
SET @ld_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'loadings'
);
SET @sql = IF(@ld_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN loadings DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER travel_tickets',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
