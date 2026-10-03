-- =====================================================
-- 103: إصلاح أعمدة جدول employees المفقودة
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE employees ADD COLUMN department_id INT NULL AFTER department;
ALTER TABLE employees ADD COLUMN job_id INT NULL AFTER position;
ALTER TABLE employees ADD COLUMN manager_id INT NULL AFTER job_id;
ALTER TABLE employees ADD COLUMN status ENUM('active','vacation','suspended','terminated') DEFAULT 'active' AFTER file_status;
ALTER TABLE employees ADD COLUMN cost_center_id INT NULL AFTER branch_id;
ALTER TABLE employees ADD COLUMN account_id INT NULL AFTER cost_center_id;
ALTER TABLE employees ADD COLUMN work_start_time TIME NULL;
ALTER TABLE employees ADD COLUMN work_end_time TIME NULL;

ALTER TABLE employees MODIFY COLUMN file_status ENUM('open','closed','archived') DEFAULT 'open';

ALTER TABLE employees ADD CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;
ALTER TABLE employees ADD CONSTRAINT fk_employees_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL;
ALTER TABLE employees ADD CONSTRAINT fk_employees_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL;
ALTER TABLE employees ADD CONSTRAINT fk_employees_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL;
ALTER TABLE employees ADD CONSTRAINT fk_employees_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS=1;
