-- =====================================================
-- 53: HR Requests Fix - إضافة الأعمدة والجداول المفقودة بشكل آمن
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS add_lr_col(IN col VARCHAR(64), IN def VARCHAR(255))
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'leave_requests' AND column_name = col) THEN
        SET @sql = CONCAT('ALTER TABLE leave_requests ADD COLUMN ', col, ' ', def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE IF NOT EXISTS add_lr_index(IN idx VARCHAR(64), IN cols VARCHAR(255))
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'leave_requests' AND index_name = idx) THEN
        SET @sql = CONCAT('ALTER TABLE leave_requests ADD INDEX ', idx, ' (', cols, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE IF NOT EXISTS add_lr_fk(IN fk VARCHAR(64), IN col VARCHAR(64))
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'leave_requests' AND constraint_name = fk) THEN
        SET @sql = CONCAT('ALTER TABLE leave_requests ADD CONSTRAINT ', fk, ' FOREIGN KEY (', col, ') REFERENCES users(id) ON DELETE SET NULL');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

CREATE PROCEDURE IF NOT EXISTS create_lr_attachments()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'leave_request_attachments') THEN
        CREATE TABLE leave_request_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NULL,
            mime_type VARCHAR(100) NULL,
            file_size INT NULL,
            uploaded_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_request (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    END IF;
END$$

CREATE PROCEDURE IF NOT EXISTS add_lr_wf_step2()
BEGIN
    SET @wf_id = (SELECT id FROM legacy_approval_workflows WHERE entity_type = 'leave_request');
    IF @wf_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM legacy_approval_steps WHERE workflow_id = @wf_id AND step_order = 2) THEN
        INSERT INTO legacy_approval_steps (workflow_id, step_order, role_id, min_amount)
        SELECT @wf_id, 2, r.id, 0 FROM roles r WHERE r.name IN ('مدير عام','مدير النظام');
    END IF;
END$$

DELIMITER ;

CALL add_lr_col('request_type', "ENUM('leave','exit','delay','work_resumption') DEFAULT 'leave' AFTER leave_type");
CALL add_lr_col('start_time', "TIME NULL AFTER date_to");
CALL add_lr_col('end_time', "TIME NULL AFTER start_time");
CALL add_lr_col('requested_by', "INT NULL AFTER employee_id");
CALL add_lr_col('notes', "VARCHAR(500) NULL AFTER reason");

CALL add_lr_index('idx_request_type', 'request_type');
CALL add_lr_index('idx_status', 'status');
CALL add_lr_fk('fk_lr_requested_by', 'requested_by');

ALTER TABLE leave_requests MODIFY COLUMN leave_type ENUM('annual','sick','unpaid','emergency','hourly','n/a') DEFAULT 'annual';

CALL create_lr_attachments();
CALL add_lr_wf_step2();

DROP PROCEDURE IF EXISTS add_lr_col;
DROP PROCEDURE IF EXISTS add_lr_index;
DROP PROCEDURE IF EXISTS add_lr_fk;
DROP PROCEDURE IF EXISTS create_lr_attachments;
DROP PROCEDURE IF EXISTS add_lr_wf_step2;

INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.leave.request','تقديم طلبات الموارد البشرية','hr'),
('hr.leave.approve','اعتماد طلبات الموارد البشرية','hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم') AND p.code = 'hr.leave.approve';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي','محاسب','موظف','كاشير','شيف','أمين مخزن','مسؤول مشتريات') AND p.code = 'hr.leave.request';

SET FOREIGN_KEY_CHECKS=1;
