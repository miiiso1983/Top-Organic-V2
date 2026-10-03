-- =====================================================
-- 156: حصر أنواع الإجازة اليومية بنوعين فقط (بدون راتب: قصيرة/طويلة)
-- مع مسارات موافقة مختلفة حسب المدة:
--   - قصيرة (<=3 أيام): المدير المباشر ثم قسم الموارد البشرية.
--   - طويلة (>=4 أيام): المدير المباشر ثم مدير المدير ثم قسم الموارد البشرية.
-- طلبات الإذن الزمني (hourly) ومباشرة العمل (work_resumption) تبقى بمسارها
-- القديم (موافقة المدير العام) دون تغيير.
-- =====================================================
SET NAMES utf8mb4;

UPDATE approval_templates
SET fields_json = '[{"name":"employee_id","type":"number","label":"الموظف","required":true},
    {"name":"employee_name","type":"text","label":"اسم الموظف","required":true},
    {"name":"request_type","type":"select","label":"نوع الطلب","options":["leave","exit","delay","work_resumption"],"required":true},
    {"name":"leave_type","type":"select","label":"نوع الإجازة","options":["unpaid_short","unpaid_long","hourly","n/a"],"required":false},
    {"name":"date_from","type":"date","label":"من تاريخ","required":true},
    {"name":"date_to","type":"date","label":"إلى تاريخ","required":false},
    {"name":"start_time","type":"text","label":"من الساعة","required":false},
    {"name":"end_time","type":"text","label":"إلى الساعة","required":false},
    {"name":"reason","type":"textarea","label":"السبب","required":false}]'
WHERE code = 'hr_leave';

SET @tpl_id = (SELECT id FROM approval_templates WHERE code = 'hr_leave' LIMIT 1);
SET @hr_role = COALESCE((SELECT id FROM roles WHERE name = 'مدير موارد بشرية' LIMIT 1), (SELECT id FROM roles WHERE level = 60 LIMIT 1));

-- المسار القديم (بدون تغيير): إذن زمني / مباشرة عمل → موافقة المدير العام (خطوة نهائية واحدة)
-- نُبقي على صف الخطوة القديم كما هو (لا حذف) حفاظاً على سجل القرارات التاريخية المرتبطة به
-- (request_step_decisions.step_id مرتبط بـ ON DELETE CASCADE)، ونقيّده بشرط ليقتصر على
-- الأنواع القديمة فقط بدلاً من كل طلبات الإجازة.
UPDATE approval_steps SET condition_json = '{"leave_type":["hourly","n\/a"]}'
WHERE template_id = @tpl_id AND step_order = 1 AND approver_type = 'role';

-- إجازة قصيرة بدون راتب (<=3 أيام): المدير المباشر ثم الموارد البشرية
INSERT INTO approval_steps (template_id, step_order, title, description, approver_type, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
VALUES (@tpl_id, 1, 'موافقة المدير المباشر', 'موافقة المدير المباشر على الإجازة القصيرة بدون راتب', 'direct_manager', '{"leave_type":["unpaid_short","unpaid_long"]}', 1, 0, 'sequential', 1, '{"push":1,"email":0,"whatsapp":0}');

INSERT INTO approval_steps (template_id, step_order, title, description, approver_type, role_id, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
VALUES (@tpl_id, 2, 'موافقة قسم الموارد البشرية', 'اعتماد نهائي من قسم الموارد البشرية', 'role', @hr_role, '{"leave_type":["unpaid_short"]}', 1, 1, 'sequential', 1, '{"push":1,"email":0,"whatsapp":0}');

-- إجازة طويلة بدون راتب (>=4 أيام): المدير المباشر ثم مدير المدير ثم الموارد البشرية
INSERT INTO approval_steps (template_id, step_order, title, description, approver_type, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
VALUES (@tpl_id, 2, 'موافقة مدير المدير / الإدارة العليا', 'موافقة إضافية للإجازة الطويلة بدون راتب', 'department_manager', '{"leave_type":["unpaid_long"]}', 1, 0, 'sequential', 1, '{"push":1,"email":0,"whatsapp":0}');

INSERT INTO approval_steps (template_id, step_order, title, description, approver_type, role_id, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
VALUES (@tpl_id, 3, 'موافقة قسم الموارد البشرية', 'اعتماد نهائي من قسم الموارد البشرية', 'role', @hr_role, '{"leave_type":["unpaid_long"]}', 1, 1, 'sequential', 1, '{"push":1,"email":0,"whatsapp":0}');
