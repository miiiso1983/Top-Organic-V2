-- =====================================================
-- 01: الجداول الأساسية (فروع، مستخدمون، أدوار، صلاحيات)
-- =====================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    phone VARCHAR(30),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    level INT DEFAULT 0 COMMENT 'المستوى الإداري: أعلى رقم = صلاحية أعلى',
    description VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    module VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30),
    role_id INT NOT NULL,
    branch_id INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    skey VARCHAR(80) PRIMARY KEY,
    svalue TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- طابور المزامنة للعمل الهجين (أوف لاين/أون لاين)
CREATE TABLE IF NOT EXISTS sync_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity VARCHAR(60) NOT NULL COMMENT 'اسم الجدول/الكيان',
    entity_id BIGINT NOT NULL,
    action ENUM('insert','update','delete') NOT NULL,
    payload JSON,
    synced TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL,
    INDEX idx_synced (synced)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- العمليات الواردة من الخادم المركزي بانتظار المراجعة اليدوية
CREATE TABLE IF NOT EXISTS sync_incoming (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity VARCHAR(60) NOT NULL,
    entity_id BIGINT NOT NULL,
    action ENUM('insert','update','delete') NOT NULL,
    payload JSON,
    reviewed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reviewed (reviewed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- سجل التدقيق
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(120) NOT NULL,
    entity VARCHAR(60),
    entity_id BIGINT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
