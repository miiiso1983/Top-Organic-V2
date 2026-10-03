<?php
declare(strict_types=1);
$base = dirname(__DIR__);
define('BASE_PATH', $base);
require $base . '/vendor/autoload.php';

use Mpdf\Mpdf;

$mdPath = $base . '/docs/HR_PAYROLL_MIGRATION_GUIDE.md';
$pdfPath = $base . '/docs/HR_PAYROLL_MIGRATION_GUIDE.pdf';
$fontUrl = 'https://github.com/rastikerdar/vazirmatn/raw/master/fonts/ttf/Vazirmatn-Regular.ttf';
$fontPath = '/tmp/Vazirmatn-Regular.ttf';

if (!file_exists($mdPath)) {
    fwrite(STDERR, "Markdown file not found: $mdPath\n");
    exit(1);
}

if (!file_exists($fontPath)) {
    $data = @file_get_contents($fontUrl);
    if ($data === false || $data === '') {
        fwrite(STDERR, "Failed to download Arabic font from $fontUrl\n");
        exit(1);
    }
    file_put_contents($fontPath, $data);
}

$md = file_get_contents($mdPath);
$html = markdownToHtml($md);

$fontName = 'vazirmatn';

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 20,
    'margin_bottom' => 20,
    'fontDir' => ['/tmp'],
    'fontdata' => [
        $fontName => [
            'R' => 'Vazirmatn-Regular.ttf',
            'B' => 'Vazirmatn-Regular.ttf',
            'I' => 'Vazirmatn-Regular.ttf',
            'BI' => 'Vazirmatn-Regular.ttf',
            'useOTL' => 0xFF,
            'useKashida' => 75,
        ],
    ],
    'default_font' => $fontName,
    'default_font_size' => 11,
    'directionality' => 'rtl',
]);

$mpdf->SetTitle('دليل الانتقال من Excel إلى نظام الرواتب');
$mpdf->WriteHTML('<body dir="rtl">' . $html . '</body>');
$mpdf->Output($pdfPath, 'F');

echo "PDF generated: $pdfPath\n";

function markdownToHtml(string $md): string
{
    $lines = explode("\n", $md);
    $html = [];
    $inList = false;
    $inTable = false;
    $tableRows = [];

    foreach ($lines as $line) {
        $line = rtrim($line);

        if ($line === '---') {
            if ($inList) { $html[] = '</ul>'; $inList = false; }
            if ($inTable) { $html[] = renderTable($tableRows); $tableRows = []; $inTable = false; }
            $html[] = '<hr>';
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
            if ($inList) { $html[] = '</ul>'; $inList = false; }
            if ($inTable) { $html[] = renderTable($tableRows); $tableRows = []; $inTable = false; }
            $level = strlen($m[1]);
            $html[] = "<h$level>" . inline($m[2]) . "</h$level>";
            continue;
        }

        if (preg_match('/^\|(.+)\|$/', $line, $m)) {
            if ($inList) { $html[] = '</ul>'; $inList = false; }
            $cells = array_map('trim', explode('|', $m[1]));
            if (count($cells) > 0) {
                $isSeparator = true;
                foreach ($cells as $c) {
                    if (!preg_match('/^\s*[-:]+\s*$/', $c)) { $isSeparator = false; break; }
                }
                if (!$isSeparator) {
                    $tableRows[] = $cells;
                    $inTable = true;
                    continue;
                }
            }
        }

        if ($inTable && $line === '') {
            $html[] = renderTable($tableRows);
            $tableRows = [];
            $inTable = false;
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
            if ($inList) { $html[] = '</ul>'; $inList = false; }
            if ($inTable) { $html[] = renderTable($tableRows); $tableRows = []; $inTable = false; }
            $html[] = '<p>' . inline($line) . '</p>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            if ($inTable) { $html[] = renderTable($tableRows); $tableRows = []; $inTable = false; }
            if (!$inList) { $html[] = '<ul>'; $inList = true; }
            $html[] = '<li>' . inline($m[1]) . '</li>';
            continue;
        }

        if ($line === '' && $inList) {
            $html[] = '</ul>';
            $inList = false;
            continue;
        }

        if ($line === '') {
            if ($inTable) { $html[] = renderTable($tableRows); $tableRows = []; $inTable = false; }
            continue;
        }

        if ($inTable) {
            $tableRows[] = array_map('trim', explode('|', trim($line, '|')));
            continue;
        }

        if ($inList) { $html[] = '</ul>'; $inList = false; }
        $html[] = '<p>' . inline($line) . '</p>';
    }

    if ($inList) { $html[] = '</ul>'; }
    if ($inTable) { $html[] = renderTable($tableRows); }

    return implode("\n", $html);
}

function inline(string $text): string
{
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/^>\s*/', '', $text);
    return $text;
}

function renderTable(array $rows): string
{
    if (count($rows) < 1) return '';
    $html = '<table border="1" cellpadding="6" cellspacing="0" style="width:100%; border-collapse:collapse; margin:10px 0;">\n';
    $html .= '<thead><tr>';
    foreach ($rows[0] as $h) {
        $html .= '<th style="background:#f0f0f0;">' . inline($h) . '</th>';
    }
    $html .= '</tr></thead>\n<tbody>\n';
    for ($i = 1; $i < count($rows); $i++) {
        $html .= '<tr>';
        foreach ($rows[$i] as $c) {
            $html .= '<td>' . inline($c) . '</td>';
        }
        $html .= '</tr>\n';
    }
    $html .= '</tbody></table>\n';
    return $html;
}
