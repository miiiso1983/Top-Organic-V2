-- Migration: دعم الورديات المتقطعة (Split Shifts)
-- يسمح بوجود فترات عمل متعددة في نفس الوردية
-- مثال: 08:00-12:00 ثم 16:00-23:00

-- جدول فترات الوردية المتعددة
CREATE TABLE IF NOT EXISTS shift_segments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    start_time TIME NOT NULL COMMENT 'وقت بدء الفترة',
    end_time TIME NOT NULL COMMENT 'وقت انتهاء الفترة',
    segment_order TINYINT NOT NULL DEFAULT 1 COMMENT 'ترتيب الفترة',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shift_segment (shift_id, start_time, end_time),
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- نقل البيانات الحالية: كل وردية حالية تحصل على فترة واحدة من start_time/end_time
INSERT INTO shift_segments (shift_id, start_time, end_time, segment_order)
SELECT id, start_time, end_time, 1
FROM shifts
WHERE NOT EXISTS (
    SELECT 1 FROM shift_segments ss WHERE ss.shift_id = shifts.id
);

-- عمود إضافي لتسهيل الاستعلام: إجمالي دقائق العمل المتوقعة لكل وردية
ALTER TABLE shifts
    ADD COLUMN IF NOT EXISTS total_minutes INT DEFAULT 0 COMMENT 'إجمالي دقائق العمل المتوقعة';

-- تحديث إجمالي الدقائق لكل وردية من فتراتها
UPDATE shifts s
SET s.total_minutes = (
    SELECT COALESCE(SUM(
        TIMESTAMPDIFF(MINUTE, ss.start_time, ss.end_time)
    ), 0)
    FROM shift_segments ss
    WHERE ss.shift_id = s.id
);
