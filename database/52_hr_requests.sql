-- =====================================================
-- 52: HR Requests - نظام طلبات الموارد البشرية (إجازة، إذن خروج، تأخير، مباشرة)
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1. توسيع جدول الإجازات ليصبح جدول طلبات الموارد البشرية
ALTER TABLE leave_requests
  ADD COLUMN IF NOT EXISTS request_type ENUM('leave','exit','delay','work_resumption') DEFAULT 'leave' AFTER leave_type,
  ADD COLUMN IF NOT EXISTS start_time TIME NULL AFTER date_to,
  ADD COLUMN IF NOT EXISTS end_time TIME NULL AFTER start_time,
  ADD COLUMN IF NOT EXISTS requested_by INT NULL AFTER employee_id,
  ADD COLUMN IF NOT EXISTS notes VARCHAR(500) NULL AFTER reason,
  ADD INDEX idx_request_type (request_type),
  ADD INDEX idx_status (status),
  ADD FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL;

-- 2. السماح بقيمة n/a لنوع الإجازة عندما يكون الطلب غير إجازة
ALTER TABLE leave_requests MODIFY COLUMN leave_type ENUM('annual','sick','unpaid','emergency','hourly','n/a') DEFAULT 'annual';

-- 3. تحديث السجلات الموجودة لتكون من نوع إجازة
UPDATE leave_requests SET request_type = 'leave' WHERE request_type IS NULL OR request_type = '';
UPDATE leave_requests SET leave_type = 'annual' WHERE leave_type IS NULL OR leave_type = '';

-- 4. جدول مرفقات الطلبات
CREATE TABLE IF NOT EXISTS leave_request_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    file_size INT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. إضافة مستوى ثانٍ لمسار اعتماد الطلبات
SET @wf_id = (SELECT id FROM legacy_approval_workflows WHERE entity_type = 'leave_request');
INSERT IGNORE INTO legacy_approval_steps (workflow_id, step_order, role_id, min_amount)
SELECT @wf_id, 2, r.id, 0 FROM roles r WHERE r.name IN ('مدير عام','مدير النظام') AND @wf_id IS NOT NULL;

-- 6. صلاحيات جديدة للطلبات
INSERT IGNORE INTO permissions (code, name, module) VALUES
('hr.leave.request','تقديم طلبات الموارد البشرية','hr'),
('hr.leave.approve','اعتماد طلبات الموارد البشرية','hr');

-- 7. منح الصلاحيات للأدوار
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم') AND p.code = 'hr.leave.approve';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name IN ('مدير النظام','مدير عام','مدير مطعم','مدير مالي','محاسب','موظف','كاشير','شيف','أمين مخزن','مسؤول مشتريات') AND p.code = 'hr.leave.request';

SET FOREIGN_KEY_CHECKS=1;
