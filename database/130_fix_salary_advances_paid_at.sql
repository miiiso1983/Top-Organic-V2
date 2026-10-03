-- =====================================================
-- 130: إصلاح عمود paid_at في جدول salary_advances
-- (تصحيح للميغراتين 46 و 54 اللذين يستخدمان IF NOT EXISTS غير مدعوم في MySQL 8)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'salary_advances' AND column_name = 'paid_at'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE salary_advances ADD COLUMN paid_at TIMESTAMP NULL AFTER approved_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
