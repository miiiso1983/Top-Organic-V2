-- =====================================================
-- 117: إضافة حقل الموظف لقالب طلب السلفة
-- =====================================================
SET NAMES utf8mb4;

UPDATE approval_templates
SET fields_json = '[{"name": "employee_id", "type": "hidden", "label": "", "required": false}, {"name": "employee_name", "type": "select", "label": "الموظف", "required": true}, {"name": "amount", "type": "number", "label": "المبلغ", "required": true}, {"name": "reason", "type": "textarea", "label": "السبب", "required": false}]'
WHERE code = 'hr_advance';
