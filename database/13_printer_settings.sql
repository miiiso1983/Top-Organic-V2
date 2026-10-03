-- =====================================================
-- 13: إعدادات الطابعات الحرارية للفواتير
-- =====================================================
SET NAMES utf8mb4;

INSERT INTO settings (skey, svalue) VALUES
('printer_receipt_width', '80'),
('printer_receipt_header', 'Top Organic\nشكراً لزيارتكم'),
('printer_receipt_footer', 'نتمنى لكم وجبة شهية\nTop Organic'),
('printer_show_logo', '1'),
('printer_show_tax', '0'),
('printer_tax_number', ''),
('printer_auto_print', '1'),
('printer_name', ''),
('printer_show_cashier', '1'),
('printer_kitchen_name', ''),
('printer_copies', '1')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);
