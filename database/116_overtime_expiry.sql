-- =====================================================
-- 116: إضافة حالة "منتهي الصلاحية" لطلبات العمل الإضافي
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE overtime_requests MODIFY COLUMN status ENUM('pending','manager_approved','fully_approved','rejected','expired') NOT NULL DEFAULT 'pending' COMMENT 'حالة طلب العمل الإضافي';

SET FOREIGN_KEY_CHECKS=1;
