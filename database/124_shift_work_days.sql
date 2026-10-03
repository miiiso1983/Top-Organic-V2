SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- جدول أيام العمل للوردية
CREATE TABLE IF NOT EXISTS shift_work_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=الأحد، 1=الإثنين، 2=الثلاثاء، 3=الأربعاء، 4=الخميس، 5=الجمعة، 6=السبت',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shift_day (shift_id, day_of_week),
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    INDEX idx_shift (shift_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- تعبئة أيام العمل الافتراضية للورديات الموجودة (السبت والجمعة عطلة، باقي الأيام عمل)
INSERT IGNORE INTO shift_work_days (shift_id, day_of_week)
SELECT s.id, d.day
FROM shifts s
JOIN (
    SELECT 0 AS day UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
) d ON 1=1
WHERE NOT EXISTS (
    SELECT 1 FROM shift_work_days swd WHERE swd.shift_id = s.id
);

SET FOREIGN_KEY_CHECKS=1;
