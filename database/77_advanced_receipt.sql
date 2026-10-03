-- =====================================================
-- 77: الاستلام المتقدم: دفعات، باركود/QR، فروقات، تالف/ناقص
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- تفاصيل بنود الاستلام
ALTER TABLE goods_receipt_items
    ADD COLUMN IF NOT EXISTS barcode VARCHAR(100) NULL COMMENT 'الباركود الممسوح',
    ADD COLUMN IF NOT EXISTS qr_code VARCHAR(255) NULL COMMENT 'QR Code المقروء',
    ADD COLUMN IF NOT EXISTS batch_no VARCHAR(100) NULL COMMENT 'رقم التشغيلة/الدفعة',
    ADD COLUMN IF NOT EXISTS production_date DATE NULL COMMENT 'تاريخ الإنتاج',
    ADD COLUMN IF NOT EXISTS expected_qty DECIMAL(14,4) NULL COMMENT 'الكمية المتوقعة من PO',
    ADD COLUMN IF NOT EXISTS variance_qty DECIMAL(14,4) NULL COMMENT 'فرق الكمية (ناقص)',
    ADD COLUMN IF NOT EXISTS damaged_qty DECIMAL(14,4) DEFAULT 0 COMMENT 'الكمية التالفة',
    ADD COLUMN IF NOT EXISTS accepted_qty DECIMAL(14,4) NULL COMMENT 'الكمية المقبولة (actual - damaged)',
    ADD COLUMN IF NOT EXISTS damage_reason VARCHAR(255) NULL COMMENT 'سبب التلف/النقص';

-- تفاصيل الدفعات في حركات المخزون
ALTER TABLE stock_movements
    ADD COLUMN IF NOT EXISTS batch_no VARCHAR(100) NULL COMMENT 'رقم الدفعة',
    ADD COLUMN IF NOT EXISTS production_date DATE NULL COMMENT 'تاريخ الإنتاج',
    ADD COLUMN IF NOT EXISTS barcode VARCHAR(100) NULL COMMENT 'الباركود',
    ADD COLUMN IF NOT EXISTS qr_code VARCHAR(255) NULL COMMENT 'QR Code';

-- جدول تتبع الدفعات في المخزون
CREATE TABLE IF NOT EXISTS stock_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    item_id INT NOT NULL,
    batch_no VARCHAR(100) NOT NULL,
    qty DECIMAL(14,4) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,4) DEFAULT 0,
    production_date DATE NULL,
    expiry_date DATE NULL,
    barcode VARCHAR(100) NULL,
    qr_code VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wh_item_batch (warehouse_id, item_id, batch_no),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
