-- Migration 46: إصلاح ظهور السلف في الرواتب
-- إنشاء جدول سداد لسلف قصيرة الأجل المسجلة مسبقاً والتي لا تملك جدول سداد

-- إضافة عمود paid_at المفقود في جدول السلف
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP NULL;

-- إنشاء جدول سداد لكل السلف المعتمدة/المصروفة التي لا تملك جدول سداد
INSERT IGNORE INTO advance_repayment_schedule (advance_id, period, amount)
SELECT id,
       DATE_FORMAT(COALESCE(paid_at, approved_at, created_at), '%Y-%m'),
       amount
FROM salary_advances
WHERE advance_type = 'short'
  AND status IN ('approved','paid')
  AND installments <= 1
  AND NOT EXISTS (SELECT 1 FROM advance_repayment_schedule s WHERE s.advance_id = salary_advances.id);

-- التأكد من أن السلف قصيرة الأجل المسجلة حديثاً لديها remaining_amount صحيح
UPDATE salary_advances
SET remaining_amount = amount
WHERE advance_type = 'short'
  AND status IN ('approved','paid')
  AND paid_amount = 0
  AND remaining_amount = 0
  AND NOT EXISTS (SELECT 1 FROM advance_repayment_schedule s WHERE s.advance_id = salary_advances.id AND s.status = 'paid');
