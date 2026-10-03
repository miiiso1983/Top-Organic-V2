-- =====================================================
-- 119: إضافة تفاصيل حساب الاستحقاقات في الاستقالات
-- =====================================================
SET NAMES utf8mb4;

ALTER TABLE resignations
ADD COLUMN calculation_breakdown_json TEXT NULL COMMENT 'تفاصيل حساب الاستحقاق: راتب، غياب، إضافي، سلف، سكن، خصومات'
AFTER total_due;
