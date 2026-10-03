-- Migration 121: إضافة قوالب Workflow لطلب التعيين وإيقاف الدوام

SET @manager_roles = '23,24,25,4,3,2,1';
SET @hr_roles = '25,26';
SET @finance_roles = '3,5';

INSERT INTO approval_templates (code, name, description, icon, fields_json, is_active, created_by)
VALUES (
  'hiring_request',
  'طلب تعيين موظف جديد',
  'طلب تعيين موظف جديد مع الموافقة المتدرجة: مدير مباشر → HR → مالية',
  '<i class="fa-solid fa-user-plus"></i>',
  '[{"name":"job_title","type":"text","label":"المسمى الوظيفي","required":true},{"name":"department_id","type":"select","label":"القسم","required":true,"options":[]},{"name":"expected_salary","type":"number","label":"الراتب المتوقع","required":true},{"name":"reason","type":"textarea","label":"سبب التعيين","required":true},{"name":"notes","type":"textarea","label":"ملاحظات","required":false}]',
  1,
  1
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name), description = VALUES(description), icon = VALUES(icon), fields_json = VALUES(fields_json), is_active = 1;

SET @hiring_tpl = (SELECT id FROM approval_templates WHERE code = 'hiring_request' LIMIT 1);

INSERT INTO approval_templates (code, name, description, icon, fields_json, is_active, created_by)
VALUES (
  'suspension_request',
  'طلب إيقاف دوام موظف',
  'طلب إيقاف مؤقت أو نهائي لدوام موظف مع الموافقة المتدرجة: مدير مباشر → HR → مالية',
  '<i class="fa-solid fa-user-slash"></i>',
  '[{"name":"employee_id","type":"employee","label":"الموظف","required":true},{"name":"suspension_date","type":"date","label":"تاريخ الإيقاف","required":true},{"name":"suspension_type","type":"select","label":"نوع الإيقاف","required":true,"options":["مؤقت","نهائي"]},{"name":"reason","type":"textarea","label":"سبب الإيقاف","required":true},{"name":"notes","type":"textarea","label":"ملاحظات","required":false}]',
  1,
  1
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name), description = VALUES(description), icon = VALUES(icon), fields_json = VALUES(fields_json), is_active = 1;

SET @suspension_tpl = (SELECT id FROM approval_templates WHERE code = 'suspension_request' LIMIT 1);

-- Allowed roles: المدراء والإدارة يمكنهم الإنشاء
DELETE FROM template_allowed_roles WHERE template_id IN (@hiring_tpl, @suspension_tpl);
INSERT INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT @hiring_tpl, id, 1, 0 FROM roles WHERE level >= 60;
INSERT INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT @suspension_tpl, id, 1, 0 FROM roles WHERE level >= 60;

-- Steps: 1=مدير مباشر, 2=HR, 3=مالية
DELETE FROM approval_steps WHERE template_id IN (@hiring_tpl, @suspension_tpl);
INSERT INTO approval_steps (template_id, step_order, title, approver_type, role_id, description, is_final) VALUES
(@hiring_tpl, 1, 'موافقة المدير المباشر', 'direct_manager', NULL, 'المدير المباشر للطالب', 0),
(@hiring_tpl, 2, 'موافقة الموارد البشرية', 'role', 25, 'مدير الموارد البشرية', 0),
(@hiring_tpl, 3, 'موافقة المدير المالي', 'role', 3, 'المدير المالي', 1);

INSERT INTO approval_steps (template_id, step_order, title, approver_type, role_id, description, is_final) VALUES
(@suspension_tpl, 1, 'موافقة المدير المباشر', 'direct_manager', NULL, 'المدير المباشر للطالب', 0),
(@suspension_tpl, 2, 'موافقة الموارد البشرية', 'role', 25, 'مدير الموارد البشرية', 0),
(@suspension_tpl, 3, 'موافقة المدير المالي', 'role', 3, 'المدير المالي', 1);
