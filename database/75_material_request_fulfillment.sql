-- =====================================================
-- 75: ربط المناقلات والشراء بطلبات المواد + التوقيع الإلكتروني
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1) ربط المناقلة بطلب المواد (إضافة الأعمدة أولاً ثم المفاتيح الخارجية)
ALTER TABLE stock_transfers
    ADD COLUMN IF NOT EXISTS material_request_id INT NULL COMMENT 'طلب المواد الأب' AFTER id,
    ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(120) NULL COMMENT 'اسم المستلم',
    ADD COLUMN IF NOT EXISTS recipient_signature TEXT NULL COMMENT 'توقيع المستلم Base64',
    ADD COLUMN IF NOT EXISTS recipient_user_id INT NULL COMMENT 'مستخدم المستلم',
    ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP NULL COMMENT 'وقت التسليم',
    ADD COLUMN IF NOT EXISTS delivered_by INT NULL COMMENT 'مستخدم التسليم',
    ADD COLUMN IF NOT EXISTS prepared_by INT NULL COMMENT 'مستخدم التجهيز',
    ADD COLUMN IF NOT EXISTS prepared_at TIMESTAMP NULL COMMENT 'وقت التجهيز';

ALTER TABLE stock_transfers
    ADD CONSTRAINT fk_st_material_request FOREIGN KEY (material_request_id) REFERENCES material_requests(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_st_recipient_user FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_st_delivered_by FOREIGN KEY (delivered_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_st_prepared_by FOREIGN KEY (prepared_by) REFERENCES users(id) ON DELETE SET NULL;

-- 2) تعديل حالة المناقلة لإضافة "تم التجهيز"
ALTER TABLE stock_transfers
    MODIFY COLUMN status ENUM('pending','approved','prepared','delivered','rejected') DEFAULT 'pending';

-- 3) ربط بنود المناقلة ببنود طلب المواد
ALTER TABLE stock_transfer_items
    ADD COLUMN IF NOT EXISTS material_request_item_id INT NULL COMMENT 'بند طلب المواد المرتبط';

ALTER TABLE stock_transfer_items
    ADD CONSTRAINT fk_sti_material_request_item FOREIGN KEY (material_request_item_id) REFERENCES material_request_items(id) ON DELETE SET NULL;

-- 4) ربط طلب الشراء بطلب المواد (اختياري للاستعلام السريع)
ALTER TABLE purchase_requests
    ADD COLUMN IF NOT EXISTS material_request_id INT NULL COMMENT 'طلب المواد الأب';

ALTER TABLE purchase_requests
    ADD CONSTRAINT fk_pr_material_request FOREIGN KEY (material_request_id) REFERENCES material_requests(id) ON DELETE SET NULL;

-- 5) مسار موافقة خاص بمناقلات طلبات المواد (لا تُسلّم تلقائياً عند الاعتماد)
INSERT IGNORE INTO legacy_approval_workflows (entity_type, name) VALUES
('material_request_transfer', 'مناقلة طلب مواد');

INSERT IGNORE INTO legacy_approval_steps (workflow_id, step_order, role_id, min_amount)
SELECT id, 1, 1, 0 FROM legacy_approval_workflows WHERE entity_type = 'material_request_transfer';

SET FOREIGN_KEY_CHECKS=1;
