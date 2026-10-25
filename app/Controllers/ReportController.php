<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, ExportHelper, Helpers, AccountingService, UnitConversionService, ReservationReportService, ProcurementReportService, DashboardService};

class ReportController extends Controller
{
    private function range(): array
    {
        $from = (string)($_GET['from'] ?? date('Y-m-01'));
        $to   = (string)($_GET['to'] ?? date('Y-m-d'));
        return [$from, $to];
    }

    public function index(): void
    {
        $filters = DashboardService::filters();
        $data = DashboardService::dashboardData($filters);

        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
        $categories = Database::query("SELECT id, name FROM menu_categories WHERE is_active=1 ORDER BY name")->fetchAll();
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();
        $statuses = ['pending'=>'معلّق','confirmed'=>'مؤكد','preparing'=>'قيد التحضير','ready'=>'جاهز','served'=>'مقدَّم','delivering'=>'قيد التوصيل','completed'=>'مكتمل','cancelled'=>'ملغى'];
        $orderTypes = ['dine_in'=>'صالة','takeaway'=>'سفري','delivery'=>'توصيل','online'=>'إلكتروني'];

        $this->view('reports/index', array_merge([
            'title' => 'لوحة التقارير',
            'filters' => $filters,
            'branches' => $branches,
            'categories' => $categories,
            'employees' => $employees,
            'statuses' => $statuses,
            'orderTypes' => $orderTypes,
        ], $data));
    }

    public function export(string $tab): void
    {
        $allowed = ['sales', 'inventory', 'hr', 'expenses'];
        if (!in_array($tab, $allowed, true)) {
            $this->flash('danger', 'تبويب غير موجود');
            Response::redirect('/reports');
        }

        $filters = DashboardService::filters();
        $format = (string)($_GET['format'] ?? 'excel');
        if (!in_array($format, ['excel', 'pdf', 'csv'], true)) {
            $format = 'excel';
        }
        $period = 'من ' . $filters['from'] . ' إلى ' . $filters['to'];
        $filename = $tab . '_report_' . $filters['from'] . '_' . $filters['to'];

        $typeLabels = ['dine_in'=>'صالة','takeaway'=>'سفري','delivery'=>'توصيل','online'=>'إلكتروني'];
        $methodLabels = ['cash'=>'نقدي','card'=>'بطاقة','online'=>'إلكتروني','wallet'=>'محفظة'];
        $movementLabels = ['in'=>'إدخال','out'=>'إخراج','transfer_in'=>'مناقلة واردة','transfer_out'=>'مناقلة صادرة','adjustment'=>'تعديل','consumption'=>'استهلاك','return'=>'مرتجع'];

        if ($tab === 'sales') {
            $salesByDate = DashboardService::salesByDate($filters);
            $salesByPayment = DashboardService::salesByPaymentMethod($filters);
            $salesByType = DashboardService::salesByOrderType($filters);
            $topItems = DashboardService::topItems($filters);
            $captain = DashboardService::captainPerformance($filters);

            $dailyRows = array_map(fn($d) => [$d['d'], (int)$d['orders_count'], number_format((float)$d['total'], 0)], $salesByDate);
            $paymentRows = array_map(fn($p) => [($methodLabels[$p['method']] ?? $p['method']), (int)$p['orders_count'], number_format((float)$p['total'], 0)], $salesByPayment);
            $typeRows = array_map(fn($t) => [($typeLabels[$t['order_type']] ?? $t['order_type']), (int)$t['orders_count'], number_format((float)$t['total'], 0)], $salesByType);
            $topRows = array_map(fn($i) => [Helpers::e($i['name']), (float)$i['qty'], number_format((float)$i['total'], 0)], $topItems);
            $captainRows = array_map(fn($c) => [Helpers::e($c['captain_name'] ?? '-'), (int)$c['orders_count'], number_format((float)$c['total_sales'], 0)], $captain);

            if ($format === 'excel') {
                ExportHelper::excel($filename . '.xlsx', [
                    ['title' => 'المبيعات اليومية', 'report_title' => 'تقرير المبيعات', 'period' => $period, 'headers' => ['اليوم', 'عدد الطلبات', 'الإجمالي'], 'rows' => $dailyRows, 'totals' => []],
                    ['title' => 'حسب طريقة الدفع', 'headers' => ['طريقة الدفع', 'عدد الطلبات', 'المبلغ'], 'rows' => $paymentRows, 'totals' => []],
                    ['title' => 'حسب النوع', 'headers' => ['النوع', 'عدد الطلبات', 'المبلغ'], 'rows' => $typeRows, 'totals' => []],
                    ['title' => 'أفضل الأصناف', 'headers' => ['الصنف', 'الكمية', 'الإيراد'], 'rows' => $topRows, 'totals' => []],
                    ['title' => 'أداء الكابتن', 'headers' => ['الكابتن', 'عدد الطلبات', 'إجمالي المبيعات'], 'rows' => $captainRows, 'totals' => []],
                ]);
            } elseif ($format === 'pdf') {
                $pdfRows = $this->buildPdfSections([
                    'المبيعات اليومية' => ['headers' => ['اليوم', 'عدد الطلبات', 'المبلغ'], 'rows' => $dailyRows],
                    'حسب طريقة الدفع' => ['headers' => ['طريقة الدفع', 'عدد الطلبات', 'المبلغ'], 'rows' => $paymentRows],
                    'حسب النوع' => ['headers' => ['النوع', 'عدد الطلبات', 'المبلغ'], 'rows' => $typeRows],
                    'أفضل الأصناف' => ['headers' => ['الصنف', 'الكمية', 'الإيراد'], 'rows' => $topRows],
                    'أداء الكابتن' => ['headers' => ['الكابتن', 'عدد الطلبات', 'المبلغ'], 'rows' => $captainRows],
                ]);
                ExportHelper::pdf($filename . '.pdf', [
                    'title' => 'تقرير المبيعات', 'period' => $period,
                    'headers' => ['البيان', 'القيمة', ''], 'rows' => $pdfRows, 'totals' => [],
                    'orientation' => 'A4-L',
                ]);
            } else {
                ExportHelper::csv($filename . '.csv', ['اليوم', 'عدد الطلبات', 'الإجمالي'], $dailyRows);
            }
        } elseif ($tab === 'inventory') {
            $stockValue = DashboardService::stockValueByCategory($filters);
            $movement = DashboardService::inventoryMovement($filters);

            $valueRows = array_map(fn($v) => [Helpers::e($v['name']), (int)$v['items_count'], number_format((float)$v['total_value'], 0)], $stockValue);
            $movementRows = array_map(fn($m) => [($movementLabels[$m['movement_type']] ?? $m['movement_type']), (int)$m['movements_count'], (float)$m['qty']], $movement);

            if ($format === 'excel') {
                ExportHelper::excel($filename . '.xlsx', [
                    ['title' => 'قيمة المخزون حسب التصنيف', 'report_title' => 'تقرير المخزون', 'period' => $period, 'headers' => ['التصنيف', 'عدد الأصناف', 'القيمة'], 'rows' => $valueRows, 'totals' => []],
                    ['title' => 'حركات المخزون', 'headers' => ['النوع', 'عدد الحركات', 'الكمية'], 'rows' => $movementRows, 'totals' => []],
                ]);
            } elseif ($format === 'pdf') {
                $pdfRows = $this->buildPdfSections([
                    'قيمة المخزون حسب التصنيف' => ['headers' => ['التصنيف', 'عدد الأصناف', 'القيمة'], 'rows' => $valueRows],
                    'حركات المخزون' => ['headers' => ['النوع', 'عدد الحركات', 'الكمية'], 'rows' => $movementRows],
                ]);
                ExportHelper::pdf($filename . '.pdf', [
                    'title' => 'تقرير المخزون', 'period' => $period,
                    'headers' => ['البيان', 'القيمة', ''], 'rows' => $pdfRows, 'totals' => [],
                    'orientation' => 'A4-L',
                ]);
            } else {
                ExportHelper::csv($filename . '.csv', ['التصنيف', 'عدد الأصناف', 'القيمة'], $valueRows);
            }
        } elseif ($tab === 'hr') {
            $attendance = DashboardService::hrAttendanceSummary($filters);
            $performance = DashboardService::employeePerformance($filters);

            $attendanceRows = array_map(fn($a) => [
                Helpers::e($a['full_name']), $a['emp_code'] ?? '-', Helpers::e($a['department'] ?? '-'),
                $a['first_in'] ? date('H:i', strtotime($a['first_in'])) : '-',
                $a['last_out'] ? date('H:i', strtotime($a['last_out'])) : '-',
                (int)$a['check_ins']
            ], $attendance);
            $performanceRows = array_map(fn($p) => [
                Helpers::e($p['full_name']), $p['emp_code'] ?? '-', Helpers::e($p['department'] ?? '-'),
                number_format((float)$p['avg_score'], 1), (int)$p['tasks_completed']
            ], $performance);

            if ($format === 'excel') {
                ExportHelper::excel($filename . '.xlsx', [
                    ['title' => 'الحضور والانصراف', 'report_title' => 'تقرير الموارد البشرية', 'period' => $period, 'headers' => ['الموظف', 'الكود', 'القسم', 'أول حضور', 'آخر انصراف', 'حضور'], 'rows' => $attendanceRows, 'totals' => []],
                    ['title' => 'أداء الموظفين', 'headers' => ['الموظف', 'الكود', 'القسم', 'متوسط التقييم', 'مهام منجزة'], 'rows' => $performanceRows, 'totals' => []],
                ]);
            } elseif ($format === 'pdf') {
                $pdfRows = $this->buildPdfSections([
                    'الحضور والانصراف' => ['headers' => ['الموظف', 'الكود', 'القسم', 'أول حضور', 'آخر انصراف', 'حضور'], 'rows' => $attendanceRows],
                    'أداء الموظفين' => ['headers' => ['الموظف', 'الكود', 'القسم', 'متوسط التقييم', 'مهام منجزة'], 'rows' => $performanceRows],
                ], 6);
                ExportHelper::pdf($filename . '.pdf', [
                    'title' => 'تقرير الموارد البشرية', 'period' => $period,
                    'headers' => ['البيان', 'القيمة', '', '', '', ''], 'rows' => $pdfRows, 'totals' => [],
                    'orientation' => 'A4-L',
                ]);
            } else {
                ExportHelper::csv($filename . '.csv', ['الموظف', 'الكود', 'القسم', 'أول حضور', 'آخر انصراف', 'حضور'], $attendanceRows);
            }
        } else { // expenses
            $expenses = DashboardService::expensesByCategory($filters);
            $expenseRows = array_map(fn($e) => [Helpers::e($e['category']), (int)$e['expenses_count'], number_format((float)$e['total'], 0)], $expenses);

            if ($format === 'excel') {
                ExportHelper::excel($filename . '.xlsx', [
                    ['title' => 'المصروفات حسب التصنيف', 'report_title' => 'تقرير المصروفات', 'period' => $period, 'headers' => ['التصنيف', 'عدد العمليات', 'المبلغ'], 'rows' => $expenseRows, 'totals' => []],
                ]);
            } elseif ($format === 'pdf') {
                $pdfRows = $this->buildPdfSections([
                    'المصروفات حسب التصنيف' => ['headers' => ['التصنيف', 'عدد العمليات', 'المبلغ'], 'rows' => $expenseRows],
                ]);
                ExportHelper::pdf($filename . '.pdf', [
                    'title' => 'تقرير المصروفات', 'period' => $period,
                    'headers' => ['البيان', 'القيمة', ''], 'rows' => $pdfRows, 'totals' => [],
                    'orientation' => 'A4-L',
                ]);
            } else {
                ExportHelper::csv($filename . '.csv', ['التصنيف', 'عدد العمليات', 'المبلغ'], $expenseRows);
            }
        }
    }

    private function buildPdfSections(array $sections, int $maxCols = 3): array
    {
        $rows = [];
        foreach ($sections as $title => $section) {
            $rows[] = array_pad(['=== ' . $title . ' ==='], $maxCols, '');
            foreach ($section['rows'] as $r) {
                $rows[] = array_pad(array_values($r), $maxCols, '');
            }
        }
        return $rows;
    }

    public function sales(): void
    {
        [$from, $to] = $this->range();
        $summary = Database::query(
            "SELECT COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS total_sales,
                    COALESCE(AVG(total),0) AS avg_order
             FROM orders WHERE status <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?",
            [$from, $to]
        )->fetch();
        $byType = Database::query(
            "SELECT order_type, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total
             FROM orders WHERE status <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY order_type",
            [$from, $to]
        )->fetchAll();
        $topItems = Database::query(
            "SELECT mi.name, SUM(oi.qty) AS qty, SUM(oi.qty * oi.unit_price) AS total
             FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status <> 'cancelled' AND DATE(o.created_at) BETWEEN ? AND ?
             GROUP BY mi.id ORDER BY qty DESC",
            [$from, $to]
        )->fetchAll();
        $daily = Database::query(
            "SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS total
             FROM orders WHERE status <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY DATE(created_at) ORDER BY d",
            [$from, $to]
        )->fetchAll();

        $orderItems = Database::query(
            "SELECT oi.menu_item_id, oi.qty, DATE(o.created_at) AS order_date
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status <> 'cancelled' AND DATE(o.created_at) BETWEEN ? AND ?",
            [$from, $to]
        )->fetchAll();
        $cost = 0;
        foreach ($orderItems as $r) {
            $cost += AccountingService::recipeCost((int)$r['menu_item_id'], (float)$r['qty'], $r['order_date']);
        }
        $profit = (float)$summary['total_sales'] - $cost;

        $format = (string)($_GET['format'] ?? '');
        if ($format === 'excel' || $format === 'pdf') {
            $typeLabels = ['dine_in'=>'صالة','takeaway'=>'سفري','delivery'=>'توصيل','online'=>'إلكتروني'];
            $summaryRows = [[
                $summary['orders_count'], number_format($summary['total_sales'], 0), number_format($cost, 0), number_format($profit, 0), number_format($summary['avg_order'], 0)
            ]];
            $byTypeRows = array_map(fn($t) => [($typeLabels[$t['order_type']] ?? $t['order_type']), $t['cnt'], number_format($t['total'], 0)], $byType);
            $topItemsRows = array_map(fn($i) => [$i['name'], (float)$i['qty'], number_format($i['total'], 0)], $topItems);
            $dailyRows = array_map(fn($d) => [$d['d'], number_format($d['total'], 0)], $daily);
            $filename = 'sales_report_' . $from . '_' . $to;
            if ($format === 'excel') {
                ExportHelper::excel($filename . '.xlsx', [
                    ['title' => 'ملخص', 'report_title' => 'تقرير المبيعات', 'period' => 'من ' . $from . ' إلى ' . $to,
                     'headers' => ['عدد الطلبات', 'إجمالي المبيعات', 'كلفة البضاعة (تاريخية)', 'مجمل الربح', 'متوسط قيمة الطلب'], 'rows' => $summaryRows, 'totals' => []],
                    ['title' => 'حسب النوع', 'headers' => ['النوع', 'عدد الطلبات', 'الإجمالي'], 'rows' => $byTypeRows, 'totals' => []],
                    ['title' => 'أفضل الأصناف', 'headers' => ['الصنف', 'الكمية', 'الإيراد'], 'rows' => $topItemsRows, 'totals' => []],
                    ['title' => 'مبيعات يومية', 'headers' => ['اليوم', 'الإجمالي'], 'rows' => $dailyRows, 'totals' => []],
                ]);
            }
            $pdfRows = array_merge(
                [['=== ' . "ملخص" . ' ===', '', '', '']],
                $summaryRows,
                [['=== ' . "حسب النوع" . ' ===', '', '', '']],
                $byTypeRows,
                [['=== ' . "أفضل الأصناف" . ' ===', '', '', '']],
                $topItemsRows,
                [['=== ' . "مبيعات يومية" . ' ===', '', '', '']],
                $dailyRows
            );
            ExportHelper::pdf($filename . '.pdf', [
                'title' => 'تقرير المبيعات', 'period' => 'من ' . $from . ' إلى ' . $to,
                'headers' => ['البيان', '', '', ''], 'rows' => $pdfRows, 'totals' => [],
                'orientation' => 'A4-L',
            ]);
        }

        $this->view('reports/sales', [
            'title' => 'تقرير المبيعات', 'from' => $from, 'to' => $to,
            'summary' => $summary, 'byType' => $byType, 'topItems' => $topItems, 'daily' => $daily,
            'cost' => $cost, 'profit' => $profit,
        ]);
    }

    public function inventory(): void
    {
        $rows = Database::query(
            "SELECT i.id, i.name, u.name AS unit_name, i.unit_id, i.cost_price, COALESCE(SUM(s.qty),0) AS qty
             FROM items i
             JOIN units u ON u.id = i.unit_id
             LEFT JOIN stock s ON s.item_id = i.id
             WHERE i.is_active=1 GROUP BY i.id ORDER BY value DESC"
        )->fetchAll();
        $valuation = [];
        foreach ($rows as $r) {
            $r['qty'] = UnitConversionService::fromBaseUnit((int) $r['unit_id'], (float) $r['qty']);
            $r['value'] = $r['qty'] * (float) $r['cost_price'];
            if ($r['qty'] > 0) {
                $valuation[] = $r;
            }
        }
        usort($valuation, fn($a, $b) => $b['value'] <=> $a['value']);
        $waste = Database::query(
            "SELECT w.*, i.name AS item_name FROM waste_log w
             JOIN items i ON i.id = w.item_id
             WHERE DATE_FORMAT(w.created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
             ORDER BY w.id DESC"
        )->fetchAll();
        $expiring = Database::query(
            "SELECT m.expiry_date, i.name, SUM(m.qty) AS qty
             FROM stock_movements m JOIN items i ON i.id = m.item_id
             WHERE m.expiry_date IS NOT NULL AND m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
               AND m.movement_type IN ('in','transfer_in')
             GROUP BY i.id, m.expiry_date ORDER BY m.expiry_date"
        )->fetchAll();

        $format = (string)($_GET['format'] ?? '');
        if ($format === 'excel' || $format === 'pdf') {
            $totalValue = array_sum(array_column($valuation, 'value'));
            $valuationRows = array_map(fn($v) => [$v['name'], $v['unit_name'], (float)$v['qty'], number_format($v['cost_price'], 0), number_format($v['value'], 0)], $valuation);
            $expiringRows = array_map(fn($x) => [$x['name'], (float)$x['qty'], $x['expiry_date']], $expiring);
            $wasteRows = array_map(fn($w) => [$w['item_name'], (float)$w['qty'], $w['reason'] ?? '-', date('m/d', strtotime($w['created_at']))], $waste);
            if ($format === 'excel') {
                ExportHelper::excel('inventory_report_' . date('Y-m-d') . '.xlsx', [
                    ['title' => 'تقييم المخزون', 'report_title' => 'تقرير المخزون', 'period' => date('Y-m-d'),
                     'headers' => ['المادة', 'الوحدة', 'الرصيد', 'سعر الكلفة', 'القيمة'], 'rows' => $valuationRows, 'totals' => ['إجمالي قيمة المخزون' => number_format($totalValue, 0)]],
                    ['title' => 'مواد قريبة الانتهاء', 'headers' => ['المادة', 'الكمية', 'تاريخ الانتهاء'], 'rows' => $expiringRows, 'totals' => []],
                    ['title' => 'سجل الهدر', 'headers' => ['المادة', 'الكمية', 'السبب', 'التاريخ'], 'rows' => $wasteRows, 'totals' => []],
                ]);
            }
            ExportHelper::pdf('inventory_report_' . date('Y-m-d') . '.pdf', [
                'title' => 'تقرير المخزون', 'period' => date('Y-m-d'),
                'headers' => ['المادة', 'الوحدة', 'الرصيد', 'سعر الكلفة', 'القيمة'], 'rows' => $valuationRows,
                'totals' => ['إجمالي قيمة المخزون' => number_format($totalValue, 0)],
            ]);
        }

        $this->view('reports/inventory', [
            'title' => 'تقرير المخزون', 'valuation' => $valuation,
            'waste' => $waste, 'expiring' => $expiring,
        ]);
    }

    public function profit(): void
    {
        [$from, $to] = $this->range();
        $sales = Database::query(
            "SELECT COALESCE(SUM(total),0) AS t FROM orders
             WHERE status <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]
        )->fetch();
        $orderItems = Database::query(
            "SELECT oi.menu_item_id, oi.qty, DATE(o.created_at) AS order_date
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status <> 'cancelled' AND DATE(o.created_at) BETWEEN ? AND ?",
            [$from, $to]
        )->fetchAll();
        $cogs = 0;
        foreach ($orderItems as $r) {
            $cogs += AccountingService::recipeCost((int)$r['menu_item_id'], (float)$r['qty'], $r['order_date']);
        }
        $expenses = Database::query(
            "SELECT COALESCE(SUM(amount),0) AS t FROM expenses
             WHERE status IN ('approved','paid') AND expense_date BETWEEN ? AND ?", [$from, $to]
        )->fetch();
        $payroll = Database::query(
            "SELECT COALESCE(SUM(net_salary),0) AS t FROM payrolls
             WHERE status='paid' AND period BETWEEN DATE_FORMAT(?, '%Y-%m') AND DATE_FORMAT(?, '%Y-%m')",
            [$from, $to]
        )->fetch();

        $sales = (float)$sales['t'];
        $cogs = (float)$cogs;
        $expenses = (float)$expenses['t'];
        $payroll = (float)$payroll['t'];
        $grossProfit = $sales - $cogs;
        $netProfit = $grossProfit - $expenses - $payroll;

        $format = (string)($_GET['format'] ?? '');
        if ($format === 'excel' || $format === 'pdf') {
            $rows = [
                ['إجمالي المبيعات', number_format($sales, 0)],
                ['كلفة البضاعة المباعة', '(' . number_format($cogs, 0) . ')'],
                ['مجمل الربح', number_format($grossProfit, 0)],
                ['المصروفات التشغيلية', '(' . number_format($expenses, 0) . ')'],
                ['الرواتب المدفوعة', '(' . number_format($payroll, 0) . ')'],
                ['صافي الربح', number_format($netProfit, 0)],
            ];
            if ($format === 'excel') {
                ExportHelper::excel('profit_loss_report_' . $from . '_' . $to . '.xlsx', [[
                    'title' => 'الأرباح', 'report_title' => 'تقرير الأرباح والخسائر', 'period' => 'من ' . $from . ' إلى ' . $to,
                    'headers' => ['البند', 'المبلغ'], 'rows' => $rows, 'totals' => [],
                ]]);
            }
            ExportHelper::pdf('profit_loss_report_' . $from . '_' . $to . '.pdf', [
                'title' => 'تقرير الأرباح والخسائر', 'period' => 'من ' . $from . ' إلى ' . $to,
                'headers' => ['البند', 'المبلغ'], 'rows' => $rows, 'totals' => [],
            ]);
        }

        $this->view('reports/profit', [
            'title' => 'تقرير الأرباح والخسائر', 'from' => $from, 'to' => $to,
            'sales' => $sales, 'cogs' => $cogs, 'expenses' => $expenses, 'payroll' => $payroll,
        ]);
    }

    public function hr(): void
    {
        $today = Database::query(
            "SELECT e.full_name, COALESCE(report_department.name, e.department) AS department,
                    MIN(CASE WHEN a.check_type='in' THEN a.checked_at END) AS first_in,
                    MAX(CASE WHEN a.check_type='out' THEN a.checked_at END) AS last_out
             FROM employees e
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             LEFT JOIN attendance a ON a.employee_id = e.id AND DATE(a.checked_at) = CURDATE()
             WHERE e.file_status='open' GROUP BY e.id ORDER BY e.full_name"
        )->fetchAll();
        $leavesThisMonth = Database::query(
            "SELECT l.*, e.full_name FROM leave_requests l
             JOIN employees e ON e.id = l.employee_id
             WHERE l.status='approved' AND DATE_FORMAT(l.date_from,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')"
        )->fetchAll();

        $format = (string)($_GET['format'] ?? '');
        if ($format === 'excel' || $format === 'pdf') {
            $todayRows = array_map(fn($t) => [
                $t['full_name'], $t['department'] ?? '-',
                $t['first_in'] ? date('H:i', strtotime($t['first_in'])) : '-',
                $t['last_out'] ? date('H:i', strtotime($t['last_out'])) : '-',
                $t['first_in'] ? 'حاضر' : 'غائب'
            ], $today);
            $leavesRows = array_map(fn($l) => [
                $l['full_name'], $l['date_from'], $l['date_to'], $l['reason'] ?? '-'
            ], $leavesThisMonth);
            if ($format === 'excel') {
                ExportHelper::excel('hr_report_' . date('Y-m-d') . '.xlsx', [
                    ['title' => 'حضور اليوم', 'report_title' => 'تقرير الموارد البشرية', 'period' => date('Y-m-d'),
                     'headers' => ['الموظف', 'القسم', 'أول حضور', 'آخر انصراف', 'الحالة'], 'rows' => $todayRows, 'totals' => []],
                    ['title' => 'إجازات الشهر', 'headers' => ['الموظف', 'من', 'إلى', 'السبب'], 'rows' => $leavesRows, 'totals' => []],
                ]);
            }
            ExportHelper::pdf('hr_report_' . date('Y-m-d') . '.pdf', [
                'title' => 'تقرير الموارد البشرية', 'period' => date('Y-m-d'),
                'headers' => ['الموظف', 'القسم', 'أول حضور', 'آخر انصراف', 'الحالة'], 'rows' => $todayRows,
                'totals' => [],
            ]);
        }

        $this->view('reports/hr', [
            'title' => 'تقرير الموارد البشرية', 'today' => $today, 'leavesThisMonth' => $leavesThisMonth,
        ]);
    }

    public function reservations(): void
    {
        [$from, $to] = $this->range();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $q = (string)($_GET['q'] ?? '');

        $data = [
            'summary' => ReservationReportService::summary($from, $to, $branchId ?: null, $q ?: null),
            'byBranch' => ReservationReportService::byBranch($from, $to),
            'byPeriod' => ReservationReportService::byPeriod($from, $to, $branchId ?: null),
            'byType' => ReservationReportService::byType($from, $to, $branchId ?: null),
            'occupancy' => ReservationReportService::occupancyByTable($from, $to, $branchId ?: null),
            'topCustomers' => ReservationReportService::topCustomers($from, $to, $branchId ?: null),
            'cancelled' => ReservationReportService::cancelled($from, $to, $branchId ?: null),
            'noShow' => ReservationReportService::noShow($from, $to, $branchId ?: null),
            'avgDuration' => ReservationReportService::avgOccupancyDuration($from, $to, $branchId ?: null),
            'peakHours' => ReservationReportService::peakHours($from, $to, $branchId ?: null),
            'callCenter' => ReservationReportService::callCenterPerformance($from, $to, $branchId ?: null),
            'reception' => ReservationReportService::receptionPerformance($from, $to, $branchId ?: null),
            'vip' => ReservationReportService::vipReport($from, $to, $branchId ?: null),
            'status' => ReservationReportService::status($from, $to, $branchId ?: null),
        ];

        $format = (string)($_GET['format'] ?? '');
        if ($format === 'excel' || $format === 'pdf') {
            self::exportReservations($format, $from, $to, $branchId, $q, $data);
            return;
        }

        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
        $this->view('reports/reservations', [
            'title' => 'تقرير الحجوزات', 'from' => $from, 'to' => $to, 'branch_id' => $branchId, 'q' => $q,
            'branches' => $branches, 'data' => $data,
        ]);
    }

    private static function exportReservations(string $format, string $from, string $to, int $branchId, string $q, array $data): void
    {
        $period = 'من ' . $from . ' إلى ' . $to;
        $statusLabels = ['pending'=>'بانتظار التأكيد','confirmed'=>'مؤكد','seated'=>'تم الجلوس','cancelled'=>'ملغي','no_show'=>'لم يحضر','waiting'=>'قائمة انتظار','rejected'=>'مرفوض','completed'=>'مكتمل'];
        $filename = 'reservations_report_' . $from . '_' . $to;

        $sheets = [];
        $sheets[] = [
            'title' => 'ملخص', 'report_title' => 'تقرير الحجوزات', 'period' => $period,
            'headers' => ['المؤشر', 'القيمة'],
            'rows' => [
                ['إجمالي الحجوزات', $data['summary']['total']],
                ['المؤكدة', $data['summary']['confirmed']],
                ['تم الجلوس', $data['summary']['seated']],
                ['الملغاة', $data['summary']['cancelled']],
                ['No Show', $data['summary']['no_show']],
                ['قائمة الانتظار', $data['summary']['waiting']],
                ['إجمالي الضيوف', $data['summary']['total_guests']],
                ['نسبة الإشغال %', $data['summary']['occupancy_rate'] . '%'],
                ['متوسط عدد الضيوف', $data['summary']['avg_party_size']],
            ],
            'totals' => [],
        ];
        $sheets[] = ['title' => 'حسب الفرع', 'headers' => ['الفرع', 'الحجوزات', 'الضيوف', 'تم الجلوس', 'الملغاة', 'No Show'], 'rows' => array_map(fn($r) => [$r['branch_name'] ?: 'بدون فرع', $r['total'], $r['guests'], $r['seated'], $r['cancelled'], $r['no_show']], $data['byBranch']), 'totals' => []];
        $sheets[] = ['title' => 'حسب الفترة', 'headers' => ['اليوم', 'الحجوزات', 'الضيوف', 'تم الجلوس'], 'rows' => array_map(fn($r) => [$r['day'], $r['total'], $r['guests'], $r['seated']], $data['byPeriod']), 'totals' => []];
        $sheets[] = ['title' => 'حسب النوع', 'headers' => ['النوع', 'الحجوزات', 'الضيوف', 'تم الجلوس', 'الملغاة'], 'rows' => array_map(fn($r) => [$r['type_name'], $r['total'], $r['guests'], $r['seated'], $r['cancelled']], $data['byType']), 'totals' => []];
        $sheets[] = ['title' => 'أكثر العملاء زيارة', 'headers' => ['العميل', 'الهاتف', 'عدد الزيارات', 'الضيوف', 'تم الجلوس'], 'rows' => array_map(fn($r) => [$r['customer_name'], $r['customer_phone'], $r['visits'], $r['guests'], $r['seated_visits']], $data['topCustomers']), 'totals' => []];
        $sheets[] = ['title' => 'الملغاة', 'headers' => ['الرقم', 'العميل', 'الهاتف', 'الضيوف', 'التاريخ', 'الفرع', 'السبب'], 'rows' => array_map(fn($r) => [$r['reservation_no'], $r['customer_name'], $r['customer_phone'], $r['guests'], $r['reserved_at'], $r['branch_name'] ?: '-', $r['cancellation_reason'] ?: '-'], $data['cancelled']), 'totals' => []];
        $sheets[] = ['title' => 'No Show', 'headers' => ['الرقم', 'العميل', 'الهاتف', 'الضيوف', 'التاريخ', 'الفرع'], 'rows' => array_map(fn($r) => [$r['reservation_no'], $r['customer_name'], $r['customer_phone'], $r['guests'], $r['reserved_at'], $r['branch_name'] ?: '-'], $data['noShow']), 'totals' => []];
        $sheets[] = ['title' => 'أكثر الأوقات ازدحاماً', 'headers' => ['الساعة', 'الحجوزات', 'الضيوف'], 'rows' => array_map(fn($r) => [$r['hour'] . ':00', $r['total'], $r['guests']], $data['peakHours']), 'totals' => []];
        $sheets[] = ['title' => 'Call Center', 'headers' => ['الموظف', 'الحجوزات', 'المؤكدة', 'تم الجلوس', 'الملغاة', 'No Show'], 'rows' => array_map(fn($r) => [$r['full_name'] ?: 'غير معروف', $r['total'], $r['confirmed'], $r['seated'], $r['cancelled'], $r['no_show']], $data['callCenter']), 'totals' => []];
        $sheets[] = ['title' => 'الاستقبال', 'headers' => ['الموظف', 'إجمالي العمليات', 'تسجيل دخول', 'تسجيل خروج', 'إضافة للانتظار'], 'rows' => array_map(fn($r) => [$r['full_name'] ?: 'غير معروف', $r['total'], $r['check_ins'], $r['check_outs'], $r['waitlisted']], $data['reception']), 'totals' => []];
        $sheets[] = ['title' => 'VIP', 'headers' => ['الرقم', 'العميل', 'الهاتف', 'الضيوف', 'التاريخ', 'الحالة', 'الفرع', 'الطاولة'], 'rows' => array_map(fn($r) => [$r['reservation_no'], $r['customer_name'], $r['customer_phone'], $r['guests'], $r['reserved_at'], $statusLabels[$r['status']] ?? $r['status'], $r['branch_name'] ?: '-', $r['table_name'] ?: '-'], $data['vip']), 'totals' => []];

        if ($format === 'excel') {
            ExportHelper::excel($filename . '.xlsx', $sheets);
        }

        $pdfRows = [];
        foreach ($sheets as $sheet) {
            $pdfRows[] = ['=== ' . $sheet['title'] . ' ===', ''];
            foreach ($sheet['rows'] as $row) {
                $pdfRows[] = array_slice(array_values($row), 0, 6);
            }
        }
        ExportHelper::pdf($filename . '.pdf', [
            'title' => 'تقرير الحجوزات', 'period' => $period,
            'headers' => ['البيان', 'القيمة'], 'rows' => $pdfRows, 'totals' => [],
            'orientation' => 'A4-L',
        ]);
    }

    public function procurementReports(): void
    {
        $this->view('reports/procurement_index', ['title' => 'تقارير المشتريات والمخزون']);
    }

    public function procurementReport(string $type): void
    {
        $f = ProcurementReportService::filters($_GET);
        $format = (string)($_GET['format'] ?? '');
        $method = 'report' . str_replace('_', '', ucwords($type, '_'));
        $method = lcfirst(str_replace(' ', '', $method));
        // normalize method names
        $map = [
            'material_requests' => 'materialRequests', 'purchase_requests' => 'purchaseRequests',
            'transfer_requests' => 'transferRequests', 'new_items' => 'newItems',
            'top_requested' => 'topRequestedItems', 'cycle_time' => 'requestCycleTime',
            'purchase_orders' => 'purchaseOrders', 'suppliers' => 'suppliers',
            'variances' => 'receiptVariances', 'unavailable' => 'unavailableItems',
            'stagnant' => 'stagnantItems', 'consumption' => 'departmentConsumption',
            'costs' => 'purchaseCosts', 'price_analysis' => 'priceAnalysis',
            'production' => 'production', 'transfers' => 'transfers',
            'receipts' => 'receipts', 'approvals' => 'approvals',
        ];
        $method = $map[$type] ?? $method;
        if (!method_exists(ProcurementReportService::class, $method)) {
            $this->flash('danger', 'التقرير غير موجود');
            Response::redirect('/reports/procurement');
        }
        $rows = ProcurementReportService::$method($f);
        $headers = self::reportHeaders($type);
        $excelRows = array_map(fn($r) => array_map(fn($h) => $r[$h['key']] ?? $r[$h['key']] ?? '', $headers), $rows);
        $plainHeaders = array_map(fn($h) => $h['label'], $headers);
        $plainRows = array_map(fn($r) => array_map(fn($h) => $this->formatCell($r, $h), $headers), $rows);
        $title = self::reportTitle($type);
        $filename = $type . '_report_' . $f['from'] . '_' . $f['to'];
        if ($format === 'excel') {
            ExportHelper::excel($filename . '.xlsx', [
                ['title' => 'البيانات', 'report_title' => $title, 'period' => 'من ' . $f['from'] . ' إلى ' . $f['to'],
                 'headers' => $plainHeaders, 'rows' => $plainRows, 'totals' => []],
            ]);
        }
        if ($format === 'pdf') {
            ExportHelper::pdf($filename . '.pdf', [
                'title' => $title, 'period' => 'من ' . $f['from'] . ' إلى ' . $f['to'],
                'headers' => $plainHeaders, 'rows' => $plainRows, 'totals' => [],
            ]);
        }
        $this->view('reports/procurement_report', [
            'title' => $title,
            'type' => $type,
            'filters' => $f,
            'headers' => $headers,
            'rows' => $rows,
            'branches' => Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll(),
            'departments' => Database::query("SELECT id, name FROM departments ORDER BY name")->fetchAll(),
            'suppliers' => Database::query("SELECT id, name FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll(),
            'items' => Database::query("SELECT id, name FROM items WHERE is_active=1 ORDER BY name")->fetchAll(),
        ]);
    }

    private function formatCell(array $row, array $h): string
    {
        $value = $row[$h['key']] ?? '';
        if (($h['format'] ?? '') === 'money') return Helpers::money($value);
        if (($h['format'] ?? '') === 'number') return number_format((float)$value, 2);
        if (($h['format'] ?? '') === 'datetime') return $value ? date('Y-m-d H:i', strtotime($value)) : '-';
        if (($h['format'] ?? '') === 'date') return $value ? date('Y-m-d', strtotime($value)) : '-';
        return (string)$value;
    }

    private static function reportTitle(string $type): string
    {
        $titles = [
            'material_requests' => 'تقرير طلبات المواد', 'purchase_requests' => 'تقرير طلبات الشراء',
            'transfer_requests' => 'تقرير طلبات المناقلة', 'new_items' => 'تقرير المواد الجديدة',
            'top_requested' => 'تقرير المواد الأكثر طلباً', 'cycle_time' => 'تقرير زمن دورة الطلب',
            'purchase_orders' => 'تقرير أوامر الشراء', 'suppliers' => 'تقرير الموردين',
            'variances' => 'تقرير الفروقات في الاستلام', 'unavailable' => 'تقرير المواد غير المتوفرة',
            'stagnant' => 'تقرير المواد الراكدة', 'consumption' => 'تقرير استهلاك الأقسام',
            'costs' => 'تقرير تكاليف المشتريات', 'price_analysis' => 'تحليل أسعار الشراء',
            'production' => 'تقرير التصنيع', 'transfers' => 'تقرير المناقلات',
            'receipts' => 'تقرير الاستلام', 'approvals' => 'تقرير الموافقات',
        ];
        return $titles[$type] ?? 'تقرير';
    }

    private static function reportHeaders(string $type): array
    {
        $headers = [
            'material_requests' => [
                ['key' => 'request_no', 'label' => 'الرقم'], ['key' => 'requester_name', 'label' => 'الطالب'],
                ['key' => 'department_name', 'label' => 'القسم'], ['key' => 'branch_name', 'label' => 'الفرع'],
                ['key' => 'status', 'label' => 'الحالة'], ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
                ['key' => 'cycle_hours', 'label' => 'ساعات الدورة'],
            ],
            'purchase_requests' => [
                ['key' => 'request_no', 'label' => 'الرقم'], ['key' => 'mr_no', 'label' => 'طلب المواد'],
                ['key' => 'requester_name', 'label' => 'الطالب'], ['key' => 'status', 'label' => 'الحالة'],
                ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
            ],
            'transfer_requests' => [
                ['key' => 'transfer_no', 'label' => 'الرقم'], ['key' => 'mr_no', 'label' => 'طلب المواد'],
                ['key' => 'from_warehouse', 'label' => 'من'], ['key' => 'to_warehouse', 'label' => 'إلى'],
                ['key' => 'status', 'label' => 'الحالة'], ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
            ],
            'new_items' => [
                ['key' => 'name', 'label' => 'المادة'], ['key' => 'category_name', 'label' => 'التصنيف'],
                ['key' => 'unit_name', 'label' => 'الوحدة'], ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
            ],
            'top_requested' => [
                ['key' => 'item_name', 'label' => 'المادة'], ['key' => 'total_qty', 'label' => 'الكمية', 'format' => 'number'],
                ['key' => 'request_count', 'label' => 'عدد الطلبات'],
            ],
            'cycle_time' => [
                ['key' => 'request_no', 'label' => 'الرقم'], ['key' => 'status', 'label' => 'الحالة'],
                ['key' => 'created_at', 'label' => 'الإنشاء', 'format' => 'datetime'],
                ['key' => 'approved_at', 'label' => 'الموافقة', 'format' => 'datetime'],
                ['key' => 'approval_hours', 'label' => 'ساعات الموافقة'], ['key' => 'receipt_hours', 'label' => 'ساعات الاستلام'],
            ],
            'purchase_orders' => [
                ['key' => 'po_no', 'label' => 'الرقم'], ['key' => 'supplier_name', 'label' => 'المورد'],
                ['key' => 'mr_no', 'label' => 'طلب المواد'], ['key' => 'total', 'label' => 'الإجمالي', 'format' => 'money'],
                ['key' => 'status', 'label' => 'الحالة'], ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
            ],
            'suppliers' => [
                ['key' => 'name', 'label' => 'المورد'], ['key' => 'po_count', 'label' => 'عدد أوامر الشراء'],
                ['key' => 'total_amount', 'label' => 'إجمالي المشتريات', 'format' => 'money'],
            ],
            'variances' => [
                ['key' => 'receipt_no', 'label' => 'سند الاستلام'], ['key' => 'po_no', 'label' => 'أمر الشراء'],
                ['key' => 'item_name', 'label' => 'المادة'], ['key' => 'expected_qty', 'label' => 'المتوقع', 'format' => 'number'],
                ['key' => 'actual_qty', 'label' => 'الفعلي', 'format' => 'number'], ['key' => 'variance_qty', 'label' => 'الناقص', 'format' => 'number'],
                ['key' => 'damaged_qty', 'label' => 'التالف', 'format' => 'number'], ['key' => 'damage_reason', 'label' => 'السبب'],
            ],
            'unavailable' => [
                ['key' => 'name', 'label' => 'المادة'], ['key' => 'stock_qty', 'label' => 'الرصيد', 'format' => 'number'],
            ],
            'stagnant' => [
                ['key' => 'name', 'label' => 'المادة'], ['key' => 'stock_qty', 'label' => 'الرصيد', 'format' => 'number'],
                ['key' => 'last_movement', 'label' => 'آخر حركة', 'format' => 'datetime'],
            ],
            'consumption' => [
                ['key' => 'department_name', 'label' => 'القسم'], ['key' => 'item_name', 'label' => 'المادة'],
                ['key' => 'total_qty', 'label' => 'الكمية', 'format' => 'number'], ['key' => 'request_count', 'label' => 'عدد الطلبات'],
            ],
            'costs' => [
                ['key' => 'po_no', 'label' => 'أمر الشراء'], ['key' => 'supplier_name', 'label' => 'المورد'],
                ['key' => 'line_total', 'label' => 'المجموع', 'format' => 'money'], ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
            ],
            'price_analysis' => [
                ['key' => 'item_name', 'label' => 'المادة'], ['key' => 'supplier_name', 'label' => 'المورد'],
                ['key' => 'avg_price', 'label' => 'متوسط السعر', 'format' => 'money'], ['key' => 'min_price', 'label' => 'أقل سعر', 'format' => 'money'],
                ['key' => 'max_price', 'label' => 'أعلى سعر', 'format' => 'money'], ['key' => 'po_count', 'label' => 'عدد الطلبات'],
            ],
            'production' => [
                ['key' => 'order_no', 'label' => 'الرقم'], ['key' => 'item_name', 'label' => 'المنتج'],
                ['key' => 'qty', 'label' => 'الكمية', 'format' => 'number'], ['key' => 'status', 'label' => 'الحالة'],
                ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
            ],
            'transfers' => [
                ['key' => 'transfer_no', 'label' => 'الرقم'], ['key' => 'mr_no', 'label' => 'طلب المواد'],
                ['key' => 'from_warehouse', 'label' => 'من'], ['key' => 'to_warehouse', 'label' => 'إلى'],
                ['key' => 'status', 'label' => 'الحالة'], ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
            ],
            'receipts' => [
                ['key' => 'receipt_no', 'label' => 'الرقم'], ['key' => 'po_no', 'label' => 'أمر الشراء'],
                ['key' => 'warehouse_name', 'label' => 'المخزن'], ['key' => 'item_name', 'label' => 'المادة'],
                ['key' => 'expected_qty', 'label' => 'المتوقع', 'format' => 'number'], ['key' => 'actual_qty', 'label' => 'الفعلي', 'format' => 'number'],
                ['key' => 'accepted_qty', 'label' => 'المقبول', 'format' => 'number'], ['key' => 'created_at', 'label' => 'التاريخ', 'format' => 'datetime'],
            ],
            'approvals' => [
                ['key' => 'id', 'label' => 'الرقم'], ['key' => 'entity_type', 'label' => 'النوع'],
                ['key' => 'workflow_name', 'label' => 'مسار الموافقة'], ['key' => 'requester_name', 'label' => 'الطالب'],
                ['key' => 'status', 'label' => 'الحالة'], ['key' => 'created_at', 'label' => 'الإنشاء', 'format' => 'datetime'],
                ['key' => 'hours', 'label' => 'ساعات'],
            ],
        ];
        return $headers[$type] ?? [];
    }
}
