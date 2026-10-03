-- =====================================================
-- 136: تطوير آلية صرف السلف — تسجيل طريقة الدفع والحساب/الصندوق
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. إضافة أعمدة طريقة الصرف والربط
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='salary_advances' AND column_name='payment_method');
SET @sql = IF(@col_exists=0, 'ALTER TABLE salary_advances ADD COLUMN payment_method ENUM(''cash'',''bank'') NULL AFTER paid_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='salary_advances' AND column_name='bank_account_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE salary_advances ADD COLUMN bank_account_id INT NULL AFTER payment_method', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='salary_advances' AND column_name='cash_register_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE salary_advances ADD COLUMN cash_register_id INT NULL AFTER bank_account_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='salary_advances' AND column_name='paid_by');
SET @sql = IF(@col_exists=0, 'ALTER TABLE salary_advances ADD COLUMN paid_by INT NULL AFTER cash_register_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. المفاتيح الخارجية (شرطية)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='salary_advances' AND constraint_name='fk_salary_advances_bank_account');
SET @sql = IF(@fk_exists=0, 'ALTER TABLE salary_advances ADD CONSTRAINT fk_salary_advances_bank_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='salary_advances' AND constraint_name='fk_salary_advances_cash_register');
SET @sql = IF(@fk_exists=0, 'ALTER TABLE salary_advances ADD CONSTRAINT fk_salary_advances_cash_register FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='salary_advances' AND constraint_name='fk_salary_advances_paid_by');
SET @sql = IF(@fk_exists=0, 'ALTER TABLE salary_advances ADD CONSTRAINT fk_salary_advances_paid_by FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
