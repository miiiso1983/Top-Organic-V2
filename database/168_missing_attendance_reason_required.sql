-- =====================================================
-- 168: جعل حقل "السبب / الملاحظات" إلزامياً في طلب نسيان البصمة
-- =====================================================
SET NAMES utf8mb4;

UPDATE approval_templates
SET fields_json = '[{"name":"employee_id","type":"number","label":"الموظف","required":true},
{"name":"employee_name","type":"text","label":"اسم الموظف","required":true},
{"name":"request_type","type":"select","label":"نسيان","required":true,"options":[{"value":"in","label":"بصمة دخول"},{"value":"out","label":"بصمة خروج"}]},
{"name":"clock_in_at","type":"datetime","label":"تاريخ ووقت بصمة الدخول","required":false},
{"name":"clock_out_at","type":"datetime","label":"تاريخ ووقت بصمة الخروج","required":false},
{"name":"reason","type":"textarea","label":"السبب / الملاحظات","required":true}]'
WHERE code = 'hr_missing_attendance';
