-- =====================================================
-- 139: إضافة بيان لكل سطر في القيد اليومي
-- =====================================================
SET NAMES utf8mb4;

ALTER TABLE journal_lines
    ADD COLUMN IF NOT EXISTS description VARCHAR(255) NULL AFTER credit;
