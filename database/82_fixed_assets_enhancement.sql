-- =====================================================
-- 82: نظام إدارة الأصول الثابتة المتقدم (Fixed Assets)
-- =====================================================
SET NAMES utf8mb4;
SET @db = DATABASE();
SET @sql = NULL;

-- توسيع طريقة الإهلاك لدعم طرق إضافية
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='depreciation_method' AND data_type='enum';
SET @sql = IF(@exists=1, "ALTER TABLE fixed_assets MODIFY COLUMN depreciation_method VARCHAR(30) DEFAULT 'straight_line'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- رقم الأصل / الباركود / QR
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='barcode';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN barcode VARCHAR(60) NULL AFTER asset_no', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='qr_code';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- القسم والمسؤول والموقع
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='department_id';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN department_id INT NULL AFTER branch_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='responsible_user_id';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN responsible_user_id INT NULL AFTER department_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='location';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN location VARCHAR(150) NULL AFTER responsible_user_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- القيم الدفترية والمتبقية
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='book_value';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN book_value DECIMAL(14,2) DEFAULT 0 AFTER purchase_cost', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='accumulated_depreciation';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN accumulated_depreciation DECIMAL(14,2) DEFAULT 0 AFTER book_value', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='residual_value';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN residual_value DECIMAL(14,2) DEFAULT 0 AFTER accumulated_depreciation', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- تاريخ البيع/الاستبعاد والمبالغ
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='disposal_date';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN disposal_date DATE NULL AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='disposal_amount';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN disposal_amount DECIMAL(14,2) DEFAULT 0 AFTER disposal_date', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='fixed_assets' AND column_name='sale_amount';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD COLUMN sale_amount DECIMAL(14,2) DEFAULT 0 AFTER disposal_amount', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- فهرسة
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.statistics WHERE table_schema=@db AND table_name='fixed_assets' AND index_name='idx_fa_barcode';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD INDEX idx_fa_barcode (barcode)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.statistics WHERE table_schema=@db AND table_name='fixed_assets' AND index_name='idx_fa_status';
SET @sql = IF(@exists=0, 'ALTER TABLE fixed_assets ADD INDEX idx_fa_status (status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- حركات الأصول (شراء، بيع، تحويل، صيانة، استبعاد، إعادة تقييم، جرد، إهلاك)
CREATE TABLE IF NOT EXISTS asset_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    transaction_type VARCHAR(30) NOT NULL,
    transaction_date DATE NOT NULL,
    amount DECIMAL(14,2) DEFAULT 0,
    qty DECIMAL(14,4) DEFAULT 1,
    from_branch_id INT NULL,
    to_branch_id INT NULL,
    from_department_id INT NULL,
    to_department_id INT NULL,
    from_user_id INT NULL,
    to_user_id INT NULL,
    notes VARCHAR(255),
    journal_entry_id BIGINT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES fixed_assets(id) ON DELETE CASCADE,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    INDEX idx_asset_trans_type (asset_id, transaction_type),
    INDEX idx_asset_trans_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- صيانة الأصول
CREATE TABLE IF NOT EXISTS asset_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    maintenance_date DATE NOT NULL,
    next_due_date DATE NULL,
    cost DECIMAL(14,2) DEFAULT 0,
    provider VARCHAR(120) NULL,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES fixed_assets(id) ON DELETE CASCADE,
    INDEX idx_asset_maint_date (asset_id, maintenance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- تاريخ مواقع الأصول
CREATE TABLE IF NOT EXISTS asset_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    location VARCHAR(150) NULL,
    branch_id INT NULL,
    department_id INT NULL,
    user_id INT NULL,
    effective_date DATE NOT NULL,
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES fixed_assets(id) ON DELETE CASCADE,
    INDEX idx_asset_loc_date (asset_id, effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- تفاصيل إهلاك وحدات الإنتاج (اختياري)
CREATE TABLE IF NOT EXISTS asset_production_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    period VARCHAR(7) NOT NULL,
    units_produced DECIMAL(14,4) DEFAULT 0,
    total_estimated_units DECIMAL(14,4) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_asset_period (asset_id, period),
    FOREIGN KEY (asset_id) REFERENCES fixed_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الحسابات المساعدة للأصول (إن لم تكن موجودة)
INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
VALUES ('4200', 'أرباح أخرى', 'revenue', 4, 'revenue', 1, 0);
