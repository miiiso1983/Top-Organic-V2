-- =====================================================
-- 36: سجل أسعار الشراء (Purchase Prices History)
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    supplier_id INT NULL,
    purchase_price DECIMAL(12,4) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'IQD',
    purchase_date DATE NOT NULL,
    is_last_price TINYINT(1) DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    INDEX idx_item_date (item_id, purchase_date),
    INDEX idx_item_last (item_id, is_last_price),
    UNIQUE KEY uq_item_supplier_date (item_id, supplier_id, purchase_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
