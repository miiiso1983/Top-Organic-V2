-- =====================================================
-- 16: نظام مخزن السوبرماركت
-- توسيعات على المخزون والعهد والتصنيع لدعم الباركود والأقسام والتقارير
-- =====================================================
SET NAMES utf8mb4;

-- تمييز المخازن عن الأقسام (تصنيع، مبيعات، عادي)
ALTER TABLE warehouses ADD COLUMN warehouse_type
    ENUM('warehouse','department','sales','manufacturing') DEFAULT 'warehouse' NOT NULL
    COMMENT 'warehouse: مخزن، department: قسم، sales: قسم مبيعات، manufacturing: قسم تصنيع';
UPDATE warehouses SET warehouse_type = 'warehouse' WHERE warehouse_type IS NULL;

-- فهرس للبحث السريع بالباركود
DROP INDEX idx_items_barcode ON items;
CREATE INDEX idx_items_barcode ON items(barcode);

-- تتبع الوحدة المدخلة في حركات المخزون (اختياري)
ALTER TABLE stock_movements ADD COLUMN input_unit_id INT NULL AFTER qty;
ALTER TABLE stock_movements DROP FOREIGN KEY fk_sm_unit;
ALTER TABLE stock_movements ADD CONSTRAINT fk_sm_unit
    FOREIGN KEY (input_unit_id) REFERENCES units(id) ON DELETE SET NULL;

-- تتبع الوحدة في بنود التحويلات
ALTER TABLE stock_transfer_items ADD COLUMN unit_id INT NULL AFTER qty;
ALTER TABLE stock_transfer_items DROP FOREIGN KEY fk_sti_unit;
ALTER TABLE stock_transfer_items ADD CONSTRAINT fk_sti_unit
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL;

-- وصفات تصنيع داخلية (مدخلات -> مادة ناتجة واحدة)
CREATE TABLE IF NOT EXISTS production_recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    output_item_id INT NOT NULL,
    output_qty DECIMAL(12,4) NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (output_item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS production_recipe_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    item_id INT NOT NULL,
    qty DECIMAL(12,4) NOT NULL,
    FOREIGN KEY (recipe_id) REFERENCES production_recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إضافة بيانات أولية للأقسام إن لم تكن موجودة
INSERT IGNORE INTO warehouses (branch_id, name, location, warehouse_type) VALUES
(1, 'قسم التصنيع', 'المصنع', 'manufacturing'),
(1, 'قسم المبيعات', 'منطقة البيع', 'sales');

-- صلاحيات جوال إضافية (مخصصة للتطبيق)
INSERT IGNORE INTO permissions (code, name, module) VALUES
('inventory.mobile','عمليات المخزن من الجوال','inventory'),
('custody.mobile','عمليات العهدة من الجوال','inventory'),
('production.mobile','عمليات التصنيع من الجوال','inventory');

-- منح الصلاحيات الجوالة للأدوار ذات الصلة
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','أمين مخزن','شيف') AND p.code IN ('inventory.mobile','custody.mobile','production.mobile');

-- إضافة أعمدة في جدول العهدة للمخزن والوحدة
ALTER TABLE custody ADD COLUMN warehouse_id INT NULL AFTER item_id;
ALTER TABLE custody ADD COLUMN unit_id INT NULL AFTER qty;
ALTER TABLE custody DROP FOREIGN KEY fk_custody_warehouse;
ALTER TABLE custody ADD CONSTRAINT fk_custody_warehouse
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;
ALTER TABLE custody DROP FOREIGN KEY fk_custody_unit;
ALTER TABLE custody ADD CONSTRAINT fk_custody_unit
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL;

-- إضافة وحدات أساسية إن لم تكن موجودة
INSERT IGNORE INTO units (name, base_unit_id, factor) VALUES
('حبة', NULL, 1),
('غرام', NULL, 1),
('مليليتر', NULL, 1);

INSERT IGNORE INTO units (name, base_unit_id, factor)
SELECT 'كيلوغرام', id, 1000 FROM units WHERE name = 'غرام';

INSERT IGNORE INTO units (name, base_unit_id, factor)
SELECT 'ليتر', id, 1000 FROM units WHERE name = 'مليليتر';

-- إضافة أقسام التصنيع والمبيعات إن لم تكن موجودة
INSERT IGNORE INTO warehouses (branch_id, name, location, warehouse_type) VALUES
(1, 'قسم التصنيع', 'المصنع', 'manufacturing'),
(1, 'قسم المبيعات', 'منطقة البيع', 'sales');
