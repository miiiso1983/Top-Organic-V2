-- =====================================================
-- 07: الموارد البشرية
-- =====================================================

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_code VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NULL COMMENT 'ربط بحساب دخول النظام',
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30),
    position VARCHAR(100),
    department VARCHAR(100),
    admin_level INT DEFAULT 0 COMMENT 'المستوى الإداري',
    base_salary DECIMAL(12,2) DEFAULT 0,
    hire_date DATE,
    file_status ENUM('open','closed') DEFAULT 'open',
    branch_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الحضور والانصراف (بصمة هاتف مع موقع وصورة)
CREATE TABLE IF NOT EXISTS attendance (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    check_type ENUM('in','out') NOT NULL,
    checked_at DATETIME NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    photo VARCHAR(255) NULL,
    source ENUM('mobile','web','device') DEFAULT 'mobile',
    within_geofence TINYINT(1) DEFAULT 1 COMMENT 'داخل نطاق موقع العمل',
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_emp_date (employee_id, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الإجازات
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('annual','sick','unpaid','emergency','hourly') DEFAULT 'annual',
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    reason VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- السلف
CREATE TABLE IF NOT EXISTS salary_advances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    advance_type ENUM('short','long') DEFAULT 'short' COMMENT 'قصيرة أو طويلة الأجل',
    installments INT DEFAULT 1,
    reason VARCHAR(255),
    status ENUM('pending','approved','rejected','paid','settled') DEFAULT 'pending',
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- المكافآت والخصومات والزيادات
CREATE TABLE IF NOT EXISTS salary_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    adj_type ENUM('bonus','deduction','raise','penalty') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255),
    adj_date DATE NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    approved_by INT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- الرواتب الشهرية
CREATE TABLE IF NOT EXISTS payrolls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    period VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    base_salary DECIMAL(12,2) NOT NULL,
    bonuses DECIMAL(12,2) DEFAULT 0,
    deductions DECIMAL(12,2) DEFAULT 0,
    advances DECIMAL(12,2) DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL,
    status ENUM('draft','approved','paid') DEFAULT 'draft',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_emp_period (employee_id, period),
    FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- التقييم الوظيفي
CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    score TINYINT NOT NULL COMMENT 'من 1 إلى 10',
    notes VARCHAR(255),
    evaluated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- المهام
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    assigned_to INT NOT NULL,
    due_date DATE NULL,
    status ENUM('open','in_progress','done','cancelled') DEFAULT 'open',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
