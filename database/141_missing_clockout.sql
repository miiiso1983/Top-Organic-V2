-- =====================================================
-- 141: التعامل مع نسيان بصمة الخروج
-- =====================================================
SET NAMES utf8mb4;

-- تفعيل/تعطيل التسوية التلقائية لبصمة الخروج (1 = مفعل)
INSERT IGNORE INTO settings (skey, svalue) VALUES ('auto_clockout_enabled', '1');

-- التأخير (بالدقائق) بعد نهاية الوردية قبل إنشاء تنبيه نسيان الخروج
INSERT IGNORE INTO settings (skey, svalue) VALUES ('missing_clockout_alert_delay_minutes', '30');

-- وقت انتهاء العمل الافتراضي عند غياب الوردية
INSERT IGNORE INTO settings (skey, svalue) VALUES ('default_work_end_time', '17:00');

-- قاعدة تبليغ المديرين عند نسيان بصمة الخروج
INSERT IGNORE INTO notification_rules (event_type, min_admin_level, branch_specific, department_specific, is_active)
VALUES ('missing_clockout', 1, 1, 0, 1);
