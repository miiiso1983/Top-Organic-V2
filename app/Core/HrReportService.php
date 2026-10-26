<?php
namespace App\Core;

class HrReportService
{
    private static function buildFilter(array $filters, ?string $statusCol = null, string $statusTable = 'e'): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['employee_id'])) {
            $where[] = 'e.id = ?';
            $params[] = (int)$filters['employee_id'];
        }
        if (!empty($filters['branch_id'])) {
            $where[] = 'e.branch_id = ?';
            $params[] = (int)$filters['branch_id'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 'e.department_id = ?';
            $params[] = (int)$filters['department_id'];
        }
        if (!empty($filters['status']) && $statusCol) {
            $where[] = "$statusTable.$statusCol = ?";
            $params[] = $filters['status'];
        }
        return [implode(' AND ', $where), $params];
    }

    private static function dateRange(string $dateCol, string $from, string $to, string &$where, array &$params): void
    {
        if ($from) {
            if ($where === '1=1') {
                $where = "$dateCol >= ?";
            } else {
                $where .= " AND $dateCol >= ?";
            }
            $params[] = $from . ' 00:00:00';
        }
        if ($to) {
            if ($where === '1=1') {
                $where = "$dateCol <= ?";
            } else {
                $where .= " AND $dateCol <= ?";
            }
            $params[] = $to . ' 23:59:59';
        }
    }

    /**
     * حل نطاق التاريخ الفعلي للتقرير بمبدأ «أولوية الفترة»:
     * إذا حدّد المستخدم فترة صالحة (YYYY-MM) تُشتق منها حدود الشهر (أول يوم → آخر يوم)
     * وتُتجاهل حقلا «من تاريخ/إلى تاريخ» اليدويان؛ وإلا تُستخدم التواريخ اليدوية كما هي.
     *
     * @return array{0:string,1:string} [from, to] بصيغة Y-m-d أو سلاسل فارغة
     */
    private static function resolveDateRange(array $filters): array
    {
        $period = trim((string)($filters['period'] ?? ''));
        if ($period !== '' && preg_match('/^\d{4}-\d{2}$/', $period)) {
            $from = $period . '-01';
            return [$from, date('Y-m-t', strtotime($from))];
        }
        return [
            trim((string)($filters['date_from'] ?? '')),
            trim((string)($filters['date_to'] ?? '')),
        ];
    }

    private static function applyPeriodDateRange(string $dateCol, array $filters, string &$where, array &$params): void
    {
        [$from, $to] = self::resolveDateRange($filters);
        self::dateRange($dateCol, $from, $to, $where, $params);
    }

    public static function getReport(string $type, array $filters): array
    {
        switch ($type) {
            case 'payroll': return self::payroll($filters);
            case 'bonuses': return self::adjustments('bonus', $filters);
            case 'deductions': return self::adjustments('deduction', $filters);
            case 'raises': return self::adjustments('raise', $filters);
            case 'penalties': return self::penalties($filters);
            case 'advances': return self::advances($filters);
            case 'long_advances': return self::longAdvances($filters);
            case 'attendance': return self::attendance($filters);
            case 'attendance_matrix': return self::attendanceMatrix($filters);
            case 'delays': return self::delays($filters);
            case 'absences': return self::absences($filters);
            case 'overtime': return self::overtime($filters);
            case 'leaves': return self::leaves($filters);
            case 'exits': return self::exits($filters);
            case 'evaluations': return self::evaluations($filters);
            case 'location': return self::location($filters);
            case 'monthly_performance': return self::monthlyPerformance($filters);
            case 'achievement': return self::achievement($filters);
            case 'regularity': return self::regularity($filters);
            case 'payroll_summary': return self::payrollSummary($filters);
            case 'work_resumptions': return self::workResumptions($filters);
            case 'resignations': return self::resignations($filters);
            case 'fingerprint_forgiveness': return self::fingerprintForgiveness($filters);
            case 'custom': return self::custom($filters);
            default: return self::payroll($filters);
        }
    }

    /** Calendar-day presence based solely on stored clock-ins, including employees without punches. */
    public static function attendanceMatrix(array $filters): array
    {
        $report = ['title'=>'تقرير الحضور اليومي حسب الموظف', 'columns'=>['full_name','department'],
            'headers'=>['اسم الموظف','القسم'], 'rows'=>[], 'dates'=>[]];
        $zone = new \DateTimeZone('Asia/Baghdad');
        $today = new \DateTimeImmutable('today', $zone);
        $parse = static function(string $value) use ($zone): ?\DateTimeImmutable {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
            return $date && $date->format('Y-m-d') === $value ? $date : null;
        };
        $from = trim((string)($filters['date_from'] ?? ''));
        $to = trim((string)($filters['date_to'] ?? ''));
        if ($from !== '' || $to !== '') {
            $from = $from ?: $to;
            $to = $to ?: $from;
        } else {
            $period = trim((string)($filters['period'] ?? '')) ?: $today->format('Y-m');
            $first = $parse($period.'-01');
            if (!$first) return $report + ['error'=>'الفترة غير صالحة. استخدم YYYY-MM أو حدد من وإلى تاريخ.'];
            $from = $first->format('Y-m-d');
            $to = ($period === $today->format('Y-m') ? $today : $first->modify('last day of this month'))->format('Y-m-d');
        }
        $start = $parse($from); $end = $parse($to);
        if (!$start || !$end || $start > $end) {
            return $report + ['error'=>'حدد نطاق تاريخ صحيحًا؛ تاريخ البداية يجب ألا يكون بعد تاريخ النهاية.'];
        }
        // Excel supports 16,384 columns; reject explicitly rather than silently truncating dates.
        if ((int)$start->diff($end)->days + 1 > 16382) {
            return $report + ['error'=>'عدد التواريخ يتجاوز سعة أعمدة Excel. اختر نطاقًا أقصر.'];
        }
        $report['filters'] = ['period'=>'','date_from'=>$from,'date_to'=>$to];
        $report['period_label'] = $from.' — '.$to;
        for ($day=$start; $day <= $end; $day=$day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $report['dates'][] = $date;
            $report['columns'][] = 'day_'.$date;
            $report['headers'][] = $date;
        }
        [$where,$params] = self::buildFilter($filters, 'status');
        $employees = Database::query("SELECT e.id,e.full_name,COALESCE(d.name,e.department,'—') AS department
            FROM employees e LEFT JOIN departments d ON d.id=e.department_id
            WHERE $where ORDER BY e.full_name,e.id", $params)->fetchAll();
        $punches = Database::query("SELECT a.employee_id,DATE(a.checked_at) AS attendance_day
            FROM attendance a JOIN employees e ON e.id=a.employee_id
            WHERE $where AND a.check_type='in' AND a.checked_at>=? AND a.checked_at<?
            GROUP BY a.employee_id,DATE(a.checked_at)",
            [...$params,$from.' 00:00:00',$end->modify('+1 day')->format('Y-m-d').' 00:00:00'])->fetchAll();
        $present = [];
        foreach ($punches as $punch) $present[(int)$punch['employee_id']][$punch['attendance_day']] = true;
        foreach ($employees as $employee) {
            $row = ['full_name'=>$employee['full_name'],'department'=>$employee['department']];
            foreach ($report['dates'] as $date) {
                $row['day_'.$date] = isset($present[(int)$employee['id']][$date]) ? 'حاضر'
                    : ($date > $today->format('Y-m-d') ? 'لم يأتِ بعد' : 'غائب');
            }
            $report['rows'][] = $row;
        }
        return $report;
    }

    public static function payroll(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);
        if (!empty($filters['period'])) {
            $w .= ' AND p.period = ?';
            $p[] = $filters['period'];
        }
        $rows = Database::query(
            "SELECT p.*, e.full_name, COALESCE(report_department.name, e.department) AS department, e.emp_code
             FROM payrolls p JOIN employees e ON e.id = p.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w ORDER BY p.period DESC, e.full_name",
            $p
        )->fetchAll();
        return ['title' => 'تقرير الرواتب', 'columns' => ['emp_code','full_name','department','period','base_salary','allowances','overtime','raises','bonuses','travel_tickets','deductions','advances','security_deposit','loadings','tips','social_security','rounding_adjustment','tax_amount','net_salary','status'], 'headers' => ['الكود','الموظف','القسم','الفترة','أساسي','بدلات','إضافي','زيادات','مكافآت','تذاكر سفر','خصومات','سلف','تأمين المستحقات','تحميلات','تيبس','ضمان اجتماعي','تقريب','ضريبة','صافي','حالة'], 'rows' => $rows, 'money' => ['base_salary','allowances','overtime','raises','bonuses','travel_tickets','deductions','advances','security_deposit','loadings','tips','social_security','rounding_adjustment','tax_amount','net_salary']];
    }

    /** ملخص شهري للرواتب حسب القسم */
    public static function payrollSummary(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);
        if (!empty($filters['period'])) {
            $w .= ' AND p.period = ?';
            $p[] = $filters['period'];
        }
        $rows = Database::query(
            "SELECT COALESCE(report_department.name, e.department) AS department,
                    COUNT(*) AS employee_count,
                    SUM(p.base_salary) AS total_base_salary,
                    SUM(p.allowances) AS total_allowances,
                    SUM(p.overtime) AS total_overtime,
                    SUM(p.raises) AS total_raises,
                    SUM(p.bonuses) AS total_bonuses,
                    SUM(p.travel_tickets) AS total_travel_tickets,
                    SUM(p.deductions) AS total_deductions,
                    SUM(p.advances) AS total_advances,
                    SUM(p.security_deposit) AS total_security_deposit,
                    SUM(p.loadings) AS total_loadings,
                    SUM(p.tips) AS total_tips,
                    SUM(p.social_security) AS total_social_security,
                    SUM(p.tax_amount) AS total_tax,
                    SUM(p.net_salary) AS total_net_salary,
                    SUM(p.rounding_adjustment) AS total_rounding
             FROM payrolls p
             JOIN employees e ON e.id = p.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w
             GROUP BY COALESCE(report_department.name, e.department)
             ORDER BY COALESCE(report_department.name, e.department)",
            $p
        )->fetchAll();
        return [
            'title' => 'ملخص الرواتب الشهري',
            'columns' => [
                'department','employee_count','total_base_salary','total_allowances','total_overtime','total_raises','total_bonuses','total_travel_tickets',
                'total_deductions','total_advances','total_security_deposit','total_insurance','total_loadings','total_tips','total_social_security','total_tax','total_net_salary','total_rounding'
            ],
            'headers' => [
                'القسم','عدد الموظفين','الأساسي','البدلات','الإضافي','الزيادات','المكافآت','تذاكر سفر','الخصومات','السلف','تأمين المستحقات','التحميلات','التيبس','الضمان الاجتماعي','الضريبة','الصافي','التقريب'
            ],
            'rows' => $rows,
            'money' => [
                'total_base_salary','total_allowances','total_overtime','total_raises','total_bonuses','total_travel_tickets',
                'total_deductions','total_advances','total_security_deposit','total_loadings','total_tips','total_social_security','total_tax','total_net_salary','total_rounding'
            ],
        ];
    }


    private static function adjustments(string $adjType, array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);
        self::applyPeriodDateRange('a.effective_date', $filters, $w, $p);
        $w .= ' AND a.adjustment_type = ? AND a.status = "approved"';
        $p[] = $adjType;
        $rows = Database::query(
            "SELECT a.*, e.full_name, COALESCE(report_department.name, e.department) AS department, e.emp_code
             FROM hr_adjustments a JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w ORDER BY a.effective_date DESC",
            $p
        )->fetchAll();
        $titles = ['bonus'=>'تقرير المكافآت','deduction'=>'تقرير الخصومات','raise'=>'تقرير الزيادات','penalty'=>'تقرير الجزاءات'];
        return ['title' => $titles[$adjType], 'columns' => ['emp_code','full_name','department','amount','effective_date','period','reason','status'], 'headers' => ['الكود','الموظف','القسم','المبلغ','التاريخ','الفترة','السبب','الحالة'], 'rows' => $rows, 'money' => ['amount']];
    }

    /** تقرير الجزاءات المخصص: كل تسويات الجزاء (penalty) بجميع حالاتها مع تفاصيل الموظف والأيام والمبلغ والسبب */
    private static function penalties(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);
        self::applyPeriodDateRange('a.effective_date', $filters, $w, $p);
        $w .= " AND a.adjustment_type = 'penalty'";
        $rows = Database::query(
            "SELECT a.*, e.full_name, COALESCE(report_department.name, e.department) AS department, e.emp_code, j.title AS job_title
             FROM hr_adjustments a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             LEFT JOIN jobs j ON j.id = e.job_id
             WHERE $w
             ORDER BY a.effective_date DESC, a.id DESC",
            $p
        )->fetchAll();
        return [
            'title' => 'تقرير الجزاءات',
            'columns' => ['emp_code','full_name','department','job_title','days','amount','effective_date','period','reason','status'],
            'headers' => ['الكود','الموظف','القسم','الوظيفة','عدد الأيام','المبلغ','التاريخ','الفترة','السبب','الحالة'],
            'rows' => $rows,
            'money' => ['amount'],
        ];
    }

    public static function advances(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters, 'status', 'sa');
        self::applyPeriodDateRange('sa.created_at', $filters, $w, $p);
        if (isset($filters['is_long_term'])) {
            $w .= ' AND sa.is_long_term = ?';
            $p[] = (int)$filters['is_long_term'];
        }
        $rows = Database::query(
            "SELECT sa.*, e.full_name, COALESCE(report_department.name, e.department) AS department, e.emp_code
             FROM salary_advances sa JOIN employees e ON e.id = sa.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w ORDER BY sa.created_at DESC",
            $p
        )->fetchAll();
        return ['title' => 'تقرير السلف', 'columns' => ['emp_code','full_name','department','amount','advance_type','installments','paid_amount','remaining_amount','status','created_at'], 'headers' => ['الكود','الموظف','القسم','المبلغ','النوع','الأقساط','المسدد','المتبقي','الحالة','التاريخ'], 'rows' => $rows, 'money' => ['amount','paid_amount','remaining_amount']];
    }

    public static function longAdvances(array $filters): array
    {
        $filters['is_long_term'] = 1;
        return self::advances($filters);
    }

    public static function attendance(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);

        [$from, $to] = self::resolveDateRange($filters);
        $rangeFrom = $from ? date('Y-m-d', strtotime($from . ' -1 day')) : '';
        $rangeTo = $to ? date('Y-m-d', strtotime($to . ' +1 day')) : '';

        $raw = Database::query(
            "SELECT e.emp_code, e.full_name, COALESCE(report_department.name, e.department) AS department, a.employee_id, a.id,
                    a.check_type, a.checked_at, a.source
             FROM employees e
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             JOIN attendance a ON a.employee_id = e.id
             WHERE $w" . ($rangeFrom && $rangeTo ? " AND DATE(a.checked_at) BETWEEN ? AND ?" : "") . "
             ORDER BY e.full_name ASC, a.checked_at ASC, a.id ASC",
            array_merge($p, ($rangeFrom && $rangeTo ? [$rangeFrom, $rangeTo] : []))
        )->fetchAll();

        $employeeIds = array_unique(array_map(fn ($r) => (int)$r['employee_id'], $raw));
        $shiftMap = HrService::loadShiftMap($employeeIds, $rangeFrom, $rangeTo);
        $groups = HrService::pairAndGroupAttendance($raw, $shiftMap, null, $from, $to);

        $rows = [];
        foreach ($groups as $g) {
            $inLines = [];
            $outLines = [];
            foreach ($g['pairs'] as $pair) {
                $inLines[] = $pair['in'] === '-' ? 'غير مسجل' : $pair['in'];
                $outLines[] = $pair['out'] === '-' ? 'غير مسجل' : $pair['out'];
            }
            if (!$inLines) {
                $inLines[] = 'غير مسجل';
                $outLines[] = 'غير مسجل';
            }
            $rows[] = [
                'emp_code' => $g['emp_code'],
                'full_name' => $g['full_name'],
                'department' => $g['department'],
                'work_date' => $g['work_date'] ? date('Y-m-d', strtotime($g['work_date'])) : '-',
                'check_in' => implode("\n", $inLines),
                'check_out' => implode("\n", $outLines),
                'total_hours' => $g['total_hours'],
            ];
        }

        return [
            'title' => 'تقرير الحضور والانصراف التفصيلي',
            'columns' => ['emp_code','full_name','department','work_date','check_in','check_out','total_hours'],
            'headers' => ['الكود','الموظف','القسم','التاريخ','وقت الدخول','وقت الخروج','إجمالي وقت الدوام'],
            'rows' => $rows,
        ];
    }

    /** تقرير الأداء الشهري للمندوبين/الموظفين */
    public static function monthlyPerformance(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);

        $period = !empty($filters['period']) && preg_match('/^\d{4}-\d{2}$/', $filters['period'])
            ? $filters['period']
            : date('Y-m');
        $periodStart = $period . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $totalDays = (int)date('t', strtotime($periodStart));

        $employees = Database::query(
            "SELECT e.id, e.emp_code, e.full_name, COALESCE(report_department.name, e.department) AS department, e.shift_id, e.attendance_type, e.required_hours
             FROM employees e
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w ORDER BY e.full_name",
            $p
        )->fetchAll();

        $shiftWorkDays = [];
        $rows = Database::query("SELECT shift_id, day_of_week FROM shift_work_days")->fetchAll();
        foreach ($rows as $r) {
            $shiftWorkDays[(int)$r['shift_id']][(int)$r['day_of_week']] = true;
        }

        // ملخص الحضور والساعات الفعلية للفترة (النظام الهجين)
        $attRangeFrom = date('Y-m-d', strtotime($periodStart . ' -1 day'));
        $attRangeTo = date('Y-m-d', strtotime($periodEnd . ' +1 day'));
        $attRecords = Database::query(
            "SELECT e.emp_code, e.full_name, COALESCE(report_department.name, e.department) AS department, a.employee_id, a.id,
                    a.check_type, a.checked_at, a.source
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w AND DATE(a.checked_at) BETWEEN ? AND ?
             ORDER BY a.employee_id, a.checked_at",
            array_merge($p, [$attRangeFrom, $attRangeTo])
        )->fetchAll();
        $attEmployeeIds = array_unique(array_map(fn ($r) => (int)$r['employee_id'], $attRecords));
        $attShiftMap = HrService::loadShiftMap($attEmployeeIds, $attRangeFrom, $attRangeTo);
        $attGroups = HrService::pairAndGroupAttendance($attRecords, $attShiftMap, null, $periodStart, $periodEnd);
        $attMap = [];
        foreach ($attGroups as $g) {
            $eid = (int)$g['employee_id'];
            if (!isset($attMap[$eid])) {
                $attMap[$eid] = ['days_present' => 0, 'total_seconds' => 0];
            }
            $attMap[$eid]['days_present']++;
            $attMap[$eid]['total_seconds'] += (int)$g['total_seconds'];
        }

        // الإجازات المعتمدة خلال الفترة
        $leaveRows = Database::query(
            "SELECT employee_id, date_from, date_to FROM leave_requests
             WHERE status = 'approved' AND date_to >= ? AND date_from <= ?",
            [$periodStart, $periodEnd]
        )->fetchAll();
        $leaveMap = [];
        $ps = new \DateTime($periodStart);
        $pe = new \DateTime($periodEnd);
        foreach ($leaveRows as $r) {
            $df = new \DateTime($r['date_from']);
            $dt = new \DateTime($r['date_to']);
            $effStart = $df > $ps ? $df : $ps;
            $effEnd = $dt < $pe ? $dt : $pe;
            if ($effStart <= $effEnd) {
                $days = (int)$effStart->diff($effEnd)->days + 1;
                $eid = (int)$r['employee_id'];
                $leaveMap[$eid] = ($leaveMap[$eid] ?? 0) + $days;
            }
        }

        $rows = [];
        foreach ($employees as $e) {
            $eid = (int)$e['id'];
            $daysPresent = $attMap[$eid]['days_present'] ?? 0;
            $totalSeconds = $attMap[$eid]['total_seconds'] ?? 0;
            $leaveDays = $leaveMap[$eid] ?? 0;

            $shiftId = (int)($e['shift_id'] ?? 0);
            $workDays = $shiftWorkDays[$shiftId] ?? [0,1,2,3,4];
            $holidays = 0;
            $cur = new \DateTime($periodStart);
            $last = new \DateTime($periodEnd);
            while ($cur <= $last) {
                $dow = (int)$cur->format('w');
                if (!isset($workDays[$dow])) {
                    $holidays++;
                }
                $cur->modify('+1 day');
            }

            $absenceDays = $totalDays - $daysPresent - $holidays - $leaveDays;
            if ($absenceDays < 0) {
                $absenceDays = 0;
            }
            $hoursPerDay = ($e['attendance_type'] ?? 'shift') === 'flexible'
                ? (float)($e['required_hours'] ?? 8)
                : 8;
            $requiredHours = ($totalDays - $holidays) * $hoursPerDay;
            $h = (int)floor($totalSeconds / 3600);
            $m = (int)floor(($totalSeconds % 3600) / 60);
            $actualHours = sprintf('%02d:%02d', $h, $m);
            $achievement = $requiredHours > 0
                ? round(($totalSeconds / ($requiredHours * 3600)) * 100, 2)
                : 0;

            $rows[] = [
                'emp_code' => $e['emp_code'],
                'full_name' => $e['full_name'],
                'department' => $e['department'],
                'total_days' => $totalDays,
                'working_days' => $daysPresent,
                'holidays' => $holidays,
                'leave_days' => $leaveDays,
                'absence_days' => $absenceDays,
                'required_hours' => $requiredHours,
                'actual_hours' => $actualHours,
                'achievement' => $achievement . '%',
            ];
        }

        return [
            'title' => 'تقرير الأداء الشهري للمندوبين/الموظفين',
            'columns' => ['emp_code','full_name','department','total_days','working_days','holidays','leave_days','absence_days','required_hours','actual_hours','achievement'],
            'headers' => ['الكود','الموظف','القسم','إجمالي أيام الشهر','أيام الدوام','العطل','الإجازات','الغياب','الساعات المطلوبة','الساعات الفعلية','نسبة الإنجاز'],
            'rows' => $rows,
        ];
    }

    /** تقرير الإنجاز: يحسب الساعات المطلوبة من الورديات والإنجاز والراتب المستحق */
    public static function achievement(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);

        $period = !empty($filters['period']) && preg_match('/^\d{4}-\d{2}$/', $filters['period'])
            ? $filters['period']
            : date('Y-m');
        $periodStart = $period . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $totalDays = (int)date('t', strtotime($periodStart));

        $holidayData = self::getHolidayDataForPeriod($periodStart, $periodEnd);
        $holidayMap = array_flip($holidayData['dates']);

        $year = (int)substr($period, 0, 4);
        $month = (int)substr($period, 5, 2);
        $monthlyHoliday = Database::query(
            "SELECT days FROM hr_holiday_months WHERE year = ? AND month = ?",
            [$year, $month]
        )->fetch();
        $holidays = $monthlyHoliday ? (int)$monthlyHoliday['days'] : count($holidayData['dates']);

        $employees = Database::query(
            "SELECT e.id, e.emp_code, e.full_name, COALESCE(report_department.name, e.department) AS department, e.shift_id, e.base_salary, e.attendance_type, e.required_hours, a.code AS account_code
             FROM employees e
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             LEFT JOIN accounts a ON a.id = e.account_id
             WHERE $w ORDER BY e.full_name",
            $p
        )->fetchAll();

        // ملخص الحضور والساعات الفعلية للفترة (النظام الهجين)
        $attRangeFrom = date('Y-m-d', strtotime($periodStart . ' -1 day'));
        $attRangeTo = date('Y-m-d', strtotime($periodEnd . ' +1 day'));
        $attRecords = Database::query(
            "SELECT e.emp_code, e.full_name, COALESCE(report_department.name, e.department) AS department, a.employee_id, a.id,
                    a.check_type, a.checked_at, a.source
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w AND DATE(a.checked_at) BETWEEN ? AND ?
             ORDER BY a.employee_id, a.checked_at",
            array_merge($p, [$attRangeFrom, $attRangeTo])
        )->fetchAll();
        $attEmployeeIds = array_unique(array_map(fn ($r) => (int)$r['employee_id'], $attRecords));
        $attShiftMap = HrService::loadShiftMap($attEmployeeIds, $attRangeFrom, $attRangeTo);
        $attGroups = HrService::pairAndGroupAttendance($attRecords, $attShiftMap, null, $periodStart, $periodEnd);
        $attMap = [];
        foreach ($attGroups as $g) {
            $eid = (int)$g['employee_id'];
            if (!isset($attMap[$eid])) {
                $attMap[$eid] = ['days_present' => 0, 'total_seconds' => 0];
            }
            $attMap[$eid]['days_present']++;
            $attMap[$eid]['total_seconds'] += (int)$g['total_seconds'];
        }

        $leaveRows = Database::query(
            "SELECT employee_id, date_from, date_to FROM leave_requests
             WHERE status = 'approved' AND date_to >= ? AND date_from <= ?",
            [$periodStart, $periodEnd]
        )->fetchAll();
        $leaveMap = [];
        $ps = new \DateTime($periodStart);
        $pe = new \DateTime($periodEnd);
        foreach ($leaveRows as $r) {
            $df = new \DateTime($r['date_from']);
            $dt = new \DateTime($r['date_to']);
            $effStart = $df > $ps ? $df : $ps;
            $effEnd = $dt < $pe ? $dt : $pe;
            if ($effStart <= $effEnd) {
                $days = (int)$effStart->diff($effEnd)->days + 1;
                $eid = (int)$r['employee_id'];
                $leaveMap[$eid] = ($leaveMap[$eid] ?? 0) + $days;
            }
        }

        $otRows = Database::query(
            "SELECT employee_id, SUM(hours) AS total_hours
             FROM overtime_requests
             WHERE status = 'fully_approved' AND request_date >= ? AND request_date <= ?
             GROUP BY employee_id",
            [$periodStart, $periodEnd]
        )->fetchAll();
        $otMap = [];
        foreach ($otRows as $r) {
            $otMap[(int)$r['employee_id']] = (float)$r['total_hours'];
        }

        $shiftWorkDays = [];
        $rows = Database::query("SELECT shift_id, day_of_week FROM shift_work_days")->fetchAll();
        foreach ($rows as $r) {
            $shiftWorkDays[(int)$r['shift_id']][(int)$r['day_of_week']] = true;
        }

        $rows = [];
        foreach ($employees as $e) {
            $eid = (int)$e['id'];
            $daysPresent = $attMap[$eid]['days_present'] ?? 0;
            $totalSeconds = $attMap[$eid]['total_seconds'] ?? 0;
            $workingHours = round($totalSeconds / 3600, 2);
            $leaveDays = $leaveMap[$eid] ?? 0;
            $overtimeHours = $otMap[$eid] ?? 0.0;

            $shiftId = (int)($e['shift_id'] ?? 0);
            $workDays = $shiftWorkDays[$shiftId] ?? [0,1,2,3,4];
            $weeklyHolidays = 0;
            $cur = new \DateTime($periodStart);
            $last = new \DateTime($periodEnd);
            while ($cur <= $last) {
                $dow = (int)$cur->format('w');
                if (!isset($workDays[$dow])) {
                    $weeklyHolidays++;
                }
                $cur->modify('+1 day');
            }
            $employeeHolidays = $holidays + $weeklyHolidays;
            $officialHolidays = $holidays;

            if (($e['attendance_type'] ?? 'shift') === 'flexible') {
                $hoursPerDay = (float)($e['required_hours'] ?? 8);
            } else {
                $hoursPerDay = self::averageShiftHoursForEmployee($eid, $periodStart, $periodEnd, $holidayMap);
            }
            $requiredHours = round(($totalDays - $employeeHolidays) * $hoursPerDay, 2);
            $absenceHours = max(0, $requiredHours - $workingHours);

            $achievement = $requiredHours > 0
                ? round((($workingHours + $overtimeHours - $absenceHours) / $requiredHours) * 100, 2)
                : 0;
            $deservedSalary = max(0, round(($achievement / 100) * (float)$e['base_salary'], 2));

            $rows[] = [
                'emp_code' => $e['emp_code'],
                'account_code' => $e['account_code'] ?? '',
                'full_name' => $e['full_name'],
                'department' => $e['department'],
                'total_days' => $totalDays,
                'holidays' => $officialHolidays,
                'working_days' => $daysPresent,
                'leave_days' => $leaveDays,
                'working_hours' => $workingHours,
                'absence_hours' => $absenceHours,
                'overtime_hours' => $overtimeHours,
                'required_hours' => $requiredHours,
                'achievement' => $achievement . '%',
                'deserved_salary' => $deservedSalary,
            ];
        }

        return [
            'title' => 'تقرير الإنجاز',
            'columns' => ['emp_code','account_code','full_name','department','total_days','holidays','working_days','leave_days','working_hours','absence_hours','overtime_hours','required_hours','achievement','deserved_salary'],
            'headers' => ['الكود','رمز الحساب','الموظف','القسم','أيام الشهر','العطل','أيام الدوام','الإجازات','ساعات الدوام','ساعات الغياب','الإضافي المعتمد','الساعات المطلوبة','نسبة الإنجاز','الراتب المستحق'],
            'rows' => $rows,
            'money' => ['deserved_salary'],
        ];
    }

    /** جمع بيانات العطل من جدول hr_holidays لفترة ما */
    private static function getHolidayDataForPeriod(string $periodStart, string $periodEnd): array
    {
        $specific = Database::query(
            "SELECT date FROM hr_holidays WHERE is_active = 1 AND date IS NOT NULL AND date >= ? AND date <= ?",
            [$periodStart, $periodEnd]
        )->fetchAll(\PDO::FETCH_COLUMN, 0);
        $weekly = Database::query(
            "SELECT day_of_week FROM hr_holidays WHERE is_active = 1 AND day_of_week IS NOT NULL"
        )->fetchAll(\PDO::FETCH_COLUMN, 0);
        $weeklyMap = array_flip($weekly);

        $dates = [];
        $cur = new \DateTime($periodStart);
        $last = new \DateTime($periodEnd);
        while ($cur <= $last) {
            $date = $cur->format('Y-m-d');
            $dow = (int)$cur->format('w');
            if (in_array($date, $specific, true) || isset($weeklyMap[$dow])) {
                $dates[] = $date;
            }
            $cur->modify('+1 day');
        }
        return ['specific' => $specific, 'weekly' => $weekly, 'dates' => $dates];
    }

    private static function averageShiftHoursForEmployee(int $employeeId, string $periodStart, string $periodEnd, array $holidayMap): float
    {
        $cur = new \DateTime($periodStart);
        $last = new \DateTime($periodEnd);
        $totalShiftSeconds = 0;
        $workingDays = 0;
        while ($cur <= $last) {
            $date = $cur->format('Y-m-d');
            if (isset($holidayMap[$date])) {
                $cur->modify('+1 day');
                continue;
            }
            $shift = HrService::getEmployeeShift($employeeId, $date);
            if ($shift && !empty($shift['start_time']) && !empty($shift['end_time']) && !empty($shift['is_work_day'])) {
                $start = new \DateTime($date . ' ' . $shift['start_time']);
                $end = new \DateTime($date . ' ' . $shift['end_time']);
                if ($end <= $start) {
                    $end->modify('+1 day');
                }
                $totalShiftSeconds += $end->getTimestamp() - $start->getTimestamp();
                $workingDays++;
            }
            $cur->modify('+1 day');
        }
        if ($workingDays > 0) {
            return round(($totalShiftSeconds / $workingDays) / 3600, 2);
        }
        $employee = Database::query("SELECT shift_id FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!empty($employee['shift_id'])) {
            $shift = Database::query("SELECT start_time, end_time FROM shifts WHERE id = ?", [(int)$employee['shift_id']])->fetch();
            if ($shift && !empty($shift['start_time']) && !empty($shift['end_time'])) {
                $start = new \DateTime('2000-01-01 ' . $shift['start_time']);
                $end = new \DateTime('2000-01-01 ' . $shift['end_time']);
                if ($end <= $start) {
                    $end->modify('+1 day');
                }
                return round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 2);
            }
        }
        return 8.0;
    }

    public static function delays(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);

        [$from, $to] = self::resolveDateRange($filters);
        $from = $from !== '' ? $from : date('Y-m-d', strtotime('-30 days'));
        $to = $to !== '' ? $to : date('Y-m-d');
        $rangeFrom = date('Y-m-d', strtotime($from . ' -1 day'));
        $rangeTo = date('Y-m-d', strtotime($to . ' +1 day'));

        $records = Database::query(
            "SELECT e.emp_code, e.full_name, COALESCE(report_department.name, e.department) AS department, att.employee_id, att.id,
                    att.check_type, att.checked_at, att.source
             FROM attendance att JOIN employees e ON e.id = att.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w AND DATE(att.checked_at) BETWEEN ? AND ?
             ORDER BY att.employee_id, att.checked_at",
            array_merge($p, [$rangeFrom, $rangeTo])
        )->fetchAll();

        $employeeIds = array_unique(array_map(fn ($r) => (int)$r['employee_id'], $records));
        $shiftMap = HrService::loadShiftMap($employeeIds, $rangeFrom, $rangeTo);
        $groups = HrService::pairAndGroupAttendance($records, $shiftMap, null, $from, $to);

        $out = [];
        foreach ($groups as $g) {
            $eid = (int)$g['employee_id'];
            $shift = $shiftMap[$eid] ?? null;
            if (($shift['default']['attendance_type'] ?? 'shift') === 'flexible') {
                continue;
            }
            if (empty($g['first_in'])) {
                continue;
            }
            $workDate = $g['work_date'];
            $startTime = $shift['overrides'][$workDate]['start_time'] ?? $shift['default']['start_time'] ?? null;
            $start = new \DateTime($workDate . ' ' . ($startTime ?? '08:00'));
            $check = new \DateTime($g['first_in']);
            $delaySeconds = $check->getTimestamp() - $start->getTimestamp();
            if ($delaySeconds > 0) {
                $out[] = [
                    'emp_code' => $g['emp_code'],
                    'full_name' => $g['full_name'],
                    'department' => $g['department'],
                    'shift_name' => $shift['overrides'][$workDate]['shift_name'] ?? $shift['default']['shift_name'] ?? '-',
                    'work_date' => $workDate,
                    'delay_hours' => round($delaySeconds / 3600, 2),
                    'checked_at' => date('H:i', strtotime($g['first_in'])),
                    'checked_out_at' => !empty($g['last_out']) ? date('H:i', strtotime($g['last_out'])) : '-',
                    'source' => $g['first_in_source'] ?: '-',
                ];
            }
        }
        return ['title' => 'تقرير التأخير', 'columns' => ['emp_code','full_name','department','shift_name','work_date','delay_hours','checked_at','checked_out_at','source'], 'headers' => ['الكود','الموظف','القسم','الوردية','تاريخ العمل','ساعات التأخير','وقت الدخول','وقت الخروج','المصدر'], 'rows' => $out];
    }

    public static function absences(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);
        [$from, $to] = self::resolveDateRange($filters);
        $from = $from !== '' ? $from : date('Y-m-d', strtotime('-30 days'));
        $to = $to !== '' ? $to : date('Y-m-d');
        $params = array_merge([$from, $to], $p, [$from, $to]);
        $sql = "WITH RECURSIVE dates AS (
            SELECT ? AS dt
            UNION ALL
            SELECT dt + INTERVAL 1 DAY FROM dates WHERE dt < ?
        )
        SELECT e.emp_code, e.full_name, COALESCE(report_department.name, e.department) AS department, d.dt AS missing_date
        FROM employees e
             LEFT JOIN departments report_department ON report_department.id = e.department_id
        CROSS JOIN dates d
        LEFT JOIN attendance a ON a.employee_id = e.id AND DATE(a.checked_at) = d.dt AND a.check_type = 'in'
        WHERE $w AND e.file_status = 'open' AND d.dt BETWEEN ? AND ? AND a.id IS NULL
        ORDER BY e.full_name, d.dt";
        $rows = Database::query($sql, $params)->fetchAll();
        return ['title' => 'تقرير الغياب', 'columns' => ['emp_code','full_name','department','missing_date'], 'headers' => ['الكود','الموظف','القسم','تاريخ الغياب'], 'rows' => $rows];
    }

    public static function overtime(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters, 'status', 'o');
        self::applyPeriodDateRange('o.request_date', $filters, $w, $p);
        $rows = Database::query(
            "SELECT o.*, e.emp_code, e.full_name, COALESCE(report_department.name, e.department) AS department
             FROM overtime_requests o
             JOIN employees e ON e.id = o.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w ORDER BY o.request_date DESC, e.full_name",
            $p
        )->fetchAll();
        return ['title' => 'تقرير العمل الإضافي', 'columns' => ['emp_code','full_name','department','request_date','hours','status','biometric_match'], 'headers' => ['الكود','الموظف','القسم','التاريخ','الساعات','الحالة','مطابقة البصمة'], 'rows' => $rows];
    }

    public static function leaves(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);
        self::applyPeriodDateRange('lr.date_from', $filters, $w, $p);
        $w .= ' AND lr.request_type = "leave"';
        $rows = Database::query(
            "SELECT lr.*, e.full_name, COALESCE(report_department.name, e.department) AS department, e.emp_code
             FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w ORDER BY lr.date_from DESC",
            $p
        )->fetchAll();
        return ['title' => 'تقرير الإجازات', 'columns' => ['emp_code','full_name','department','leave_type','date_from','date_to','status','reason'], 'headers' => ['الكود','الموظف','القسم','النوع','من','إلى','الحالة','السبب'], 'rows' => $rows];
    }

    public static function exits(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);
        self::applyPeriodDateRange('lr.date_from', $filters, $w, $p);
        $w .= ' AND lr.request_type = "exit"';
        $rows = Database::query(
            "SELECT lr.*, e.full_name, COALESCE(report_department.name, e.department) AS department, e.emp_code
             FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w ORDER BY lr.date_from DESC",
            $p
        )->fetchAll();
        return ['title' => 'تقرير الأذونات', 'columns' => ['emp_code','full_name','department','date_from','date_to','status','reason'], 'headers' => ['الكود','الموظف','القسم','من','إلى','الحالة','السبب'], 'rows' => $rows];
    }

    /**
     * تقرير المباشرات: الموظفون الجدد (تاريخ المباشرة hire_date) وفق نفس منطق صفحة "المباشرات والإغلاقات"
     */
    public static function workResumptions(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);
        self::applyPeriodDateRange('e.hire_date', $filters, $w, $p);
        $rows = Database::query(
            "SELECT e.emp_code, e.full_name, COALESCE(d.name, e.department) AS department, COALESCE(d.name, e.department) AS department_name, j.title AS job_title,
                    e.hire_date, e.base_salary, e.file_status, e.status
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN jobs j ON j.id = e.job_id
             WHERE $w ORDER BY e.hire_date DESC, e.full_name",
            $p
        )->fetchAll();
        return [
            'title' => 'تقرير المباشرات',
            'columns' => ['emp_code','full_name','department_name','job_title','hire_date','base_salary','file_status','status'],
            'headers' => ['الكود','الموظف','القسم','الوظيفة','تاريخ المباشرة','الراتب الأساسي','حالة الملف','الحالة'],
            'rows' => $rows,
            'money' => ['base_salary'],
        ];
    }

    /**
     * تقرير الإغلاقات: إغلاق ملف الموظف (استقالة / إنهاء خدمة) مع تفاصيل التسوية
     */
    public static function resignations(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);
        self::applyPeriodDateRange('r.resignation_date', $filters, $w, $p);
        $rows = Database::query(
            "SELECT r.*, e.full_name, COALESCE(d.name, e.department) AS department, e.emp_code, e.status AS employee_status,
                    COALESCE(d.name, e.department) AS department_name, j.title AS job_title
             FROM resignations r
             JOIN employees e ON e.id = r.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN jobs j ON j.id = e.job_id
             WHERE $w ORDER BY r.resignation_date DESC",
            $p
        )->fetchAll();
        return [
            'title' => 'تقرير الإغلاقات',
            'columns' => ['emp_code','full_name','department_name','job_title','resignation_date','days_worked','daily_salary','total_due','employee_status','reason'],
            'headers' => ['الكود','الموظف','القسم','الوظيفة','تاريخ الإغلاق','أيام العمل','الراتب اليومي','المحساب النهائي','حالة الموظف','السبب'],
            'rows' => $rows,
            'money' => ['daily_salary','total_due'],
        ];
    }

    /**
     * تقرير طلبات نسيان البصمة: المصدر الفعلي هو جدول missing_attendance_requests.
     * عمود التاريخ المعروض هو created_at (تاريخ تقديم الطلب) وليس clock_in_at/clock_out_at (تاريخ البصمة الفعلي).
     * اسم الموافق يُستخرج من محرك سير العمل (request_step_decisions.actor_id -> users.full_name)
     * عبر workflow_request_id، لأن أعمدة manager_approved_by/grand_manager_approved_by في الجدول نفسه
     * غير مضمونة التعبئة دائماً ضمن مسار الاعتماد الحالي عبر محرك سير العمل.
     */
    public static function fingerprintForgiveness(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters, 'status', 'mar');
        self::applyPeriodDateRange('mar.created_at', $filters, $w, $p);
        $rows = Database::query(
            "SELECT mar.id, mar.created_at AS request_date, mar.request_type, mar.reason, mar.status,
                    e.full_name, e.emp_code, COALESCE(report_department.name, e.department) AS department, e.branch_id,
                    COALESCE((
                        SELECT u.full_name FROM request_step_decisions rsd
                        JOIN users u ON u.id = rsd.actor_id
                        WHERE rsd.request_id = mar.workflow_request_id AND rsd.decision = 'approved'
                        ORDER BY rsd.id DESC LIMIT 1
                    ), '—') AS approved_by_name
             FROM missing_attendance_requests mar
             JOIN employees e ON e.id = mar.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w
             ORDER BY mar.created_at DESC",
            $p
        )->fetchAll();
        return [
            'title' => 'تقرير طلبات نسيان البصمة',
            'columns' => ['full_name', 'request_date', 'request_type', 'approved_by_name', 'reason'],
            'headers' => ['اسم مقدم الطلب', 'التاريخ', 'نوع الطلب', 'اسم الموافق', 'سبب التأخير / النسيان'],
            'rows' => $rows,
        ];
    }

    /**
     * تقرير الأداء الوظيفي: مخصص بالكامل لكل مسمى وظيفي (لا يوجد تقرير أداء عام موحد).
     * يتطلب تحديد job_id؛ عند عدم تحديده يُعاد هيكل فارغ بعلامة job_mode لتنبيه الواجهة لطلب اختيار الوظيفة.
     * يعرض كل الموظفين النشطين في الوظيفة المحددة مع آخر تقييم لهم (أو تقييم فترة محددة إن طُلبت)
     * ومؤشرات الأداء (KPIs) الفعلية المطبّقة على هذه الوظيفة تحديداً (عامة + خاصة بالوظيفة).
     */
    public static function evaluations(array $filters): array
    {
        $jobId = (int)($filters['job_id'] ?? 0);
        $period = (string)($filters['period'] ?? '');

        if (!$jobId) {
            return [
                'title' => 'تقرير الأداء الوظيفي (KPI)',
                'job_mode' => true,
                'job_id' => 0,
                'job_title' => '',
                'kpis' => [],
                'employees' => [],
            ];
        }

        $jobTitle = (string)Database::query("SELECT title FROM jobs WHERE id = ?", [$jobId])->fetchColumn();

        // مؤشرات الأداء المطبّقة فعلياً على هذه الوظيفة: العامة (غير المرتبطة بأي وظيفة) + الخاصة بهذه الوظيفة تحديداً
        $kpis = Database::query(
            "SELECT pk.id, pk.name, pk.description, pk.weight
             FROM performance_kpis pk
             LEFT JOIN performance_kpi_jobs pkj ON pkj.kpi_id = pk.id
             WHERE pk.is_active = 1 AND (pkj.kpi_id IS NULL OR pkj.job_id = ?)
             GROUP BY pk.id
             ORDER BY pk.id",
            [$jobId]
        )->fetchAll();

        // الموظفون النشطون حالياً الذين يشغلون هذه الوظيفة
        $employees = Database::query(
            "SELECT e.id, e.emp_code, e.full_name, COALESCE(d.name, e.department) AS department_name
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.file_status = 'open' AND e.job_id = ?
             ORDER BY e.full_name",
            [$jobId]
        )->fetchAll();

        $evaluationsByEmp = [];
        $itemsByEval = [];
        $employeeIds = array_map(fn ($e) => (int)$e['id'], $employees);

        if ($employeeIds) {
            $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
            $params = $employeeIds;
            $periodCond = '';
            if ($period !== '') {
                $periodCond = ' AND period = ?';
                $params[] = $period;
            }
            // آخر تقييم لكل موظف (أو تقييم الفترة المحددة تحديداً إن طُلبت)
            $evalRows = Database::query(
                "SELECT ev.* FROM evaluations ev
                 INNER JOIN (
                    SELECT employee_id, MAX(id) AS max_id
                    FROM evaluations
                    WHERE employee_id IN ($placeholders)$periodCond
                    GROUP BY employee_id
                 ) latest ON latest.max_id = ev.id",
                $params
            )->fetchAll();
            foreach ($evalRows as $ev) {
                $evaluationsByEmp[(int)$ev['employee_id']] = $ev;
            }

            $evalIds = array_map(fn ($e) => (int)$e['id'], $evalRows);
            if ($evalIds) {
                $evalPlaceholders = implode(',', array_fill(0, count($evalIds), '?'));
                $items = Database::query(
                    "SELECT ei.*, pk.name AS kpi_name, pk.description AS kpi_description
                     FROM evaluation_items ei
                     JOIN performance_kpis pk ON pk.id = ei.kpi_id
                     WHERE ei.evaluation_id IN ($evalPlaceholders)
                     ORDER BY pk.id",
                    $evalIds
                )->fetchAll();
                foreach ($items as $it) {
                    $itemsByEval[(int)$it['evaluation_id']][] = $it;
                }
            }

            // عدد المكافآت المالية المعتمدة (خلال الفترة المحددة، أو إجمالي التاريخ إن لم تُحدد فترة)
            $bonusParams = $employeeIds;
            $bonusCond = '';
            if ($period !== '') { $bonusCond = ' AND period = ?'; $bonusParams[] = $period; }
            $bonusRows = Database::query(
                "SELECT employee_id, COUNT(*) AS c FROM hr_adjustments
                 WHERE employee_id IN ($placeholders) AND adjustment_type = 'bonus' AND status = 'approved'$bonusCond
                 GROUP BY employee_id",
                $bonusParams
            )->fetchAll();
            $bonusByEmp = array_column($bonusRows, 'c', 'employee_id');

            // مجموع أيام الخصم (جزاء أيام) المعتمدة
            $penaltyParams = $employeeIds;
            $penaltyCond = '';
            if ($period !== '') { $penaltyCond = ' AND period = ?'; $penaltyParams[] = $period; }
            $penaltyRows = Database::query(
                "SELECT employee_id, COALESCE(SUM(days), 0) AS d FROM hr_adjustments
                 WHERE employee_id IN ($placeholders) AND adjustment_type = 'penalty' AND status = 'approved'$penaltyCond
                 GROUP BY employee_id",
                $penaltyParams
            )->fetchAll();
            $penaltyByEmp = array_column($penaltyRows, 'd', 'employee_id');

            // عدد الإنذارات الصادرة
            $warnParams = $employeeIds;
            $warnCond = '';
            if ($period !== '') { $warnCond = " AND DATE_FORMAT(warning_date, '%Y-%m') = ?"; $warnParams[] = $period; }
            $warnRows = Database::query(
                "SELECT employee_id, COUNT(*) AS c FROM employee_warnings
                 WHERE employee_id IN ($placeholders)$warnCond
                 GROUP BY employee_id",
                $warnParams
            )->fetchAll();
            $warnByEmp = array_column($warnRows, 'c', 'employee_id');
        }

        $employeesData = [];
        foreach ($employees as $e) {
            $eid = (int)$e['id'];
            $eval = $evaluationsByEmp[$eid] ?? null;
            $employeesData[] = [
                'id' => $eid,
                'emp_code' => $e['emp_code'],
                'full_name' => $e['full_name'],
                'department_name' => $e['department_name'],
                'evaluation' => $eval,
                'items' => $eval ? ($itemsByEval[(int)$eval['id']] ?? []) : [],
                'bonus_count' => (int)($bonusByEmp[$eid] ?? 0),
                'penalty_days' => (float)($penaltyByEmp[$eid] ?? 0),
                'warning_count' => (int)($warnByEmp[$eid] ?? 0),
            ];
        }

        return [
            'title' => 'تقرير الأداء الوظيفي — ' . ($jobTitle ?: 'غير محدد'),
            'job_mode' => true,
            'job_id' => $jobId,
            'job_title' => $jobTitle,
            'kpis' => $kpis,
            'employees' => $employeesData,
        ];
    }

    public static function location(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);
        self::applyPeriodDateRange('att.checked_at', $filters, $w, $p);
        $w .= ' AND att.within_geofence = 0';
        $rows = Database::query(
            "SELECT att.*, e.full_name, COALESCE(report_department.name, e.department) AS department, e.emp_code
             FROM attendance att JOIN employees e ON e.id = att.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w ORDER BY att.checked_at DESC",
            $p
        )->fetchAll();
        return ['title' => 'تقرير مراقبة المواقع', 'columns' => ['emp_code','full_name','department','checked_at','check_type','within_geofence','longitude','latitude'], 'headers' => ['الكود','الموظف','القسم','التاريخ','النوع','خارج النطاق','خط الطول','خط العرض'], 'rows' => $rows];
    }

    public static function regularity(array $filters): array
    {
        [$w, $p] = self::buildFilter($filters);

        [$from, $to] = self::resolveDateRange($filters);
        $from = $from !== '' ? $from : date('Y-m-d', strtotime('-30 days'));
        $to = $to !== '' ? $to : date('Y-m-d');
        $rangeFrom = date('Y-m-d', strtotime($from . ' -1 day'));
        $rangeTo = date('Y-m-d', strtotime($to . ' +1 day'));

        $records = Database::query(
            "SELECT e.emp_code, e.full_name, COALESCE(report_department.name, e.department) AS department, att.employee_id, att.id,
                    att.check_type, att.checked_at, att.source
             FROM attendance att JOIN employees e ON e.id = att.employee_id
             LEFT JOIN departments report_department ON report_department.id = e.department_id
             WHERE $w AND DATE(att.checked_at) BETWEEN ? AND ?
             ORDER BY att.employee_id, att.checked_at",
            array_merge($p, [$rangeFrom, $rangeTo])
        )->fetchAll();

        $employeeIds = array_unique(array_map(fn ($r) => (int)$r['employee_id'], $records));
        $shiftMap = HrService::loadShiftMap($employeeIds, $rangeFrom, $rangeTo);
        $groups = HrService::pairAndGroupAttendance($records, $shiftMap, null, $from, $to);

        $rows = [];
        foreach ($groups as $g) {
            $eid = (int)$g['employee_id'];
            $shift = $shiftMap[$eid] ?? null;
            $isFlexible = ($shift['default']['attendance_type'] ?? 'shift') === 'flexible';
            $workDate = $g['work_date'];
            $shiftInfo = $shift['overrides'][$workDate] ?? $shift['default'] ?? null;
            $shiftName = $shiftInfo['shift_name'] ?? ($shift['default']['shift_name'] ?? '-');
            $startTime = $shiftInfo['start_time'] ?? null;
            $endTime = $shiftInfo['end_time'] ?? null;

            $shiftLabel = ($shiftName && $startTime && $endTime)
                ? $shiftName . ' (' . substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5) . ')'
                : ($shiftName !== '-' ? $shiftName : 'بدون وردية');

            $delay = '-';
            $early = '-';
            if (!$isFlexible && $startTime && !empty($g['first_in'])) {
                $start = new \DateTime($workDate . ' ' . $startTime);
                $check = new \DateTime($g['first_in']);
                $delaySeconds = $check->getTimestamp() - $start->getTimestamp();
                if ($delaySeconds > 0) {
                    $delay = self::formatMinutes((int)round($delaySeconds / 60));
                }
            }
            if (!$isFlexible && $endTime && !empty($g['last_out'])) {
                $end = new \DateTime($workDate . ' ' . $endTime);
                if ($endTime <= $startTime) {
                    $end->modify('+1 day');
                }
                $lastOut = new \DateTime($g['last_out']);
                $earlySeconds = $end->getTimestamp() - $lastOut->getTimestamp();
                if ($earlySeconds > 0) {
                    $early = self::formatMinutes((int)round($earlySeconds / 60));
                }
            }

            $rows[] = [
                'emp_code' => $g['emp_code'],
                'full_name' => $g['full_name'],
                'department' => $g['department'],
                'work_date' => $workDate,
                'shift' => $shiftLabel,
                'check_in' => $g['first_in'] ? date('H:i', strtotime($g['first_in'])) : '-',
                'check_out' => $g['last_out'] ? date('H:i', strtotime($g['last_out'])) : '-',
                'delay' => $delay,
                'early_exit' => $early,
            ];
        }

        return [
            'title' => 'تقرير الانتظام',
            'columns' => ['emp_code','full_name','department','work_date','shift','check_in','check_out','delay','early_exit'],
            'headers' => ['الكود','الموظف','القسم','التاريخ','الوردية','وقت الدخول الفعلي','وقت الخروج الفعلي','التأخير','الخروج المبكر'],
            'rows' => $rows,
        ];
    }

    private static function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '-';
        }
        $h = (int)floor($minutes / 60);
        $m = $minutes % 60;
        return ($h > 0 ? $h . ' س ' : '') . ($m > 0 ? $m . ' د' : ($h > 0 ? '' : '0 د'));
    }

    public static function custom(array $filters): array
    {
        return ['title' => 'تقارير مخصصة', 'columns' => ['full_name','department','attendance','absence','leaves','advances','salary'], 'headers' => ['الموظف','القسم','الحضور','الغياب','الإجازات','السلف','الراتب'], 'rows' => []];
    }
}
