CREATE TABLE IF NOT EXISTS mobile_app_sessions (
 token_hash CHAR(64) PRIMARY KEY, user_id INT NOT NULL,
 platform VARCHAR(16) NOT NULL, build_number INT NOT NULL DEFAULT 0,
 version_name VARCHAR(32) NOT NULL DEFAULT '', ambiguous TINYINT NOT NULL DEFAULT 0,
 last_seen DATETIME NOT NULL, INDEX idx_mobile_seen (last_seen), INDEX idx_mobile_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
