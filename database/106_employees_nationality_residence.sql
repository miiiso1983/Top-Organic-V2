-- Migration: add nationality and residence columns to employees table
-- for Excel import and HR forms

ALTER TABLE employees
    ADD COLUMN nationality VARCHAR(100) NULL AFTER phone,
    ADD COLUMN residence VARCHAR(255) NULL AFTER nationality;
