-- =====================================================
-- 58: تحسين نظام التبليغات — تعميمات، استهداف، تأكيد استلام، سجل قراءة
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. تحديث جدول الإشعارات بحقول التعميم والاستهداف وتأكيد القراءة
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS sender_id INT NULL AFTER user_id;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS is_announcement TINYINT(1) DEFAULT 0;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_type ENUM('all','level','role','branch','department','user') DEFAULT 'user';
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_level INT NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_role_id INT NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_branch_id INT NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_department_id INT NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS read_at TIMESTAMP NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS read_ip VARCHAR(45) NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS broadcast_id BIGINT NULL COMMENT 'رابط جميع نسخ التعميم الواحد';
ALTER TABLE notifications ADD FOREIGN KEY IF NOT EXISTS (sender_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE notifications ADD FOREIGN KEY IF NOT EXISTS (target_role_id) REFERENCES roles(id) ON DELETE SET NULL;
ALTER TABLE notifications ADD FOREIGN KEY IF NOT EXISTS (target_branch_id) REFERENCES branches(id) ON DELETE SET NULL;
ALTER TABLE notifications ADD FOREIGN KEY IF NOT EXISTS (target_department_id) REFERENCES departments(id) ON DELETE SET NULL;
ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_target (target_type, target_role_id, target_branch_id, target_department_id);
ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_read (user_id, is_announcement, read_at);

-- 2. سجل تفصيلي لتأكيد الاستلام (للتعميمات والإشعارات المهمة)
CREATE TABLE IF NOT EXISTS notification_read_receipts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    notification_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_ip VARCHAR(45) NULL,
    UNIQUE KEY uq_receipt (notification_id, user_id),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. صلاحيات التبليغات والتعميمات
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.notifications.send', 'إرسال تبليغات وتعميمات', 'hr'),
('hr.notifications.view', 'عرض التبليغات والتعميمات', 'hr'),
('hr.notifications.history', 'سجل التبليغات وتأكيدات الاستلام', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code IN ('hr.notifications.send','hr.notifications.view','hr.notifications.history','hr.notification_rules');

SET FOREIGN_KEY_CHECKS=1;
