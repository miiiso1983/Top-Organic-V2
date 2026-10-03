-- =====================================================
-- 74: تصنيفات المواد في طلبات المواد والمشتريات
-- =====================================================
SET NAMES utf8mb4;

-- 1) توسيع تصنيفات المواد في item_categories
ALTER TABLE item_categories
    MODIFY COLUMN type ENUM(
        'raw',           -- مواد أولية
        'prepared',      -- مواد محضرة مسبقاً
        'operational',   -- مواد تشغيلية
        'asset',         -- موجودات وأصول
        'tool',          -- أدوات التشغيل
        'consumable',    -- مواد استهلاكية
        'packaging',     -- مواد تعبئة وتغليف
        'maintenance',   -- مواد صيانة
        'cleaning',      -- مواد نظافة
        'clothing',      -- ملابس
        'other'          -- أخرى
    ) DEFAULT 'raw' COMMENT 'تصنيف المادة';

-- 2) إضافة أعمدة المواد الجديدة في بنود طلب المواد
ALTER TABLE material_request_items
    MODIFY COLUMN item_id INT NULL COMMENT 'مادة موجودة (null إذا كانت مادة جديدة)',
    ADD COLUMN is_new_item TINYINT(1) DEFAULT 0 COMMENT 'هل المادة جديدة غير موجودة في النظام',
    ADD COLUMN new_item_name VARCHAR(150) NULL COMMENT 'اسم المادة الجديدة',
    ADD COLUMN new_item_category_id INT NULL COMMENT 'تصنيف المادة الجديدة',
    ADD COLUMN new_item_unit_id INT NULL COMMENT 'وحدة المادة الجديدة',
    ADD COLUMN new_item_notes TEXT NULL COMMENT 'ملاحظات المادة الجديدة',
    ADD FOREIGN KEY (new_item_category_id) REFERENCES item_categories(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (new_item_unit_id) REFERENCES units(id) ON DELETE SET NULL;

-- 2.1) ربط طلب المواد بطلب الشراء الناتج عن المواد الجديدة
ALTER TABLE material_requests
    ADD COLUMN purchase_request_id INT NULL COMMENT 'طلب الشراء المرتبط بالمواد الجديدة',
    ADD FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests(id) ON DELETE SET NULL;

-- 2.2) ربط بند طلب الشراء ببند طلب المواد لتحديث التلبية تلقائياً عند الاستلام
ALTER TABLE purchase_request_items
    ADD COLUMN material_request_item_id INT NULL COMMENT 'بند طلب المواد المرتبط',
    ADD FOREIGN KEY (material_request_item_id) REFERENCES material_request_items(id) ON DELETE SET NULL;

-- 3) إضافة تصنيفات افتراضية إذا لم تكن موجودة
INSERT IGNORE INTO item_categories (name, type) VALUES
('مواد أولية', 'raw'),
('مواد محضرة مسبقاً', 'prepared'),
('مواد تشغيلية', 'operational'),
('موجودات وأصول', 'asset'),
('أدوات تشغيل', 'tool'),
('مواد استهلاكية', 'consumable'),
('مواد تعبئة وتغليف', 'packaging'),
('مواد صيانة', 'maintenance'),
('مواد نظافة', 'cleaning'),
('ملابس', 'clothing'),
('أخرى', 'other');

-- 4) إضافة صلاحية إدارة تصنيفات المواد
INSERT IGNORE INTO permissions (code, name, module) VALUES
('item_categories.manage', 'إدارة تصنيفات المواد', 'inventory');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'مدير النظام' AND p.code = 'item_categories.manage';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير عام','مدير مطعم','أمين مخزن') AND p.code = 'item_categories.manage';
