-- =====================================================
-- 85: مراكز الكلفة (Cost Centers)
-- =====================================================
SET NAMES utf8mb4;
SET @db = DATABASE();
SET @sql = NULL;

-- توسيع جدول مراكز الكلفة
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='cost_centers' AND column_name='code';
SET @sql = IF(@exists=0, 'ALTER TABLE cost_centers ADD COLUMN code VARCHAR(20) NULL AFTER name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='cost_centers' AND column_name='description';
SET @sql = IF(@exists=0, 'ALTER TABLE cost_centers ADD COLUMN description VARCHAR(255) NULL AFTER code', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='cost_centers' AND column_name='manager_id';
SET @sql = IF(@exists=0, 'ALTER TABLE cost_centers ADD COLUMN manager_id INT NULL AFTER parent_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ربط العمليات بمراكز الكلفة
-- orders already has cost_center_id in most installs; skip if exists
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='purchase_orders' AND column_name='cost_center_id';
SET @sql = IF(@exists=0, 'ALTER TABLE purchase_orders ADD COLUMN cost_center_id INT NULL AFTER supplier_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='cash_transactions' AND column_name='cost_center_id';
SET @sql = IF(@exists=0, 'ALTER TABLE cash_transactions ADD COLUMN cost_center_id INT NULL AFTER category', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='bank_transactions' AND column_name='cost_center_id';
SET @sql = IF(@exists=0, 'ALTER TABLE bank_transactions ADD COLUMN cost_center_id INT NULL AFTER reference', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='payments' AND column_name='cost_center_id';
SET @sql = IF(@exists=0, 'ALTER TABLE payments ADD COLUMN cost_center_id INT NULL AFTER reference', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='cash_advances' AND column_name='cost_center_id';
SET @sql = IF(@exists=0, 'ALTER TABLE cash_advances ADD COLUMN cost_center_id INT NULL AFTER purpose', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='custody_expenses' AND column_name='cost_center_id';
SET @sql = IF(@exists=0, 'ALTER TABLE custody_expenses ADD COLUMN cost_center_id INT NULL AFTER category', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- توزيع المصروفات على عدة مراكز كلفة
CREATE TABLE IF NOT EXISTS cost_center_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_type VARCHAR(30) NOT NULL,
    source_id INT NOT NULL,
    cost_center_id INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source (source_type, source_id),
    INDEX idx_cost_center (cost_center_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
