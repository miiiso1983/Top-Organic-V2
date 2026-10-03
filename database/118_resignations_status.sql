-- =====================================================
-- 118: إضافة حالة "مستقيل" وإنشاء جدول الاستقالات
-- =====================================================
SET NAMES utf8mb4;

-- تعديل حالة الموظف لإضافة "مستقيل"
ALTER TABLE employees
MODIFY COLUMN status ENUM('active','vacation','suspended','terminated','resigned') DEFAULT 'active' NULL;

-- جدول الاستقالات والمخالصات
CREATE TABLE IF NOT EXISTS resignations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    resignation_date DATE NOT NULL,
    reason VARCHAR(500) NULL,
    days_worked DECIMAL(5,2) NOT NULL DEFAULT 0,
    daily_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_due DECIMAL(12,2) NOT NULL DEFAULT 0,
    custody_summary_json TEXT NULL COMMENT 'ملخص حالة تسليم العهد',
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
