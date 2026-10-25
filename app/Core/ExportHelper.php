<?php
namespace App\Core;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Mpdf\Mpdf;

/**
 * تصدير التقارير إلى Excel و PDF بدعم العربية
 */
class ExportHelper
{
    /** تصدير ملف Excel من عدة أوراق */
    public static function excel(string $filename, array $sheets): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(11);
        $spreadsheet->getDefaultStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        foreach ($sheets as $i => $sheet) {
            if ($i > 0) {
                $spreadsheet->createSheet();
            }
            $active = $spreadsheet->getSheet($i);
            $active->setTitle(mb_substr($sheet['title'] ?? 'Sheet' . ($i + 1), 0, 31, 'UTF-8'));
            $active->setRightToLeft(true);

            $rowIdx = 1;
            // عنوان التقرير
            if (!empty($sheet['report_title'])) {
                $active->setCellValue('A' . $rowIdx, $sheet['report_title']);
                $active->mergeCells('A' . $rowIdx . ':' . self::col(count($sheet['headers'] ?? [])) . $rowIdx);
                $active->getStyle('A' . $rowIdx)->getFont()->setBold(true)->setSize(14);
                $active->getStyle('A' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $rowIdx++;
            }
            // فترة التقرير
            if (!empty($sheet['period'])) {
                $active->setCellValue('A' . $rowIdx, $sheet['period']);
                $active->mergeCells('A' . $rowIdx . ':' . self::col(count($sheet['headers'] ?? [])) . $rowIdx);
                $active->getStyle('A' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $rowIdx++;
            }
            $rowIdx++; // فاصل

            $headers = $sheet['headers'] ?? [];
            $colCount = count($headers);
            $active->fromArray($headers, null, 'A' . $rowIdx);
            $headerRange = 'A' . $rowIdx . ':' . self::col($colCount) . $rowIdx;
            $active->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $active->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
            $active->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $rowIdx++;

            $rows = $sheet['rows'] ?? [];
            foreach ($rows as $r) {
                $values = array_values($r);
                $active->fromArray($values, null, 'A' . $rowIdx);
                foreach ($sheet['durationColumns'] ?? [] as $column) {
                    $value = (string)($values[$column] ?? '');
                    if (preg_match('/^(\d+):([0-5]\d)$/D', $value, $duration)) {
                        $cell = self::col((int)$column + 1) . $rowIdx;
                        $minutes = (int)$duration[1] * 60 + (int)$duration[2];
                        $active->setCellValueExplicit($cell, $minutes / 1440, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $active->getStyle($cell)->getNumberFormat()->setFormatCode('[h]:mm');
                    }
                }
                $rowIdx++;
            }

            // صف إجمالي محاذٍ للأعمدة (قيمة واحدة أسفل كل عمود، مثل صف الإجمالي في الجدول التفاعلي)
            if (!empty($sheet['totalsRow']) && is_array($sheet['totalsRow'])) {
                $active->fromArray(array_values($sheet['totalsRow']), null, 'A' . $rowIdx);
                $totalsRange = 'A' . $rowIdx . ':' . self::col($colCount) . $rowIdx;
                $active->getStyle($totalsRange)->getFont()->setBold(true);
                $active->getStyle($totalsRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
                $rowIdx++;
            }

            // إجماليات (سطور منفصلة بصيغة اسم-قيمة)
            if (!empty($sheet['totals']) && is_array($sheet['totals'])) {
                foreach ($sheet['totals'] as $label => $value) {
                    $active->setCellValue('A' . $rowIdx, $label);
                    $active->setCellValue(self::col($colCount) . $rowIdx, $value);
                    $active->getStyle('A' . $rowIdx . ':' . self::col($colCount) . $rowIdx)->getFont()->setBold(true);
                    $rowIdx++;
                }
            }

            // تنسيق الحدود
            $lastCol = self::col($colCount);
            $lastRow = max(1, $rowIdx - 1);
            $active->getStyle('A1:' . $lastCol . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // توسيع الأعمدة
            for ($c = 1; $c <= $colCount; $c++) {
                $active->getColumnDimension(self::col($c))->setAutoSize(true);
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /** تصدير ملف PDF بدعم العربية (عبر mPDF + خط XB Riyaz) */
    public static function pdf(string $filename, array $data): void
    {
        $mpdf = self::mpdf($data['orientation'] ?? 'A4');

        $title = $data['title'] ?? 'تقرير';
        $period = $data['period'] ?? '';
        $headers = $data['headers'] ?? [];
        $rows = $data['rows'] ?? [];
        $totals = $data['totals'] ?? [];

        $html = '<div dir="rtl" style="font-family: xbriyaz, Arial, sans-serif; text-align: right;">'
              . '<div style="text-align:center;font-size:18px;font-weight:bold;margin-bottom:4px;">'
              . Helpers::e($title) . '</div>';
        if ($period) {
            $html .= '<div style="text-align:center;font-size:12px;margin-bottom:8px;">'
                   . Helpers::e($period) . '</div>';
        }
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:11px;">'
               . '<thead><tr style="background:#1F4E78;color:#fff;">';
        foreach ($headers as $h) {
            $html .= '<th style="border:1px solid #333;padding:5px;text-align:center;">'
                   . Helpers::e($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>';
            foreach (array_values($r) as $cell) {
                $html .= '<td style="border:1px solid #333;padding:4px;text-align:center;">'
                       . Helpers::e((string)$cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $totalsRow = $data['totalsRow'] ?? [];
        if ($totalsRow) {
            $html .= '<tr style="font-weight:bold;background:#f1f5f9;">';
            foreach (array_values($totalsRow) as $cell) {
                $html .= '<td style="border:1px solid #333;padding:4px;text-align:center;">'
                       . Helpers::e((string)$cell) . '</td>';
            }
            $html .= '</tr>';
        }
        if ($totals) {
            foreach ($totals as $label => $value) {
                $html .= '<tr style="font-weight:bold;background:#f0f0f0;">'
                       . '<td style="border:1px solid #333;padding:4px;" colspan="' . (count($headers) - 1) . '">'
                       . Helpers::e($label) . '</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">'
                       . Helpers::e((string)$value) . '</td></tr>';
            }
        }
        $html .= '</tbody></table></div>';

        $mpdf->WriteHTML($html);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $mpdf->Output($filename, 'D');
        exit;
    }

    /** تصدير ملف CSV بسيط (UTF-8 مع BOM لدعم Excel والعربية) */
    public static function csv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($output, array_values($r), ',', '"', '');
        }
        fclose($output);
        exit;
    }

    /** إنشاء مثيل mPDF بخط XB Riyaz ودعم RTL */
    private static function mpdf(string $format = 'A4'): Mpdf
    {
        $fontDir = BASE_PATH . '/vendor/mpdf/mpdf/ttfonts';
        return new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'default_font' => 'xbriyaz',
            'fontDir' => [$fontDir],
            'fontdata' => [
                'xbriyaz' => [
                    'R' => 'XB Riyaz.ttf',
                    'B' => 'XB RiyazBd.ttf',
                    'I' => 'XB RiyazIt.ttf',
                    'BI' => 'XB RiyazBdIt.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'direction' => 'rtl',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ]);
    }

    /** تصدير بطاقة تعريف الصنف إلى PDF */
    public static function itemCardPdf(string $filename, array $data): void
    {
        $item = $data['item'] ?? [];
        $movements = $data['movements'] ?? [];
        $mpdf = self::mpdf('A4');

        $row = fn($label, $value) => '<tr><td style="width:35%;border:1px solid #333;padding:6px;background:#f9f9f9;font-weight:bold;">'
            . Helpers::e($label) . '</td>'
            . '<td style="border:1px solid #333;padding:6px;">' . Helpers::e($value) . '</td></tr>';

        $html = '<div dir="rtl" style="font-family: xbriyaz, Arial, sans-serif; text-align: right;">'
              . '<div style="text-align:center;font-size:20px;font-weight:bold;margin-bottom:6px;">بطاقة تعريف الصنف</div>'
              . '<div style="text-align:center;font-size:14px;color:#555;margin-bottom:12px;">'
              . 'رقم البطاقة: ' . Helpers::e($item['card_no'] ?? '-') . '</div>'
              . '<table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:16px;">'
              . $row('الباركود', $item['barcode'] ?? '-')
              . $row('اسم الصنف', $item['name'] ?? '-')
              . $row('التصنيف', ($item['category_name'] ?? '') . ' (' . ($item['category_type'] ?? '') . ')')
              . $row('الوحدة', $item['unit_name'] ?? '-')
              . $row('الرصيد الكلي', (float) ($item['total_qty'] ?? 0))
              . $row('سعر الكلفة', Helpers::money($item['cost_price'] ?? 0))
              . $row('الوصف / المواصفات', $item['description'] ?? '-')
              . $row('المستودع الرئيسي', $item['warehouse_name'] ?? '-')
              . $row('مطبخ التحضير', $item['kitchen_name'] ?? '-')
              . $row('مدة التحضير', ($item['prep_time_minutes'] ?? 0) . ' دقيقة')
              . '</table>';

        if ($movements) {
            $html .= '<div style="font-size:14px;font-weight:bold;margin-bottom:6px;">آخر حركات المخزون</div>'
                   . '<table style="width:100%;border-collapse:collapse;font-size:11px;">'
                   . '<tr style="background:#1F4E78;color:#fff;">'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">التاريخ</th>'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">المستودع</th>'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">النوع</th>'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">الكمية</th>'
                   . '</tr>';
            foreach ($movements as $m) {
                $html .= '<tr>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . Helpers::e($m['created_at'] ?? '') . '</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . Helpers::e($m['warehouse_name'] ?? '') . '</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . Helpers::e($m['movement_type'] ?? '') . '</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . (float) ($m['qty'] ?? 0) . '</td>'
                       . '</tr>';
            }
            $html .= '</table>';
        }

        $html .= '<div style="margin-top:20px;text-align:center;font-size:10px;color:#888;">'
               . 'تم إنشاء هذه البطاقة من نظام Top Organic بتاريخ ' . date('Y-m-d H:i') . '</div>'
               . '</div>';

        $mpdf->WriteHTML($html);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $mpdf->Output($filename, 'D');
        exit;
    }

    /** تصدير قالب تكلفة الصنف إلى PDF */
    public static function itemCostSheetPdf(string $filename, array $data): void
    {
        $item = $data['item'] ?? [];
        $sheet = $data['sheet'] ?? [];
        $lines = $data['lines'] ?? [];
        $totalCost = (float) ($data['totalCost'] ?? 0);
        $costPerUnit = (float) ($data['costPerUnit'] ?? 0);
        $profit = (float) ($data['profit'] ?? 0);
        $marginPct = $costPerUnit > 0 ? ($profit / $costPerUnit) : 0;
        $foodCostPct = (float) ($data['foodCostPct'] ?? 0);
        $mpdf = self::mpdf('A4');

        $row = fn($label, $value) => '<tr><td style="width:40%;border:1px solid #333;padding:6px;background:#f9f9f9;font-weight:bold;">'
            . Helpers::e($label) . '</td>'
            . '<td style="border:1px solid #333;padding:6px;">' . $value . '</td></tr>';

        $html = '<div dir="rtl" style="font-family: xbriyaz, Arial, sans-serif; text-align: right;">'
              . '<div style="text-align:center;font-size:20px;font-weight:bold;margin-bottom:6px;">قالب تكلفة الصنف</div>'
              . '<div style="text-align:center;font-size:14px;color:#555;margin-bottom:12px;">'
              . Helpers::e($item['name'] ?? '-') . '</div>'
              . '<table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:16px;">'
              . $row('الباركود', Helpers::e($item['barcode'] ?? '-'))
              . $row('وحدة الناتج', Helpers::e($sheet['yield_unit_name'] ?? $item['unit_name'] ?? '-'))
              . $row('كمية الناتج', (float) ($sheet['yield_qty'] ?? 1))
              . $row('هدر القسم', number_format((float) ($sheet['waste_rate'] ?? 0) * 100, 2) . '%')
              . $row('إجمالي الكلفة', Helpers::money($totalCost))
              . $row('كلفة الوحدة', Helpers::money($costPerUnit))
              . $row('سعر البيع', Helpers::money($sheet['sale_price'] ?? 0))
              . $row('الربح', Helpers::money($profit) . ' (' . number_format($marginPct * 100, 2) . '%)')
              . $row('نسبة تكلفة الغذاء', number_format($foodCostPct * 100, 2) . '%')
              . '</table>';

        if ($lines) {
            $html .= '<div style="font-size:14px;font-weight:bold;margin-bottom:6px;">المكونات والأوزان</div>'
                   . '<table style="width:100%;border-collapse:collapse;font-size:11px;">'
                   . '<tr style="background:#1F4E78;color:#fff;">'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">#</th>'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">المادة</th>'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">الكمية</th>'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">الوحدة</th>'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">هدر المادة %</th>'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">زيادة الناتج %</th>'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">الكمية الإجمالية</th>'
                   . '<th style="border:1px solid #333;padding:5px;text-align:center;">الكلفة</th>'
                   . '</tr>';
            foreach ($lines as $idx => $line) {
                $html .= '<tr>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . ($idx + 1) . '</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . Helpers::e($line['ingredient_name'] ?? '') . '</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . (float) ($line['qty'] ?? 0) . '</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . Helpers::e($line['line_unit_name'] ?? '') . '</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . number_format((float) ($line['waste_rate'] ?? 0) * 100, 2) . '%</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . number_format((float) ($line['yield_gain_rate'] ?? 0) * 100, 2) . '%</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . number_format((float) ($line['gross_qty'] ?? 0), 4) . ' ' . Helpers::e($line['line_unit_name'] ?? '') . '</td>'
                       . '<td style="border:1px solid #333;padding:4px;text-align:center;">' . Helpers::money($line['line_cost'] ?? 0) . '</td>'
                       . '</tr>';
            }
            $html .= '</table>';
        }

        $html .= '<div style="margin-top:20px;text-align:center;font-size:10px;color:#888;">'
               . 'تم إنشاء هذا القالب من نظام Top Organic بتاريخ ' . date('Y-m-d H:i') . '</div>'
               . '</div>';

        $mpdf->WriteHTML($html);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $mpdf->Output($filename, 'D');
        exit;
    }

    /** رقم العمود إلى حرف (1 => A) */
    private static function col(int $n): string
    {
        $letters = '';
        while ($n > 0) {
            $n--;
            $letters = chr(65 + ($n % 26)) . $letters;
            $n = intdiv($n, 26);
        }
        return $letters ?: 'A';
    }
}
