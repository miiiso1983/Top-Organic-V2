-- =====================================================
-- 56: تحسين التسويات المالية وإضافة الإنذارات
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. تأمين جدول التسويات
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

-- 2. جدول الإنذارات
CREATE TABLE IF NOT EXISTS employee_warnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    warning_type VARCHAR(60) NOT NULL COMMENT 'verbal, written, final',
    reason TEXT NOT NULL,
    warning_date DATE NOT NULL,
    status ENUM('active','resolved','expired') DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee (employee_id),
    INDEX idx_date (warning_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. مسار اعتماد التسويات المالية
INSERT IGNORE INTO legacy_approval_workflows (entity_type, name) VALUES ('hr_adjustment', 'اعتماد التسويات المالية');
SET @wf_id = (SELECT id FROM legacy_approval_workflows WHERE entity_type = 'hr_adjustment');
INSERT IGNORE INTO legacy_approval_steps (workflow_id, step_order, role_id, min_amount)
SELECT @wf_id, 1, r.id, 0 FROM roles r WHERE r.name IN ('مدير النظام','مدير عام') ORDER BY r.level DESC LIMIT 1;

-- 4. صلاحيات
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.adjustments.manage', 'إدارة التسويات المالية', 'hr'),
('hr.warnings.manage', 'إدارة إنذارات الموظفين', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code IN ('hr.adjustments.manage','hr.warnings.manage');

-- 5. تنبيهات
INSERT IGNORE INTO notification_rules (event_type, min_admin_level, branch_specific, department_specific)
VALUES ('adjustment_request', 1, 1, 1);

SET FOREIGN_KEY_CHECKS=1;
