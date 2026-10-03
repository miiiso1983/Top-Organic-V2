-- ربط الحضور والغياب بحساب الراتب: أيام الدوام المتوقعة/الفعلية والإجازات

SET @ed_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'expected_days'
);
SET @sql = IF(@ed_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN expected_days INT NOT NULL DEFAULT 0 COMMENT ''أيام العمل المتوقعة حسب الوردية والعطل'' AFTER absence_days',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @wd_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'working_days'
);
SET @sql = IF(@wd_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN working_days INT NOT NULL DEFAULT 0 COMMENT ''أيام الدوام الفعلية من البصمات'' AFTER expected_days',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ld_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'leave_days'
);
SET @sql = IF(@ld_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN leave_days INT NOT NULL DEFAULT 0 COMMENT ''أيام الإجازات المعتمدة'' AFTER working_days',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @uld_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'unpaid_leave_days'
);
SET @sql = IF(@uld_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN unpaid_leave_days INT NOT NULL DEFAULT 0 COMMENT ''أيام الإجازات بدون راتب (تُخصم)'' AFTER leave_days',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- وضع احتساب الغياب: alerts (من تنبيهات عدم البصمة) أو attendance (من سجل البصمات مقابل الوردية)
INSERT IGNORE INTO settings (skey, svalue) VALUES ('payroll_absence_mode', 'attendance');
