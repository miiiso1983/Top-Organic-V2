-- =====================================================
-- 98: PTT مزامنة تلقائية للأعضاء حسب القسم
-- =====================================================
SET NAMES utf8mb4;

DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS EnsureColumnExists(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = p_table
          AND column_name = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE ', p_table, ' ADD COLUMN ', p_column, ' ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

CALL EnsureColumnExists('ptt_channels', 'auto_sync', 'TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'مزامنة تلقائية لأعضاء القسم\' AFTER department');

INSERT IGNORE INTO permissions (code, name, module) VALUES
('ptt.sync_departments', 'مزامنة أعضاء قنوات PTT حسب القسم', 'ptt');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'مدير النظام' AND p.code = 'ptt.sync_departments';
