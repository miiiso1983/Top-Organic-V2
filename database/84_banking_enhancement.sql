-- =====================================================
-- 84: البنوك والصندوق (Cash & Banking)
-- =====================================================
SET NAMES utf8mb4;
SET @db = DATABASE();
SET @sql = NULL;

-- توسيع جدول الصناديق
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='cash_registers' AND column_name='account_id';
SET @sql = IF(@exists=0, 'ALTER TABLE cash_registers ADD COLUMN account_id INT NULL AFTER branch_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='cash_registers' AND column_name='responsible_user_id';
SET @sql = IF(@exists=0, 'ALTER TABLE cash_registers ADD COLUMN responsible_user_id INT NULL AFTER account_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- الحسابات البنكية
CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_no VARCHAR(50) NOT NULL,
    iban VARCHAR(50) NULL,
    branch VARCHAR(100) NULL,
    currency VARCHAR(10) DEFAULT 'SAR',
    opening_balance DECIMAL(14,2) DEFAULT 0,
    current_balance DECIMAL(14,2) DEFAULT 0,
    account_id INT NULL,
    cost_center_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الحركات البنكية
CREATE TABLE IF NOT EXISTS bank_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NOT NULL,
    trx_type ENUM('deposit','withdrawal','transfer_in','transfer_out','check_in','check_out','fee','interest') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    reference VARCHAR(60) NULL,
    description VARCHAR(255) NULL,
    trx_date DATE NOT NULL,
    journal_entry_id BIGINT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bank (bank_account_id),
    INDEX idx_date (trx_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- التحويلات بين الصناديق والحسابات
CREATE TABLE IF NOT EXISTS bank_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_type ENUM('cash','bank') NOT NULL,
    from_id INT NOT NULL,
    to_type ENUM('cash','bank') NOT NULL,
    to_id INT NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    transfer_date DATE NOT NULL,
    reference VARCHAR(60) NULL,
    description VARCHAR(255) NULL,
    status ENUM('draft','completed','cancelled') DEFAULT 'completed',
    journal_entry_id BIGINT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (transfer_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الشيكات (واردة/صادرة)
CREATE TABLE IF NOT EXISTS checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    check_no VARCHAR(50) NOT NULL,
    check_type ENUM('incoming','outgoing') NOT NULL,
    party_type ENUM('customer','supplier','employee','other') NULL,
    party_id INT NULL,
    bank_account_id INT NULL,
    amount DECIMAL(14,2) NOT NULL,
    check_date DATE NOT NULL,
    due_date DATE NULL,
    status ENUM('pending','cleared','bounced','cancelled') DEFAULT 'pending',
    cleared_date DATE NULL,
    reference VARCHAR(60) NULL,
    description VARCHAR(255) NULL,
    journal_entry_id BIGINT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (check_type),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- التسويات البنكية
CREATE TABLE IF NOT EXISTS bank_reconciliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NOT NULL,
    statement_date DATE NOT NULL,
    statement_balance DECIMAL(14,2) NOT NULL,
    system_balance DECIMAL(14,2) NOT NULL,
    difference DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('open','reconciled') DEFAULT 'open',
    notes VARCHAR(255) NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bank (bank_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bank_reconciliation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reconciliation_id INT NOT NULL,
    trx_type ENUM('deposit_in_transit','outstanding_check','fee','interest','correction','other') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    description VARCHAR(255) NULL,
    is_resolved TINYINT(1) DEFAULT 0,
    INDEX idx_recon (reconciliation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- السلف النقدية
CREATE TABLE IF NOT EXISTS cash_advances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    advance_date DATE NOT NULL,
    purpose VARCHAR(255) NULL,
    status ENUM('pending','approved','settled','cancelled') DEFAULT 'pending',
    settled_amount DECIMAL(14,2) DEFAULT 0,
    payment_method ENUM('cash','bank') DEFAULT 'cash',
    cash_register_id INT NULL,
    bank_account_id INT NULL,
    journal_entry_id BIGINT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- العهد المالية
CREATE TABLE IF NOT EXISTS financial_custodies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    custody_date DATE NOT NULL,
    purpose VARCHAR(255) NULL,
    status ENUM('active','settled','cancelled') DEFAULT 'active',
    spent_amount DECIMAL(14,2) DEFAULT 0,
    returned_amount DECIMAL(14,2) DEFAULT 0,
    cash_register_id INT NULL,
    bank_account_id INT NULL,
    journal_entry_id BIGINT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- مصروفات العهدة
CREATE TABLE IF NOT EXISTS custody_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custody_id INT NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(80) NULL,
    description VARCHAR(255) NULL,
    receipt_no VARCHAR(60) NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_custody (custody_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- حسابات محاسبية مساعدة
INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
VALUES ('1040', 'نقد في الطريق', 'asset', 1, 'cash_in_transit', 1, 0);

INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
VALUES ('1050', 'شيكات تحت التحصيل', 'asset', 1, 'checks_pending', 1, 0);

INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
VALUES ('1060', 'سلف نقدية للموظفين', 'asset', 1, 'cash_advances', 1, 0);

INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
VALUES ('1070', 'عهد مالية', 'asset', 1, 'financial_custody', 1, 0);

INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
VALUES ('2050', 'شيكات صادرة', 'liability', 2, 'outgoing_checks', 1, 0);
