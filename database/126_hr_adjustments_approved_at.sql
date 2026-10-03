-- =====================================================
-- 126: إضافة تاريخ الموافقة لجدول التسويات المالية
-- =====================================================
SET NAMES utf8mb4;

SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'hr_adjustments' AND column_name = 'approved_at'
);
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE hr_adjustments ADD COLUMN approved_at TIMESTAMP NULL AFTER approved_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
