-- =====================================================
-- 37: تعزيز جداول الوصفات والمكونات
-- =====================================================
SET NAMES utf8mb4;

SET @db = DATABASE();

-- ====== recipes: إضافة حقول اختيارية ======
-- yield_unit_id
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='recipes' AND column_name='yield_unit_id';
SET @sql = IF(@exists=0, "ALTER TABLE recipes ADD COLUMN yield_unit_id INT NULL AFTER yield_qty", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- portions
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='recipes' AND column_name='portions';
SET @sql = IF(@exists=0, "ALTER TABLE recipes ADD COLUMN portions INT NOT NULL DEFAULT 1 AFTER yield_unit_id", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- department_id (ربط بأقسام المطبخ إن وُجدت)
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='recipes' AND column_name='department_id';
SET @sql = IF(@exists=0, "ALTER TABLE recipes ADD COLUMN department_id INT NULL AFTER portions", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- version_no
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='recipes' AND column_name='version_no';
SET @sql = IF(@exists=0, "ALTER TABLE recipes ADD COLUMN version_no INT NOT NULL DEFAULT 1 AFTER notes", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- مفاتيح أجنبية اختيارية
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.table_constraints
 WHERE table_schema=@db AND table_name='recipes' AND constraint_name='fk_recipes_yield_unit';
SET @sql = IF(@exists=0, "ALTER TABLE recipes ADD CONSTRAINT fk_recipes_yield_unit FOREIGN KEY (yield_unit_id) REFERENCES units(id) ON DELETE SET NULL", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.table_constraints
 WHERE table_schema=@db AND table_name='recipes' AND constraint_name='fk_recipes_department';
SET @sql = IF(@exists=0, "ALTER TABLE recipes ADD CONSTRAINT fk_recipes_department FOREIGN KEY (department_id) REFERENCES kitchen_sections(id) ON DELETE SET NULL", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ====== recipe_ingredients: أعمدة تفصيلية ======
-- unit_id
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='recipe_ingredients' AND column_name='unit_id';
SET @sql = IF(@exists=0, "ALTER TABLE recipe_ingredients ADD COLUMN unit_id INT NULL AFTER qty", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- waste_percent
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='recipe_ingredients' AND column_name='waste_percent';
SET @sql = IF(@exists=0, "ALTER TABLE recipe_ingredients ADD COLUMN waste_percent DECIMAL(6,3) NOT NULL DEFAULT 0 AFTER unit_id", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- department_waste_percent
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='recipe_ingredients' AND column_name='department_waste_percent';
SET @sql = IF(@exists=0, "ALTER TABLE recipe_ingredients ADD COLUMN department_waste_percent DECIMAL(6,3) NOT NULL DEFAULT 0 AFTER waste_percent", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- output_waste_percent
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='recipe_ingredients' AND column_name='output_waste_percent';
SET @sql = IF(@exists=0, "ALTER TABLE recipe_ingredients ADD COLUMN output_waste_percent DECIMAL(6,3) NOT NULL DEFAULT 0 AFTER department_waste_percent", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- notes
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns
 WHERE table_schema=@db AND table_name='recipe_ingredients' AND column_name='notes';
SET @sql = IF(@exists=0, "ALTER TABLE recipe_ingredients ADD COLUMN notes VARCHAR(255) NULL AFTER output_waste_percent", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK للوحدة
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.table_constraints
 WHERE table_schema=@db AND table_name='recipe_ingredients' AND constraint_name='fk_recipe_ingredients_unit';
SET @sql = IF(@exists=0, "ALTER TABLE recipe_ingredients ADD CONSTRAINT fk_recipe_ingredients_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL", "SELECT 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
