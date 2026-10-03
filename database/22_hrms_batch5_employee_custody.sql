-- =====================================================
-- 22: HRMS Batch 5 - نظام إدارة العهد (Employee Custody System)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. حالة القطعة في المخزن (للأصول المحددة بالرقم التسلسلي)
ALTER TABLE items ADD COLUMN IF NOT EXISTS custody_status ENUM('in_stock','in_custody','disposed') DEFAULT 'in_stock' AFTER is_active;

-- 2. جدول العهد المرتبطة بالموظفين
CREATE TABLE IF NOT EXISTS employee_custody (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    item_id INT NOT NULL,
    warehouse_id INT NULL,
    serial_number VARCHAR(100) NULL COMMENT 'الرقم التسلسلي للقطعة',
    qty DECIMAL(12,3) NOT NULL DEFAULT 1,
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    total_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('active','returned','damaged','lost') DEFAULT 'active',
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NULL COMMENT 'تاريخ استحقاق الإرجاع',
    returned_at TIMESTAMP NULL,
    returned_condition VARCHAR(100) NULL,
    notes VARCHAR(255),
    issued_by INT NULL,
    returned_by INT NULL,
    journal_entry_id BIGINT NULL,
    return_entry_id BIGINT NULL,
    penalty_adjustment_id INT NULL,
    clearance_required TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (returned_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    FOREIGN KEY (return_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    FOREIGN KEY (penalty_adjustment_id) REFERENCES hr_adjustments(id) ON DELETE SET NULL,
    INDEX idx_employee_status (employee_id, status),
    INDEX idx_due_date (due_date),
    INDEX idx_serial (serial_number),
    INDEX idx_item_status (item_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. سجل تنبيهات العهد
CREATE TABLE IF NOT EXISTS employee_custody_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custody_id INT NOT NULL,
    employee_id INT NOT NULL,
    alert_date DATE NOT NULL,
    alert_type ENUM('due_soon','overdue') NOT NULL DEFAULT 'due_soon',
    is_acknowledged TINYINT(1) DEFAULT 0,
    acknowledged_by INT NULL,
    acknowledged_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (custody_id) REFERENCES employee_custody(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_custody_alert (custody_id, alert_type, alert_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. حسابات محاسبية للعهدة
INSERT IGNORE INTO accounts (code, name, type) VALUES
('1160', 'ذمم عهدة الموظفين', 'asset'),
('5130', 'غرامات العهدة', 'expense');

-- 5. تحديث شجرة الحسابات للحسابات الجديدة
SET @assets_id = (SELECT id FROM accounts WHERE code='1');
SET @exp_id = (SELECT id FROM accounts WHERE code='5');
UPDATE accounts SET parent_id = @assets_id, account_group='receivables', is_leaf=1 WHERE code='1160';
UPDATE accounts SET parent_id = @exp_id, account_group='operating', is_leaf=1 WHERE code='5130';

-- 6. صلاحيات جديدة
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.custody.view', 'عرض العهد', 'hr'),
('hr.custody.manage', 'إدارة العهد', 'hr'),
('hr.custody.clearance', 'إخلاء طرف العهد', 'hr');

-- 7. منح الصلاحيات للمديرين
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code IN ('hr.custody.view','hr.custody.manage','hr.custody.clearance');

-- 8. إعدادات الحسابات المحاسبية للعهدة
INSERT IGNORE INTO settings (skey, svalue) VALUES
('hr_account_custody_receivable', '1160'),
('hr_account_custody_penalty', '5130'),
('hr_account_inventory', '1100'),
('custody_alert_days_before', '7');

SET FOREIGN_KEY_CHECKS=1;
