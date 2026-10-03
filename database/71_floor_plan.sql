-- =====================================================
-- 71: Floor Plan Enhancement - تحسين مخطط الطاولات
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. دعم دمج الطاولات
ALTER TABLE dining_tables
    ADD COLUMN IF NOT EXISTS parent_table_id INT NULL,
    ADD COLUMN IF NOT EXISTS merged_capacity INT NULL,
    ADD INDEX IF NOT EXISTS idx_parent_table (parent_table_id);

-- 2. تحديث حالة الطاولة لتشمل merged
ALTER TABLE dining_tables
    MODIFY COLUMN status ENUM('available','occupied','reserved','cleaning','bill_requested','merged') DEFAULT 'available';

-- 3. إعدادات مخطط الطاولات
INSERT INTO settings (skey, svalue) VALUES
('floor_plan_auto_assign', '1'),
('floor_plan_vip_priority', '1'),
('floor_plan_merge_radius', '120')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- 4. صلاحيات جديدة
INSERT IGNORE INTO permissions (code, name, module) VALUES
('floor_plan.manage', 'إدارة مخطط الطاولات', 'sales'),
('floor_plan.merge', 'دمج وتقسيم الطاولات', 'sales');

SET FOREIGN_KEY_CHECKS=1;
