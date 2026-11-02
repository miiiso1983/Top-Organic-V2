<?php
use App\Core\Database;
Database::query("CREATE TABLE IF NOT EXISTS ptt_receiver_devices (
 device_id VARCHAR(36) PRIMARY KEY,user_id INT NOT NULL,token VARCHAR(256) NOT NULL,
 environment VARCHAR(16) NOT NULL DEFAULT 'production',is_active TINYINT NOT NULL DEFAULT 1,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_ptt_receiver_user (user_id,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "PTT receiver devices ready.\n";
