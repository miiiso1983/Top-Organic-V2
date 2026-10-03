-- =====================================================
-- 79: دعم الإشعارات متعددة القنوات
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email VARCHAR(120) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS push_token VARCHAR(255) NULL COMMENT 'Firebase/OneSignal token للتطبيق',
    ADD COLUMN IF NOT EXISTS notify_email TINYINT(1) DEFAULT 1,
    ADD COLUMN IF NOT EXISTS notify_whatsapp TINYINT(1) DEFAULT 1,
    ADD COLUMN IF NOT EXISTS notify_sms TINYINT(1) DEFAULT 0;

ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS email VARCHAR(120) NULL AFTER phone;

ALTER TABLE notification_queue
    MODIFY COLUMN channel ENUM('push','email','whatsapp','sms') NOT NULL;

CREATE TABLE IF NOT EXISTS notification_event_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(60) NOT NULL COMMENT 'mr_created, mr_approved, po_issued, receipt_done, invoice_created...',
    entity_id INT NOT NULL,
    user_id INT NOT NULL,
    channel ENUM('push','email','whatsapp','sms') NOT NULL,
    status ENUM('pending','sent','failed') DEFAULT 'pending',
    error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إعدادات قنوات الإشعارات (اختياري: تفعيل البريد/واتساب/SMS/FCM)
INSERT INTO settings (skey, svalue) VALUES
('email_method', 'queue'),
('whatsapp_api_url', ''),
('whatsapp_token', ''),
('sms_api_url', ''),
('sms_token', ''),
('fcm_server_key', '')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

SET FOREIGN_KEY_CHECKS=1;
