-- =====================================================
-- 34: سجل تاريخ أسعار المكونات (Item Price History)
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS item_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    price DECIMAL(12,4) NOT NULL,
    effective_date DATE NOT NULL,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_item_effective_date (item_id, effective_date),
    INDEX idx_item_effective (item_id, effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
