-- =====================================================
-- 94: وحدة Push-to-Talk المؤسسية (Enterprise PTT)
-- قنوات + رسائل صوتية + مستلمون + حالات تتبع
-- =====================================================
SET NAMES utf8mb4;

-- القنوات (غير محدودة: أقسام/فروع/مشاريع/عامة)
CREATE TABLE IF NOT EXISTS ptt_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255),
    branch_id INT NULL COMMENT 'قناة خاصة بفرع (NULL = عامة)',
    department VARCHAR(100) NULL COMMENT 'قناة خاصة بقسم',
    color VARCHAR(20) DEFAULT '#2563eb',
    icon VARCHAR(40) DEFAULT 'fa-solid fa-tower-broadcast',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_readonly TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'للقراءة فقط (الإرسال للمشرفين)',
    owner_id INT NULL COMMENT 'مشرف القناة',
    max_duration_sec INT NULL COMMENT 'حد مدة الرسالة (NULL = إعداد النظام)',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- أعضاء القنوات وصلاحياتهم داخلها
CREATE TABLE IF NOT EXISTS ptt_channel_members (
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    can_send TINYINT(1) NOT NULL DEFAULT 1,
    can_broadcast_live TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'البث المباشر (مرحلة لاحقة)',
    role ENUM('member','supervisor') NOT NULL DEFAULT 'member',
    muted TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'كتم الإشعارات (يستلم بلا تنبيه)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (channel_id, user_id),
    FOREIGN KEY (channel_id) REFERENCES ptt_channels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الرسائل الصوتية
CREATE TABLE IF NOT EXISTS ptt_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NULL COMMENT 'رسالة قناة (NULL = مباشرة/مخصصة)',
    sender_id INT NOT NULL,
    recipient_type ENUM('channel','user','multi','department','branch','level','all') NOT NULL,
    recipient_json JSON NULL COMMENT 'معرفات المستلمين للأنواع user/multi/department/branch/level',
    priority ENUM('normal','important','urgent','emergency') NOT NULL DEFAULT 'normal',
    file_path VARCHAR(255) NOT NULL,
    file_ext VARCHAR(10) NOT NULL,
    codec VARCHAR(30) DEFAULT 'opus',
    duration_sec DECIMAL(6,2) DEFAULT 0,
    size_bytes INT DEFAULT 0,
    require_ack TINYINT(1) DEFAULT 0 COMMENT 'طلب تأكيد استلام',
    require_listen TINYINT(1) DEFAULT 0 COMMENT 'طلب تأكيد استماع',
    require_reply TINYINT(1) DEFAULT 0 COMMENT 'رد إجباري',
    status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    transcript TEXT NULL COMMENT 'النص المفرّغ (AI - مرحلة 2)',
    transcript_lang VARCHAR(10) NULL,
    branch_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES ptt_channels(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    INDEX idx_channel (channel_id, id),
    INDEX idx_sender (sender_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- حالة كل مستلم (تتبع التسليم/التشغيل/التأكيد)
CREATE TABLE IF NOT EXISTS ptt_message_recipients (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('sent','delivered','played','acknowledged','failed') NOT NULL DEFAULT 'sent',
    delivered_at DATETIME NULL,
    played_at DATETIME NULL,
    acknowledged_at DATETIME NULL,
    UNIQUE KEY uq_msg_user (message_id, user_id),
    FOREIGN KEY (message_id) REFERENCES ptt_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الصلاحيات
INSERT IGNORE INTO permissions (code, name, module) VALUES
('ptt.use','استخدام PTT (إرسال واستقبال)','ptt'),
('ptt.manage','إدارة قنوات PTT','ptt'),
('ptt.emergency','إرسال رسائل طارئة PTT','ptt'),
('ptt.broadcast_live','البث المباشر PTT','ptt'),
('ptt.delete','حذف رسائل PTT','ptt');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name = 'مدير النظام' AND p.code LIKE 'ptt.%';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.level >= 80 AND p.code IN ('ptt.use','ptt.emergency');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE p.code = 'ptt.use';

-- القنوات الافتراضية
INSERT IGNORE INTO ptt_channels (code, name, description, color, icon, is_active) VALUES
('kitchen','المطبخ','قناة فريق المطبخ','#dc2626','fa-solid fa-fire-burner',1),
('cashier','الكاشير','قناة الكاشير والمحاسبة','#16a34a','fa-solid fa-cash-register',1),
('reception','الاستقبال','قناة الاستقبال والضيافة','#0891b2','fa-solid fa-bell-concierge',1),
('call_center','الكول سنتر','قناة مركز الاتصال','#7c3aed','fa-solid fa-headset',1),
('warehouse','المخزن','قناة المخازن والمستودعات','#d97706','fa-solid fa-boxes-stacked',1),
('purchasing','المشتريات','قناة المشتريات والموردين','#0d9488','fa-solid fa-cart-shopping',1),
('management','الإدارة','قناة الإدارة العامة','#111827','fa-solid fa-user-tie',1),
('hr','الموارد البشرية','قناة شؤون الموظفين','#be185d','fa-solid fa-users-gear',1),
('accounting','الحسابات','قناة المحاسبة والمالية','#1d4ed8','fa-solid fa-calculator',1),
('maintenance','الصيانة','قناة الصيانة والتشغيل','#b45309','fa-solid fa-screwdriver-wrench',1),
('security','الأمن','قناة الأمن والحراسة','#334155','fa-solid fa-shield-halved',1),
('delivery','التوصيل','قناة التوصيل والسائقين','#0284c7','fa-solid fa-truck-fast',1),
('all_staff','جميع الموظفين','إذاعة عامة لكل الموظفين','#4f46e5','fa-solid fa-bullhorn',1);

-- عضوية مدير النظام (مشرف) في كل القنوات
INSERT IGNORE INTO ptt_channel_members (channel_id, user_id, can_send, can_broadcast_live, role)
SELECT c.id, u.id, 1, 1, 'supervisor' FROM ptt_channels c
JOIN users u ON u.id = (SELECT MIN(id) FROM users)
JOIN roles r ON r.id = u.role_id AND r.name = 'مدير النظام';

-- قناة جميع الموظفين: عضوية كل المستخدمين الحاليين
INSERT IGNORE INTO ptt_channel_members (channel_id, user_id, can_send, role)
SELECT c.id, u.id, 1, 'member' FROM ptt_channels c CROSS JOIN users u
WHERE c.code = 'all_staff';
