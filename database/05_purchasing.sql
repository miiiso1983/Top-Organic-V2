-- =====================================================
-- 05: المشتريات والموردون
-- =====================================================

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30),
    email VARCHAR(120),
    address VARCHAR(255),
    contract_start DATE NULL,
    contract_end DATE NULL,
    rating TINYINT DEFAULT 0 COMMENT 'تقييم من 1 إلى 5',
    balance DECIMAL(14,2) DEFAULT 0 COMMENT 'الرصيد الدائن للمورد',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- طلبات الشراء (من الأقسام)
CREATE TABLE IF NOT EXISTS purchase_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_no VARCHAR(30) NOT NULL UNIQUE,
    department VARCHAR(100),
    status ENUM('pending','approved','rejected','converted') DEFAULT 'pending',
    notes TEXT,
    requested_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    item_id INT NULL COMMENT 'فارغ إن كانت مادة جديدة',
    new_item_name VARCHAR(150) NULL,
    qty DECIMAL(14,4) NOT NULL,
    notes VARCHAR(255),
    FOREIGN KEY (request_id) REFERENCES purchase_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- عروض الأسعار
CREATE TABLE IF NOT EXISTS quotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NULL,
    supplier_id INT NOT NULL,
    total DECIMAL(14,2) DEFAULT 0,
    status ENUM('received','accepted','rejected') DEFAULT 'received',
    valid_until DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- أوامر الشراء
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_no VARCHAR(30) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    request_id INT NULL,
    status ENUM('draft','sent','partially_received','received','cancelled') DEFAULT 'draft',
    total DECIMAL(14,2) DEFAULT 0,
    payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    item_id INT NOT NULL,
    qty DECIMAL(14,4) NOT NULL,
    unit_price DECIMAL(12,4) NOT NULL,
    received_qty DECIMAL(14,4) DEFAULT 0,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الاستلام والفحص
CREATE TABLE IF NOT EXISTS goods_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(30) NOT NULL UNIQUE,
    po_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    inspection_result ENUM('accepted','partially_accepted','rejected') DEFAULT 'accepted',
    notes TEXT,
    received_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goods_receipt_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    item_id INT NOT NULL,
    qty DECIMAL(14,4) NOT NULL,
    unit_price DECIMAL(12,4) NOT NULL,
    expiry_date DATE NULL,
    FOREIGN KEY (receipt_id) REFERENCES goods_receipts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- تقييم أداء الموردين
CREATE TABLE IF NOT EXISTS supplier_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    quality TINYINT DEFAULT 3,
    delivery TINYINT DEFAULT 3,
    pricing TINYINT DEFAULT 3,
    notes VARCHAR(255),
    evaluated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
