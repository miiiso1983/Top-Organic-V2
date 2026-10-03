-- =====================================================
-- 44: وحدة الأصناف المحضرة (Prepared Items / Semi-finished Goods)
-- تأكد من وجود جداول BOM وقوالب التكلفة والأعمدة اللازمة
-- =====================================================
SET NAMES utf8mb4;

-- جدول رأس قالب التكلفة (BOM header)
CREATE TABLE IF NOT EXISTS item_cost_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    yield_qty DECIMAL(12,4) NOT NULL DEFAULT 1 COMMENT 'كمية الناتج من الوصفة',
    yield_unit_id INT NULL COMMENT 'وحدة الناتج',
    waste_rate DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT 'هدر القسم/التحضير (0.10 = 10%)',
    sale_price DECIMAL(12,4) NOT NULL DEFAULT 0,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (yield_unit_id) REFERENCES units(id) ON DELETE SET NULL,
    UNIQUE KEY uq_item_cost_sheet_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول سطور قالب التكلفة (BOM lines)
CREATE TABLE IF NOT EXISTS item_cost_sheet_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cost_sheet_id INT NOT NULL,
    ingredient_item_id INT NOT NULL COMMENT 'مادة المخزون المستخدمة',
    qty DECIMAL(16,6) NOT NULL COMMENT 'الكمية الصافية بالوحدة المختارة',
    unit_id INT NULL COMMENT 'وحدة الكمية المدخلة',
    waste_rate DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT 'هدر المادة الأولية',
    yield_gain_rate DECIMAL(8,4) NOT NULL DEFAULT 0 COMMENT 'زيادة الناتج بعد الطبخ',
    gross_qty DECIMAL(16,6) NOT NULL DEFAULT 0 COMMENT 'الكمية الإجمالية بالوحدة الأساسية',
    line_cost DECIMAL(16,6) NOT NULL DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (cost_sheet_id) REFERENCES item_cost_sheets(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- تأكد من وجود عمود standard_cost في items لضمان تحديث التكلفة المعيارية
SET @sql = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'items' AND column_name = 'standard_cost'),
    'SELECT 1',
    'ALTER TABLE items ADD COLUMN standard_cost DECIMAL(12,4) NOT NULL DEFAULT 0 AFTER cost_price'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- تأكد من وجود عمود item_type في items (نوع الصنف: raw/prepared/finished/...)
SET @sql = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'items' AND column_name = 'item_type'),
    'SELECT 1',
    "ALTER TABLE items ADD COLUMN item_type ENUM('raw','prepared','finished','packaging','service') DEFAULT 'raw' AFTER name_en"
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
