SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- حقول إضافية لدورة عمل طلبات الصيانة: الأهمية، الحالة التفصيلية أثناء التنفيذ،
-- الملاحظات والأدوات المستخدمة، وتكلفة الصيانة.
ALTER TABLE maintenance_requests
    ADD COLUMN IF NOT EXISTS priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal' COMMENT 'الأهمية' AFTER description,
    ADD COLUMN IF NOT EXISTS work_status ENUM('parts_requested','in_maintenance','external_maintenance','completed') NULL COMMENT 'حالة تفصيلية أثناء التنفيذ' AFTER status,
    ADD COLUMN IF NOT EXISTS resolution_notes TEXT NULL COMMENT 'الملاحظات والأدوات المستخدمة' AFTER finished_at,
    ADD COLUMN IF NOT EXISTS cost DECIMAL(10,2) NULL COMMENT 'تكلفة الصيانة' AFTER resolution_notes;

SET FOREIGN_KEY_CHECKS=1;
