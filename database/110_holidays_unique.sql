-- =====================================================
-- 110: منع تكرار العطل في جدول hr_holidies
-- =====================================================
SET NAMES utf8mb4;

-- حذف التكرارات (إن وجدت) مع الاحتفاظ بأول صف
DELETE h1 FROM hr_holidays h1
INNER JOIN hr_holidays h2
  ON h1.id > h2.id
  AND (
    (h1.date IS NOT NULL AND h2.date IS NOT NULL AND h1.date = h2.date)
    OR (h1.day_of_week IS NOT NULL AND h2.day_of_week IS NOT NULL AND h1.day_of_week = h2.day_of_week)
  );

-- قيود فريدة لمنع التكرار
ALTER TABLE hr_holidays
  ADD UNIQUE KEY uk_holidays_date (date),
  ADD UNIQUE KEY uk_holidays_day_of_week (day_of_week);
