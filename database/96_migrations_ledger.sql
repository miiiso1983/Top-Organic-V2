-- =====================================================
-- 96: سجل الترحيلات (Migrations Ledger) — منع التكرار وتتبع الحالة
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(120) NOT NULL UNIQUE,
    status ENUM('ok','warnings','failed') NOT NULL DEFAULT 'ok',
    statements_ok INT DEFAULT 0,
    statements_warned INT DEFAULT 0,
    error TEXT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
