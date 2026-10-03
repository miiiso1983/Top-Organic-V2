-- =====================================================
-- 68: OpenAI Integration Settings - إعدادات OpenAI
-- =====================================================
SET NAMES utf8mb4;

-- إضافة صلاحية إدارة إعدادات OpenAI
INSERT IGNORE INTO permissions (code, name, module) VALUES
('ai_assistant.openai_settings', 'إعدادات OpenAI', 'sales');

-- إضافة الإعدادات الافتراضية
INSERT INTO settings (skey, svalue) VALUES
('openai_enabled', '0'),
('openai_api_key', ''),
('openai_model', 'gpt-4o-mini'),
('openai_max_tokens', '800'),
('openai_temperature', '0.3')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);
