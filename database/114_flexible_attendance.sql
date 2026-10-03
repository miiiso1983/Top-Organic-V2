-- =====================================================
-- 114: نظام ساعات العمل المرنة (Flexible Attendance)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE employees
    ADD COLUMN attendance_type ENUM('shift', 'flexible') NOT NULL DEFAULT 'shift' AFTER shift_id,
    ADD COLUMN required_hours DECIMAL(4,2) NOT NULL DEFAULT 8.00 AFTER attendance_type;

SET FOREIGN_KEY_CHECKS=1;
