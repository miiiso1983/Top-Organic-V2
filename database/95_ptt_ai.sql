-- =====================================================
-- 95: PTT المرحلة 2 — ردود صوتية + حقول الذكاء الاصطناعي + إعدادات الاحتفاظ
-- =====================================================
SET NAMES utf8mb4;

-- الردود الصوتية وربطها بالرسالة الأصل
ALTER TABLE ptt_messages
    ADD COLUMN reply_to BIGINT NULL COMMENT 'رد على رسالة أخرى' AFTER channel_id,
    ADD COLUMN ai_summary TEXT NULL COMMENT 'ملخص AI',
    ADD COLUMN ai_tasks_json JSON NULL COMMENT 'مهام مستخرجة بواسطة AI',
    ADD COLUMN ai_translation TEXT NULL COMMENT 'ترجمة النص المفرغ',
    ADD COLUMN ai_translation_lang VARCHAR(10) NULL,
    ADD CONSTRAINT fk_ptt_reply FOREIGN KEY (reply_to) REFERENCES ptt_messages(id) ON DELETE SET NULL;

ALTER TABLE ptt_message_recipients
    ADD COLUMN reply_message_id BIGINT NULL COMMENT 'رسالة الرد الصوتي من هذا المستلم' AFTER acknowledged_at;

-- إعدادات افتراضية للوحدة
INSERT IGNORE INTO settings (skey, svalue) VALUES
('ptt_max_duration_sec','60'),
('ptt_retention_days','90'),
('ptt_auto_transcribe','0');
