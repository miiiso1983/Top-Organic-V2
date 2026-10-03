-- تحديث حقول الرواتب والتسويات: إضافي تيبس، خصومات بالأيام، خدمات السكن، إعدادات التأمين في ملف الموظف

-- 1) إضافة نوع تسوية جديد "إضافي تيبس" ضمن hr_adjustments
ALTER TABLE hr_adjustments MODIFY COLUMN adjustment_type ENUM('bonus','deduction','raise','penalty','tips_addition') NOT NULL;

-- 2) عدد أيام الخصم/الجزاء (بديل عن إدخال مبلغ مباشر عند نوع deduction/penalty)
SET @days_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'hr_adjustments' AND column_name = 'days'
);
SET @sql = IF(@days_exists = 0,
    'ALTER TABLE hr_adjustments ADD COLUMN days DECIMAL(5,2) NULL COMMENT ''عدد أيام الخصم/الجزاء'' AFTER amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) حقول خدمات السكن في ملف الموظف
SET @ht_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'employees' AND column_name = 'housing_type'
);
SET @sql = IF(@ht_exists = 0,
    'ALTER TABLE employees ADD COLUMN housing_type VARCHAR(30) NULL COMMENT ''نوع السكن''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @hmd_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'employees' AND column_name = 'housing_monthly_deduction'
);
SET @sql = IF(@hmd_exists = 0,
    'ALTER TABLE employees ADD COLUMN housing_monthly_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT ''استقطاع خدمات السكن الشهري''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) إعدادات التأمين الثابتة في ملف الموظف (نسبة + عدد مرات الاستقطاع)
SET @ip_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'employees' AND column_name = 'insurance_percentage'
);
SET @sql = IF(@ip_exists = 0,
    'ALTER TABLE employees ADD COLUMN insurance_percentage DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT ''نسبة التأمين الشهري %''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @io_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'employees' AND column_name = 'insurance_occurrences'
);
SET @sql = IF(@io_exists = 0,
    'ALTER TABLE employees ADD COLUMN insurance_occurrences INT NOT NULL DEFAULT 0 COMMENT ''عدد مرات الاستقطاع التلقائي للتأمين''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) أعمدة جديدة في مسير الرواتب
SET @ta_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'tips_addition'
);
SET @sql = IF(@ta_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN tips_addition DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tips',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @hd_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'payrolls' AND column_name = 'housing_deduction'
);
SET @sql = IF(@hd_exists = 0,
    'ALTER TABLE payrolls ADD COLUMN housing_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER loadings',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) وسم سجلات التأمين المولّدة تلقائياً لتمييزها عن الإدخال اليدوي
SET @auto_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'employee_monthly_insurance' AND column_name = 'is_auto'
);
SET @sql = IF(@auto_exists = 0,
    'ALTER TABLE employee_monthly_insurance ADD COLUMN is_auto TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''تم توليده تلقائياً من إعداد الملف''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
