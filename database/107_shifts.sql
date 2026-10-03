-- =====================================================
-- 107: نظام الورديات (Shifts Management)
-- =====================================================
SET NAMES utf8mb4;

-- جدول الورديات الأساسية
CREATE TABLE IF NOT EXISTS shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL COMMENT 'اسم الوردية',
    start_time TIME NOT NULL COMMENT 'وقت البدء',
    end_time TIME NOT NULL COMMENT 'وقت الانتهاء',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ربط الموظف بوردية افتراضية
ALTER TABLE employees ADD COLUMN shift_id INT NULL COMMENT 'الوردية الافتراضية' AFTER work_end_time,
    ADD CONSTRAINT fk_employees_shift FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE SET NULL;

-- جدول الورديات الخاصة بيوم محدد (override)
CREATE TABLE IF NOT EXISTS employee_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    shift_date DATE NOT NULL COMMENT 'اليوم المحدد',
    shift_id INT NOT NULL COMMENT 'الوردية المطلوبة لهذا اليوم',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_emp_date (employee_id, shift_date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_emp_date (employee_id, shift_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إضافة حقول حساب التأخير والإضافي في الحضور
ALTER TABLE attendance
    ADD COLUMN late_minutes INT DEFAULT 0 COMMENT 'دقائق التأخير عن بدء الوردية',
    ADD COLUMN early_minutes INT DEFAULT 0 COMMENT 'دقائق الخروج المبكر',
    ADD COLUMN overtime_minutes INT DEFAULT 0 COMMENT 'دقائق العمل الإضافي';
