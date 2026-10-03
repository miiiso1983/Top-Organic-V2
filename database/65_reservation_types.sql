-- =====================================================
-- 65: Reservation Types - أنواع الحجز المخصصة
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. جدول أنواع الحجز
CREATE TABLE IF NOT EXISTS reservation_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name_ar VARCHAR(80) NOT NULL,
    name_en VARCHAR(80) NULL,
    description TEXT NULL,
    icon VARCHAR(20) NULL DEFAULT '🍽️',
    color VARCHAR(10) NULL DEFAULT '#10914d',
    min_guests INT DEFAULT 1,
    max_guests INT DEFAULT 50,
    default_guests INT DEFAULT 2,
    min_advance_hours INT DEFAULT 0,
    max_advance_days INT DEFAULT 30,
    duration_minutes INT DEFAULT 90,
    price_per_person DECIMAL(15,2) NULL,
    deposit_amount DECIMAL(15,2) NULL,
    deposit_required TINYINT(1) DEFAULT 0,
    requires_approval TINYINT(1) DEFAULT 0,
    requires_manager_confirmation TINYINT(1) DEFAULT 0,
    requires_contract TINYINT(1) DEFAULT 0,
    allows_partial_payment TINYINT(1) DEFAULT 0,
    workflow_template_id INT NULL,
    permission_code VARCHAR(80) NULL,
    message_template_confirm TEXT NULL,
    message_template_reminder TEXT NULL,
    message_template_cancel TEXT NULL,
    requirements TEXT NULL,
    default_notes TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. تحويل عمود نوع الحجز إلى VARCHAR
ALTER TABLE reservations
    MODIFY COLUMN reservation_type VARCHAR(40) NOT NULL DEFAULT 'regular';

-- 3. الأنواع الافتراضية
INSERT INTO reservation_types (code, name_ar, name_en, icon, color, min_guests, max_guests, default_guests, duration_minutes, requires_approval, permission_code, message_template_confirm, requirements) VALUES
('regular', 'الحجز العادي', 'Regular Reservation', '🍽️', '#10914d', 1, 20, 2, 90, 0, 'reservations.regular.manage', 'تم تأكيد حجزك العادي لـ {guests} أشخاص في {branch} بتاريخ {date} الساعة {time}. ننتظرك!', 'لا يوجد متطلبات خاصة'),
('vip', 'حجز VIP', 'VIP Reservation', '👑', '#8b5cf6', 1, 12, 2, 120, 1, 'reservations.vip.manage', 'تم تأكيد حجزك VIP لـ {guests} أشخاص في {branch} بتاريخ {date} الساعة {time}. سيتواصل معك مدير القسم.', 'يتطلب موافقة المدير وخدمة مميزة'),
('birthday', 'حفلات أعياد الميلاد', 'Birthday Party', '🎂', '#ec4899', 4, 30, 8, 120, 1, 'reservations.birthday.manage', 'تم استلام طلب حفلة عيد الميلاد لـ {guests} شخص. سنتواصل لتأكيد التفاصيل والكيكة.', 'تنسيق مسبق للكيكة والديكور، يتطلب عقد'),
('meeting', 'صالات الاجتماعات', 'Meeting Room', '💼', '#3b82f6', 2, 50, 6, 180, 1, 'reservations.meeting.manage', 'تم تأكيد حجز صالة الاجتماعات لـ {guests} شخص في {branch} بتاريخ {date} الساعة {time}.', 'يتطلب شاشة عرض/بروجكتور، يفضل حجز مسبق 24 ساعة'),
('events', 'صالات المناسبات', 'Event Hall', '🎉', '#f59e0b', 10, 200, 25, 240, 1, 'reservations.events.manage', 'تم استلام طلب حجز صالة المناسبات لـ {guests} شخص. سنتواصل لاستلام العقد.', 'يتطلب عقد وتأمين قاعة، يتطلب موافقة إدارية'),
('wedding', 'صالات الأعراس', 'Wedding Hall', '💒', '#d946ef', 50, 500, 100, 300, 1, 'reservations.wedding.manage', 'تم استلام طلب حجز صالة الأعراس لـ {guests} شخص. سيتواصل معك فريق التنسيق.', 'يتطلب عقد وتأمين، تنسيق قائمة الطعام مسبقاً'),
('group', 'الحجز الجماعي', 'Group Reservation', '👥', '#10b981', 8, 60, 12, 120, 1, 'reservations.group.manage', 'تم تأكيد الحجز الجماعي لـ {guests} شخص في {branch} بتاريخ {date} الساعة {time}.', 'يتطلب دفع عربون 30% للمجموعات الكبيرة'),
('corporate', 'الحجز الخاص بالشركات', 'Corporate Reservation', '🏢', '#6366f1', 5, 100, 10, 180, 1, 'reservations.corporate.manage', 'تم تأكيد الحجز Corporate لـ {guests} شخص في {branch} بتاريخ {date} الساعة {time}.', 'يتطلب فاتورة ضريبية وتصنيف الشركة')
ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), name_en = VALUES(name_en), icon = VALUES(icon), color = VALUES(color);

-- 4. تحديث الحجوزات القديمة
UPDATE reservations SET reservation_type = 'regular' WHERE reservation_type = 'dine_in';
UPDATE reservations SET reservation_type = 'events' WHERE reservation_type IN ('private','event');
UPDATE reservations SET reservation_type = 'corporate' WHERE reservation_type = 'business';
UPDATE reservations SET reservation_type = 'birthday' WHERE reservation_type = 'birthday';
UPDATE reservations SET reservation_type = 'delivery' WHERE reservation_type = 'delivery';
UPDATE reservations SET reservation_type = 'takeaway' WHERE reservation_type = 'takeaway';

-- 5. صلاحيات جديدة
INSERT IGNORE INTO permissions (code, name, module) VALUES
('reservations.types.manage', 'إدارة أنواع الحجز', 'sales'),
('reservations.vip.manage', 'إدارة حجز VIP', 'sales'),
('reservations.birthday.manage', 'إدارة حفلات أعياد الميلاد', 'sales'),
('reservations.meeting.manage', 'إدارة صالات الاجتماعات', 'sales'),
('reservations.events.manage', 'إدارة صالات المناسبات', 'sales'),
('reservations.wedding.manage', 'إدارة صالات الأعراس', 'sales'),
('reservations.group.manage', 'إدارة الحجز الجماعي', 'sales'),
('reservations.corporate.manage', 'إدارة الحجز Corporate', 'sales');

SET FOREIGN_KEY_CHECKS=1;
