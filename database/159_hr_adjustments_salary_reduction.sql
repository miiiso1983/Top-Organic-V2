-- إضافة نوع تسوية جديد "تخفيض راتب" (salary_reduction) إلى جدول التسويات المالية
ALTER TABLE hr_adjustments
  MODIFY adjustment_type ENUM('bonus','deduction','raise','penalty','tips_addition','salary_reduction') NOT NULL;
