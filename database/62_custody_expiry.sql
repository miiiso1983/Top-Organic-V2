-- =====================================================
-- 62: Custody Enhancement - تمييز العهد الثابتة والاستهلاكية وتاريخ الانتهاء
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. إضافة عمود النوع (ثابتة / استهلاكية) وتاريخ الانتهاء
ALTER TABLE employee_custody
    ADD COLUMN IF NOT EXISTS type ENUM('fixed','consumable') NOT NULL DEFAULT 'fixed' COMMENT 'نوع العهدة: ثابتة أو استهلاكية' AFTER status,
    ADD COLUMN IF NOT EXISTS expiry_date DATE NULL COMMENT 'تاريخ انتهاء العهدة الاستهلاكية' AFTER due_date,
    ADD INDEX IF NOT EXISTS idx_expiry_date (expiry_date),
    ADD INDEX IF NOT EXISTS idx_type (type);

-- 2. تحديث جدول التنبيهات لاستيعاب تنبيهات الانتهاء
ALTER TABLE employee_custody_alerts
    MODIFY COLUMN alert_type ENUM('due_soon','overdue','expiring') NOT NULL DEFAULT 'due_soon' COMMENT 'نوع التنبيه';

-- 3. إعداد افتراضي لعدد أيام التنبيه قبل انتهاء العهدة
INSERT INTO settings (skey, svalue, sgroup, label) VALUES
('custody_expiry_alert_days', '15', 'hr', 'عدد أيام التنبيه قبل انتهاء العهدة الاستهلاكية')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), label = VALUES(label);

-- 4. إضافة قواعد إشعارات افتراضية لانتهاء العهدة (إذا لم تكن موجودة)
INSERT INTO notification_rules (event_type, is_active, min_admin_level, branch_specific, department_specific, created_at)
SELECT 'custody_expiring', 1, 50, 1, 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM notification_rules WHERE event_type = 'custody_expiring');

SET FOREIGN_KEY_CHECKS=1;
