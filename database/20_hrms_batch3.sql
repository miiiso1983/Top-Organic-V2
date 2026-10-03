-- =====================================================
-- 20: HRMS Batch 3 - التسويات المالية، المراتب، والتقارير
-- =====================================================
SET NAMES utf8mb4;

-- جدول التسويات المالية (مكافئات، خصومات، زيادات)
CREATE TABLE IF NOT EXISTS hr_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    adjustment_type ENUM('bonus','deduction','raise','penalty') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    effective_date DATE NOT NULL,
    period VARCHAR(7) NULL COMMENT 'YYYY-MM للتطبيق على راتب محدد',
    reason VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    requested_by INT NULL,
    approved_by INT NULL,
    admin_level_required INT DEFAULT 1 COMMENT 'أدنى مستوى مطلوب للموافقة',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_emp_period (employee_id, period),
    INDEX idx_effective (effective_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إضافة حقل لتفاصيل مستحقات المرتب
ALTER TABLE payrolls ADD COLUMN absence_days INT DEFAULT 0;
ALTER TABLE payrolls ADD COLUMN absence_deduction DECIMAL(12,2) DEFAULT 0;
ALTER TABLE payrolls ADD COLUMN raises DECIMAL(12,2) DEFAULT 0;
ALTER TABLE payrolls ADD COLUMN penalties DECIMAL(12,2) DEFAULT 0;
ALTER TABLE payrolls ADD COLUMN generated_by INT NULL;
ALTER TABLE payrolls ADD COLUMN approved_by INT NULL;
ALTER TABLE payrolls ADD FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE payrolls ADD FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

-- مسار اعتماد التسويات المالية
INSERT IGNORE INTO legacy_approval_workflows (entity_type, name) VALUES ('hr_adjustment', 'اعتماد التسويات المالية');
SET @wf_id = (SELECT id FROM legacy_approval_workflows WHERE entity_type = 'hr_adjustment');
INSERT IGNORE INTO legacy_approval_steps (workflow_id, step_order, role_id, min_amount)
SELECT @wf_id, 1, r.id, 0 FROM roles r WHERE r.name IN ('مدير النظام','مدير عام') ORDER BY r.level DESC LIMIT 1;

-- صلاحيات جديدة
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.adjustments.manage', 'إدارة التسويات المالية', 'hr'),
('hr.payroll.manage', 'إدارة المراتب', 'hr'),
('payroll.manage', 'إدارة كشف الرواتب', 'hr'),
('hr.payslip.view', 'عرض ورقة الراتب', 'hr'),
('hr.reports.view', 'عرض تقارير الموارد البشرية', 'hr');

-- منح الصلاحيات للمديرين
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code IN ('hr.adjustments.manage','hr.payroll.manage','payroll.manage','hr.payslip.view','hr.reports.view');

-- إضافة التنبيه للتسويات المالية
INSERT IGNORE INTO notification_rules (event_type, min_admin_level, branch_specific, department_specific)
VALUES ('adjustment_request', 1, 1, 1);
