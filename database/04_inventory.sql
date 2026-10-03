-- =====================================================
-- 04: المخزون والمستودعات
-- =====================================================

CREATE TABLE IF NOT EXISTS warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('raw','prepared','asset','tool','clothing','other') DEFAULT 'raw'
        COMMENT 'مواد أولية/محضرة/موجودات/أدوات/ملابس'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL COMMENT 'كغم، غم، لتر، مل، حبة',
    base_unit_id INT NULL,
    factor DECIMAL(12,6) DEFAULT 1 COMMENT 'معامل التحويل للوحدة الأساسية'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(60) UNIQUE,
    name VARCHAR(150) NOT NULL,
    category_id INT NOT NULL,
    unit_id INT NOT NULL,
    min_qty DECIMAL(12,3) DEFAULT 0 COMMENT 'حد التنبيه للنواقص',
    cost_price DECIMAL(12,4) DEFAULT 0,
    track_expiry TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    available_for_purchase_request TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES item_categories(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الأرصدة الحالية لكل مادة في كل مخزن
CREATE TABLE IF NOT EXISTS stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    item_id INT NOT NULL,
    qty DECIMAL(14,4) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_wh_item (warehouse_id, item_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- حركات المخزون (إدخال/إخراج/تحويل/جرد/استهلاك)
CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    destination_warehouse_id INT NULL COMMENT 'مخزن/قسم الوجهة للإخراج والمناقلة الصادرة',
    item_id INT NOT NULL,
    movement_type ENUM('in','out','transfer_in','transfer_out','adjustment','consumption','return') NOT NULL,
    qty DECIMAL(14,4) NOT NULL,
    unit_cost DECIMAL(12,4) DEFAULT 0,
    expiry_date DATE NULL,
    reference VARCHAR(60) COMMENT 'رقم مرجعي: أمر شراء، طلب، جرد...',
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (destination_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_item (item_id), INDEX idx_type (movement_type), INDEX idx_destination (destination_warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- التحويلات بين المخازن (مناقلة)
CREATE TABLE IF NOT EXISTS stock_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_no VARCHAR(30) NOT NULL UNIQUE,
    from_warehouse_id INT NOT NULL,
    to_warehouse_id INT NOT NULL,
    status ENUM('pending','approved','delivered','rejected') DEFAULT 'pending',
    requested_by INT,
    approved_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    item_id INT NOT NULL,
    qty DECIMAL(14,4) NOT NULL,
    unit_id INT NULL,
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الجرد
CREATE TABLE IF NOT EXISTS stocktakes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    stocktake_type ENUM('periodic','surprise') DEFAULT 'periodic',
    status ENUM('open','closed') DEFAULT 'open',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stocktake_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stocktake_id INT NOT NULL,
    item_id INT NOT NULL,
    system_qty DECIMAL(14,4) NOT NULL,
    counted_qty DECIMAL(14,4) NOT NULL,
    FOREIGN KEY (stocktake_id) REFERENCES stocktakes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- العهدة (أدوات وأجهزة بعهدة قسم أو شخص)
CREATE TABLE IF NOT EXISTS custody (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    qty DECIMAL(12,3) NOT NULL DEFAULT 1,
    custodian_type ENUM('employee','department') NOT NULL,
    employee_id INT NULL,
    department VARCHAR(100) NULL,
    expiry_date DATE NULL COMMENT 'تاريخ انتهاء العهدة',
    status ENUM('active','returned','damaged','lost') DEFAULT 'active',
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    returned_at TIMESTAMP NULL,
    FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
