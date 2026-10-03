-- =====================================================
-- 76: دورة حياة المشتريات: RFQ، عروض الأسعار، تتبع PO، الشحن
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- طلب عروض أسعار (RFQ) مرتبط بطلب الشراء
CREATE TABLE IF NOT EXISTS request_for_quotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_request_id INT NOT NULL,
    rfq_no VARCHAR(30) NOT NULL UNIQUE,
    status ENUM('draft','sent','received','closed','cancelled') DEFAULT 'draft',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_pr (purchase_request_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- بنود RFQ
CREATE TABLE IF NOT EXISTS rfq_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rfq_id INT NOT NULL,
    item_id INT NOT NULL,
    qty DECIMAL(14,4) NOT NULL,
    unit_id INT NULL,
    notes VARCHAR(255),
    FOREIGN KEY (rfq_id) REFERENCES request_for_quotations(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ربط عروض الأسعار الموجودة بـ RFQ
ALTER TABLE quotations
    ADD COLUMN IF NOT EXISTS rfq_id INT NULL COMMENT 'طلب عروض الأسعار المرتبط' AFTER id,
    ADD CONSTRAINT fk_q_rfq FOREIGN KEY (rfq_id) REFERENCES request_for_quotations(id) ON DELETE SET NULL;

-- تفاصيل بنود كل عرض سعر
CREATE TABLE IF NOT EXISTS quotation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quotation_id INT NOT NULL,
    item_id INT NOT NULL,
    qty DECIMAL(14,4) NOT NULL,
    unit_id INT NULL,
    unit_price DECIMAL(12,4) NOT NULL,
    total DECIMAL(14,2) DEFAULT 0,
    notes VARCHAR(255),
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- تتبع حالة أمر الشراء (Processing, Shipped, In Transit, Delivered...)
CREATE TABLE IF NOT EXISTS purchase_order_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    status ENUM('processing','shipped','in_transit','customs','delivered_to_warehouse','received','cancelled') DEFAULT 'processing',
    location VARCHAR(255) NULL,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_po (po_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- شحنات أمر الشراء
CREATE TABLE IF NOT EXISTS purchase_order_shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    tracking_no VARCHAR(100) NULL,
    carrier VARCHAR(120) NULL,
    shipping_date DATE NULL,
    expected_arrival DATE NULL,
    actual_arrival DATE NULL,
    status ENUM('draft','shipped','in_transit','delivered','received') DEFAULT 'draft',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_po (po_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- حقول إضافية لأمر الشراء
ALTER TABLE purchase_orders
    ADD COLUMN IF NOT EXISTS rfq_id INT NULL COMMENT 'RFQ المصدر' AFTER request_id,
    ADD COLUMN IF NOT EXISTS shipping_status ENUM('not_shipped','shipped','in_transit','delivered','received') DEFAULT 'not_shipped',
    ADD COLUMN IF NOT EXISTS expected_arrival DATE NULL,
    ADD COLUMN IF NOT EXISTS actual_arrival DATE NULL,
    ADD CONSTRAINT fk_po_rfq FOREIGN KEY (rfq_id) REFERENCES request_for_quotations(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS=1;
