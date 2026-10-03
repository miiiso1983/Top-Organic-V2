-- =====================================================
-- 98: إضافة عمود link لطابور الإشعارات (لتمرير رابط الفتح لـ FCM/Web Push)
-- =====================================================
SET NAMES utf8mb4;

ALTER TABLE notification_queue
    ADD COLUMN link VARCHAR(500) NULL AFTER body;
