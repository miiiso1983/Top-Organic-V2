-- =====================================================
-- 72: Waiting List Module - وحدة قائمة الانتظار
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. توسيع جدول الحجوزات لدعم قائمة الانتظار بشكل صريح
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS party_size INT NULL AFTER guests,
    ADD COLUMN IF NOT EXISTS expected_arrival_at DATETIME NULL AFTER reserved_at,
    ADD COLUMN IF NOT EXISTS is_vip TINYINT(1) DEFAULT 0 AFTER customer_email,
    ADD COLUMN IF NOT EXISTS waiting_notified TINYINT(1) DEFAULT 0 AFTER is_vip,
    ADD COLUMN IF NOT EXISTS priority_score INT DEFAULT 0 AFTER waitlist_position,
    ADD INDEX IF NOT EXISTS idx_waiting_priority (status, priority_score DESC, expected_arrival_at, created_at);

-- 2. تعبئة القيم الافتراضية من الأعمدة الموجودة
UPDATE reservations SET party_size = guests WHERE party_size IS NULL;
UPDATE reservations SET expected_arrival_at = reserved_at WHERE expected_arrival_at IS NULL;

-- 3. إعدادات الوحدة
INSERT INTO settings (skey, svalue) VALUES
('waiting_list_auto_notify', '1'),
('waiting_list_vip_priority', '1'),
('waiting_list_max_wait_minutes', '60'),
('waiting_list_sms_notify', '0')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- 4. صلاحية إدارة قائمة الانتظار
INSERT IGNORE INTO permissions (code, name, module) VALUES
('waiting_list.manage', 'إدارة قائمة الانتظار', 'sales'),
('waiting_list.notify', 'إرسال تنبيهات قائمة الانتظار', 'sales');

SET FOREIGN_KEY_CHECKS=1;
