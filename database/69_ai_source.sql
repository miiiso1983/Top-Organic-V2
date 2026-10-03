-- =====================================================
-- 69: AI Assistant source - إضافة مصدر AI Assistant للحجوزات
-- =====================================================
SET NAMES utf8mb4;

ALTER TABLE reservations
    MODIFY COLUMN source ENUM('web','call_center','whatsapp','walk_in','ai_assistant') DEFAULT 'call_center';
