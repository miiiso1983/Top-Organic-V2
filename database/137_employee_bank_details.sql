-- =====================================================
-- 137: بيانات الموظف البنكية (لتصدير كشف البنك مع الرواتب)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='employees' AND column_name='bank_name');
SET @sql = IF(@col_exists=0, 'ALTER TABLE employees ADD COLUMN bank_name VARCHAR(100) NULL AFTER phone', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='employees' AND column_name='bank_account_no');
SET @sql = IF(@col_exists=0, 'ALTER TABLE employees ADD COLUMN bank_account_no VARCHAR(50) NULL AFTER bank_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='employees' AND column_name='bank_iban');
SET @sql = IF(@col_exists=0, 'ALTER TABLE employees ADD COLUMN bank_iban VARCHAR(50) NULL AFTER bank_account_no', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
