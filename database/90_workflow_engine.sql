-- =====================================================
-- 90: محرك Workflow الديناميكي (Dynamic Rule Engine)
-- بنية إضافية فوق نظام الموافقات الحالي - لا تكسر البيانات القائمة
-- =====================================================
SET NAMES utf8mb4;

-- تعريفات Workflow (قوالب متقدمة مع نطاق التطبيق)
CREATE TABLE IF NOT EXISTS workflow_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500),
    request_type VARCHAR(60) NOT NULL COMMENT 'نوع الطلب (كود القالب أو نوع كيان)',
    template_id INT NULL COMMENT 'ربط بقالب approval_templates الحالي',
    branch_ids JSON NULL COMMENT 'الفروع المطبقة [1,2] - NULL = الكل',
    department_ids JSON NULL COMMENT 'الأقسام المطبقة - NULL = الكل',
    priority INT NOT NULL DEFAULT 100 COMMENT 'الأصغر = أولوية أعلى عند تطابق عدة تعريفات',
    valid_from DATE NULL,
    valid_to DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES approval_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type_active (request_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إصدارات التعريف (مسودة/منشور/مؤرشف) لحماية الطلبات الجارية عند التعديل
CREATE TABLE IF NOT EXISTS workflow_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    definition_id INT NOT NULL,
    version_no INT NOT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    published_by INT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_def_version (definition_id, version_no),
    FOREIGN KEY (definition_id) REFERENCES workflow_definitions(id) ON DELETE CASCADE,
    FOREIGN KEY (published_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- عقد المخطط (Nodes): بداية/موافقة/شرط/إجراء/إشعار/انتظار/رفض/إعادة/نهاية
CREATE TABLE IF NOT EXISTS workflow_nodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version_id INT NOT NULL,
    node_key VARCHAR(60) NOT NULL COMMENT 'معرّف فريد داخل النسخة',
    node_type ENUM('start','approval','condition','action','notify','wait','reject','return','end') NOT NULL,
    title VARCHAR(150) NOT NULL,
    config_json JSON NULL COMMENT 'إعدادات العقدة حسب نوعها (معتمد/شرط/إجراء/SLA...)',
    pos_x INT DEFAULT 0,
    pos_y INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_version_key (version_id, node_key),
    FOREIGN KEY (version_id) REFERENCES workflow_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الأسهم بين العقد (handle: default / yes / no للشروط)
CREATE TABLE IF NOT EXISTS workflow_edges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version_id INT NOT NULL,
    from_node_id INT NOT NULL,
    to_node_id INT NOT NULL,
    handle VARCHAR(30) NOT NULL DEFAULT 'default',
    label VARCHAR(80),
    FOREIGN KEY (version_id) REFERENCES workflow_versions(id) ON DELETE CASCADE,
    FOREIGN KEY (from_node_id) REFERENCES workflow_nodes(id) ON DELETE CASCADE,
    FOREIGN KEY (to_node_id) REFERENCES workflow_nodes(id) ON DELETE CASCADE,
    INDEX idx_from (from_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- قواعد شرطية قابلة لإعادة الاستخدام (DSL متداخل JSON)
CREATE TABLE IF NOT EXISTS workflow_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255),
    rule_json JSON NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- مهام المعتمدين لكل طلب (تدعم المتوازي واللجان)
CREATE TABLE IF NOT EXISTS workflow_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    node_id INT NOT NULL,
    assigned_to INT NOT NULL,
    assigned_by_type VARCHAR(40) NOT NULL COMMENT 'user/role/position/direct_manager/permission/group...',
    status ENUM('pending','approved','rejected','returned','skipped','cancelled','escalated') NOT NULL DEFAULT 'pending',
    due_at DATETIME NULL,
    decided_at DATETIME NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_req_node_user (request_id, node_id, assigned_to),
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (node_id) REFERENCES workflow_nodes(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assignee (assigned_to, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- التفويض والبديل أثناء الإجازة
CREATE TABLE IF NOT EXISTS approval_delegations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    scope VARCHAR(60) NOT NULL DEFAULT 'all' COMMENT 'all أو request_type محدد',
    valid_from DATE NOT NULL,
    valid_to DATE NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_from (from_user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SLA لكل عقدة موافقة + سياسة التصعيد
CREATE TABLE IF NOT EXISTS workflow_sla (
    id INT AUTO_INCREMENT PRIMARY KEY,
    node_id INT NOT NULL UNIQUE,
    max_hours INT NOT NULL DEFAULT 24,
    remind_after_hours INT NULL,
    escalate_to_json JSON NULL COMMENT 'نفس بنية إعداد المعتمد',
    auto_action VARCHAR(30) NULL COMMENT 'none | auto_approve | auto_reject',
    FOREIGN KEY (node_id) REFERENCES workflow_nodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ربط الطلب بنسخة Workflow التي سار عليها + العقدة الحالية
ALTER TABLE requests
    ADD COLUMN workflow_version_id INT NULL,
    ADD COLUMN current_node_id INT NULL;
ALTER TABLE requests
    ADD CONSTRAINT fk_req_wf_version FOREIGN KEY (workflow_version_id) REFERENCES workflow_versions(id) ON DELETE SET NULL;

-- توسيع سجل التدقيق: الجهاز والـ IP والعقدة
ALTER TABLE request_history
    ADD COLUMN node_id INT NULL,
    ADD COLUMN ip VARCHAR(45) NULL,
    ADD COLUMN user_agent VARCHAR(255) NULL,
    ADD COLUMN device VARCHAR(120) NULL;
ALTER TABLE request_history
    MODIFY COLUMN action ENUM('created','submitted','approved','rejected','returned_for_edit','resubmitted','comment','cancelled','viewed','node_entered','auto_approved','escalated','delegated','action_executed') NOT NULL;
