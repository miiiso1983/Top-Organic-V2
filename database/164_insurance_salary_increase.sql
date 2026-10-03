-- إضافة تتبع عدد "شهور الاستقطاع" المستهلكة فعلياً بكل سجل تأمين شهري (عادة 1، وقد تكون أكثر عند تسوية زيادة الراتب دفعة واحدة)
ALTER TABLE employee_monthly_insurance
  ADD COLUMN occurrence_count INT NOT NULL DEFAULT 1 AFTER amount,
  ADD COLUMN is_settlement TINYINT(1) NOT NULL DEFAULT 0 AFTER occurrence_count,
  ADD COLUMN base_salary_snapshot DECIMAL(12,2) NULL AFTER is_settlement;

UPDATE employee_monthly_insurance SET occurrence_count = 1 WHERE occurrence_count = 0;
