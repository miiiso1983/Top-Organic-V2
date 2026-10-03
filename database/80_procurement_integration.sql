-- =====================================================
-- 80: تكامل وحدة المشتريات والمواد مع باقي الأنظمة
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1) مراكز الكلفة والروابط التشغيلية في طلبات المواد
ALTER TABLE material_requests
    ADD COLUMN IF NOT EXISTS cost_center_id INT NULL AFTER branch_id,
    ADD COLUMN IF NOT EXISTS production_order_id INT NULL AFTER cost_center_id,
    ADD COLUMN IF NOT EXISTS kitchen_section_id INT NULL AFTER production_order_id,
    ADD COLUMN IF NOT EXISTS warehouse_id INT NULL AFTER kitchen_section_id;

ALTER TABLE material_requests
    ADD CONSTRAINT fk_mr_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_mr_production_order FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_mr_kitchen_section FOREIGN KEY (kitchen_section_id) REFERENCES kitchen_sections(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_mr_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;

-- 2) مراكز الكلفة في المشتريات والمناقلات (نضيف الفرع أولاً إن لم يكن موجوداً)
ALTER TABLE purchase_requests
    ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER request_no,
    ADD COLUMN IF NOT EXISTS cost_center_id INT NULL AFTER branch_id;

ALTER TABLE purchase_requests
    ADD CONSTRAINT fk_pr_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_pr_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL;

ALTER TABLE purchase_orders
    ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER request_id,
    ADD COLUMN IF NOT EXISTS cost_center_id INT NULL AFTER branch_id;

ALTER TABLE purchase_orders
    ADD CONSTRAINT fk_po_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_po_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL;

ALTER TABLE goods_receipts
    ADD COLUMN IF NOT EXISTS branch_id INT NULL AFTER po_id,
    ADD COLUMN IF NOT EXISTS cost_center_id INT NULL AFTER branch_id;

ALTER TABLE goods_receipts
    ADD CONSTRAINT fk_gr_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_gr_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL;

ALTER TABLE stock_transfers
    ADD COLUMN IF NOT EXISTS cost_center_id INT NULL AFTER material_request_id;

ALTER TABLE stock_transfers
    ADD CONSTRAINT fk_st_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL;

-- 3) ربط أوامر التصنيع بالوصفات ومراكز الكلفة
ALTER TABLE production_orders
    ADD COLUMN IF NOT EXISTS recipe_id INT NULL AFTER item_id,
    ADD COLUMN IF NOT EXISTS cost_center_id INT NULL AFTER recipe_id;

ALTER TABLE production_orders
    ADD CONSTRAINT fk_po_recipe FOREIGN KEY (recipe_id) REFERENCES production_recipes(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_po_cost_center2 FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL;

-- 4) ربط الأصول الثابتة بالاستلام والفواتير
ALTER TABLE goods_receipt_items
    ADD COLUMN IF NOT EXISTS fixed_asset_id INT NULL AFTER damage_reason;

ALTER TABLE goods_receipt_items
    ADD CONSTRAINT fk_gri_fixed_asset FOREIGN KEY (fixed_asset_id) REFERENCES fixed_assets(id) ON DELETE SET NULL;

ALTER TABLE purchase_invoice_items
    ADD COLUMN IF NOT EXISTS fixed_asset_id INT NULL AFTER total;

ALTER TABLE purchase_invoice_items
    ADD CONSTRAINT fk_pii_fixed_asset FOREIGN KEY (fixed_asset_id) REFERENCES fixed_assets(id) ON DELETE SET NULL;

-- 5) صلاحيات تكاملية
INSERT IGNORE INTO permissions (code, name, module) VALUES
('material_request.mobile','طلبات المواد من الجوال','procurement'),
('procurement.dashboard','عرض إحصائيات المشتريات في Dashboard','procurement'),
('procurement.ai','الاستعلام الذكي عن المشتريات','procurement');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','أمين مخزن','مسؤول مشتريات','شيف')
  AND p.code IN ('material_request.mobile','procurement.dashboard','procurement.ai');

SET FOREIGN_KEY_CHECKS=1;
