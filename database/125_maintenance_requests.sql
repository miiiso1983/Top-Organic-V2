SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- إنشاء قسم الصيانة الافتراضي إذا لم يكن موجوداً
INSERT IGNORE INTO departments (name, code, is_active) VALUES ('الصيانة', 'MAINT', 1);

CREATE TABLE IF NOT EXISTS maintenance_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(30) NOT NULL UNIQUE,
    requester_id INT NOT NULL COMMENT 'موظف صاحب الطلب',
    requester_user_id INT NULL COMMENT 'مستخدم صاحب الطلب',
    title VARCHAR(150) NOT NULL COMMENT 'اسم العطل',
    device_name VARCHAR(150) NOT NULL COMMENT 'اسم الجهاز',
    description TEXT NULL COMMENT 'ملاحظات إضافية',
    image_path VARCHAR(255) NOT NULL COMMENT 'صورة العطل',
    status ENUM('pending','assigned','in_progress','completed','confirmed','cancelled') DEFAULT 'pending',
    maintenance_department_id INT NULL COMMENT 'قسم الصيانة',
    assigned_to INT NULL COMMENT 'موظف الصيانة المختار',
    assigned_by INT NULL COMMENT 'مدير الصيانة الذي عين المهمة',
    assigned_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    confirmed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES employees(id) ON DELETE RESTRICT,
    FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (maintenance_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES employees(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_requester (requester_id),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS maintenance_status_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maintenance_request_id INT NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NOT NULL,
    changed_by INT NULL COMMENT 'مستخدم غيّر الحالة',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (maintenance_request_id) REFERENCES maintenance_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request (maintenance_request_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
