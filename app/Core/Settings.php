<?php
namespace App\Core;

/**
 * قراءة وكتابة الإعدادات من جدول settings
 */
class Settings
{
    /** يقرأ قيمة إعداد أو يرجع القيمة الافتراضية */
    public static function get(string $key, $default = null)
    {
        $row = Database::query("SELECT svalue FROM settings WHERE skey = ?", [$key])->fetch();
        return $row ? $row['svalue'] : $default;
    }

    /** يكتب قيمة إعداد */
    public static function set(string $key, $value): void
    {
        Database::query(
            "INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [$key, (string)$value]
        );
    }

    /** يقرأ إعدادات الطابعة الحرارية كمصفوفة */
    public static function printer(): array
    {
        return [
            'receipt_width'    => (int)self::get('printer_receipt_width', 80),
            'receipt_header'   => self::get('printer_receipt_header', "Top Organic\nشكراً لزيارتكم"),
            'receipt_footer'   => self::get('printer_receipt_footer', "نتمنى لكم وجبة شهية\nTop Organic"),
            'show_logo'        => (bool)self::get('printer_show_logo', 1),
            'show_tax'         => (bool)self::get('printer_show_tax', 0),
            'tax_number'       => self::get('printer_tax_number', ''),
            'auto_print'       => (bool)self::get('printer_auto_print', 1),
            'printer_name'     => self::get('printer_name', ''),
            'cashier_name'     => (bool)self::get('printer_show_cashier', 1),
            'kitchen_printer'  => self::get('printer_kitchen_name', ''),
            'copies'           => max(1, (int)self::get('printer_copies', 1)),
        ];
    }
}
