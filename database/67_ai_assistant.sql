-- =====================================================
-- 67: AI Reservation Assistant - مساعد الحجز الذكي
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. المحادثات عبر القنوات
CREATE TABLE IF NOT EXISTS ai_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel ENUM('web','whatsapp','messenger','telegram','phone','api') NOT NULL DEFAULT 'web',
    channel_id VARCHAR(120) NOT NULL COMMENT 'معرف العميل في القناة (رقم/جلسة)',
    session_id VARCHAR(120) NOT NULL,
    customer_id INT NULL,
    customer_phone VARCHAR(30) NULL,
    customer_name VARCHAR(120) NULL,
    language VARCHAR(10) DEFAULT 'ar',
    status ENUM('active','handoff','closed') DEFAULT 'active',
    handoff_to INT NULL,
    handoff_reason TEXT NULL,
    context_json JSON NULL,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_channel (channel, channel_id),
    INDEX idx_session (session_id),
    INDEX idx_status (status, last_message_at),
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. الرسائل
CREATE TABLE IF NOT EXISTS ai_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    direction ENUM('in','out') NOT NULL,
    role ENUM('user','assistant','system','human') DEFAULT 'user',
    message TEXT NOT NULL,
    intent VARCHAR(40) NULL,
    payload_json JSON NULL,
    is_handoff TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation (conversation_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. إعدادات القنوات
CREATE TABLE IF NOT EXISTS ai_channel_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel VARCHAR(40) NOT NULL UNIQUE,
    is_enabled TINYINT(1) DEFAULT 0,
    webhook_url VARCHAR(255) NULL,
    api_key VARCHAR(255) NULL,
    api_secret VARCHAR(255) NULL,
    settings_json JSON NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. سجل الأحداث
CREATE TABLE IF NOT EXISTS ai_intent_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    message TEXT NULL,
    detected_intent VARCHAR(40) NULL,
    confidence DECIMAL(5,2) NULL,
    extracted_entities JSON NULL,
    response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. إعدادات افتراضية
INSERT INTO ai_channel_configs (channel, is_enabled) VALUES
('web', 1),
('whatsapp', 0),
('messenger', 0),
('telegram', 0),
('phone', 0),
('api', 0)
ON DUPLICATE KEY UPDATE channel = VALUES(channel);

-- 6. صلاحيات
INSERT IGNORE INTO permissions (code, name, module) VALUES
('ai_assistant.manage', 'إدارة مساعد الحجز الذكي', 'sales'),
('ai_assistant.handoff', 'استلام المحادثات المحولة من AI', 'sales'),
('ai_assistant.view', 'عرض سجل محادثات AI', 'sales');

SET FOREIGN_KEY_CHECKS=1;
