SET NAMES utf8mb4;

-- 1. إضافة عمود نوع المعتمد لخطوات سير العمل (إن لم يكن موجوداً)
SET @dbname = DATABASE();
SET @tablename = 'approval_steps';
SET @columnname = 'approver_type';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) = 0,
  'ALTER TABLE approval_steps ADD COLUMN approver_type ENUM(''position'', ''role'', ''direct_manager'', ''department_manager'') NOT NULL DEFAULT ''position'' AFTER title;',
  'SELECT 1;'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. تحديث الخطوات الموجودة لتحديد نوع المعتمد
UPDATE approval_steps SET approver_type = 'position' WHERE job_title_id IS NOT NULL;
UPDATE approval_steps SET approver_type = 'role' WHERE job_title_id IS NULL AND role_id IS NOT NULL;

-- 3. حذف خطوات قالب الإجازة (رقم 2) القديمة لإعادة بنائها
DELETE FROM approval_steps WHERE template_id = 2;

-- 4. إنشاء خطوات قالب الإجازة الجديدة
--    إذا كانت الإجازة <= 5 أيام: المدير المباشر -> HR Coordinator
--    إذا كانت الإجازة > 5 أيام: المدير المباشر -> مدير القسم -> HR Coordinator
INSERT INTO approval_steps
    (template_id, step_order, title, approver_type, description, job_title_id, role_id, min_amount, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
VALUES
    (2, 1, 'موافقة المدير المباشر', 'direct_manager', NULL, NULL, NULL, 0, '{}', 1, 0, 'sequential', 1, '{"push":1}'),
    (2, 2, 'موافقة مدير القسم', 'department_manager', NULL, NULL, NULL, 0, '{"leave_days":{">":5}}', 1, 0, 'sequential', 1, '{"push":1}'),
    (2, 3, 'موافقة HR Coordinator', 'position', NULL, 35, NULL, 0, '{}', 1, 1, 'sequential', 1, '{"push":1}');

-- 5. تعديل الطلبات المعلّقة الحالية لتتناسب مع الترتيب الجديد
--    إذا كانت الإجازة <= 5 أيام وكانت في الخطوة القديمة 2 (HR)، تصبح الخطوة 3 (HR)
--    إذا كانت > 5 أيام تبقى في الخطوة 2 (مدير القسم)
UPDATE requests
SET current_step = CASE
    WHEN DATEDIFF(
        JSON_UNQUOTE(JSON_EXTRACT(data_json, '$.date_to')),
        JSON_UNQUOTE(JSON_EXTRACT(data_json, '$.date_from'))
    ) + 1 <= 5 THEN 3
    ELSE 2
END
WHERE template_id = 2 AND status = 'pending' AND current_step = 2;
