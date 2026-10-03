-- =====================================================
-- 135: تكامل محاسبي احترافي للموارد البشرية والرواتب
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. إضافة حسابات محاسبية لمكونات الراتب المفقودة
SET @liab_id = (SELECT id FROM accounts WHERE code='2');
SET @exp_id  = (SELECT id FROM accounts WHERE code='5');

INSERT IGNORE INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance) VALUES
('2110', 'التأمين المستحق', 'liability', @liab_id, 'payables', 1, 0),
('2120', 'الضمان الاجتماعي المستحق', 'liability', @liab_id, 'payables', 1, 0),
('2130', 'تحميلات موظفين مستحقة', 'liability', @liab_id, 'payables', 1, 0),
('2140', 'تيبس مستحق', 'liability', @liab_id, 'payables', 1, 0),
('2150', 'أمانات موظفين', 'liability', @liab_id, 'payables', 1, 0),
('5130', 'تذاكر سفر', 'expense', @exp_id, 'operating', 1, 0);

-- 2. ربط الحسابات بإعدادات HR
INSERT IGNORE INTO settings (skey, svalue) VALUES
('hr_account_salary_payable', '2100'),
('hr_account_tax_payable', '2200'),
('hr_account_insurance_payable', '2110'),
('hr_account_social_security_payable', '2120'),
('hr_account_loadings_payable', '2130'),
('hr_account_tips_payable', '2140'),
('hr_account_security_deposit_payable', '2150'),
('hr_account_travel_tickets', '5130');

-- 3. ربط التسويات بالقيد المحاسبي لإمكانية التتبع والعكس
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='hr_adjustments' AND column_name='journal_entry_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE hr_adjustments ADD COLUMN journal_entry_id BIGINT NULL AFTER approved_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
