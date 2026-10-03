-- إضافة نوع تسوية جديد "تسوية عدد أيام دوام" (work_days_settlement)
-- زيادة على الراتب = عدد الأيام المُدخلة × تكلفة يوم العمل (الراتب الأساسي / 30)
-- تُحفظ في hr_adjustments وتنعكس في عمود مخصص بمسير الرواتب ضمن الاستحقاقات
ALTER TABLE hr_adjustments
  MODIFY COLUMN adjustment_type ENUM('bonus','deduction','raise','penalty','tips_addition','salary_reduction','work_days_settlement') NOT NULL;

ALTER TABLE payrolls
  ADD COLUMN IF NOT EXISTS work_days_settlement DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tips_addition;
