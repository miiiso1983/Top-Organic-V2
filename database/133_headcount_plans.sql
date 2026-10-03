-- خطة الأعداد (Headcount Plan) حسب القسم والفترة
CREATE TABLE IF NOT EXISTS hr_headcount_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    period VARCHAR(7) NOT NULL,
    planned_count INT NOT NULL DEFAULT 0,
    budget_salary DECIMAL(14,2) NOT NULL DEFAULT 0,
    notes TEXT,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_dept_period (department_id, period)
);
