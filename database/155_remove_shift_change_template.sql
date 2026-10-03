-- =====================================================
-- 155: إلغاء قالب "طلب تغيير وردية ليوم محدد" (hr_shift_change)
-- الميزة أُلغيت بالكامل من واجهات التطبيق؛ نُعطّل القالب هنا بدلاً من
-- حذفه فعلياً حفاظاً على أي طلبات تاريخية مرتبطة به عبر requests.template_id
-- =====================================================
SET NAMES utf8mb4;

UPDATE approval_templates SET is_active = 0 WHERE code = 'hr_shift_change';

DELETE tar FROM template_allowed_roles tar
    JOIN approval_templates t ON t.id = tar.template_id
    WHERE t.code = 'hr_shift_change';
