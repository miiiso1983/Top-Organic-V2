-- =====================================================
-- 14: وحدة المحاسبة المتكاملة (Chart of Accounts, Fixed Assets, COGS, Depreciation)
-- =====================================================
SET NAMES utf8mb4;

-- شجرة الحسابات: إضافة الأعمدة بشكل متوافق مع MySQL/MariaDB
SET @db = DATABASE();

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='accounts' AND column_name='account_group';
SET @sql = IF(@exists=0, 'ALTER TABLE accounts ADD COLUMN account_group VARCHAR(50) NULL AFTER type', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='accounts' AND column_name='is_leaf';
SET @sql = IF(@exists=0, 'ALTER TABLE accounts ADD COLUMN is_leaf TINYINT(1) DEFAULT 1 AFTER account_group', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='accounts' AND column_name='opening_balance';
SET @sql = IF(@exists=0, 'ALTER TABLE accounts ADD COLUMN opening_balance DECIMAL(14,2) DEFAULT 0 AFTER is_leaf', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='accounts' AND column_name='notes';
SET @sql = IF(@exists=0, 'ALTER TABLE accounts ADD COLUMN notes VARCHAR(255) NULL AFTER opening_balance', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- تتبع مصدر القيد (طلب، رواتب، استلام، أصل...)
SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='journal_entries' AND column_name='source_type';
SET @sql = IF(@exists=0, 'ALTER TABLE journal_entries ADD COLUMN source_type VARCHAR(40) NULL AFTER cost_center_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='journal_entries' AND column_name='source_id';
SET @sql = IF(@exists=0, 'ALTER TABLE journal_entries ADD COLUMN source_id BIGINT NULL AFTER source_type', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='journal_entries' AND column_name='status';
SET @sql = IF(@exists=0, "ALTER TABLE journal_entries ADD COLUMN status ENUM('draft','posted','reversed') DEFAULT 'posted' AFTER source_id", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.columns WHERE table_schema=@db AND table_name='journal_entries' AND column_name='is_closing';
SET @sql = IF(@exists=0, 'ALTER TABLE journal_entries ADD COLUMN is_closing TINYINT(1) DEFAULT 0 AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = NULL;
SELECT COUNT(*) INTO @exists FROM information_schema.statistics WHERE table_schema=@db AND table_name='journal_entries' AND index_name='idx_source';
SET @sql = IF(@exists=0, 'ALTER TABLE journal_entries ADD INDEX idx_source (source_type, source_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- تكلفة البضاعة المباعة لكل بند طلب
CREATE TABLE IF NOT EXISTS cogs_entries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    order_item_id BIGINT NOT NULL,
    recipe_id INT NULL,
    cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    journal_entry_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- فئات الأصول الثابتة
CREATE TABLE IF NOT EXISTS asset_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    default_life_years INT DEFAULT 5,
    default_salvage_rate DECIMAL(5,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الأصول الثابتة
CREATE TABLE IF NOT EXISTS fixed_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_no VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category_id INT NULL,
    purchase_date DATE NOT NULL,
    purchase_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    salvage_value DECIMAL(14,2) DEFAULT 0,
    life_years INT NOT NULL DEFAULT 5,
    depreciation_method ENUM('straight_line','declining_balance') DEFAULT 'straight_line',
    status ENUM('active','disposed','sold') DEFAULT 'active',
    cost_center_id INT NULL,
    branch_id INT NULL,
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES asset_categories(id),
    FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- قيود الإهلاك
CREATE TABLE IF NOT EXISTS depreciation_entries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    journal_entry_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES fixed_assets(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    UNIQUE KEY uq_asset_period (asset_id, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الفترات المحاسبية (لفتح/إقفال الشهور)
CREATE TABLE IF NOT EXISTS accounting_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period VARCHAR(7) NOT NULL UNIQUE COMMENT 'YYYY-MM',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('open','closed') DEFAULT 'open',
    closed_by INT NULL,
    closed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- شجرة الحسابات الافتراضية
-- ==========================================
INSERT INTO accounts (code, name, type, account_group, is_leaf, opening_balance, notes) VALUES
('1','الأصول','asset','root',0,0,'الأصول'),
('2','الخصوم','liability','root',0,0,'الخصوم'),
('3','حقوق الملكية','equity','root',0,0,'حقوق الملكية'),
('4','الإيرادات','revenue','root',0,0,'الإيرادات'),
('5','المصروفات','expense','root',0,0,'المصروفات')
ON DUPLICATE KEY UPDATE name=VALUES(name), type=VALUES(type), account_group=VALUES(account_group), is_leaf=VALUES(is_leaf);

-- تحديث الحسابات الموجودة لتنتمي للأب المناسب
SET @assets_id = (SELECT id FROM accounts WHERE code='1');
SET @liab_id = (SELECT id FROM accounts WHERE code='2');
SET @equity_id = (SELECT id FROM accounts WHERE code='3');
SET @rev_id = (SELECT id FROM accounts WHERE code='4');
SET @exp_id = (SELECT id FROM accounts WHERE code='5');
UPDATE accounts SET parent_id = @assets_id, account_group='current_assets', is_leaf=1 WHERE code='1000';
UPDATE accounts SET parent_id = @assets_id, account_group='inventory', is_leaf=1 WHERE code='1100';
UPDATE accounts SET parent_id = @liab_id, account_group='payables', is_leaf=1 WHERE code='2000';
UPDATE accounts SET parent_id = @rev_id, account_group='revenue', is_leaf=1 WHERE code='4000';
UPDATE accounts SET parent_id = @exp_id, account_group='cogs', is_leaf=1 WHERE code='5000';
UPDATE accounts SET parent_id = @exp_id, account_group='salaries', is_leaf=1 WHERE code='5100';
UPDATE accounts SET parent_id = @exp_id, account_group='operating', is_leaf=1 WHERE code='5200';

-- حسابات إضافية لإكمال شجرة الحسابات
INSERT INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance) VALUES
('1010','البنك','asset',@assets_id,'current_assets',1,0),
('1110','الذمم المدينة','asset',@assets_id,'receivables',1,0),
('1120','مصروفات مدفوعة مقدماً','asset',@assets_id,'prepaid',1,0),
('1200','الأصول الثابتة','asset',@assets_id,'fixed_assets',1,0),
('1210','مجمع الإهلاك','asset',@assets_id,'accumulated_depreciation',1,0),
('2100','الرواتب المستحقة','liability',@liab_id,'payables',1,0),
('2200','الضريبة المستحقة','liability',@liab_id,'taxes',1,0),
('2300','مصاريف مستحقة','liability',@liab_id,'accruals',1,0),
('3000','رأس المال','equity',@equity_id,'capital',1,0),
('3100','الأرباح المحتجزة','equity',@equity_id,'retained',1,0),
('3200','مسحوبات المالك','equity',@equity_id,'drawings',1,0),
('4100','خصومات المبيعات','revenue',@rev_id,'contra_revenue',1,0),
('5300','مصاريف الإهلاك','expense',@exp_id,'depreciation',1,0),
('5400','مصاريف الكهرباء والماء','expense',@exp_id,'utilities',1,0),
('5500','الإيجار','expense',@exp_id,'rent',1,0),
('5600','الإعلان والتسويق','expense',@exp_id,'marketing',1,0)
ON DUPLICATE KEY UPDATE name=VALUES(name), type=VALUES(type), parent_id=VALUES(parent_id), account_group=VALUES(account_group), is_leaf=VALUES(is_leaf);

-- فئات أصول افتراضية
INSERT INTO asset_categories (name, default_life_years, default_salvage_rate) VALUES
('معدات المطبخ', 7, 10.00),
('معدات الصالة', 5, 10.00),
('أجهزة الكمبيوتر والطابعات', 3, 0.00),
('أثاث ومفروشات', 10, 10.00),
('مركبات', 5, 15.00)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- صلاحيات المحاسبة
INSERT INTO permissions (code, name, module) VALUES
('accounting.view','عرض المحاسبة','accounting'),
('accounting.manage','إدارة المحاسبة','accounting'),
('accounting.reports','التقارير المالية','accounting')
ON DUPLICATE KEY UPDATE name=VALUES(name), module=VALUES(module);

-- منح الصلاحيات الجديدة للأدوار المالية والإدارية
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.name IN ('مدير النظام','مدير عام','مدير مالي','محاسب') AND p.code IN ('accounting.view','accounting.manage','accounting.reports')
ON DUPLICATE KEY UPDATE role_id=role_id;
