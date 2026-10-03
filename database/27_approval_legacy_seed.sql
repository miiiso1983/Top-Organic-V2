-- 27: إنشاء جداول الموافقات القديمة (legacy) وإدخال مساراتها الافتراضية
-- يُستخدم عند التثبيتات الجديدة التي لا تحتوي على الجداول القديمة.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS legacy_approval_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_entity (entity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legacy_approval_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id BIGINT NOT NULL,
    amount DECIMAL(14,2) DEFAULT 0,
    current_step INT DEFAULT 1,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    requested_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES legacy_approval_workflows(id),
    INDEX idx_status (status),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legacy_approval_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    step_order INT NOT NULL,
    role_id INT NOT NULL,
    min_amount DECIMAL(14,2) DEFAULT 0,
    UNIQUE KEY uq_wf_step (workflow_id, step_order),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legacy_approval_actions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT NOT NULL,
    step_order INT NOT NULL,
    decided_by INT NOT NULL,
    decision ENUM('approved','rejected') NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES legacy_approval_requests(id) ON DELETE CASCADE,
    INDEX idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- مسارات الموافقات الافتراضية
INSERT IGNORE INTO legacy_approval_workflows (entity_type, name) VALUES
('purchase_request', 'طلبات الشراء'),
('leave_request', 'طلبات الإجازة'),
('expense', 'المصروفات'),
('salary_advance', 'السلف'),
('salary_adjustment', 'المكافآت والخصومات'),
('discount', 'الخصومات'),
('stock_transfer', 'المناقلات المخزنية'),
('quotation', 'عروض الأسعار');

-- خطوة موافقة واحدة بشكل افتراضي: مدير النظام (role_id=1)
-- يمكن للمشرف تعديلها لاحقاً حسب الهيكل التنظيمي.
INSERT IGNORE INTO legacy_approval_steps (workflow_id, step_order, role_id, min_amount)
SELECT id, 1, 1, 0 FROM legacy_approval_workflows;

SET FOREIGN_KEY_CHECKS=1;
