-- =====================================================
-- 83: الحسابات المدينة والدائنة (AR/AP)
-- =====================================================
SET NAMES utf8mb4;
SET @db = DATABASE();
SET @sql = NULL;

-- ربط العميل/المورد بحساب في دفتر الحسابات (اختياري)
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='customers' AND column_name='account_id';
SET @sql = IF(@exists=0, 'ALTER TABLE customers ADD COLUMN account_id INT NULL AFTER balance', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='suppliers' AND column_name='account_id';
SET @sql = IF(@exists=0, 'ALTER TABLE suppliers ADD COLUMN account_id INT NULL AFTER balance', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- تتبع التسويات والخصمات للذمم
CREATE TABLE IF NOT EXISTS arap_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    party_type VARCHAR(20) NOT NULL,
    party_id INT NOT NULL,
    adjustment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    adjustment_type VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    journal_entry_id BIGINT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_party (party_type, party_id),
    INDEX idx_date (adjustment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- تاريخ التخصيص (لتخصيص دفعة لفاتورة/أمر شراء) - اختياري
CREATE TABLE IF NOT EXISTS arap_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    party_type VARCHAR(20) NOT NULL,
    party_id INT NOT NULL,
    payment_id BIGINT NULL,
    order_id BIGINT NULL,
    po_id INT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_party (party_type, party_id),
    INDEX idx_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الحسابات المساعدة (AR/AP) إذا لم تكن موجودة
INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
VALUES ('1110', 'الذمم المدينة', 'asset', 1, 'receivables', 1, 0);

INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
VALUES ('2000', 'الموردون', 'liability', 2, 'payables', 1, 0);

INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
VALUES ('5210', 'مخاسرات وخصمات الذمم', 'expense', 5, 'expense', 1, 0);

INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
VALUES ('4100', 'إيرادات أخرى', 'revenue', 4, 'revenue', 1, 0);
