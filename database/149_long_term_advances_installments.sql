-- =====================================================
-- 149: السلف طويلة الأجل — طريقة احتساب القسط (نسبة من الراتب أو مبلغ ثابت)
--       وإضافة عمود السلف طويلة الأجل إلى الرواتب
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- طريقة احتساب القسط الشهري للسلفة: مبلغ ثابت مستقطع، أو نسبة مئوية من الراتب الأساسي
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS installment_method ENUM('fixed','percentage') DEFAULT 'fixed'
  COMMENT 'طريقة احتساب القسط للسلف طويلة الأجل: fixed = مبلغ مستقطع ثابت، percentage = نسبة من الراتب';
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS installment_percentage DECIMAL(5,2) NULL
  COMMENT 'نسبة القسط الشهري من الراتب الأساسي عند installment_method = percentage';
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS installment_amount DECIMAL(12,2) NULL
  COMMENT 'قيمة القسط الشهري الفعلية المحتسبة (ثابتة أو محسوبة من النسبة وقت تقديم الطلب)';

-- عمود مستقل لعرض إجمالي أقساط السلف طويلة الأجل ضمن استقطاعات الراتب
ALTER TABLE payrolls ADD COLUMN IF NOT EXISTS long_term_advances DECIMAL(12,2) DEFAULT 0;

SET FOREIGN_KEY_CHECKS=1;
