-- =====================================================
-- 06: المالية والمحاسبة
-- =====================================================

CREATE TABLE IF NOT EXISTS cost_centers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    parent_id INT NULL,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- شجرة الحسابات
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
    parent_id INT NULL,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- القيود المحاسبية
CREATE TABLE IF NOT EXISTS journal_entries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    entry_no VARCHAR(30) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    description VARCHAR(255),
    cost_center_id INT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_lines (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    entry_id BIGINT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(14,2) DEFAULT 0,
    credit DECIMAL(14,2) DEFAULT 0,
    FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الصندوق النقدي
CREATE TABLE IF NOT EXISTS cash_registers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    name VARCHAR(80) NOT NULL,
    balance DECIMAL(14,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    register_id INT NOT NULL,
    trx_type ENUM('in','out') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    category VARCHAR(80) COMMENT 'مبيعات، مشتريات، رواتب، مصاريف...',
    reference VARCHAR(60),
    description VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (register_id) REFERENCES cash_registers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- المصروفات
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_no VARCHAR(30) NOT NULL UNIQUE,
    category VARCHAR(100) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    cost_center_id INT NULL,
    expense_date DATE NOT NULL,
    description VARCHAR(255),
    status ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
    created_by INT,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الذمم المدينة والدائنة (دفعات وتحصيل)
CREATE TABLE IF NOT EXISTS payments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    payment_no VARCHAR(30) NOT NULL UNIQUE,
    party_type ENUM('supplier','customer','employee','other') NOT NULL,
    party_id INT NULL,
    direction ENUM('in','out') NOT NULL COMMENT 'تحصيل أو دفع',
    amount DECIMAL(14,2) NOT NULL,
    method ENUM('cash','card','transfer') DEFAULT 'cash',
    reference VARCHAR(60),
    description VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الميزانيات التشغيلية
CREATE TABLE IF NOT EXISTS budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cost_center_id INT NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    planned_amount DECIMAL(14,2) NOT NULL,
    notes VARCHAR(255),
    FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id),
    UNIQUE KEY uq_cc_period (cost_center_id, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
