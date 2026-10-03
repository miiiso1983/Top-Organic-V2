-- =====================================================
-- 112: إضافة department_id لجدول عقود الموظف
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE employee_contracts
    ADD COLUMN department_id INT NULL AFTER position,
    ADD CONSTRAINT fk_employee_contracts_department
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS=1;
