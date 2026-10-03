-- =====================================================
-- 88: إصلاح جدول التقييمات وإنشاء الجداول المرتبطة
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS add_col_if_missing(IN tbl VARCHAR(64), IN col VARCHAR(64), IN def VARCHAR(255))
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=tbl AND column_name=col) THEN
        SET @sql = CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', col, ' ', def);
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- تحديث جدول التقييمات
CALL add_col_if_missing('evaluations', 'period', "VARCHAR(7) NULL COMMENT 'YYYY-MM'");
CALL add_col_if_missing('evaluations', 'evaluation_type', "ENUM('monthly','quarterly','half_yearly','annual') DEFAULT 'monthly'");
CALL add_col_if_missing('evaluations', 'status', "ENUM('draft','finalized') DEFAULT 'draft'");
CALL add_col_if_missing('evaluations', 'finalized_at', "TIMESTAMP NULL");
CALL add_col_if_missing('evaluations', 'adjustment_id', "INT NULL");
CALL add_col_if_missing('evaluations', 'total_score', "DECIMAL(5,2) DEFAULT 0");
CALL add_col_if_missing('evaluations', 'kpi_score', "DECIMAL(5,2) DEFAULT 0");
CALL add_col_if_missing('evaluations', 'manager_score', "DECIMAL(5,2) DEFAULT 0");
CALL add_col_if_missing('evaluations', 'admin_score', "DECIMAL(5,2) DEFAULT 0");
CALL add_col_if_missing('evaluations', 'self_score', "DECIMAL(5,2) DEFAULT 0");
CALL add_col_if_missing('evaluations', 'recommendation', "ENUM('none','bonus','raise','promotion','warning') DEFAULT 'none'");
CALL add_col_if_missing('evaluations', 'manager_notes', "TEXT NULL");
CALL add_col_if_missing('evaluations', 'admin_notes', "TEXT NULL");
CALL add_col_if_missing('evaluations', 'finalized_by', "INT NULL");
ALTER TABLE evaluations ADD FOREIGN KEY (adjustment_id) REFERENCES hr_adjustments(id) ON DELETE SET NULL;
ALTER TABLE evaluations ADD FOREIGN KEY (finalized_by) REFERENCES users(id) ON DELETE SET NULL;

-- إزالة العمود القديم score إذا كان موجوداً
SET @drop_score = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='evaluations' AND column_name='score'), 'ALTER TABLE evaluations DROP COLUMN score', 'SELECT 1');
PREPARE stmt FROM @drop_score; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- جداول المؤشرات والأهداف
CREATE TABLE IF NOT EXISTS performance_kpis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    weight DECIMAL(5,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS evaluation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    kpi_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL DEFAULT 0,
    weight DECIMAL(5,2) DEFAULT 0,
    notes VARCHAR(255),
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (kpi_id) REFERENCES performance_kpis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS evaluation_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    goal_id INT NOT NULL,
    achievement_rate DECIMAL(5,2) DEFAULT 0,
    notes VARCHAR(255) NULL,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (goal_id) REFERENCES employee_goals(id) ON DELETE CASCADE,
    UNIQUE KEY uq_eval_goal (evaluation_id, goal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- صلاحيات
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.evaluation.manage', 'إدارة تقييم الموظفين', 'hr'),
('hr.evaluation.view', 'عرض تقييمات الموظفين', 'hr'),
('hr.goals.manage', 'إدارة أهداف الموظفين', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي') AND p.code IN ('hr.evaluation.manage','hr.evaluation.view','hr.goals.manage');

DROP PROCEDURE IF EXISTS add_col_if_missing;
SET FOREIGN_KEY_CHECKS=1;
