-- =====================================================
-- 146: مؤشرات أداء (KPIs) خاصة بكل وظيفة/مسمى وظيفي
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- تنظيف أي إجراء متبقٍّ من محاولة سابقة فاشلة (احتياط)
DROP PROCEDURE IF EXISTS add_col_if_missing_146;

-- ربط مؤشر الأداء بوظيفة محددة (NULL = مؤشر عام يطبق على كل الوظائف)
ALTER TABLE performance_kpis ADD COLUMN IF NOT EXISTS job_id INT NULL COMMENT 'الوظيفة المرتبطة بالمؤشر، NULL يعني مؤشر عام' AFTER description;
ALTER TABLE performance_kpis ADD FOREIGN KEY IF NOT EXISTS (job_id) REFERENCES jobs(id) ON DELETE SET NULL;
ALTER TABLE performance_kpis ADD INDEX IF NOT EXISTS idx_performance_kpis_job (job_id);

SET FOREIGN_KEY_CHECKS=1;
