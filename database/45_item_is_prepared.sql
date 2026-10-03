-- =====================================================
-- 45: إضافة حقل is_prepared للأصناف للتمييز بين المواد الأولية والمحضرة/الجاهزة
-- =====================================================
SET NAMES utf8mb4;

-- إضافة العمود إذا لم يكن موجوداً (MySQL 8.0+)
ALTER TABLE items ADD COLUMN IF NOT EXISTS is_prepared TINYINT(1) NOT NULL DEFAULT 0;

-- تحديث القيم الحالية بناءً على item_type ونوع التصنيف
UPDATE items i
JOIN item_categories c ON c.id = i.category_id
SET i.is_prepared = 1
WHERE i.item_type IN ('prepared', 'finished')
   OR c.type = 'prepared';
