SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- منع تكرار طلب العمل الإضافي لنفس الموظف ونفس اليوم على مستوى قاعدة البيانات
SET @db = DATABASE();
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.statistics
 WHERE table_schema=@db AND table_name='overtime_requests' AND index_name='uniq_overtime_employee_date';
SET @sql = IF(@exists=0, 'ALTER TABLE overtime_requests ADD UNIQUE KEY uniq_overtime_employee_date (employee_id, request_date)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
