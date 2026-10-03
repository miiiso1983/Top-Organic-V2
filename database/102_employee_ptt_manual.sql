SET NAMES utf8mb4;

-- علم للعضويات المضافة يدوياً من ملف الموظف
SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ptt_channel_members' AND COLUMN_NAME = 'manual') = 0, 'ALTER TABLE ptt_channel_members ADD COLUMN `manual` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ptt_channel_members' AND INDEX_NAME = 'idx_ptt_channel_members_manual') = 0, 'CREATE INDEX idx_ptt_channel_members_manual ON ptt_channel_members(user_id, `manual`)', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
