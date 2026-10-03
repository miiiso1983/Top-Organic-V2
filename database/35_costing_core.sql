-- =====================================================
-- 35: توسيع بنية الأصناف والوحدات (Costing Core)
-- آمنة ومتوافقة: لا تكسر البيانات الحالية
-- =====================================================
SET NAMES utf8mb4;

SET @db = DATABASE();

-- ========= توسيع جدول items =========
-- code
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='items' AND column_name='code';
SET @sql = IF(@exists=0, "ALTER TABLE items ADD COLUMN code VARCHAR(60) NULL AFTER id", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- فهرس/فريد على code
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.statistics
 WHERE table_schema=@db AND table_name='items' AND index_name='uq_items_code';
SET @sql = IF(@exists=0, "ALTER TABLE items ADD UNIQUE KEY uq_items_code (code)", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- name_en
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='items' AND column_name='name_en';
SET @sql = IF(@exists=0, "ALTER TABLE items ADD COLUMN name_en VARCHAR(150) NULL AFTER name", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- item_type
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='items' AND column_name='item_type';
SET @sql = IF(@exists=0,
  "ALTER TABLE items ADD COLUMN item_type ENUM('raw','prepared','finished','packaging','service') DEFAULT 'raw' AFTER name_en",
  "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- purchase_unit_id
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='items' AND column_name='purchase_unit_id';
SET @sql = IF(@exists=0, "ALTER TABLE items ADD COLUMN purchase_unit_id INT NULL AFTER unit_id", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- recipe_unit_id
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='items' AND column_name='recipe_unit_id';
SET @sql = IF(@exists=0, "ALTER TABLE items ADD COLUMN recipe_unit_id INT NULL AFTER purchase_unit_id", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- conversion_factor (من recipe_unit إلى الوحدة الأساسية item.unit_id)
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='items' AND column_name='conversion_factor';
SET @sql = IF(@exists=0, "ALTER TABLE items ADD COLUMN conversion_factor DECIMAL(12,6) NOT NULL DEFAULT 1 AFTER recipe_unit_id", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- standard_cost
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='items' AND column_name='standard_cost';
SET @sql = IF(@exists=0, "ALTER TABLE items ADD COLUMN standard_cost DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER cost_price", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- مفاتيح أجنبية اختيارية للوحدات الجديدة
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.table_constraints
 WHERE table_schema=@db AND table_name='items' AND constraint_name='fk_items_purchase_unit';
SET @sql = IF(@exists=0, "ALTER TABLE items ADD CONSTRAINT fk_items_purchase_unit FOREIGN KEY (purchase_unit_id) REFERENCES units(id) ON DELETE SET NULL", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.table_constraints
 WHERE table_schema=@db AND table_name='items' AND constraint_name='fk_items_recipe_unit';
SET @sql = IF(@exists=0, "ALTER TABLE items ADD CONSTRAINT fk_items_recipe_unit FOREIGN KEY (recipe_unit_id) REFERENCES units(id) ON DELETE SET NULL", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========= توسيع جدول units =========
-- symbol
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='units' AND column_name='symbol';
SET @sql = IF(@exists=0, "ALTER TABLE units ADD COLUMN symbol VARCHAR(16) NULL AFTER name", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
