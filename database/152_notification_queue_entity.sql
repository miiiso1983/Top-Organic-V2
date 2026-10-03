-- =====================================================
-- 152: إضافة أعمدة تصنيف الإشعار لطابور Push (entity_type/entity_id/action_type)
-- تُستخدم لتمرير بيانات إضافية إلى FCM لتمكين تطبيق الموبايل من فتح
-- الواجهة المناسبة تلقائياً عند الضغط على الإشعار (Deep Linking)
-- =====================================================
SET NAMES utf8mb4;

ALTER TABLE notification_queue
    ADD COLUMN entity_type VARCHAR(50) NULL AFTER link,
    ADD COLUMN entity_id INT NULL AFTER entity_type,
    ADD COLUMN action_type VARCHAR(50) NULL AFTER entity_id;
