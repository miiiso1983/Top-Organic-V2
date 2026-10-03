-- إضافة إمكانية إلغاء تسوية مالية معتمدة سابقاً (مكافأة/خصم/زيادة/جزاء...) قبل نهاية الشهر
ALTER TABLE hr_adjustments MODIFY COLUMN status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending';
ALTER TABLE hr_adjustments ADD COLUMN cancelled_by INT NULL AFTER approved_at;
ALTER TABLE hr_adjustments ADD COLUMN cancelled_at TIMESTAMP NULL AFTER cancelled_by;
ALTER TABLE hr_adjustments ADD COLUMN cancel_reason VARCHAR(255) NULL AFTER cancelled_at;
ALTER TABLE hr_adjustments ADD CONSTRAINT fk_hr_adjustments_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL;
