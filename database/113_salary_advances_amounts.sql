-- =====================================================
-- 113: تأمين أعمدة المبالغ المدفوعة والمتبقية في جدول السلف
-- =====================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE salary_advances
    ADD COLUMN paid_amount DECIMAL(12,2) DEFAULT 0,
    ADD COLUMN remaining_amount DECIMAL(12,2) DEFAULT 0;

SET FOREIGN_KEY_CHECKS=1;
