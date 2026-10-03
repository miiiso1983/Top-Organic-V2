-- =====================================================
-- 30: نظام توزيع الطلبات على أقسام المطبخ (Kitchen Routing)
-- =====================================================
SET NAMES utf8mb4;

-- أقسام المطبخ (Kitchen Sections): مطبخ شرقي، أراكيل، مشروبات...
CREATE TABLE IF NOT EXISTS kitchen_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NULL,
    name VARCHAR(80) NOT NULL COMMENT 'اسم قسم المطبخ',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ربط كل صنف منيو بقسم المطبخ المسؤول عنه
SET @addMenuSection = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'menu_items' AND column_name = 'kitchen_section_id'),
    'SELECT 1',
    'ALTER TABLE menu_items ADD COLUMN kitchen_section_id INT NULL AFTER station_id'
));
PREPARE stmt FROM @addMenuSection;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- تخزين قسم المطبخ في صنف الطلب لضمان التوجيه الصحيح
SET @addOrderSection = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'order_items' AND column_name = 'kitchen_section_id'),
    'SELECT 1',
    'ALTER TABLE order_items ADD COLUMN kitchen_section_id INT NULL AFTER station_id'
));
PREPARE stmt FROM @addOrderSection;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ترحيل بيانات محطات المطبخ القديمة (kitchen_stations) إلى الأقسام الجديدة
INSERT INTO kitchen_sections (branch_id, name, is_active, sort_order)
SELECT branch_id, name, is_active, id
FROM kitchen_stations
WHERE NOT EXISTS (SELECT 1 FROM kitchen_sections ks WHERE ks.name = kitchen_stations.name);

-- ربط الأصناف المرتبطة بمحطات المطبخ القديمة بالأقسام الجديدة
UPDATE menu_items mi
SET kitchen_section_id = (SELECT ks.id FROM kitchen_sections ks
                          JOIN kitchen_stations st ON st.name = ks.name
                          WHERE st.id = mi.station_id LIMIT 1)
WHERE mi.station_id IS NOT NULL AND mi.kitchen_section_id IS NULL;
