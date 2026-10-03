-- =====================================================
-- 23: Enhance dining_tables with floor, hall, capacity
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE dining_tables
    ADD COLUMN IF NOT EXISTS floor VARCHAR(50) NULL COMMENT 'رقم أو اسم الطابق' AFTER floor_id,
    ADD COLUMN IF NOT EXISTS hall VARCHAR(100) NULL COMMENT 'اسم الصالة أو القاعة' AFTER floor,
    ADD COLUMN IF NOT EXISTS capacity INT NOT NULL DEFAULT 4 COMMENT 'سعة الطاولة (عدد الكراسي)' AFTER hall;

-- رفع البيانات الموجودة: سعة = المقاعد
UPDATE dining_tables SET capacity = seats WHERE capacity = 4 AND seats IS NOT NULL;

-- رفع البيانات الموجودة: اسم الطابق من جدول floors
UPDATE dining_tables t
    LEFT JOIN floors f ON f.id = t.floor_id
    SET t.floor = f.name
    WHERE t.floor_id IS NOT NULL AND t.floor IS NULL;

SET FOREIGN_KEY_CHECKS=1;
