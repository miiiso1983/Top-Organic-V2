-- =====================================================
-- 40: دقة قوالب التكلفة (هدر تراكمي + زيادة ناتج + كمية إجمالية)
-- =====================================================
SET NAMES utf8mb4;

SET @db = DATABASE();

-- ====== item_cost_sheets: هدر القسم على مستوى الترويسة ======
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='item_cost_sheets' AND column_name='waste_rate';
SET @sql = IF(@exists=0, "ALTER TABLE item_cost_sheets ADD COLUMN waste_rate DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT 'هدر القسم/التحضير (0.10 = 10%)' AFTER yield_unit_id", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ====== item_cost_sheet_lines: زيادة الناتج ======
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='item_cost_sheet_lines' AND column_name='yield_gain_rate';
SET @sql = IF(@exists=0, "ALTER TABLE item_cost_sheet_lines ADD COLUMN yield_gain_rate DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT 'زيادة الناتج بعد الطبخ (1.50 = 150%)' AFTER waste_rate", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ====== item_cost_sheet_lines: الكمية الإجمالية بالوحدة الأساسية ======
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='item_cost_sheet_lines' AND column_name='gross_qty';
SET @sql = IF(@exists=0, "ALTER TABLE item_cost_sheet_lines ADD COLUMN gross_qty DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'الكمية الإجمالية بالوحدة الأساسية بعد الهدر والزيادة' AFTER yield_gain_rate", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
