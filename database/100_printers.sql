SET NAMES utf8mb4;

-- جدول الطابعات
CREATE TABLE IF NOT EXISTS printers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'thermal',
    kitchen_id INT NULL,
    config JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_printer_kitchen (kitchen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ترحيل أسماء الطابعات الموجودة في مراكز التحضير إلى جدول الطابعات
INSERT INTO printers (name, type, kitchen_id, config, is_active)
SELECT DISTINCT printer_name, 'thermal', id, NULL, 1
FROM preparation_kitchens
WHERE printer_name IS NOT NULL AND printer_name <> '';
