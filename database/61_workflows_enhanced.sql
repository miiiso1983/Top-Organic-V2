-- =====================================================
-- 61: تحسين نظام Workflow والموافقات (HR + parallel + notifications)
-- =====================================================
SET NAMES utf8mb4;

-- 1) تحسين جدول الخطوات: دعم الموافقات المتوازية وقنوات الإشعارات
ALTER TABLE approval_steps
    ADD COLUMN step_type ENUM('sequential','parallel') NOT NULL DEFAULT 'sequential' COMMENT 'تسلسلي أو متوازي',
    ADD COLUMN required_count INT NOT NULL DEFAULT 1 COMMENT 'عدد الموافقات المطلوبة في الخطوة المتوازية (0 = الجميع)',
    ADD COLUMN notify_channels JSON NULL COMMENT '{"push":1,"email":1,"whatsapp":0}';

-- 2) سجل قرارات كل خطوة (للخطوات المتوازية والتسلسلية)
CREATE TABLE IF NOT EXISTS request_step_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    step_id INT NOT NULL,
    decision ENUM('approved','rejected','returned_for_edit') NOT NULL,
    actor_id INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_request_step_actor (request_id, step_id, actor_id),
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES approval_steps(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_request_step (request_id, step_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) ربط العمليات بالطلبات (HR Workflow Links)
CREATE TABLE IF NOT EXISTS hr_workflow_links (
    entity_type VARCHAR(60) NOT NULL,
    entity_id INT NOT NULL,
    request_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (entity_type, entity_id),
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    INDEX idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) طابور إرسال الإشعارات عبر Email/WhatsApp
CREATE TABLE IF NOT EXISTS notification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel ENUM('email','whatsapp','sms') NOT NULL,
    user_id INT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    body TEXT NOT NULL,
    status ENUM('pending','sent','failed') DEFAULT 'pending',
    error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) إضافة بريد إلكتروني للمستخدم
ALTER TABLE users ADD COLUMN email VARCHAR(120) NULL AFTER full_name;
ALTER TABLE employees ADD COLUMN email VARCHAR(120) NULL AFTER phone;

-- 6) قوالب Workflow للموارد البشرية
INSERT IGNORE INTO approval_templates (code, name, description, icon, fields_json, amount_field, is_active)
VALUES
('hr_leave', 'طلب إجازة / خروج / تأخير / مباشرة', 'مسار الموافقات على طلبات الإجازات والأذونات والتأخير', '🌴', '[{"name":"employee_id","label":"الموظف","type":"employee","required":true},{"name":"employee_name","label":"اسم الموظف","type":"text","required":true},{"name":"request_type","label":"نوع الطلب","type":"select","required":true,"options":["leave","exit","delay","work_resumption"]},{"name":"leave_type","label":"نوع الإجازة","type":"select","required":false,"options":["annual","sick","unpaid","emergency","hourly","n/a"]},{"name":"date_from","label":"من تاريخ","type":"date","required":true},{"name":"date_to","label":"إلى تاريخ","type":"date","required":false},{"name":"start_time","label":"من الساعة","type":"text","required":false},{"name":"end_time","label":"إلى الساعة","type":"text","required":false},{"name":"reason","label":"السبب","type":"textarea","required":false}]', '', 1),
('hr_advance', 'طلب سلفة', 'مسار الموافقات على السلف المالية', '💰', '[{"name":"employee_id","label":"الموظف","type":"employee","required":true},{"name":"employee_name","label":"اسم الموظف","type":"text","required":true},{"name":"amount","label":"المبلغ","type":"number","required":true},{"name":"advance_type","label":"نوع السلفة","type":"select","required":true,"options":["short","long"]},{"name":"installments","label":"عدد الأقساط","type":"number","required":true},{"name":"reason","label":"السبب","type":"textarea","required":false}]', 'amount', 1),
('hr_adjustment', 'طلب تسوية مالية', 'مسار الموافقات على المكافآت والخصومات والزيادات والجزاءات', '⚖️', '[{"name":"employee_id","label":"الموظف","type":"employee","required":true},{"name":"employee_name","label":"اسم الموظف","type":"text","required":true},{"name":"adjustment_type","label":"نوع التسوية","type":"select","required":true,"options":["bonus","deduction","raise","penalty"]},{"name":"amount","label":"المبلغ","type":"number","required":true},{"name":"period","label":"الفترة","type":"text","required":false},{"name":"effective_date","label":"تاريخ التطبيق","type":"date","required":true},{"name":"reason","label":"السبب","type":"textarea","required":false}]', 'amount', 1);

-- 7) خطوات افتراضية لكل قالب (مدير عام أو أعلى دور متاح)
SET @manager_role = COALESCE((SELECT id FROM roles WHERE name = 'مدير عام' LIMIT 1), (SELECT id FROM roles ORDER BY level DESC LIMIT 1));

INSERT INTO approval_steps (template_id, step_order, title, description, role_id, min_amount, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
SELECT t.id, 1, 'موافقة المدير العام', 'المراجعة الإدارية الأولى', @manager_role, 0, '{}', 1, 1, 'sequential', 1, '{"push":1,"email":0,"whatsapp":0}'
FROM approval_templates t WHERE t.code IN ('hr_leave','hr_advance','hr_adjustment')
AND NOT EXISTS (SELECT 1 FROM approval_steps s WHERE s.template_id = t.id);

-- 8) صلاحيات Workflow
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.workflow.view','عرض Workflow الموارد البشرية','hr'),
('hr.workflow.manage','إدارة قوالب Workflow الموارد البشرية','hr');

-- 9) منح الصلاحيات للأدوار الإدارية
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.level >= 80 AND p.code IN ('workflow.create','workflow.decide','workflow.view_all','hr.workflow.view','hr.workflow.manage');
