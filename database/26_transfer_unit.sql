-- 26: إضافة unit_id إلى بند المناقلة لتخزين الوحدة المستخدمة
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE stock_transfer_items
    ADD COLUMN unit_id INT NULL AFTER qty,
    ADD FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS=1;
