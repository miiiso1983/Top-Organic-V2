-- =====================================================
-- 57: تحسين نظام تقييم الموظفين — نصف سنوي، أهداف، تقييم متعدد المصادر، ترقيات
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. تحديث جدول التقييمات
ALTER TABLE evaluations MODIFY COLUMN evaluation_type ENUM('monthly','quarterly','half_yearly','annual') DEFAULT 'monthly';
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS manager_score DECIMAL(5,2) DEFAULT 0;
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS admin_score DECIMAL(5,2) DEFAULT 0;
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS self_score DECIMAL(5,2) DEFAULT 0;
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS kpi_score DECIMAL(5,2) DEFAULT 0;
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS recommendation ENUM('none','bonus','raise','promotion','warning') DEFAULT 'none';
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS manager_notes TEXT NULL;
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL;
ALTER TABLE evaluations ADD COLUMN IF NOT EXISTS finalized_by INT NULL;
ALTER TABLE evaluations ADD FOREIGN KEY IF NOT EXISTS (finalized_by) REFERENCES users(id) ON DELETE SET NULL;

-- 2. أهداف الموظفين
CREATE TABLE IF NOT EXISTS employee_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    target_value DECIMAL(12,2) NULL,
    actual_value DECIMAL(12,2) NULL,
    unit VARCHAR(50) NULL,
    due_date DATE NULL,
    status ENUM('active','achieved','missed','cancelled') DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. ربط الأهداف بالتقييمات
CREATE TABLE IF NOT EXISTS evaluation_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    goal_id INT NOT NULL,
    achievement_rate DECIMAL(5,2) DEFAULT 0 COMMENT '0-100',
    notes VARCHAR(255) NULL,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (goal_id) REFERENCES employee_goals(id) ON DELETE CASCADE,
    UNIQUE KEY uq_eval_goal (evaluation_id, goal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. صلاحيات
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.goals.manage', 'إدارة أهداف الموظفين', 'hr'),
('hr.evaluation.view', 'عرض تقييمات الموظفين', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code IN ('hr.goals.manage','hr.evaluation.view','hr.evaluation.manage');

SET FOREIGN_KEY_CHECKS=1;
