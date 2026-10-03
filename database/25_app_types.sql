-- 25: إعدادات أنواع التطبيقات (HungerStation, Jahez, Mrsool...)
CREATE TABLE IF NOT EXISTS app_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(30) NULL,
    account_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders
    ADD COLUMN app_type_id INT NULL AFTER order_type,
    ADD FOREIGN KEY (app_type_id) REFERENCES app_types(id) ON DELETE SET NULL;

ALTER TABLE order_payments
    MODIFY COLUMN method ENUM('cash','card','online','wallet','credit') DEFAULT 'cash';

-- بذرة أنواع التطبيقات الافتراضية
INSERT INTO app_types (name, code, is_active) VALUES
('HungerStation', 'hungerstation', 1),
('Jahez', 'jahez', 1),
('Mrsool', 'mrsool', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code), is_active = VALUES(is_active);
