-- =====================================================
-- 78: فواتير الشراء ومطابقة الاستلام والحسابات الدائنة
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS purchase_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) NOT NULL UNIQUE COMMENT 'رقم فاتورة المورد',
    supplier_id INT NOT NULL,
    po_id INT NOT NULL COMMENT 'أمر الشراء المرتبط',
    receipt_id INT NOT NULL COMMENT 'سند الاستلام المرتبط',
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(14,2) DEFAULT 0,
    discount_amount DECIMAL(14,2) DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','sent_to_accounting','matched','approved','paid') DEFAULT 'draft',
    payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    paid_amount DECIMAL(14,2) DEFAULT 0,
    remaining_amount DECIMAL(14,2) DEFAULT 0,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (receipt_id) REFERENCES goods_receipts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    item_id INT NOT NULL,
    qty DECIMAL(14,4) NOT NULL,
    unit_price DECIMAL(12,4) NOT NULL,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES purchase_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_receipt_matching (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    receipt_id INT NOT NULL,
    po_id INT NOT NULL,
    matched_by INT,
    matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('matched','discrepancy') DEFAULT 'matched',
    notes TEXT,
    FOREIGN KEY (invoice_id) REFERENCES purchase_invoices(id),
    FOREIGN KEY (receipt_id) REFERENCES goods_receipts(id),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_archive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(40) NOT NULL COMMENT 'purchase_invoice, purchase_order, goods_receipt...',
    entity_id INT NOT NULL,
    document_type VARCHAR(40) NOT NULL COMMENT 'invoice, receipt, po, payment_proof',
    file_path VARCHAR(255) NOT NULL,
    notes VARCHAR(255),
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
