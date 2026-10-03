-- =====================================================
-- 51: Employee Management Module — complete employee file
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    department_id INT NULL,
    code VARCHAR(20) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS department_id INT NULL AFTER department,
    ADD COLUMN IF NOT EXISTS job_id INT NULL AFTER position,
    ADD COLUMN IF NOT EXISTS manager_id INT NULL AFTER job_id,
    ADD COLUMN IF NOT EXISTS status ENUM('active','vacation','suspended','terminated') DEFAULT 'active' AFTER file_status,
    ADD COLUMN IF NOT EXISTS cost_center_id INT NULL AFTER branch_id,
    ADD COLUMN IF NOT EXISTS account_id INT NULL AFTER cost_center_id,
    ADD CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_employees_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_employees_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_employees_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_employees_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL;

INSERT IGNORE INTO departments (name) SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '';
UPDATE employees e JOIN departments d ON d.name = e.department SET e.department_id = d.id WHERE e.department_id IS NULL;

INSERT IGNORE INTO jobs (title, department_id)
SELECT DISTINCT e.position, e.department_id FROM employees e WHERE e.position IS NOT NULL AND e.position != '';
UPDATE employees e JOIN jobs j ON j.title = e.position AND (j.department_id = e.department_id OR j.department_id IS NULL)
SET e.job_id = j.id WHERE e.job_id IS NULL;

CREATE TABLE IF NOT EXISTS employee_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    doc_type ENUM('id','contract','certificate','medical','visa','other') DEFAULT 'other',
    title VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    notes VARCHAR(255),
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee (employee_id),
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    action VARCHAR(60) NOT NULL,
    field_name VARCHAR(60) NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    changed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee (employee_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.employee.documents','إدارة مستندات الموظف','hr'),
('hr.employee.audit','عرض سجل تدقيق الموظف','hr'),
('hr.department.manage','إدارة الأقسام','hr'),
('hr.job.manage','إدارة الوظائف','hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.level >= 5 AND p.code IN ('hr.employee.documents','hr.employee.audit','hr.department.manage','hr.job.manage');

SET FOREIGN_KEY_CHECKS=1;
