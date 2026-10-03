-- وحدة إدارة السكن: مواقع السكن (سكن خارجي/اليرموك/سبتي/القادسية/الداخلي) -> شقق -> غرف (بعدد أسرة) -> تسكين الموظفين

CREATE TABLE IF NOT EXISTS housing_sites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_key VARCHAR(30) NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_housing_sites_key (site_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO housing_sites (site_key, name, sort_order) VALUES
 ('external', 'سكن خارجي', 1),
 ('yarmouk', 'سكن اليرموك', 2),
 ('sebti', 'سكن سبتي', 3),
 ('qadisiya', 'سكن القادسية', 4),
 ('internal', 'سكن الداخلي', 5);

CREATE TABLE IF NOT EXISTS housing_apartments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  apartment_number VARCHAR(50) NOT NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_housing_apt_site (site_id),
  UNIQUE KEY uq_housing_site_apt (site_id, apartment_number),
  CONSTRAINT fk_housing_apt_site FOREIGN KEY (site_id) REFERENCES housing_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS housing_rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  apartment_id INT NOT NULL,
  room_number VARCHAR(50) NOT NULL,
  bed_capacity INT NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_housing_room_apt (apartment_id),
  UNIQUE KEY uq_housing_apt_room (apartment_id, room_number),
  CONSTRAINT fk_housing_room_apt FOREIGN KEY (apartment_id) REFERENCES housing_apartments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_housing_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  site_id INT NOT NULL,
  apartment_id INT NULL,
  room_id INT NULL,
  assigned_date DATE NOT NULL,
  ended_date DATE NULL,
  status ENUM('active','ended') NOT NULL DEFAULT 'active',
  notes VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_eha_employee (employee_id),
  KEY idx_eha_room (room_id),
  KEY idx_eha_status (status),
  CONSTRAINT fk_eha_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  CONSTRAINT fk_eha_site FOREIGN KEY (site_id) REFERENCES housing_sites(id),
  CONSTRAINT fk_eha_apartment FOREIGN KEY (apartment_id) REFERENCES housing_apartments(id) ON DELETE SET NULL,
  CONSTRAINT fk_eha_room FOREIGN KEY (room_id) REFERENCES housing_rooms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- صلاحية إدارة السكن — تُمنح لأعلى مستويات إدارة الموارد البشرية
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.housing.manage', 'إدارة السكن (المواقع والشقق والغرف والتسكين)', 'hr');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.name IN ('مدير النظام', 'مدير مطعم', 'مدير قسم الموارد البشرية') AND p.code = 'hr.housing.manage';
