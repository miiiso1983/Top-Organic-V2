-- =====================================================
-- 12: إدارة قوائم الطعام المتعددة + الطوابق/الصالات
-- (ملف يُنفَّذ مرة واحدة)
-- =====================================================
SET NAMES utf8mb4;

-- الطوابق / الصالات في المطعم
CREATE TABLE IF NOT EXISTS floors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NULL,
    name VARCHAR(80) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ربط كل طاولة بطابق/صالة
ALTER TABLE dining_tables ADD COLUMN floor_id INT NULL AFTER branch_id;

-- القوائم (يدعم تعدد المنيوهات: صالة، سفري، رمضان...)
CREATE TABLE IF NOT EXISTS menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ربط كل قسم بمنيو معيّن
ALTER TABLE menu_categories ADD COLUMN menu_id INT NULL AFTER id;

-- تعيين منيو محدد لكل طابق/صالة (منيو واحد أو أكثر لكل طابق)
CREATE TABLE IF NOT EXISTS menu_floor_assignments (
    menu_id INT NOT NULL,
    floor_id INT NOT NULL,
    PRIMARY KEY (menu_id, floor_id),
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
    FOREIGN KEY (floor_id) REFERENCES floors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== توافق رجعي: منيو افتراضي + طابق افتراضي لأي بيانات موجودة مسبقاً =====

-- إنشاء المنيو الافتراضي إن لم يوجد
INSERT INTO menus (name, is_default, is_active, sort_order)
SELECT 'المنيو الرئيسي', 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM menus WHERE is_default = 1);

-- ربط كل الأقسام الحالية بالمنيو الافتراضي
UPDATE menu_categories
SET menu_id = (SELECT id FROM menus WHERE is_default = 1 LIMIT 1)
WHERE menu_id IS NULL;

-- إنشاء طابق افتراضي إن لم توجد طوابق بعد
INSERT INTO floors (name, sort_order)
SELECT 'الصالة الرئيسية', 0
WHERE NOT EXISTS (SELECT 1 FROM floors);

-- ربط الطاولات الحالية التي بلا طابق بالطابق الافتراضي
UPDATE dining_tables
SET floor_id = (SELECT id FROM floors ORDER BY id LIMIT 1)
WHERE floor_id IS NULL;

-- تعيين المنيو الافتراضي لكل الطوابق الحالية
INSERT IGNORE INTO menu_floor_assignments (menu_id, floor_id)
SELECT (SELECT id FROM menus WHERE is_default = 1 LIMIT 1), id FROM floors;

-- قيود مرجعية بعد استكمال تعبئة البيانات (مع إزالة القيود القديمة إن وُجدت لتجنب التكرار)
ALTER TABLE menu_categories DROP FOREIGN KEY fk_menu_categories_menu;
ALTER TABLE menu_categories ADD CONSTRAINT fk_menu_categories_menu
    FOREIGN KEY (menu_id) REFERENCES menus(id);
ALTER TABLE dining_tables DROP FOREIGN KEY fk_dining_tables_floor;
ALTER TABLE dining_tables ADD CONSTRAINT fk_dining_tables_floor
    FOREIGN KEY (floor_id) REFERENCES floors(id);
