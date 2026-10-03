-- =====================================================
-- 50: Plate Cost Import (Excel أطباق + كلفة مواد)
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS plate_cost_import_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_filename VARCHAR(255) NOT NULL,
    status ENUM('pending', 'success', 'partial', 'error') NOT NULL DEFAULT 'pending',
    errors_json TEXT,
    warnings_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plate_cost_purchase_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_run_id INT NOT NULL,
    material_name VARCHAR(255) NOT NULL,
    material_category VARCHAR(255) NULL,
    unit_price DECIMAL(16,6) NOT NULL DEFAULT 0,
    raw_waste_rate DECIMAL(12,8) NOT NULL DEFAULT 0,
    raw_waste_over_yield DECIMAL(12,8) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NULL,
    price_as_of DATE NULL,
    supplier_id INT NULL,
    source_file_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_run_material (import_run_id, material_name),
    INDEX idx_run (import_run_id),
    INDEX idx_material (material_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plate_cost_prepared_component_bom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_run_id INT NOT NULL,
    prepared_name VARCHAR(255) NOT NULL,
    ingredient_name VARCHAR(255) NOT NULL,
    ingredient_code VARCHAR(60) NULL,
    base_quantity DECIMAL(16,6) NOT NULL DEFAULT 0,
    unit VARCHAR(30) NULL,
    unit_price DECIMAL(16,6) NOT NULL DEFAULT 0,
    raw_waste_rate DECIMAL(12,8) NOT NULL DEFAULT 0,
    raw_waste_over_yield DECIMAL(12,8) NOT NULL DEFAULT 0,
    raw_waste_quantity DECIMAL(16,6) NOT NULL DEFAULT 0,
    raw_waste_cost DECIMAL(16,6) NOT NULL DEFAULT 0,
    department_waste_rate DECIMAL(12,8) NOT NULL DEFAULT 0,
    department_waste_over_yield DECIMAL(12,8) NOT NULL DEFAULT 0,
    department_waste_quantity DECIMAL(16,6) NOT NULL DEFAULT 0,
    department_waste_cost DECIMAL(16,6) NOT NULL DEFAULT 0,
    actual_consumed_quantity DECIMAL(16,6) NOT NULL DEFAULT 0,
    line_cost DECIMAL(16,6) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_run_prepared (import_run_id, prepared_name),
    INDEX idx_run (import_run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plate_cost_dish_material_bom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_run_id INT NOT NULL,
    dish_name VARCHAR(255) NOT NULL,
    dish_code VARCHAR(60) NULL,
    dish_category VARCHAR(255) NULL,
    material_type VARCHAR(30) NOT NULL DEFAULT 'raw',
    material_name VARCHAR(255) NOT NULL,
    unit VARCHAR(30) NULL,
    base_quantity DECIMAL(16,6) NOT NULL DEFAULT 0,
    resolved_unit_cost DECIMAL(16,6) NOT NULL DEFAULT 0,
    raw_waste_over_yield DECIMAL(12,8) NOT NULL DEFAULT 0,
    department_waste_rate DECIMAL(12,8) NOT NULL DEFAULT 0,
    department_waste_over_yield DECIMAL(12,8) NOT NULL DEFAULT 0,
    actual_consumed_quantity DECIMAL(16,6) NOT NULL DEFAULT 0,
    line_cost DECIMAL(16,6) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_run_dish (import_run_id, dish_name),
    INDEX idx_run (import_run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plate_cost_dishes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_run_id INT NOT NULL,
    dish_name VARCHAR(255) NOT NULL,
    dish_code VARCHAR(60) NULL,
    dish_category VARCHAR(255) NULL,
    sale_unit VARCHAR(30) NULL,
    dish_cost DECIMAL(16,6) NOT NULL DEFAULT 0,
    sale_price DECIMAL(16,6) NOT NULL DEFAULT 0,
    gross_profit DECIMAL(16,6) NOT NULL DEFAULT 0,
    markup_on_cost DECIMAL(12,8) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_run_dish (import_run_id, dish_name),
    INDEX idx_run (import_run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
