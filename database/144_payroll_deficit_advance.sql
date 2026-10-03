-- =====================================================
-- 144: ترحيل عجز صافي الراتب السالب إلى سلفة تلقائية
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. عمود العجز في جدول الرواتب (القيمة السالبة التي جرى تصفيرها من صافي الراتب)
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS deficit_amount DECIMAL(12,2) NOT NULL DEFAULT 0;

-- 2. ربط السلفة التلقائية بمسير الراتب الذي أنشأها (لمنع تكرار الإنشاء عند إعادة الاحتساب)
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS source_payroll_id INT NULL;
ALTER TABLE salary_advances ADD FOREIGN KEY IF NOT EXISTS (source_payroll_id) REFERENCES payrolls(id) ON DELETE SET NULL;
ALTER TABLE salary_advances ADD INDEX IF NOT EXISTS idx_source_payroll (source_payroll_id);

SET FOREIGN_KEY_CHECKS=1;
