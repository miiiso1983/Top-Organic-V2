-- إضافة عمود عدد أيام الإضافي إلى مسير الرواتب
-- يُحدَّث تلقائياً عند توليد/إعادة احتساب الراتب من عدد سجلات payroll_overtime المرتبطة
ALTER TABLE payrolls ADD COLUMN overtime_days INT NOT NULL DEFAULT 0 AFTER overtime;

-- تعبئة القيم للرواتب الموجودة مسبقاً من سجلات الإضافي الفعلية
UPDATE payrolls p
SET p.overtime_days = (
    SELECT COUNT(*) FROM payroll_overtime po WHERE po.payroll_id = p.id
);
