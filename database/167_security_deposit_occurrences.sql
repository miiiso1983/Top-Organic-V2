-- إضافة أعمدة تتبع عدد المرات والتسوية دفعة واحدة إلى جدول تأمين المستحقات (employee_security_deposits)
-- بعد إلغاء آلية "التأمينات الشهرية" (employee_monthly_insurance) واعتماد نسبة/مرات ملف الموظف حصرياً على تأمين المستحقات
ALTER TABLE employee_security_deposits
    ADD COLUMN occurrence_count INT NOT NULL DEFAULT 1 AFTER amount,
    ADD COLUMN is_settlement TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN base_salary_snapshot DECIMAL(12,2) DEFAULT NULL AFTER is_settlement;
