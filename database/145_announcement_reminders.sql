-- تذكيرات التعميمات (لمرة واحدة أو دورية/روتينية)
CREATE TABLE IF NOT EXISTS announcement_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    body VARCHAR(500) NULL,
    link VARCHAR(255) NULL,
    target_type ENUM('all','level','role','branch','department','user') NOT NULL DEFAULT 'all',
    target_level INT NULL,
    target_role_id INT NULL,
    target_branch_id INT NULL,
    target_department_id INT NULL,
    target_user_ids TEXT NULL COMMENT 'JSON array من معرّفات الموظفين عند target_type=user',
    recurrence_type ENUM('once','daily','weekly','monthly') NOT NULL DEFAULT 'once',
    next_run_at DATETIME NOT NULL COMMENT 'موعد التذكير القادم',
    last_sent_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_due (is_active, next_run_at),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.notifications.reminders', 'إدارة تذكيرات التعميمات', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي')
  AND p.code = 'hr.notifications.reminders';
