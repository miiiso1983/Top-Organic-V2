-- =====================================================
-- 63: Reservations Enhancement - وحدة إدارة الحجوزات المتكاملة
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. توسيع جدول الطاولات ليدعم مخطط الأرضية
ALTER TABLE dining_tables
    ADD COLUMN floor VARCHAR(40) NULL AFTER name,
    ADD COLUMN section VARCHAR(60) NULL AFTER floor,
    ADD COLUMN shape ENUM('square','round','rect','oval') DEFAULT 'square' AFTER section,
    ADD COLUMN pos_x INT DEFAULT 0 AFTER shape,
    ADD COLUMN pos_y INT DEFAULT 0 AFTER pos_x,
    ADD COLUMN min_capacity INT DEFAULT 1 AFTER seats,
    ADD COLUMN max_capacity INT DEFAULT 4 AFTER min_capacity,
    ADD COLUMN allows_reservation TINYINT(1) DEFAULT 1 AFTER max_capacity,
    ADD INDEX idx_floor_section (floor, section),
    ADD INDEX idx_allows_reservation (allows_reservation, status);

-- 2. تحديث جدول الحجوزات ليدعم جميع المتطلبات
ALTER TABLE reservations
    ADD COLUMN reservation_no VARCHAR(30) NULL AFTER id,
    ADD COLUMN customer_email VARCHAR(120) NULL AFTER customer_phone,
    ADD COLUMN branch_id INT NULL AFTER customer_email,
    ADD COLUMN duration_minutes INT DEFAULT 90 AFTER reserved_at,
    ADD COLUMN estimated_end DATETIME NULL AFTER duration_minutes,
    ADD COLUMN reservation_type ENUM('dine_in','private','event','birthday','business','delivery','takeaway') DEFAULT 'dine_in' AFTER status,
    ADD COLUMN customer_notes TEXT NULL AFTER notes,
    ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER customer_notes,
    ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER cancellation_reason,
    ADD COLUMN waitlist_position INT NULL AFTER rejection_reason,
    ADD COLUMN workflow_request_id INT NULL AFTER waitlist_position,
    ADD COLUMN checked_in_at DATETIME NULL AFTER workflow_request_id,
    ADD COLUMN checked_out_at DATETIME NULL AFTER checked_in_at,
    ADD COLUMN order_id BIGINT NULL AFTER checked_out_at,
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD UNIQUE KEY uq_reservation_no (reservation_no),
    ADD INDEX idx_reserved_at (reserved_at),
    ADD INDEX idx_status_branch (status, branch_id),
    ADD INDEX idx_waitlist (waitlist_position),
    ADD INDEX idx_workflow_request (workflow_request_id);

ALTER TABLE reservations
    MODIFY COLUMN status ENUM('pending','confirmed','seated','cancelled','no_show','waiting','rejected','completed') DEFAULT 'pending';

-- 3. سجل العمليات (Audit Trail) للحجوزات
CREATE TABLE IF NOT EXISTS reservation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    action ENUM('created','updated','confirmed','seated','cancelled','rejected','rescheduled','waitlisted','checked_in','checked_out','notified','comment') NOT NULL,
    actor_id INT NULL,
    actor_name VARCHAR(120) NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reservation_id (reservation_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. صلاحيات جديدة
INSERT IGNORE INTO permissions (code, name, module) VALUES
('reservations.manage', 'إدارة الحجوزات', 'sales'),
('reservations.view', 'عرض الحجوزات', 'sales'),
('reservations.approve', 'الموافقة على الحجوزات', 'sales'),
('floor_plan.view', 'عرض مخطط الطاولات', 'sales'),
('floor_plan.edit', 'تعديل مخطط الطاولات', 'sales');

-- 5. قواعد الإشعارات
CREATE TABLE IF NOT EXISTS notification_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(60) NOT NULL,
    min_admin_level INT DEFAULT 0,
    branch_specific TINYINT(1) DEFAULT 0,
    department_specific TINYINT(1) DEFAULT 0,
    role_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO notification_rules (event_type, min_admin_level, branch_specific, is_active)
VALUES
('reservation_created', 30, 1, 1),
('reservation_confirmed', 30, 1, 1),
('reservation_cancelled', 30, 1, 1),
('reservation_reminder', 30, 1, 1);

-- 6. قالب Workflow للموافقة على الحجوزات الكبيرة/الفعاليات
ALTER TABLE approval_templates
    ADD COLUMN entity_table VARCHAR(60) NULL AFTER icon,
    ADD COLUMN fields JSON NULL AFTER entity_table;

INSERT IGNORE INTO approval_templates (code, name, icon, entity_table, fields_json, is_active)
VALUES (
    'reservation_approval',
    'موافقة الحجوزات',
    'calendar-check',
    'reservations',
    '[{"name":"guests","label":"عدد الضيوف","type":"number"},{"name":"reservation_type","label":"نوع الحجز","type":"text"},{"name":"reserved_at","label":"تاريخ الحجز","type":"datetime"},{"name":"notes","label":"ملاحظات","type":"textarea"}]',
    1
);

SET @manager_role = (SELECT id FROM roles WHERE name = 'مدير' LIMIT 1);
SET @manager_role = IFNULL(@manager_role, (SELECT id FROM roles ORDER BY level DESC LIMIT 1));

INSERT IGNORE INTO approval_steps (template_id, step_order, title, description, role_id, job_title_id, condition_json, min_amount, notify_on_arrive, notify_channels, step_type, required_count)
SELECT t.id, 1, 'موافقة المدير', 'المراجعة والموافقة على الحجز', @manager_role, 0, '{}', 0, 1, '{"push":1,"email":1,"whatsapp":0}', 'sequential', 1
FROM approval_templates t WHERE t.code = 'reservation_approval';

INSERT IGNORE INTO template_allowed_roles (template_id, role_id, can_create, can_view_all)
SELECT t.id, @manager_role, 1, 1
FROM approval_templates t WHERE t.code = 'reservation_approval';

-- 7. إعدادات افتراضية للحجوزات
ALTER TABLE settings ADD COLUMN sgroup VARCHAR(60) NULL AFTER svalue;
ALTER TABLE settings ADD COLUMN label VARCHAR(120) NULL AFTER sgroup;

INSERT INTO settings (skey, svalue, sgroup, label) VALUES
('reservation_default_duration', '90', 'sales', 'المدة الافتراضية للحجز بالدقائق'),
('reservation_auto_confirm_max_guests', '8', 'sales', 'أقصى عدد ضيوف للتأكيد التلقائي'),
('reservation_reminder_hours', '2', 'sales', 'ساعات التنبيه قبل موعد الحجز')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), label = VALUES(label);

SET FOREIGN_KEY_CHECKS=1;
