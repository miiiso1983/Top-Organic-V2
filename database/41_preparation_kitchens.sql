-- =====================================================
-- 41: مراكز التحضير (Preparation Kitchens)
-- توجيه كل صنف منيو إلى مطبخ تحضير معين، وعرضها في شاشات KDS منفصلة
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS preparation_kitchens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL COMMENT 'اسم مطبخ التحضير',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ربط صنف المنيو بمطبخ التحضير
SET @addMenuPrep = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'menu_items' AND column_name = 'preparation_kitchen_id'),
    'SELECT 1',
    'ALTER TABLE menu_items ADD COLUMN preparation_kitchen_id INT NULL AFTER kitchen_section_id'
));
PREPARE stmt FROM @addMenuPrep;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- تخزين مطبخ التحضير في سطر الطلب لضمان التوجيه الصحيح بعد التعديل المستقبلي
SET @addOrderPrep = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'order_items' AND column_name = 'preparation_kitchen_id'),
    'SELECT 1',
    'ALTER TABLE order_items ADD COLUMN preparation_kitchen_id INT NULL AFTER kitchen_section_id'
));
PREPARE stmt FROM @addOrderPrep;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
