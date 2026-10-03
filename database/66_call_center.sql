-- =====================================================
-- 66: Call Center - شاشة الكول سنتر للحجوزات
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. توسيع جدول العملاء ليدعم الكول سنتر
ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS email VARCHAR(120) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS is_vip TINYINT(1) DEFAULT 0 AFTER balance,
    ADD COLUMN IF NOT EXISTS birth_date DATE NULL AFTER is_vip,
    ADD COLUMN IF NOT EXISTS preferences TEXT NULL AFTER birth_date,
    ADD COLUMN IF NOT EXISTS last_call_center_note TEXT NULL AFTER notes,
    ADD INDEX IF NOT EXISTS idx_phone (phone),
    ADD INDEX IF NOT EXISTS idx_vip (is_vip);

-- 2. توسيع جدول الحجوزات ليدعم ملاحظات الكول سنتر ومصدر الحجز
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS call_center_notes TEXT NULL AFTER customer_notes,
    ADD COLUMN IF NOT EXISTS cancellation_reason VARCHAR(255) NULL AFTER call_center_notes,
    ADD COLUMN IF NOT EXISTS created_by_name VARCHAR(120) NULL AFTER created_by;

-- 3. صلاحيات الكول سنتر
INSERT IGNORE INTO permissions (code, name, module) VALUES
('call_center.manage', 'إدارة شاشة الكول سنتر', 'sales'),
('reservations.call_center', 'إنشاء وتعديل الحجز من الكول سنتر', 'sales'),
('customers.manage', 'إدارة العملاء', 'sales');

-- 4. إعدادات الكول سنتر
INSERT INTO settings (skey, svalue, sgroup, label) VALUES
('call_center_enabled', '1', 'sales', 'تفعيل شاشة الكول سنتر'),
('call_center_default_branch', '1', 'sales', 'الفرع الافتراضي للكول سنتر'),
('call_center_show_occupancy', '1', 'sales', 'عرض نسبة الإشغال في الكول سنتر')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), label = VALUES(label);

SET FOREIGN_KEY_CHECKS=1;
