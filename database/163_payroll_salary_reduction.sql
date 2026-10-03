-- إضافة عمود تخفيض الراتب لمسير الرواتب: يُخزَّن مجموع تخفيضات الراتب المعتمدة السارية للفترة
-- ليعرض عمود "الراتب الأساسي" الأساس المعدّل = (الاسمي + الزيادات) − التخفيض
ALTER TABLE payrolls
  ADD COLUMN IF NOT EXISTS salary_reduction DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER raises;
