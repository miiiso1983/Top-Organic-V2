-- =====================================================
-- 111: عدد أيام العطل الشهرية (Monthly Holiday Days Override)
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS hr_holiday_months (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL,
    month INT NOT NULL,
    days INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_year_month (year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
