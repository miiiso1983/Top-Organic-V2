-- =====================================================
-- 91: الربط العام بين أي كيان في النظام وطلبات Workflow
-- أي وحدة تربط كياناتها بأي Workflow بدون برمجة إضافية
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS workflow_entity_links (
    entity_type VARCHAR(60) NOT NULL COMMENT 'نوع الكيان: purchase_request, stock_transfer, leave_request...',
    entity_id INT NOT NULL,
    request_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (entity_type, entity_id),
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    INDEX idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
