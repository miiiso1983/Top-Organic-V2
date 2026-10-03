-- =====================================================
-- 64: Public Booking - واجهة الحجز العامة للعملاء
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 0. ضمان وجود أعمدة التجميع/التسمية في الإعدادات
ALTER TABLE settings
    ADD COLUMN IF NOT EXISTS sgroup VARCHAR(40) NULL AFTER svalue,
    ADD COLUMN IF NOT EXISTS label VARCHAR(120) NULL AFTER sgroup;

-- 1. إعدادات الحجز العامة
INSERT INTO settings (skey, svalue, sgroup, label) VALUES
('reservation_public_enabled', '1', 'sales', 'تمكين الحجز العام عبر الويب'),
('reservation_otp_enabled', '0', 'sales', 'طلب OTP للحجز العام (0 = معطل، 1 = مفعل)'),
('reservation_opening_time', '09:00', 'sales', 'وقت فتح الحجز (HH:MM)'),
('reservation_closing_time', '23:00', 'sales', 'وقت إغلاق الحجز (HH:MM)'),
('reservation_peak_hours', '19:00-22:00', 'sales', 'ساعات الذروة (HH:MM-HH:MM)'),
('reservation_time_interval', '30', 'sales', 'فاصل الفترات الزمنية بالدقائق'),
('reservation_max_per_slot', '5', 'sales', 'الحد الأقصى للحجوزات في نفس الفترة'),
('reservation_max_advance_days', '30', 'sales', 'الحد الأقصى للحجز مسبقاً بالأيام'),
('reservation_public_default_duration', '90', 'sales', 'المدة الافتراضية للحجز العام بالدقائق'),
('reservation_duplicate_window_hours', '24', 'sales', 'منع الحجز المكرر بنفس الهاتف خلال هذه الساعات'),
('reservation_public_status', 'pending', 'sales', 'الحالة الافتراضية للحجز العام (pending/confirmed)')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), label = VALUES(label);

-- 2. جدول رموز التحقق OTP (مؤقت)
CREATE TABLE IF NOT EXISTS reservation_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(120) NULL,
    otp VARCHAR(10) NOT NULL,
    attempts TINYINT(1) DEFAULT 0,
    verified_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
