-- Migration 48: إضافة مستلم محدد لقواعد التبليغات الإدارية
ALTER TABLE notification_rules ADD COLUMN IF NOT EXISTS user_id INT NULL;
ALTER TABLE notification_rules ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
