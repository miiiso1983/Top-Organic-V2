-- 28: إضافة حقل وجهة المخزن لحركات الإخراج
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE stock_movements
  ADD COLUMN destination_warehouse_id INT NULL AFTER warehouse_id,
  ADD FOREIGN KEY (destination_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
  ADD INDEX idx_destination (destination_warehouse_id);

SET FOREIGN_KEY_CHECKS=1;
