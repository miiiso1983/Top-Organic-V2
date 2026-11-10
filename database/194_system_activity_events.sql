-- Metadata only. Business audit trails remain in their original tables.
CREATE TABLE IF NOT EXISTS system_activity_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT NULL, branch_id INT NULL,
 channel VARCHAR(20) NOT NULL, action VARCHAR(190) NOT NULL,
 entity_id BIGINT NULL, route VARCHAR(255) NOT NULL, method VARCHAR(10) NOT NULL,
 result VARCHAR(20) NOT NULL, http_status SMALLINT NOT NULL,
 ip VARCHAR(45) NULL, device VARCHAR(30) NULL, duration_ms INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_activity_time (created_at, id),
 INDEX idx_activity_actor (user_id, created_at),
 INDEX idx_activity_result (result, created_at),
 INDEX idx_activity_channel (channel, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO permissions (code, name, module) VALUES
 ('admin.audit', 'عرض سجل أحداث النظام الشامل', 'admin');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code='admin.audit' WHERE r.name='مدير النظام';
