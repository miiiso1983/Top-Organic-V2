-- =====================================================
-- 08: الموافقات متعددة المستويات والإشعارات
-- =====================================================

-- النظام القديم للموافقات (للإحتفاظ بالميزانية مع الموافقات الموجودة)
-- لقد استبدله نظام Workflow الجديد (database/15_workflows.sql) في الأجهزة المحلية.
CREATE TABLE IF NOT EXISTS legacy_approval_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(60) NOT NULL UNIQUE
        COMMENT 'purchase_request, leave_request, expense, salary_advance, salary_adjustment, discount, stock_transfer, quotation',
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legacy_approval_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    step_order INT NOT NULL,
    role_id INT NOT NULL COMMENT 'الدور المطلوب للموافقة في هذا المستوى',
    min_amount DECIMAL(14,2) DEFAULT 0 COMMENT 'يُطلب هذا المستوى إذا تجاوز المبلغ هذا الحد',
    FOREIGN KEY (workflow_id) REFERENCES legacy_approval_workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legacy_approval_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id BIGINT NOT NULL,
    amount DECIMAL(14,2) DEFAULT 0,
    current_step INT DEFAULT 1,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    requested_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES legacy_approval_workflows(id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legacy_approval_actions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT NOT NULL,
    step_order INT NOT NULL,
    decided_by INT NOT NULL,
    decision ENUM('approved','rejected') NOT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES legacy_approval_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الإشعارات (للويب والموبايل)
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    body VARCHAR(500),
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
