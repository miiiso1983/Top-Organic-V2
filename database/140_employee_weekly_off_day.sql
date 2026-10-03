-- =====================================================
-- 140: يوم العطلة الأسبوعية للموظف
-- =====================================================
SET NAMES utf8mb4;

ALTER TABLE employees
    ADD COLUMN weekly_off_day TINYINT NULL COMMENT 'يوم العطلة الأسبوعية للموظف (0=أحد ... 6=سبت)' AFTER required_hours;

-- إذا أردت تحديث جميع الموظفين لتوريث العطل الأسبوعية العامة يمكن تشغيل:
-- UPDATE employees SET weekly_off_day = NULL;
