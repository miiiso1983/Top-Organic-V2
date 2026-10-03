-- =====================================================
-- 39: cost_history (  ) 
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cost_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    warehouse_id INT NULL,
    cost_method ENUM('fifo','moving_average','standard') NOT NULL DEFAULT 'moving_average',
    cost_value DECIMAL(14,4) NOT NULL,
    cost_currency VARCHAR(3) NOT NULL DEFAULT 'IQD',
    for_date DATE NOT NULL,
    source VARCHAR(30) NULL,
    source_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    UNIQUE KEY uq_item_wh_date_method (item_id, warehouse_id, for_date, cost_method),
    INDEX idx_item_date (item_id, for_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
