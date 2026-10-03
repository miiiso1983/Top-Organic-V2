-- =====================================================
-- 02: المبيعات والطلبات ونقاط البيع والحجوزات
-- =====================================================

CREATE TABLE IF NOT EXISTS menu_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    cost DECIMAL(12,2) DEFAULT 0 COMMENT 'تُحدّث من الوصفة',
    prep_minutes INT DEFAULT 10 COMMENT 'وقت التحضير بالدقائق',
    station_id INT NULL COMMENT 'محطة المطبخ المسؤولة (legacy)',
    kitchen_section_id INT NULL COMMENT 'قسم المطبخ المسؤول (Kitchen Routing)',
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES menu_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dining_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    name VARCHAR(40) NOT NULL,
    seats INT DEFAULT 4,
    status ENUM('available','occupied','reserved','cleaning') DEFAULT 'available',
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30),
    address VARCHAR(255),
    balance DECIMAL(14,2) DEFAULT 0 COMMENT 'ذمة العميل المدينة',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    customer_name VARCHAR(120),
    customer_phone VARCHAR(30),
    table_id INT NULL,
    guests INT DEFAULT 2,
    reserved_at DATETIME NOT NULL,
    source ENUM('web','call_center','whatsapp','walk_in') DEFAULT 'call_center',
    status ENUM('pending','confirmed','seated','cancelled','no_show') DEFAULT 'pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (table_id) REFERENCES dining_tables(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(30) NOT NULL UNIQUE,
    branch_id INT,
    order_type ENUM('dine_in','takeaway','delivery','online') NOT NULL DEFAULT 'dine_in',
    table_id INT NULL,
    customer_id INT NULL,
    status ENUM('pending','confirmed','preparing','ready','served','delivering','completed','cancelled') DEFAULT 'pending',
    subtotal DECIMAL(12,2) DEFAULT 0,
    discount DECIMAL(12,2) DEFAULT 0,
    tax DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    payment_status ENUM('unpaid','partial','paid','refunded') DEFAULT 'unpaid',
    delivery_address VARCHAR(255) NULL,
    delivery_fee DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (table_id) REFERENCES dining_tables(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    INDEX idx_status (status), INDEX idx_type (order_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    menu_item_id INT NOT NULL,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    notes VARCHAR(255),
    kitchen_status ENUM('queued','preparing','ready','served','cancelled') DEFAULT 'queued',
    station_id INT NULL COMMENT 'محطة المطبخ (legacy)',
    kitchen_section_id INT NULL COMMENT 'قسم المطبخ (Kitchen Routing)',
    started_at TIMESTAMP NULL,
    ready_at TIMESTAMP NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_payments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    method ENUM('cash','card','online','wallet') DEFAULT 'cash',
    amount DECIMAL(12,2) NOT NULL,
    received_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_returns (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    order_item_id BIGINT NULL,
    qty DECIMAL(10,2) DEFAULT 1,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255),
    approved_by INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_status_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    status VARCHAR(30) NOT NULL,
    changed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
