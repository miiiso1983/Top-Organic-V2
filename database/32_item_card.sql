-- =====================================================
-- 32: بطاقة تعريف الصنف (Item Identification Card)
-- =====================================================
SET NAMES utf8mb4;

-- وصف الصنف/المواصفات
SET @addDesc = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'items' AND column_name = 'description'),
    'SELECT 1',
    'ALTER TABLE items ADD COLUMN description TEXT NULL COMMENT "وصف الصنف/المواصفات"'
));
PREPARE stmt FROM @addDesc; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- المستودع الرئيسي المرتبط
SET @addWh = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'items' AND column_name = 'main_warehouse_id'),
    'SELECT 1',
    'ALTER TABLE items ADD COLUMN main_warehouse_id INT NULL COMMENT "المستودع الرئيسي"'
));
PREPARE stmt FROM @addWh; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- قسم المطبخ المسؤول عن التحضير
SET @addKit = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'items' AND column_name = 'kitchen_section_id'),
    'SELECT 1',
    'ALTER TABLE items ADD COLUMN kitchen_section_id INT NULL COMMENT "قسم المطبخ المسؤول"'
));
PREPARE stmt FROM @addKit; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- مدة التحضير بالدقائق
SET @addPrep = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'items' AND column_name = 'prep_time_minutes'),
    'SELECT 1',
    'ALTER TABLE items ADD COLUMN prep_time_minutes INT NULL DEFAULT 0 COMMENT "مدة التحضير بالدقائق"'
));
PREPARE stmt FROM @addPrep; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- رقم بطاقة التعريف
SET @addCard = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'items' AND column_name = 'card_no'),
    'SELECT 1',
    'ALTER TABLE items ADD COLUMN card_no VARCHAR(30) NULL UNIQUE COMMENT "رقم بطاقة التعريف"'
));
PREPARE stmt FROM @addCard; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- مفاتيح خارجية
SET @addFkWh = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.table_constraints
            WHERE table_schema = DATABASE() AND table_name = 'items' AND constraint_name = 'fk_items_main_warehouse'),
    'SELECT 1',
    'ALTER TABLE items ADD CONSTRAINT fk_items_main_warehouse FOREIGN KEY (main_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL'
));
PREPARE stmt FROM @addFkWh; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @addFkKit = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.table_constraints
            WHERE table_schema = DATABASE() AND table_name = 'items' AND constraint_name = 'fk_items_kitchen_section'),
    'SELECT 1',
    'ALTER TABLE items ADD CONSTRAINT fk_items_kitchen_section FOREIGN KEY (kitchen_section_id) REFERENCES kitchen_sections(id) ON DELETE SET NULL'
));
PREPARE stmt FROM @addFkKit; EXECUTE stmt; DEALLOCATE PREPARE stmt;
