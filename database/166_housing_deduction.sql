-- مبلغ الاستقطاع الافتراضي لكل موقع سكن، ومبلغ الاستقطاع الفعلي المسجَّل مع كل تسكين (للتاريخ/التدقيق)
ALTER TABLE housing_sites ADD COLUMN monthly_deduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER sort_order;
ALTER TABLE employee_housing_assignments ADD COLUMN deduction_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER room_id;
