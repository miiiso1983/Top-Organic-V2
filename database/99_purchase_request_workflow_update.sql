SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

SET @db = DATABASE();

-- إضافة علم لتحديد الأصناف المتاحة في طلبات الشراء
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'items' AND COLUMN_NAME = 'available_for_purchase_request') = 0,
    'ALTER TABLE items ADD COLUMN available_for_purchase_request TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- تعيين جميع الأصناف النشطة لتكون متاحة افتراضياً لطلبات الشراء
UPDATE items SET available_for_purchase_request = 1 WHERE is_active = 1;

-- 1) تحديث قالب طلب الشراء ليحتوي على قائمة الأصناف والمرفقات بدلاً من المورد والمبلغ الإجمالي
UPDATE approval_templates
SET fields_json = '[
  {"name":"department_id","label":"القسم","type":"select","required":true},
  {"name":"items","label":"الأصناف المطلوبة","type":"items_list","required":true},
  {"name":"reason","label":"سبب الشراء","type":"textarea","required":true},
  {"name":"urgent","label":"طلب عاجل","type":"checkbox","required":false},
  {"name":"attachment","label":"المرفقات","type":"file","required":false}
]',
    amount_field = NULL
WHERE code = 'purchase_request';

-- 2) إضافة روابط/أعمدة مطلوبة لطلب الشراء
SET @db = DATABASE();

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'workflow_request_id') = 0,
    'ALTER TABLE purchase_requests ADD COLUMN workflow_request_id INT NULL AFTER cost_center_id, ADD INDEX idx_pr_workflow (workflow_request_id)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'purchase_request_items' AND COLUMN_NAME = 'material_request_item_id') = 0,
    'ALTER TABLE purchase_request_items ADD COLUMN material_request_item_id INT NULL AFTER notes',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) إعادة ضبط خطوات موافقة طلب الشراء: 3 مستويات تسلسلية
DELETE FROM approval_steps WHERE template_id = (SELECT id FROM approval_templates WHERE code = 'purchase_request' LIMIT 1);

SET @purchase_template_id = (SELECT id FROM approval_templates WHERE code = 'purchase_request' LIMIT 1);
SET @admin_role_id = (SELECT id FROM roles WHERE name = 'مدير النظام' LIMIT 1);
SET @manager_role_id = (SELECT id FROM roles WHERE name = 'مدير عام' LIMIT 1);
SET @finance_role_id = (SELECT id FROM roles WHERE name = 'مدير مالي' LIMIT 1);
SET @restaurant_role_id = (SELECT id FROM roles WHERE name = 'مدير مطعم' LIMIT 1);

INSERT IGNORE INTO approval_steps (template_id, step_order, title, approver_type, description, role_id, can_return_for_edit, is_final, step_type, required_count, notify_channels)
VALUES
  (@purchase_template_id, 1, 'موافقة المدير المباشر', 'direct_manager', 'مراجعة الطلب من قبل المدير المباشر للموظف', NULL, 1, 0, 'sequential', 1, '{"push":1}'),
  (@purchase_template_id, 2, 'موافقة القسم المالي', 'role', 'مراجعة الميزانية والتكلفة من قبل المدير المالي', @finance_role_id, 1, 0, 'sequential', 1, '{"push":1}'),
  (@purchase_template_id, 3, 'موافقة إدارة المطعم', 'role', 'الاعتماد النهائي من قبل إدارة المطعم', @restaurant_role_id, 1, 1, 'sequential', 1, '{"push":1}');

-- 4) ضمان صلاحيات الإنشاء والموافقة
INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT @purchase_template_id, id, 1, 0 FROM roles WHERE name IN ('مسؤول مشتريات','أمين مخزن','كاشير','شيف','موظف');

INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT @purchase_template_id, id, 0, 1 FROM roles WHERE name IN ('مدير عام','مدير مالي','مدير مطعم');

SET FOREIGN_KEY_CHECKS=1;
