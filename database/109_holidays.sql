-- =====================================================
-- 109: جدول العطل الرسمية (HR Holidays)
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS hr_holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NULL,
    day_of_week TINYINT NULL,
    name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_holidays_date (date),
    UNIQUE KEY uk_holidays_day_of_week (day_of_week),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- بذور افتراضية: الجمعة والسبت كعطل أسبوعية (يمكن لـ HR تعديلها لاحقاً)
INSERT IGNORE INTO hr_holidays (date, day_of_week, name, is_active) VALUES
(NULL, 5, 'يوم الجمعة', 1),
(NULL, 6, 'يوم السبت', 1);
