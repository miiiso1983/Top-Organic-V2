-- 49: إضافة حالة "تم طلب الحساب" لطاولات الكابتن
SET NAMES utf8mb4;

ALTER TABLE dining_tables
    MODIFY COLUMN status ENUM('available','occupied','reserved','cleaning','bill_requested')
    DEFAULT 'available' NOT NULL
    COMMENT 'available: متاحة، occupied: مشغولة، reserved: محجوزة، cleaning: بانتظار التنظيف، bill_requested: تم طلب الحساب';
