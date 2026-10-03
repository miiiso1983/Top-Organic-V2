-- =====================================================
-- 92: ربط كل صالة (floor) بمطبخ تحضير محدد
-- يُستخدم لتوجيه طلبات الصالة آلياً إلى المطبخ المرتبط
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- إضافة عموم مطبخ التحضير إلى جدول الصالات/الطوابق
ALTER TABLE floors
    ADD COLUMN IF NOT EXISTS preparation_kitchen_id INT NULL AFTER sort_order,
    ADD INDEX IF NOT EXISTS idx_floors_prep_kitchen (preparation_kitchen_id);

-- ربط خارجي اختياري
SET @addFk = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.table_constraints
            WHERE table_schema = DATABASE() AND table_name = 'floors' AND constraint_name = 'fk_floors_prep_kitchen'),
    'SELECT 1',
    'ALTER TABLE floors ADD CONSTRAINT fk_floors_prep_kitchen FOREIGN KEY (preparation_kitchen_id) REFERENCES preparation_kitchens(id) ON DELETE SET NULL'
));
PREPARE stmt FROM @addFk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
