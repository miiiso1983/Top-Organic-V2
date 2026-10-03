-- =====================================================
-- 29: تصحيح أعمدة جدول العهدة (unit_id / warehouse_id)
-- يحل مشكلة "Unknown column 'unit_id' in 'INSERT INTO'" على خوادم MySQL
-- =====================================================
SET NAMES utf8mb4;
SET @db = DATABASE();

-- إضافة العمود unit_id إذا غير موجود
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
WHERE table_schema=@db AND table_name='custody' AND column_name='unit_id';
SET @sql = IF(@exists=0, 'ALTER TABLE custody ADD COLUMN unit_id INT NULL AFTER qty', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- إضافة العمود warehouse_id إذا غير موجود
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
WHERE table_schema=@db AND table_name='custody' AND column_name='warehouse_id';
SET @sql = IF(@exists=0, 'ALTER TABLE custody ADD COLUMN warehouse_id INT NULL AFTER unit_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- إضافة المفتاح الخارجي للوحدة
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.table_constraints
WHERE table_schema=@db AND table_name='custody' AND constraint_name='fk_custody_unit';
SET @sql = IF(@exists=0, 'ALTER TABLE custody ADD CONSTRAINT fk_custody_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- إضافة المفتاح الخارجي للمخزن
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.table_constraints
WHERE table_schema=@db AND table_name='custody' AND constraint_name='fk_custody_warehouse';
SET @sql = IF(@exists=0, 'ALTER TABLE custody ADD CONSTRAINT fk_custody_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
