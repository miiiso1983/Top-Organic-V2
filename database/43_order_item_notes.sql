-- =====================================================
-- 43: ملاحظات خاصة بكل صنف في order_items
-- يتيح للكابتن إضافة ملاحظة لكل صنف (بدون بصل، إكسترا صوص، ...)
-- وتظهر في وصولات المطبخ المركزي وأقسام التحضير
-- =====================================================
SET NAMES utf8mb4;

SET @addOrderItemNotes = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'order_items' AND column_name = 'notes'),
    'SELECT 1',
    'ALTER TABLE order_items ADD COLUMN notes VARCHAR(255) NULL AFTER total'
));
PREPARE stmt FROM @addOrderItemNotes;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
