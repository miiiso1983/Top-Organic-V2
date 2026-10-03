-- Migration 122: إضافة ربط طلبات التعيين والإيقاف بالـ Workflow
SET @db = DATABASE();

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'hiring_requests' AND COLUMN_NAME = 'workflow_request_id') = 0,
    'ALTER TABLE hiring_requests ADD COLUMN workflow_request_id INT NULL UNIQUE, ADD INDEX idx_hiring_workflow (workflow_request_id)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'suspension_requests' AND COLUMN_NAME = 'workflow_request_id') = 0,
    'ALTER TABLE suspension_requests ADD COLUMN workflow_request_id INT NULL UNIQUE, ADD INDEX idx_suspension_workflow (workflow_request_id)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
