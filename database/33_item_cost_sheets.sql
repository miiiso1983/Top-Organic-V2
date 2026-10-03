-- =====================================================
-- 33: قوالب حساب تكلفة الأصناف (Item Cost Sheets)
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS item_cost_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL UNIQUE,
    yield_qty DECIMAL(12,4) NOT NULL DEFAULT 1 COMMENT 'عدد وحدات الناتج',
    yield_unit_id INT NULL COMMENT 'وحدة الناتج',
    sale_price DECIMAL(12,4) DEFAULT 0 COMMENT 'سعر البيع للوحدة الناتجة',
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (yield_unit_id) REFERENCES units(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_cost_sheet_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cost_sheet_id INT NOT NULL,
    ingredient_item_id INT NOT NULL,
    qty DECIMAL(12,4) NOT NULL COMMENT 'الكمية بالوحدة المختارة',
    unit_id INT NOT NULL,
    waste_rate DECIMAL(8,4) DEFAULT 0 COMMENT 'نسبة الهدر (0.10 = 10%)',
    line_cost DECIMAL(14,4) DEFAULT 0 COMMENT 'كلفة السطر بعد الهدر',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (cost_sheet_id) REFERENCES item_cost_sheets(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
