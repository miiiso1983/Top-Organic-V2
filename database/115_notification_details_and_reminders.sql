-- =====================================================
-- 115: تفاصيل الإشعارات وتذكيرات الطلبات المعلقة
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE notifications ADD COLUMN entity_type VARCHAR(60) NULL COMMENT 'نوع الكيان المرتبط (request, task, order...)' AFTER link;
ALTER TABLE notifications ADD COLUMN entity_id INT NULL COMMENT 'معرّف الكيان' AFTER entity_type;
ALTER TABLE notifications ADD COLUMN metadata_json TEXT NULL COMMENT 'بيانات إضافية للإشعار (JSON)' AFTER entity_id;
ALTER TABLE notifications ADD COLUMN action_type VARCHAR(60) NULL COMMENT 'نوع الإشعار (reminder, approved, rejected, returned_for_edit, escalated...)' AFTER metadata_json;

CREATE TABLE IF NOT EXISTS notification_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    user_id INT NOT NULL COMMENT 'المستخدم الذي أُرسل إليه التذكير',
    reminder_type VARCHAR(60) NOT NULL COMMENT 'requester أو manager',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reminder (request_id, user_id, reminder_type),
    INDEX idx_request (request_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
