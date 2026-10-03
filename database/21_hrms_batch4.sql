-- =====================================================
-- 21: HRMS Batch 4 - إدارة الملفات، التكامل المحاسبي، السلف طويلة الأجل، والتقييم
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. تحديث حالة الملف لتدعم الأرشفة
ALTER TABLE employees MODIFY COLUMN file_status ENUM('open','closed','archived') DEFAULT 'open';

-- 2. سجل تاريخي لتغييرات حالة الملف
CREATE TABLE IF NOT EXISTS employee_file_status_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    old_status ENUM('open','closed','archived') NOT NULL,
    new_status ENUM('open','closed','archived') NOT NULL,
    reason VARCHAR(255),
    changed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. إضافات جدول السلف للسلف طويلة الأجل
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS is_long_term TINYINT(1) DEFAULT 0;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS remaining_amount DECIMAL(12,2) DEFAULT 0;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS paid_amount DECIMAL(12,2) DEFAULT 0;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS journal_entry_id BIGINT NULL;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS approved_by INT NULL;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL;
ALTER TABLE salary_advances ADD FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE salary_advances ADD FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS journal_entry_id BIGINT NULL;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS accrual_entry_id BIGINT NULL;
ALTER TABLE payrolls ADD FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL;
ALTER TABLE payrolls ADD FOREIGN KEY (accrual_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL;

-- 4. جدول جدول السداد للسلف طويلة الأجل
CREATE TABLE IF NOT EXISTS advance_repayment_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    advance_id INT NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','paid') DEFAULT 'pending',
    payroll_id INT NULL,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (advance_id) REFERENCES salary_advances(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE SET NULL,
    UNIQUE KEY uq_advance_period (advance_id, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. تحديث جدول التقييم للمكائب والحالة
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS period VARCHAR(7) NULL COMMENT 'YYYY-MM';
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS evaluation_type ENUM('monthly','quarterly','annual') DEFAULT 'monthly';
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS status ENUM('draft','finalized') DEFAULT 'draft';
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS finalized_at TIMESTAMP NULL;
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS adjustment_id INT NULL;
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS total_score DECIMAL(5,2) DEFAULT 0;
ALTER TABLE evaluations ADD FOREIGN KEY (adjustment_id) REFERENCES hr_adjustments(id) ON DELETE SET NULL;

-- 6. جداول مؤشرات أداء قابلة للتخصيص
CREATE TABLE IF NOT EXISTS performance_kpis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    weight DECIMAL(5,2) DEFAULT 0 COMMENT '0-100',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS evaluation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    kpi_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT '0-100',
    weight DECIMAL(5,2) DEFAULT 0,
    notes VARCHAR(255),
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (kpi_id) REFERENCES performance_kpis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. إضافة حسابات محاسبية للمورد البشري
INSERT IGNORE INTO accounts (code, name, type) VALUES
('1150', 'سلف الموظفين', 'asset'),
('5110', 'المكافآت والحوافز', 'expense'),
('5120', 'الخصومات والجزاءات', 'expense');

-- 8. صلاحيات جديدة
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.file.manage', 'إدارة حالة ملف الموظف', 'hr'),
('hr.advance.approve', 'الموافقة على السلف', 'hr'),
('hr.evaluation.manage', 'إدارة تقييم الموظفين', 'hr'),
('hr.evaluation.view', 'عرض التقييم', 'hr'),
('hr.accounting.settings', 'إعدادات المحاسبة للمورد البشري', 'hr');

-- 9. منح الصلاحيات للمديرين
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code IN ('hr.file.manage','hr.advance.approve','hr.evaluation.manage','hr.evaluation.view','hr.accounting.settings');

-- 10. إنشاء الأرصدة المحاسبية المرتبطة بالمورد البشري
INSERT IGNORE INTO settings (skey, svalue) VALUES
('hr_account_salary', '5100'),
('hr_account_cash', '1000'),
('hr_account_advances', '1150'),
('hr_account_bonuses', '5110'),
('hr_account_deductions', '5120');

-- 11. مؤشرات أداء افتراضية
INSERT IGNORE INTO performance_kpis (name, description, weight) VALUES
('جودة العمل', 'دقة وأداء المهام المكلفة', 30),
('الحضور والانضباط', 'المبادرة في الحضور والوقت', 25),
('التعاون والتواصل', 'القدرة على العمل مع الفريق', 20),
('الإبداع والتحسين', 'المقترحات والتحسين المستمر', 25);

SET FOREIGN_KEY_CHECKS=1;
