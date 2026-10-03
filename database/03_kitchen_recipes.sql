-- =====================================================
-- 03: المطبخ والوصفات والإنتاج
-- =====================================================

CREATE TABLE IF NOT EXISTS kitchen_stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT,
    name VARCHAR(80) NOT NULL COMMENT 'مثال: الشواية، المقالي، الحلويات',
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الوصفات: مكونات كل صنف من قائمة الطعام
CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NOT NULL UNIQUE,
    yield_qty DECIMAL(10,2) DEFAULT 1 COMMENT 'عدد الحصص الناتجة',
    notes TEXT,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recipe_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    item_id INT NOT NULL COMMENT 'مرجع لمادة المخزون',
    qty DECIMAL(12,4) NOT NULL COMMENT 'الكمية بالوحدة العامة',
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الإنتاج اليومي (تحضير مواد مصنعة داخلياً)
CREATE TABLE IF NOT EXISTS production_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_no VARCHAR(30) NOT NULL UNIQUE,
    item_id INT NOT NULL COMMENT 'المادة المحضرة الناتجة',
    planned_qty DECIMAL(12,3) NOT NULL,
    actual_qty DECIMAL(12,3) DEFAULT 0,
    waste_qty DECIMAL(12,3) DEFAULT 0 COMMENT 'الهدر',
    status ENUM('planned','in_progress','done','cancelled') DEFAULT 'planned',
    production_date DATE NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- استهلاك مواد الإنتاج
CREATE TABLE IF NOT EXISTS production_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_id INT NOT NULL,
    item_id INT NOT NULL,
    qty DECIMAL(12,4) NOT NULL,
    FOREIGN KEY (production_id) REFERENCES production_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- سجل الهدر العام
CREATE TABLE IF NOT EXISTS waste_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    qty DECIMAL(12,3) NOT NULL,
    reason VARCHAR(255),
    logged_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
