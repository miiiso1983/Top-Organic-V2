-- =====================================================
-- 92: SLA والتصعيد التلقائي — عمود التذكير للمهام
-- إعدادات SLA تُخزّن داخل config_json للعقدة (sla: {max_hours, remind_after_hours, auto_action, escalate_to})
-- =====================================================
SET NAMES utf8mb4;

ALTER TABLE workflow_tasks
    ADD COLUMN reminded_at DATETIME NULL COMMENT 'آخر تذكير أُرسل للمهمة' AFTER due_at;
