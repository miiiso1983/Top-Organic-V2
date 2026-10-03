SET NAMES utf8mb4;

ALTER TABLE employees ADD COLUMN reference_photo VARCHAR(255) NULL AFTER status;
