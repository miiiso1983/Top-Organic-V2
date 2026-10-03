-- =====================================================
-- 73: وحدة طلبات المواد والمشتريات (Material Request & Procurement)
-- =====================================================
SET NAMES utf8mb4;

-- 1) رئيس طلب المواد
CREATE TABLE IF NOT EXISTS material_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_no VARCHAR(60) UNIQUE NOT NULL,
    branch_id INT NULL,
    department_id INT NULL,
    requested_by INT NOT NULL,
    request_date DATE NOT NULL,
    priority ENUM('urgent','high','normal','low') DEFAULT 'normal',
    reason VARCHAR(500) NULL,
    notes TEXT NULL,
    status ENUM('draft','submitted','pending','approved','rejected','returned','cancelled','fulfilling','partially_fulfilled','fulfilled','closed') DEFAULT 'draft',
    workflow_request_id INT NULL,
    total_estimated_cost DECIMAL(14,2) DEFAULT 0,
    currency_code VARCHAR(3) DEFAULT 'IQD',
    attachments JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (workflow_request_id) REFERENCES requests(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_branch (branch_id),
    INDEX idx_requester (requested_by),
    INDEX idx_workflow (workflow_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) بنود طلب المواد
CREATE TABLE IF NOT EXISTS material_request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_request_id INT NOT NULL,
    item_id INT NOT NULL,
    unit_id INT NULL,
    requested_qty DECIMAL(18,4) NOT NULL,
    approved_qty DECIMAL(18,4) NULL,
    fulfilled_qty DECIMAL(18,4) DEFAULT 0,
    unit_price DECIMAL(18,4) NULL,
    total_price DECIMAL(18,4) NULL,
    warehouse_id INT NULL,
    required_date DATE NULL,
    purpose VARCHAR(500) NULL,
    notes TEXT NULL,
    status ENUM('pending','approved','rejected','partially_fulfilled','fulfilled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (material_request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    INDEX idx_request (material_request_id),
    INDEX idx_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) تفاصيل التلبية (مناقلة / أمر شراء / أمر تصنيع)
CREATE TABLE IF NOT EXISTS material_request_fulfillment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_request_id INT NOT NULL,
    material_request_item_id INT NOT NULL,
    fulfillment_type ENUM('transfer','purchase_order','manufacturing_order') NOT NULL,
    document_id INT NOT NULL,
    document_no VARCHAR(60) NULL,
    qty DECIMAL(18,4) NOT NULL,
    unit_cost DECIMAL(18,4) NULL,
    total_cost DECIMAL(18,4) NULL,
    from_warehouse_id INT NULL,
    to_warehouse_id INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (material_request_item_id) REFERENCES material_request_items(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request (material_request_id),
    INDEX idx_item (material_request_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) سجل التدقيق (Audit Trail)
CREATE TABLE IF NOT EXISTS material_request_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_request_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(60) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request (material_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) صلاحيات الوحدة
INSERT IGNORE INTO permissions (code, name, module) VALUES
('material_request.view','عرض طلبات المواد','procurement'),
('material_request.manage','إدارة طلبات المواد','procurement'),
('material_request.approve','الموافقة على طلبات المواد','procurement'),
('material_request.fulfill','تلبية طلبات المواد','procurement'),
('material_request.view_all','عرض جميع طلبات المواد','procurement'),
('material_request.audit','عرض سجل تدقيق طلبات المواد','procurement');

-- 6) منح الصلاحيات للأدوار الإدارية
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'مدير النظام' AND p.code LIKE 'material_request.%';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير عام','مدير مطعم') AND p.code IN ('material_request.view','material_request.manage','material_request.approve','material_request.view_all','material_request.audit');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مسؤول مشتريات','أمين مخزن') AND p.code IN ('material_request.view','material_request.fulfill','material_request.approve');

-- 7) قالب Workflow لطلبات المواد
INSERT IGNORE INTO approval_templates (code, name, description, icon, fields_json, amount_field, is_active)
VALUES
('material_request','طلب مواد / Material Request','طلب مواد من المخزن أو الشراء أو التصنيع','📦','[{"name":"title","label":"العنوان","type":"text","required":true},{"name":"estimated_cost","label":"التكلفة المقدرة","type":"number","required":true},{"name":"priority","label":"الأولوية","type":"select","required":true,"options":["urgent","high","normal","low"]}]','estimated_cost',1);

-- 8) خطوات افتراضية للقالب (تُستخدم role_id للأدوار الإدارية)
SET @mr_template = (SELECT id FROM approval_templates WHERE code = 'material_request' LIMIT 1);
SET @manager_role = COALESCE((SELECT id FROM roles WHERE name = 'مدير عام' LIMIT 1), (SELECT id FROM roles ORDER BY level DESC LIMIT 1));
SET @warehouse_role = COALESCE((SELECT id FROM roles WHERE name = 'أمين مخزن' LIMIT 1), @manager_role);
SET @purchasing_role = COALESCE((SELECT id FROM roles WHERE name = 'مسؤول مشتريات' LIMIT 1), @manager_role);

INSERT IGNORE INTO approval_steps (template_id, step_order, title, description, role_id, min_amount, condition_json, can_return_for_edit, is_final, step_type, required_count, notify_channels)
VALUES
(@mr_template, 1, 'موافقة المدير المباشر', 'المراجعة الإدارية الأولى', @manager_role, 0, '{}', 1, 0, 'sequential', 1, '{"push":1,"email":0,"whatsapp":0}'),
(@mr_template, 2, 'التحقق من المخزون', 'التحقق من توفر المواد وتحديد طريقة التلبية', @warehouse_role, 0, '{}', 1, 0, 'sequential', 1, '{"push":1,"email":0,"whatsapp":0}'),
(@mr_template, 3, 'الموافقة المالية', 'الموافقة على الشراء للمبالغ الكبيرة', @purchasing_role, 500, '{"priority":["urgent","high"]}', 1, 1, 'sequential', 1, '{"push":1,"email":0,"whatsapp":0}');

-- 9) السماح للأدوار بإنشاء طلبات مواد
INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT @mr_template, id, 1, 0 FROM roles WHERE name IN ('مدير النظام','مدير عام','مدير مطعم','أمين مخزن','مسؤول مشتريات','شيف');

INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT @mr_template, id, 0, 1 FROM roles WHERE name IN ('مدير النظام','مدير عام');
