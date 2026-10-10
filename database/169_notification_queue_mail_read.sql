-- =====================================================
-- 169: دعم صندوق البريد (Mail/Inbox) بتطبيق الموبايل
-- يضيف تتبّع "تمّ الفتح" لرسائل البريد الإلكتروني المُرسلة لكل مستخدم،
-- بالإضافة إلى فهرس يسرّع البحث عن رسائل مستخدم معيّن حسب بريده الإلكتروني.
-- =====================================================
ALTER TABLE notification_queue
  ADD COLUMN opened_at DATETIME NULL AFTER sent_at;

ALTER TABLE notification_queue
  ADD INDEX idx_channel_recipient (channel, recipient);
