-- =====================================================
-- 15: نظام سير العمل والموافقات (Workflow & Approval Engine)
-- =====================================================
SET NAMES utf8mb4;

-- قوالب الطلبات (طلب شراء، إجازة، عهدة، ...)
CREATE TABLE IF NOT EXISTS approval_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500),
    icon VARCHAR(60) DEFAULT '✓',
    fields_json JSON NOT NULL COMMENT 'تعريف حقول النموذج الديناميكية',
    amount_field VARCHAR(60) NULL COMMENT 'اسم الحقل الذي يحمل المبلغ',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الخطوات التسلسلية لكل قالب
CREATE TABLE IF NOT EXISTS approval_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    step_order INT NOT NULL,
    title VARCHAR(120) NOT NULL COMMENT 'مثال: موافقة المدير المباشر',
    description VARCHAR(500),
    job_title_id INT NULL COMMENT 'المسمى الوظيفي المطلوب (org_positions)',
    role_id INT NULL COMMENT 'دور احتياطي إذا لم يُعثر على المسمى الوظيفي',
    min_amount DECIMAL(14,2) DEFAULT 0 COMMENT 'تُنفذ إذا تجاوز المبلغ هذا الحد',
    condition_json JSON NULL COMMENT 'شروط إضافية لتحديد الخطوة التالية',
    can_return_for_edit TINYINT(1) DEFAULT 1,
    is_final TINYINT(1) DEFAULT 0,
    notify_on_arrive TINYINT(1) DEFAULT 1,
    FOREIGN KEY (template_id) REFERENCES approval_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (job_title_id) REFERENCES org_positions(id) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
    INDEX idx_template_order (template_id, step_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الأدوار التي تُسمح لها بإنشاء طلب من كل قالب
CREATE TABLE IF NOT EXISTS template_allowed_roles (
    template_id INT NOT NULL,
    role_id INT NOT NULL,
    can_create TINYINT(1) DEFAULT 1,
    can_view_all TINYINT(1) DEFAULT 0,
    PRIMARY KEY (template_id, role_id),
    FOREIGN KEY (template_id) REFERENCES approval_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الطلبات الفعلية
CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    request_no VARCHAR(60) UNIQUE,
    title VARCHAR(255),
    requester_id INT NOT NULL,
    requester_employee_id INT NULL,
    requester_position_id INT NULL,
    status ENUM('draft','pending','approved','rejected','returned_for_edit','cancelled') DEFAULT 'draft',
    current_step INT DEFAULT 1,
    amount DECIMAL(14,2) DEFAULT 0,
    data_json JSON NOT NULL,
    branch_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES approval_templates(id),
    FOREIGN KEY (requester_id) REFERENCES users(id),
    FOREIGN KEY (requester_employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (requester_position_id) REFERENCES org_positions(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_requester (requester_id),
    INDEX idx_template_status (template_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- سجل التتبع / Audit Log لكل طلب
CREATE TABLE IF NOT EXISTS request_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    step_id INT NULL,
    step_order INT DEFAULT 0,
    action ENUM('created','submitted','approved','rejected','returned_for_edit','resubmitted','comment','cancelled','viewed') NOT NULL,
    actor_id INT NULL,
    actor_name VARCHAR(120),
    actor_position VARCHAR(150),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES approval_steps(id) ON DELETE SET NULL,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- المشاركون في الطلب (للمرئية والإشعارات)
CREATE TABLE IF NOT EXISTS request_participants (
    request_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('requester','approver','viewer') DEFAULT 'viewer',
    PRIMARY KEY (request_id, user_id),
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- صلاحيات النظام الجديد
INSERT IGNORE INTO permissions (code, name, module) VALUES
('workflow.manage','إدارة قوالب سير العمل','workflow'),
('workflow.create','إنشاء طلبات سير العمل','workflow'),
('workflow.view_all','عرض جميع طلبات سير العمل','workflow'),
('workflow.decide','اتخاذ قرارات سير العمل','workflow');

-- منح الصلاحيات للأدوار
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'مدير النظام';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير عام','مدير مالي','مدير مطعم') AND p.code IN ('workflow.decide','workflow.view_all');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مسؤول مشتريات','أمين مخزن','كاشير','شيف','موظف') AND p.code IN ('workflow.create');

-- قوالب افتراضية
INSERT IGNORE INTO approval_templates (code, name, description, icon, fields_json, amount_field, created_by) VALUES
('purchase_request','طلب شراء','طلب شراء مواد أو معدات للمطعم','🛒',
 '[{"name":"department","label":"القسم","type":"select","required":true,"options":["المطبخ","الصالة","المخزن","الإدارة"]},{"name":"supplier","label":"المورد المقترح","type":"text","required":false},{"name":"amount","label":"المبلغ الإجمالي","type":"number","required":true},{"name":"reason","label":"سبب الشراء","type":"textarea","required":true},{"name":"urgent","label":"طلب عاجل","type":"checkbox","required":false}]',
 'amount', 1),
('leave_request','طلب إجازة','طلب إجازة سنوية أو مرضية','🌴',
 '[{"name":"leave_type","label":"نوع الإجازة","type":"select","required":true,"options":["سنوية","مرضية","بدون راتب","استثنائية"]},{"name":"date_from","label":"من تاريخ","type":"date","required":true},{"name":"date_to","label":"إلى تاريخ","type":"date","required":true},{"name":"reason","label":"السبب","type":"textarea","required":true}]',
 NULL, 1),
('custody_request','طلب عهدة','طلب استلام عهدة أو أصل','🔐',
 '[{"name":"item","label":"العنصر","type":"text","required":true},{"name":"qty","label":"الكمية","type":"number","required":true},{"name":"estimated_value","label":"القيمة التقديرية","type":"number","required":false},{"name":"purpose","label":"الغرض","type":"textarea","required":true}]',
 'estimated_value', 1);

-- الأدوار المسموحة لإنشاء كل قالب
-- ملاحظة: يفترض إعطاء المدير العام (1) للكل للتجارب والإدارة
INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT t.id, 1, 1, 1 FROM approval_templates t;

-- المستخدمون العامون يمكنون إنشاء طلبات
INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT t.id, r.id, 1, 0
FROM approval_templates t
CROSS JOIN roles r
WHERE r.name IN ('مسؤول مشتريات','أمين مخزن','كاشير','شيف','موظف');

-- المديرون يمكنون الموافقة والمرئية
INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT t.id, r.id, 0, 1
FROM approval_templates t
CROSS JOIN roles r
WHERE r.name IN ('مدير عام','مدير مالي','مدير مطعم');

-- خطوات موافقة افتراضية (بناءً على هيكل 10_orgchart.sql)
-- ملاحظة: قد تاختلف أرقام المناصب بالتنسيق بين المحليات. يمكن للآدمن تعديلها لاحقاً.
INSERT IGNORE INTO approval_steps (template_id, step_order, title, job_title_id, role_id, min_amount) VALUES
(1, 1, 'موافقة مسؤول التوريد', 34, 4, 0),
(1, 2, 'موافقة المدير المباشر', 3, 4, 0),
(2, 1, 'موافقة المدير المباشر', 4, 4, 0),
(2, 2, 'موافقة قسم الموارد البشرية', 35, 4, 0),
(3, 1, 'موافقة أمين المخزن', 39, 7, 0),
(3, 2, 'موافقة المدير المباشر', 3, 4, 0);
