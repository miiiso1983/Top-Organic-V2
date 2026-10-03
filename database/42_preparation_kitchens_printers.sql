-- =====================================================
-- 42: ربط مراكز التحضير بطابعات محددة
-- يُستخدم لطباعة البون الموجه إلى كل مركز تحضير
-- =====================================================
SET NAMES utf8mb4;

SET @addPrinter = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'preparation_kitchens' AND column_name = 'printer_name'),
    'SELECT 1',
    'ALTER TABLE preparation_kitchens ADD COLUMN printer_name VARCHAR(120) NULL COMMENT "اسم الطابعة في نظام التشغيل"'
));
PREPARE stmt FROM @addPrinter;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
