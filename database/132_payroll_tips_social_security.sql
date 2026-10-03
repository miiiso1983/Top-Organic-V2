-- التيبس الشهري (Tips): مبلغ كامل يُقسم تناسبيًا على أيام الدوام
CREATE TABLE IF NOT EXISTS employee_monthly_tips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period VARCHAR(7) NOT NULL,
    full_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    working_days INT NOT NULL DEFAULT 30,
    prorated_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_employee_period_tips (employee_id, period)
);

-- الضمان الاجتماعي الشهري (Social Security): استقطاع ثابت
CREATE TABLE IF NOT EXISTS employee_monthly_social_security (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period VARCHAR(7) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_employee_period_ss (employee_id, period)
);

-- أعمدة في مسير الرواتب
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS tips DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER loadings;
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS social_security DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tips;
