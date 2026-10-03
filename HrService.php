<?php
declare(strict_types=1);

namespace App\Core;

/**
 * خدمات إدارة الموارد البشرية
 */
class HrService
{
    /**
     * إنشاء/تحديث حساب المستخدم للموظف بناءً على المستوى الإداري
     */

    public static function recordAttendance(
        int $employeeId,
        string $type,
        ?float $lat,
        ?float $lng,
        ?string $photoPath,
        string $source = 'mobile',
        ?string $deviceId = null,
        ?string $notes = null
    ): array {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$employee || !self::isEmployeeFileOpen($employeeId)) {
            throw new \RuntimeException('ملف الموظف مغلق أو مأرشف، لا يمكن تسجيل الحضور');
        }
        $within = 0;
        $radius = 200;
        $globalRadius = (int)Settings::get('attendance_global_radius_meters', 0);
        $usedCenter = null;
        $distance = null;
        if ($lat !== null && $lng !== null) {
            if ($employee && $employee['branch_id']) {
                $branch = Database::query("SELECT * FROM branches WHERE id = ?", [(int)$employee['branch_id']])->fetch();
                if ($branch && $branch['latitude'] && $branch['longitude']) {
                    $radius = (int)($branch['allowed_radius_meters'] ?? 200);
                    if ($globalRadius > 0) {
                        $radius = $globalRadius;
                    }
                    $distance = self::distanceMeters($lat, $lng, (float)$branch['latitude'], (float)$branch['longitude']);
                    $within = $distance <= $radius ? 1 : 0;
                    $usedCenter = ['name' => $branch['name'] ?? 'الفرع', 'lat' => (float)$branch['latitude'], 'lng' => (float)$branch['longitude']];
                }
            }
            // Fallback: إعداد الجيوفينس العامة من الإعدادات
            if (!$within && !$usedCenter) {
                $geo = Database::query("SELECT svalue FROM settings WHERE skey='work_geofence'")->fetch();
                if ($geo) {
                    $g = json_decode($geo['svalue'], true);
                    if (is_array($g) && isset($g['lat'], $g['lng'])) {
                        $radius = (int)($g['radius_m'] ?? 200);
                        if ($globalRadius > 0) {
                            $radius = $globalRadius;
                        }
                        $distance = self::distanceMeters($lat, $lng, (float)$g['lat'], (float)$g['lng']);
                        $within = $distance <= $radius ? 1 : 0;
                        $usedCenter = ['name' => 'النطاق العام', 'lat' => (float)$g['lat'], 'lng' => (float)$g['lng']];
                    }
                }
            }
        }

        $strict = Database::query("SELECT svalue FROM settings WHERE skey='attendance_strict_geofence'")->fetch();
        $strictEnabled = ($strict['svalue'] ?? '1') === '1';
        if ($strictEnabled && !$within) {
            $userCoords = $lat !== null && $lng !== null ? "موقعك: {$lat}, {$lng}" : 'لم يُرسل موقع';
            $centerCoords = $usedCenter ? "موقع {$usedCenter['name']}: {$usedCenter['lat']}, {$usedCenter['lng']}" : 'لم يُحدد مركز للجيوفينس';
            $distText = $distance !== null ? 'المسافة: ' . round($distance) . ' متر' : 'المسافة غير محسوبة';
            $error = "لا يمكن تسجيل الحضور: أنت خارج النطاق الجغرافي المسموح به ({$radius} متر). {$distText}. {$userCoords}. {$centerCoords}.";
            self::logGeofenceFailure($employeeId, $lat, $lng, $usedCenter, $distance, $radius, $error);
            throw new \RuntimeException($error);
        }

        Database::query(
            "INSERT INTO attendance (employee_id, check_type, checked_at, latitude, longitude, photo, source, within_geofence, device_id, notes)
             VALUES (?,?,NOW(),?,?,?,?,?,?,?)",
            [$employeeId, $type === 'out' ? 'out' : 'in', $lat, $lng, $photoPath, $source, $within, $deviceId, $notes]
        );
        $id = (int)Database::lastInsertId();

        $checkType = $type === 'out' ? 'out' : 'in';
        $today = date('Y-m-d');
        $lateMinutes = 0;
        $earlyMinutes = 0;
        $overtimeMinutes = 0;

        $isFlexible = isset($employee['attendance_type']) && $employee['attendance_type'] === 'flexible';
        if (!$isFlexible) {
            $shift = self::getEmployeeShift($employeeId, $today);
            $attendance = Database::query("SELECT checked_at FROM attendance WHERE id = ?", [$id])->fetch();
            if ($attendance && $shift) {
                $checkedAt = new \DateTime((string)$attendance['checked_at']);
                $shiftStart = new \DateTime($today . ' ' . $shift['start_time']);
                $shiftEnd = new \DateTime($today . ' ' . $shift['end_time']);
                if ($checkType === 'in') {
                    if ($checkedAt > $shiftStart) {
                        $lateMinutes = max(0, (int)floor(($checkedAt->getTimestamp() - $shiftStart->getTimestamp()) / 60));
                    }
                } else {
                    if ($checkedAt < $shiftEnd) {
                        $earlyMinutes = max(0, (int)floor(($shiftEnd->getTimestamp() - $checkedAt->getTimestamp()) / 60));
                    } elseif ($checkedAt > $shiftEnd) {
                        $overtimeMinutes = max(0, (int)floor(($checkedAt->getTimestamp() - $shiftEnd->getTimestamp()) / 60));
                    }
                }
            }
        }
        Database::query(
            "UPDATE attendance SET late_minutes = ?, early_minutes = ?, overtime_minutes = ? WHERE id = ?",
            [$lateMinutes, $earlyMinutes, $overtimeMinutes, $id]
        );

        Helpers::queueSync('attendance', $id, 'insert', [
            'employee_id' => $employeeId, 'check_type' => $checkType,
            'latitude' => $lat, 'longitude' => $lng, 'within_geofence' => $within,
            'late_minutes' => $lateMinutes, 'early_minutes' => $earlyMinutes, 'overtime_minutes' => $overtimeMinutes,
        ]);
        return ['id' => $id, 'within_geofence' => (bool)$within, 'radius' => $radius];
    }

    private static function isWithinGeofence(float $lat, float $lng, float $branchLat, float $branchLng, float $radiusMeters): bool
    {
        return self::distanceMeters($lat, $lng, $branchLat, $branchLng) <= $radiusMeters;
    }

    private static function distanceMeters(float $lat, float $lng, float $branchLat, float $branchLng): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($branchLat - $lat);
        $dLng = deg2rad($branchLng - $lng);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat)) * cos(deg2rad($branchLat)) *
             sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private static function logGeofenceFailure(int $employeeId, ?float $lat, ?float $lng, ?array $center, ?float $distance, int $radius, string $error): void
    {
        try {
            $payload = json_encode([
                'lat' => $lat, 'lng' => $lng, 'center' => $center,
                'distance' => $distance, 'radius' => $radius, 'message' => $error,
            ], JSON_UNESCAPED_UNICODE);
            Database::query(
                "INSERT INTO employee_audit_logs (employee_id, action, field_name, old_value, new_value, changed_by)
                 VALUES (?, 'geofence_fail', 'location', ?, ?, 0)",
                [$employeeId, $payload, null]
            );
        } catch (\Throwable $e) {
            // silent
        }
    }

    public static function syncEmployeeUserAccount(
        int $employeeId,
        int $adminLevel,
        string $fullName,
        string $empCode,
        ?int $branchId,
        ?int $roleId = null,
        ?string $username = null,
        ?string $password = null
    ): void {
        if ($adminLevel <= 0 && !$roleId) {
            return;
        }
        if ($roleId) {
            $role = Database::query("SELECT id FROM roles WHERE id = ?", [$roleId])->fetch();
        } else {
            $role = Database::query(
                "SELECT id FROM roles WHERE level <= ? ORDER BY level DESC LIMIT 1",
                [$adminLevel]
            )->fetch();
        }
        if (!$role) {
            return;
        }
        $roleId = (int) $role['id'];
        $loginUsername = $username !== null && $username !== '' ? trim($username) : $empCode;
        $passwordHash = $password !== null && $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : password_hash($empCode, PASSWORD_DEFAULT);
        $user = Database::query("SELECT id FROM users WHERE username = ?", [$loginUsername])->fetch();
        if ($user) {
            $updates = "role_id = ?, full_name = ?, branch_id = ?, is_active = 1";
            $params = [$roleId, $fullName, $branchId];
            if ($password !== null && $password !== '') {
                $updates .= ", password_hash = ?";
                $params[] = $passwordHash;
            }
            $params[] = (int) $user['id'];
            Database::query("UPDATE users SET {$updates} WHERE id = ?", $params);
            $userId = (int) $user['id'];
        } else {
            Database::query(
                "INSERT INTO users (username, password_hash, full_name, role_id, branch_id)
                 VALUES (?,?,?,?,?)",
                [$loginUsername, $passwordHash, $fullName, $roleId, $branchId]
            );
            $userId = (int) Database::lastInsertId();
        }
        Database::query("UPDATE employees SET user_id = ? WHERE id = ?", [$userId, $employeeId]);
    }

    /** تحديث حساب دخول موجود مرتبط بموظف */
    public static function syncExistingUserAccount(
        int $userId,
        int $adminLevel,
        string $fullName,
        ?int $branchId,
        ?int $roleId = null,
        ?string $username = null,
        ?string $password = null
    ): void {
        $hasCredentials = ($username !== null && $username !== '') || ($password !== null && $password !== '');
        if ($adminLevel <= 0 && !$roleId && !$hasCredentials) {
            return;
        }
        if ($roleId) {
            $role = Database::query("SELECT id FROM roles WHERE id = ?", [$roleId])->fetch();
        } elseif ($adminLevel > 0) {
            $role = Database::query(
                "SELECT id FROM roles WHERE level <= ? ORDER BY level DESC LIMIT 1",
                [$adminLevel]
            )->fetch();
        } elseif ($hasCredentials) {
            $user = Database::query("SELECT role_id FROM users WHERE id = ?", [$userId])->fetch();
            if ($user && $user['role_id']) {
                $role = Database::query("SELECT id FROM roles WHERE id = ?", [(int)$user['role_id']])->fetch();
            } else {
                $role = null;
            }
        } else {
            $role = null;
        }
        if (!$role) {
            return;
        }
        $updates = "role_id = ?, full_name = ?, branch_id = ?, is_active = 1";
        $params = [(int)$role['id'], $fullName, $branchId];
        if ($username !== null && $username !== '') {
            $updates .= ", username = ?";
            $params[] = trim($username);
        }
        if ($password !== null && $password !== '') {
            $updates .= ", password_hash = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $params[] = $userId;
        Database::query("UPDATE users SET {$updates} WHERE id = ?", $params);
    }

    /** إنشاء أرصدة الإجازات الافتتاحية للموظف الجديد */
    public static function initLeaveBalances(int $employeeId): void
    {
        $year = (int) date('Y');
        $defaults = [
            ['annual', 30, 30],
            ['sick', 15, 15],
            ['exit', 0, 0],
        ];
        foreach ($defaults as [$type, $entitled, $balance]) {
            Database::query(
                "INSERT IGNORE INTO leave_balances (employee_id, year, leave_type, entitled, balance, used)
                 VALUES (?,?,?,?,?,0)",
                [$employeeId, $year, $type, $entitled, $balance]
            );
        }
    }

    /** حساب أيام الإجازة بين تاريخين */
    public static function leaveDays(string $dateFrom, string $dateTo): float
    {
        $from = strtotime($dateFrom);
        $to = strtotime($dateTo);
        if ($from === false || $to === false || $to < $from) {
            return 0;
        }
        return floor(($to - $from) / 86400) + 1;
    }

    /** تطبيق الإجازة عند الموافقة (فقط للطلبات من نوع إجازة) */
    public static function applyLeaveApproval(int $leaveId): void
    {
        $leave = Database::query("SELECT * FROM leave_requests WHERE id = ?", [$leaveId])->fetch();
        if (!$leave || $leave['status'] !== 'approved' || ($leave['request_type'] ?? 'leave') !== 'leave') {
            return;
        }
        $type = (string) $leave['leave_type'];
        if (!in_array($type, ['annual', 'sick', 'exit'], true)) {
            return;
        }
        $days = self::leaveDays((string) $leave['date_from'], (string) $leave['date_to']);
        if ($days <= 0) {
            return;
        }
        $year = (int) date('Y', strtotime((string) $leave['date_from']));
        self::ensureBalance((int) $leave['employee_id'], $year, $type);
        Database::query(
            "UPDATE leave_balances SET used = used + ?, balance = balance - ?
             WHERE employee_id = ? AND year = ? AND leave_type = ?",
            [$days, $days, (int) $leave['employee_id'], $year, $type]
        );
    }

    /** التأكد من وجود رصيد للموظف/السنة/النوع (مع الاستحقاق الافتراضي) */
    public static function ensureBalance(int $employeeId, int $year, string $type): void
    {
        $defaults = ['annual' => 30, 'sick' => 15, 'exit' => 0];
        $entitled = $defaults[$type] ?? 0;
        Database::query(
            "INSERT IGNORE INTO leave_balances (employee_id, year, leave_type, entitled, balance, used)
             VALUES (?,?,?,?,?,0)",
            [$employeeId, $year, $type, $entitled, $entitled]
        );
    }

    /** إرجاع الحد الأدنى من الإجازات في حالة الرفض (فقط للطلبات من نوع إجازة) */
    public static function revertLeaveApproval(int $leaveId): void
    {
        $leave = Database::query("SELECT * FROM leave_requests WHERE id = ?", [$leaveId])->fetch();
        if (!$leave || ($leave['request_type'] ?? 'leave') !== 'leave') {
            return;
        }
        $type = (string) $leave['leave_type'];
        if (!in_array($type, ['annual', 'sick', 'exit'], true)) {
            return;
        }
        $days = self::leaveDays((string) $leave['date_from'], (string) $leave['date_to']);
        if ($days <= 0) {
            return;
        }
        $year = (int) date('Y', strtotime((string) $leave['date_from']));
        Database::query(
            "UPDATE leave_balances SET used = GREATEST(used - ?, 0), balance = balance + ?
             WHERE employee_id = ? AND year = ? AND leave_type = ?",
            [$days, $days, (int) $leave['employee_id'], $year, $type]
        );
    }

    /** هل يوجد تداخل في تواريخ الإجازات للموظف؟ */
    public static function hasLeaveOverlap(int $employeeId, string $dateFrom, string $dateTo, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM leave_requests
                WHERE employee_id = ? AND request_type = 'leave'
                  AND status NOT IN ('rejected','cancelled')
                  AND date_from <= ? AND date_to >= ?";
        $params = [$employeeId, $dateTo, $dateFrom];
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";
        return (bool) Database::query($sql, $params)->fetch();
    }

    /** مرفقات طلب الموارد البشرية */
    public static function getLeaveRequestAttachments(int $requestId): array
    {
        return Database::query(
            "SELECT * FROM leave_request_attachments WHERE request_id = ? ORDER BY id",
            [$requestId]
        )->fetchAll();
    }

    /** سجل الموافقات على طلب الموارد البشرية */
    public static function getLeaveRequestApprovals(int $requestId): array
    {
        return Database::query(
            "SELECT a.*, u.full_name AS decided_by_name, r.name AS role_name
             FROM legacy_approval_requests lar
             LEFT JOIN legacy_approval_actions a ON a.request_id = lar.id
             LEFT JOIN users u ON u.id = a.decided_by
             LEFT JOIN roles r ON r.id = (SELECT role_id FROM legacy_approval_steps WHERE workflow_id = lar.workflow_id AND step_order = a.step_order LIMIT 1)
             WHERE lar.entity_type = 'leave_request' AND lar.entity_id = ?
             ORDER BY a.created_at",
            [$requestId]
        )->fetchAll();
    }

    /** سجل الموافقات على طلب سلفة */
    public static function getAdvanceApprovalHistory(int $advanceId): array
    {
        return Database::query(
            "SELECT a.*, u.full_name AS decided_by_name, r.name AS role_name
             FROM legacy_approval_requests lar
             LEFT JOIN legacy_approval_actions a ON a.request_id = lar.id
             LEFT JOIN users u ON u.id = a.decided_by
             LEFT JOIN roles r ON r.id = (SELECT role_id FROM legacy_approval_steps WHERE workflow_id = lar.workflow_id AND step_order = a.step_order LIMIT 1)
             WHERE lar.entity_type = 'salary_advance' AND lar.entity_id = ?
             ORDER BY a.created_at",
            [$advanceId]
        )->fetchAll();
    }

    /** التأكد من أن الموظف مرتبط بحساب مستخدم */
    public static function employeeIdForUser(int $userId): ?int
    {
        $emp = Database::query("SELECT id FROM employees WHERE user_id = ?", [$userId])->fetch();
        return $emp ? (int) $emp['id'] : null;
    }

    /**
     * التأكد من إنشاء تنبيه لموظف لم يسجّل بصمته بعد بداية وقت العمل
     */
    public static function checkMissingClockIns(?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $delay = (int) (Database::query("SELECT svalue FROM settings WHERE skey='missing_clockin_alert_delay_minutes'")->fetch()['svalue'] ?? 15);
        $defaultStart = (string) (Database::query("SELECT svalue FROM settings WHERE skey='default_work_start_time'")->fetch()['svalue'] ?? '08:00');

        $employees = Database::query(
            "SELECT e.*, b.name AS branch_name, b.allowed_radius_meters FROM employees e
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE e.file_status = 'open' AND e.work_start_time IS NOT NULL"
        )->fetchAll();

        $now = new \DateTime();
        $alertDate = new \DateTime($date);
        $alertTime = (clone $alertDate)->setTime((int) $now->format('H'), (int) $now->format('i'));
        $created = [];

        foreach ($employees as $e) {
            $start = (string) $e['work_start_time'];
            if (!$start) {
                $start = $defaultStart;
            }
            $expectedStart = (clone $alertDate)->setTime((int) substr($start, 0, 2), (int) substr($start, 3, 2));
            $alertThreshold = (clone $expectedStart)->modify("+{$delay} minutes");

            if ($alertTime < $alertThreshold) {
                continue;
            }

            $hasClockIn = Database::query(
                "SELECT id FROM attendance WHERE employee_id = ? AND DATE(checked_at) = ? AND check_type = 'in' LIMIT 1",
                [(int) $e['id'], $date]
            )->fetch();
            if ($hasClockIn) {
                continue;
            }

            $exists = Database::query(
                "SELECT id FROM attendance_alerts WHERE employee_id = ? AND alert_date = ? AND alert_type = 'missing_clockin' LIMIT 1",
                [(int) $e['id'], $date]
            )->fetch();
            if ($exists) {
                continue;
            }

            Database::query(
                "INSERT INTO attendance_alerts (employee_id, alert_date, alert_type) VALUES (?,?,?)",
                [(int) $e['id'], $date, 'missing_clockin']
            );
            $created[] = [
                'alert_id' => (int) Database::lastInsertId(),
                'employee_id' => (int) $e['id'],
                'employee_name' => (string) $e['full_name'],
            ];

            self::notifyByLevel(
                'missing_clockin',
                (int) $e['id'],
                "تأخير في بصمة " . Helpers::e($e['full_name']),
                "لم يسجّل الموظف بصمته في موعد العمل: " . $start,
                '/hr/attendance?date=' . $date,
                1
            );
        }
        return $created;
    }

    /**
     * إرسال تبليغات إدارية حسب القسم والمستوى الإداري
     */
    public static function notifyByLevel(
        string $eventType,
        int $employeeId,
        string $title,
        string $body,
        string $link,
        int $minAdminLevel = 0
    ): int {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$employee) {
            return 0;
        }

        $rules = Database::query(
            "SELECT * FROM notification_rules WHERE event_type = ? AND is_active = 1",
            [$eventType]
        )->fetchAll();
        if (!$rules) {
            return 0;
        }

        $notified = 0;
        foreach ($rules as $rule) {
            // مستلم محدد (user_id)
            if (!empty($rule['user_id'])) {
                Helpers::notify((int) $rule['user_id'], $title, $body, $link);
                $notified++;
                continue;
            }

            // مستلم حسب الدور
            if (!empty($rule['role_id'])) {
                Helpers::notifyRole((int) $rule['role_id'], $title, $body, $link);
                $notified++;
                continue;
            }

            $minLevel = max((int) $rule['min_admin_level'], $minAdminLevel);
            $sql = "SELECT id, user_id FROM employees WHERE file_status = 'open' AND admin_level >= ? AND admin_level > ? AND user_id IS NOT NULL";
            $params = [$minLevel, (int) $employee['admin_level']];
            if ($rule['branch_specific'] && $employee['branch_id']) {
                $sql .= " AND (branch_id = ? OR branch_id IS NULL)";
                $params[] = (int) $employee['branch_id'];
            }
            if ($rule['department_specific'] && $employee['department']) {
                $sql .= " AND (department = ? OR department = '' OR department IS NULL)";
                $params[] = (string) $employee['department'];
            }
            $managers = Database::query($sql, $params)->fetchAll();
            foreach ($managers as $m) {
                Helpers::notify((int) $m['user_id'], $title, $body, $link);
                $notified++;
            }
        }
        return $notified;
    }

    /**
     * حساب مكوّنات الراتب للموظف في فترة محددة
     */
    public static function calculatePayrollComponents(int $employeeId, string $period, ?int $payrollId = null): array
    {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$employee || !self::isEmployeeFileOpen($employeeId)) {
            return [];
        }
        $base = (float) $employee['base_salary'];

        // المكافئات والخصومات المعتمدة في الفترة
        $adjustments = Database::query(
            "SELECT adjustment_type, COALESCE(SUM(amount),0) AS total FROM hr_adjustments
             WHERE employee_id = ? AND status = 'approved' AND (period = ? OR (period IS NULL AND effective_date BETWEEN ? AND ?))
             GROUP BY adjustment_type",
            [$employeeId, $period, $period . '-01', date('Y-m-t', strtotime($period . '-01'))]
        )->fetchAll();
        $adjMap = array_column($adjustments, 'total', 'adjustment_type');

        // دعم التسويات القديمة في salary_adjustments (إن وجدت)
        $legacyAdjustments = Database::query(
            "SELECT adj_type, COALESCE(SUM(amount),0) AS total FROM salary_adjustments
             WHERE employee_id = ? AND status = 'approved' AND adj_date BETWEEN ? AND ?
             GROUP BY adj_type",
            [$employeeId, $period . '-01', date('Y-m-t', strtotime($period . '-01'))]
        )->fetchAll();
        $legacyMap = array_column($legacyAdjustments, 'total', 'adj_type');

        $bonuses = (float) ($adjMap['bonus'] ?? 0) + (float) ($legacyMap['bonus'] ?? 0);
        $deductions = (float) ($adjMap['deduction'] ?? 0) + (float) ($legacyMap['deduction'] ?? 0);
        $raises = (float) ($adjMap['raise'] ?? 0) + (float) ($legacyMap['raise'] ?? 0);
        $penalties = (float) ($adjMap['penalty'] ?? 0) + (float) ($legacyMap['penalty'] ?? 0);

        // أقساط السلف المستحقة في الفترة (القصيرة والطويلة الأجل)
        $advances = self::getAdvanceInstallments($employeeId, $period);

        // احتساب الغيابات من التنبيهات
        $absenceDays = (int) Database::query(
            "SELECT COUNT(*) FROM attendance_alerts
             WHERE employee_id = ? AND alert_type = 'missing_clockin' AND status IN ('new','acknowledged')
             AND alert_date BETWEEN ? AND ?",
            [$employeeId, $period . '-01', date('Y-m-t', strtotime($period . '-01'))]
        )->fetchColumn();
        $dailySalary = $base / 30;
        $absenceDeduction = $absenceDays * $dailySalary;

        // البدلات الثابتة للموظف
        $allowances = (float) Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM employee_allowances
             WHERE employee_id = ? AND is_active = 1",
            [$employeeId]
        )->fetchColumn();

        // ساعات الإضافي المرتبطة بمسير الراتب (إذا أُدخلت)
        $overtime = 0;
        if ($payrollId) {
            $overtime = (float) Database::query(
                "SELECT COALESCE(SUM(amount),0) FROM payroll_overtime WHERE payroll_id = ?",
                [$payrollId]
            )->fetchColumn();
        }

        // إجمالي الاستحقاقات قبل الضريبة
        $totalEarnings = $base + $raises + $bonuses + $allowances + $overtime;
        $totalDeductionsBeforeTax = $deductions + $penalties + $advances + $absenceDeduction;
        $taxableIncome = max(0, $totalEarnings - $totalDeductionsBeforeTax);
        $taxAmount = self::calculateTax($taxableIncome);

        $totalDeductions = $totalDeductionsBeforeTax + $taxAmount;
        $net = $totalEarnings - $totalDeductions;

        return [
            'employee' => $employee,
            'base_salary' => $base,
            'raises' => $raises,
            'bonuses' => $bonuses,
            'allowances' => $allowances,
            'overtime' => $overtime,
            'deductions' => $deductions,
            'penalties' => $penalties,
            'advances' => $advances,
            'absence_days' => $absenceDays,
            'absence_deduction' => $absenceDeduction,
            'tax_amount' => $taxAmount,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => $net,
        ];
    }

    /** حساب الضريبة وفق شرائح الضريبة */
    public static function calculateTax(float $taxableIncome): float
    {
        $brackets = Database::query(
            "SELECT * FROM tax_brackets WHERE is_active = 1 ORDER BY min_amount"
        )->fetchAll();
        if (!$brackets) {
            return 0;
        }
        $tax = 0.0;
        foreach ($brackets as $b) {
            $min = (float) $b['min_amount'];
            $max = $b['max_amount'] !== null ? (float) $b['max_amount'] : PHP_FLOAT_MAX;
            $rate = (float) $b['rate'];
            if ($taxableIncome <= $min) {
                continue;
            }
            $bracketAmount = min($taxableIncome, $max) - $min;
            if ($bracketAmount > 0) {
                $tax += $bracketAmount * $rate;
            }
        }
        return round($tax, 2);
    }

    /** توليد مرتب للموظف في فترة محددة */
    public static function generatePayroll(int $employeeId, string $period, ?int $generatedBy = null): int
    {
        if (!self::isEmployeeFileOpen($employeeId)) {
            return 0;
        }
        $exists = Database::query(
            "SELECT id FROM payrolls WHERE employee_id = ? AND period = ?",
            [$employeeId, $period]
        )->fetch();
        $payrollId = $exists ? (int) $exists['id'] : 0;
        $comp = self::calculatePayrollComponents($employeeId, $period, $payrollId ?: null);
        if (!$comp) {
            return 0;
        }
        if ($exists) {
            Database::query(
                "UPDATE payrolls SET base_salary = ?, raises = ?, bonuses = ?, allowances = ?, overtime = ?, deductions = ?, penalties = ?, advances = ?, tax_amount = ?, absence_days = ?, absence_deduction = ?, total_earnings = ?, total_deductions = ?, net_salary = ?, generated_by = ?
                 WHERE id = ?",
                [$comp['base_salary'], $comp['raises'], $comp['bonuses'], $comp['allowances'], $comp['overtime'], $comp['deductions'], $comp['penalties'], $comp['advances'], $comp['tax_amount'], $comp['absence_days'], $comp['absence_deduction'], $comp['total_earnings'], $comp['total_deductions'], $comp['net_salary'], $generatedBy, (int)$exists['id']]
            );
            $payrollId = (int) $exists['id'];
        } else {
            Database::query(
                "INSERT INTO payrolls (employee_id, period, base_salary, raises, bonuses, allowances, overtime, deductions, penalties, advances, tax_amount, absence_days, absence_deduction, total_earnings, total_deductions, net_salary, generated_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$employeeId, $period, $comp['base_salary'], $comp['raises'], $comp['bonuses'], $comp['allowances'], $comp['overtime'], $comp['deductions'], $comp['penalties'], $comp['advances'], $comp['tax_amount'], $comp['absence_days'], $comp['absence_deduction'], $comp['total_earnings'], $comp['total_deductions'], $comp['net_salary'], $generatedBy]
            );
            $payrollId = (int) Database::lastInsertId();
        }
        self::syncPayrollAllowances($payrollId, $employeeId);
        self::markRepaymentsPaid($employeeId, $period, $payrollId);
        return $payrollId;
    }

    /** مزامنة بدلات الموظف الثابتة مع مسير الراتب */
    public static function syncPayrollAllowances(int $payrollId, int $employeeId): void
    {
        Database::query("DELETE FROM payroll_allowances WHERE payroll_id = ?", [$payrollId]);
        $allowances = Database::query(
            "SELECT * FROM employee_allowances WHERE employee_id = ? AND is_active = 1",
            [$employeeId]
        )->fetchAll();
        foreach ($allowances as $a) {
            Database::query(
                "INSERT INTO payroll_allowances (payroll_id, allowance_type, amount) VALUES (?,?,?)",
                [$payrollId, $a['allowance_type'], $a['amount']]
            );
        }
    }

    /** الموافقة أو الرفض على تسوية مالية حسب المستوى الإداري */
    public static function decideAdjustment(int $adjustmentId, string $decision, ?int $approverId = null): bool
    {
        $adj = Database::query("SELECT * FROM hr_adjustments WHERE id = ?", [$adjustmentId])->fetch();
        if (!$adj || $adj['status'] !== 'pending') {
            return false;
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return false;
        }
        Database::query(
            "UPDATE hr_adjustments SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?",
            [$decision, $approverId, $adjustmentId]
        );
        Helpers::queueSync('hr_adjustments', $adjustmentId, 'update', ['status' => $decision]);
        if ($decision === 'approved') {
            AccountingService::postAdjustment($adjustmentId);
        }

        // تحديث طلب الموافقة المرتبط إن وجد
        $req = Database::query(
            "SELECT id FROM legacy_approval_requests WHERE entity_type = 'hr_adjustment' AND entity_id = ? AND status = 'pending'",
            [$adjustmentId]
        )->fetch();
        if ($req) {
            Database::query(
                "UPDATE legacy_approval_requests SET status = ?, decided_by = ? WHERE id = ?",
                [$decision, $approverId, (int)$req['id']]
            );
        }

        // إشعار للموظف عند القرار النهائي
        $emp = Database::query("SELECT user_id, full_name FROM employees WHERE id = ?", [(int)$adj['employee_id']])->fetch();
        if ($emp && $emp['user_id']) {
            $typeLabels = ['bonus'=>'مكافأة','deduction'=>'خصم','raise'=>'زيادة','penalty'=>'جزاء'];
            Helpers::notify(
                (int)$emp['user_id'],
                'نتيجة طلب تسوية',
                'تم ' . ($decision === 'approved' ? 'الموافقة' : 'الرفض') . ' على طلب ' . ($typeLabels[$adj['adjustment_type']] ?? $adj['adjustment_type']),
                '/hr/adjustments'
            );
        }
        return true;
    }

    /** التأكد من أن لدى المستخدم الصلاحية للموافقة على تسوية */
    public static function canDecideAdjustment(int $adjustmentId, int $userId): bool
    {
        $adj = Database::query("SELECT * FROM hr_adjustments WHERE id = ?", [$adjustmentId])->fetch();
        if (!$adj || $adj['status'] !== 'pending') {
            return false;
        }
        // مدير النظام أو من لديه صلاحية التسويات يمكنه الموافقة دائمًا
        $user = Database::query("SELECT role_id FROM users WHERE id = ?", [$userId])->fetch();
        if ($user) {
            $roleName = Database::query("SELECT name FROM roles WHERE id = ?", [(int)$user['role_id']])->fetchColumn();
            if ($roleName === 'مدير النظام') {
                return true;
            }
        }
        $approver = Database::query(
            "SELECT e.admin_level, e.branch_id, e.department FROM users u
             LEFT JOIN employees e ON e.user_id = u.id WHERE u.id = ?",
            [$userId]
        )->fetch();
        if (!$approver) {
            return false;
        }
        $requester = Database::query("SELECT admin_level, branch_id, department FROM employees WHERE id = ?", [(int)$adj['employee_id']])->fetch();
        if (!$requester) {
            return false;
        }
        if ((int)$approver['admin_level'] <= (int)$requester['admin_level']) {
            return false;
        }
        if ((int)$approver['admin_level'] < (int)$adj['admin_level_required']) {
            return false;
        }
        return true;
    }

    /** إنشاء إنذار جديد للموظف */
    public static function createWarning(int $employeeId, string $warningType, string $reason, string $date, ?int $createdBy = null): int
    {
        Database::query(
            "INSERT INTO employee_warnings (employee_id, warning_type, reason, warning_date, created_by) VALUES (?,?,?,?,?)",
            [$employeeId, $warningType, $reason, $date, $createdBy]
        );
        return (int) Database::lastInsertId();
    }

    /** سجل الإنذارات للموظف */
    public static function getEmployeeWarnings(int $employeeId, ?int $limit = 50): array
    {
        return Database::query(
            "SELECT w.*, u.full_name AS created_by_name FROM employee_warnings w
             LEFT JOIN users u ON u.id = w.created_by
             WHERE w.employee_id = ? ORDER BY w.warning_date DESC, w.id DESC LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    /** سجل الإنذارات النشطة للموظف */
    public static function getActiveWarnings(int $employeeId): array
    {
        return Database::query(
            "SELECT * FROM employee_warnings WHERE employee_id = ? AND status = 'active' ORDER BY warning_date DESC",
            [$employeeId]
        )->fetchAll();
    }

    /** تحديث حالة إنذار */
    public static function updateWarningStatus(int $warningId, string $status): bool
    {
        if (!in_array($status, ['active','resolved','expired'], true)) {
            return false;
        }
        Database::query("UPDATE employee_warnings SET status = ? WHERE id = ?", [$status, $warningId]);
        return true;
    }

    /** سجل الموظف الكامل من الحضور، الإجازات، السلف، والتسويات */
    public static function employeeHistory(int $employeeId, ?string $month = null): array
    {
        $from = $month ? $month . '-01' : null;
        $to = $month ? date('Y-m-t', strtotime($from)) : null;
        $baseParams = [$employeeId];
        $rangeParams = $from ? [$from, $to] : [];

        $attendanceSql = "SELECT check_type, checked_at, source, within_geofence FROM attendance WHERE employee_id = ?";
        $leavesSql = "SELECT leave_type, date_from, date_to, status, reason FROM leave_requests WHERE employee_id = ?";
        $advancesSql = "SELECT amount, advance_type, installments, status, reason, created_at FROM salary_advances WHERE employee_id = ?";
        $adjustmentsSql = "SELECT adjustment_type, amount, effective_date, status, reason FROM hr_adjustments WHERE employee_id = ?";

        if ($from && $to) {
            $attendanceSql .= " AND DATE(checked_at) BETWEEN ? AND ?";
            $leavesSql .= " AND date_from BETWEEN ? AND ?";
            $advancesSql .= " AND DATE(created_at) BETWEEN ? AND ?";
            $adjustmentsSql .= " AND effective_date BETWEEN ? AND ?";
        }

        $attendanceSql .= " ORDER BY checked_at DESC";
        $leavesSql .= " ORDER BY id DESC";
        $advancesSql .= " ORDER BY id DESC";
        $adjustmentsSql .= " ORDER BY effective_date DESC";

        return [
            'attendance' => Database::query($attendanceSql, array_merge($baseParams, $rangeParams))->fetchAll(),
            'leaves' => Database::query($leavesSql, array_merge($baseParams, $rangeParams))->fetchAll(),
            'advances' => Database::query($advancesSql, array_merge($baseParams, $rangeParams))->fetchAll(),
            'adjustments' => Database::query($adjustmentsSql, array_merge($baseParams, $rangeParams))->fetchAll(),
        ];
    }

    /** التحقق من أن ملف الموظف مفتوح */
    public static function isEmployeeFileOpen(int $employeeId): bool
    {
        $status = Database::query("SELECT file_status FROM employees WHERE id = ?", [$employeeId])->fetchColumn();
        return $status === 'open';
    }

    /** تغيير حالة ملف الموظف مع سجل تدقيق */
    public static function changeFileStatus(int $employeeId, string $newStatus, string $reason, ?int $changedBy = null): bool
    {
        if (!in_array($newStatus, ['open', 'closed', 'archived'], true)) {
            return false;
        }
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$employee) {
            return false;
        }
        // منع إغلاق/أرشفة الملف قبل تصفية كافة العهد
        if (in_array($newStatus, ['closed', 'archived'], true) && self::hasOutstandingCustody($employeeId)) {
            return false;
        }
        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE employees SET file_status = ? WHERE id = ?",
                [$newStatus, $employeeId]
            );
            Database::query(
                "INSERT INTO employee_file_status_logs (employee_id, old_status, new_status, reason, changed_by)
                 VALUES (?,?,?,?,?)",
                [$employeeId, $employee['file_status'], $newStatus, $reason, $changedBy]
            );
            Database::commit();
            Helpers::queueSync('employees', $employeeId, 'update', ['file_status' => $newStatus]);
            return true;
        } catch (\Throwable $e) {
            Database::rollBack();
            return false;
        }
    }

    /** إنشاء جدول سداد للسلفة (قصيرة أو طويلة الأجل) */
    public static function generateRepaymentSchedule(int $advanceId): void
    {
        $advance = Database::query("SELECT * FROM salary_advances WHERE id = ?", [$advanceId])->fetch();
        if (!$advance || (int)$advance['installments'] <= 0) {
            return;
        }
        Database::query("DELETE FROM advance_repayment_schedule WHERE advance_id = ? AND status = 'pending'", [$advanceId]);
        $amount = (float)$advance['amount'];
        $installments = (int)$advance['installments'];
        $startAt = $advance['approved_at'] ?: ($advance['paid_at'] ?: date('Y-m-d H:i:s'));
        $start = date('Y-m', strtotime($startAt));
        $baseInstallment = floor(($amount / $installments) * 100) / 100;
        $first = round($amount - ($baseInstallment * ($installments - 1)), 2);
        for ($i = 0; $i < $installments; $i++) {
            $period = date('Y-m', strtotime($start . ' + ' . $i . ' months'));
            $installment = $i === 0 ? $first : $baseInstallment;
            Database::query(
                "INSERT IGNORE INTO advance_repayment_schedule (advance_id, period, amount)
                 VALUES (?,?,?)",
                [$advanceId, $period, $installment]
            );
        }
        Database::query(
            "UPDATE salary_advances SET remaining_amount = amount WHERE id = ?",
            [$advanceId]
        );
    }

    /** الأقساط المستحقة للموظف في فترة محددة (السلف طويلة + قصيرة الأجل) */
    public static function getAdvanceInstallments(int $employeeId, string $period): float
    {
        // أقساط السلف طويلة الأجل المجدولة
        $scheduled = (float) Database::query(
            "SELECT COALESCE(SUM(s.amount),0) FROM advance_repayment_schedule s
             JOIN salary_advances a ON a.id = s.advance_id
             WHERE a.employee_id = ? AND a.status IN ('approved','paid','settled')
             AND s.period = ? AND s.status = 'pending'",
            [$employeeId, $period]
        )->fetchColumn();

        // السلف قصيرة الأجل التي صُرِفت في نفس الفترة ولم تُسدد بعد
        $monthStart = $period . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));
        $short = (float) Database::query(
            "SELECT COALESCE(SUM(remaining_amount),0) FROM salary_advances
             WHERE employee_id = ? AND advance_type = 'short'
             AND status IN ('approved','paid')
             AND remaining_amount > 0
             AND (DATE(approved_at) BETWEEN ? AND ? OR DATE(paid_at) BETWEEN ? AND ?)",
            [$employeeId, $monthStart, $monthEnd, $monthStart, $monthEnd]
        )->fetchColumn();

        return $scheduled + $short;
    }

    /** تحديث حالة الأقساط المدفوعة في مسير الراتب */
    public static function markRepaymentsPaid(int $employeeId, string $period, int $payrollId): void
    {
        $schedules = Database::query(
            "SELECT s.id, s.amount, s.advance_id FROM advance_repayment_schedule s
             JOIN salary_advances a ON a.id = s.advance_id
             WHERE a.employee_id = ? AND s.period = ? AND s.status = 'pending'",
            [$employeeId, $period]
        )->fetchAll();
        foreach ($schedules as $s) {
            Database::query(
                "UPDATE advance_repayment_schedule SET status = 'paid', payroll_id = ?, paid_at = NOW() WHERE id = ?",
                [$payrollId, $s['id']]
            );
            Database::query(
                "UPDATE salary_advances SET paid_amount = paid_amount + ?, remaining_amount = remaining_amount - ? WHERE id = ?",
                [(float)$s['amount'], (float)$s['amount'], (int)$s['advance_id']]
            );
        }

        // تسديد السلف قصيرة الأجل التي لا تملك جدول سداد وتم خصمها في هذا الراتب
        $monthStart = $period . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));
        Database::query(
            "UPDATE salary_advances
             SET status = 'settled', paid_amount = paid_amount + remaining_amount, remaining_amount = 0
             WHERE employee_id = ? AND advance_type = 'short'
             AND status IN ('approved','paid')
             AND remaining_amount > 0
             AND NOT EXISTS (SELECT 1 FROM advance_repayment_schedule s WHERE s.advance_id = salary_advances.id AND s.status = 'pending')
             AND (DATE(approved_at) BETWEEN ? AND ? OR DATE(paid_at) BETWEEN ? AND ?)",
            [$employeeId, $monthStart, $monthEnd, $monthStart, $monthEnd]
        );

        // تحديث السلف التي سُددت بالكامل بعد هذا المسير إلى "مسددة"
        Database::query(
            "UPDATE salary_advances SET status = 'settled'
             WHERE id IN (SELECT advance_id FROM advance_repayment_schedule WHERE payroll_id = ?)
             AND remaining_amount <= 0",
            [$payrollId]
        );
    }

    /** إنشاء تقييم أداء جديد مع عناصر KPI وتقييمات متعددة المصادر */
    public static function createEvaluation(
        int $employeeId, string $period, string $type, array $kpiScores,
        ?string $notes = null, ?int $evaluatedBy = null,
        ?float $managerScore = null, ?float $adminScore = null, ?float $selfScore = null,
        ?string $managerNotes = null, ?string $adminNotes = null,
        array $goalRatings = []
    ): int {
        if (!in_array($type, ['monthly', 'quarterly', 'half_yearly', 'annual'], true)) {
            throw new \InvalidArgumentException('نوع التقييم غير صالح');
        }
        $total = 0; $weight = 0;
        foreach ($kpiScores as $row) {
            $total += (float)$row['score'] * (float)$row['weight'];
            $weight += (float)$row['weight'];
        }
        $kpiScore = $weight > 0 ? round($total / $weight, 2) : 0;

        // احتساب النتيجة الإجمالية من KPI + مدير + إدارة + ذاتي
        $scores = array_filter([$kpiScore, $managerScore, $adminScore, $selfScore], fn($s) => $s !== null && $s > 0);
        $totalScore = $scores ? round(array_sum($scores) / count($scores), 2) : $kpiScore;

        Database::query(
            "INSERT INTO evaluations (employee_id, period, evaluation_type, notes, evaluated_by, total_score, kpi_score, manager_score, admin_score, self_score, manager_notes, admin_notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$employeeId, $period, $type, $notes, $evaluatedBy, $totalScore, $kpiScore,
             $managerScore ?? 0, $adminScore ?? 0, $selfScore ?? 0, $managerNotes, $adminNotes]
        );
        $evalId = (int)Database::lastInsertId();
        foreach ($kpiScores as $row) {
            Database::query(
                "INSERT INTO evaluation_items (evaluation_id, kpi_id, score, weight, notes)
                 VALUES (?,?,?,?,?)",
                [$evalId, (int)$row['kpi_id'], (float)$row['score'], (float)$row['weight'], $row['notes'] ?? null]
            );
        }
        foreach ($goalRatings as $g) {
            if (!empty($g['goal_id'])) {
                Database::query(
                    "INSERT INTO evaluation_goals (evaluation_id, goal_id, achievement_rate, notes)
                     VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE achievement_rate = VALUES(achievement_rate), notes = VALUES(notes)",
                    [$evalId, (int)$g['goal_id'], (float)$g['achievement_rate'], $g['notes'] ?? null]
                );
                Database::query(
                    "UPDATE employee_goals SET actual_value = target_value * (achievement_rate / 100), status = IF(achievement_rate >= 100, 'achieved', 'missed') WHERE id = ?",
                    [(int)$g['goal_id']]
                );
            }
        }
        return $evalId;
    }

    /** اعتماد التقييم وإنشاء تسوية مالية أو زيادة مرتبطة */
    public static function finalizeEvaluation(
        int $evaluationId, ?int $finalizedBy = null,
        ?float $bonusAmount = null, ?float $raiseAmount = null,
        string $recommendation = 'none'
    ): bool {
        $eval = Database::query("SELECT * FROM evaluations WHERE id = ?", [$evaluationId])->fetch();
        if (!$eval || $eval['status'] !== 'draft') {
            return false;
        }
        Database::query(
            "UPDATE evaluations SET status = 'finalized', finalized_at = NOW(), finalized_by = ?, recommendation = ? WHERE id = ?",
            [$finalizedBy, $recommendation, $evaluationId]
        );
        $adjId = null;
        if ($bonusAmount && $bonusAmount > 0) {
            Database::query(
                "INSERT INTO hr_adjustments (employee_id, adjustment_type, amount, period, effective_date, reason, status, requested_by, approved_by)
                 VALUES (?, 'bonus', ?, ?, ?, ?, 'approved', ?, ?)",
                [(int)$eval['employee_id'], $bonusAmount, $eval['period'],
                 ($eval['period'] ? $eval['period'] . '-01' : date('Y-m-d')),
                 "مكافأة بناءً على التقييم: {$eval['total_score']}",
                 $finalizedBy, $finalizedBy]
            );
            $adjId = (int)Database::lastInsertId();
        }
        if ($raiseAmount && $raiseAmount > 0) {
            Database::query(
                "INSERT INTO hr_adjustments (employee_id, adjustment_type, amount, period, effective_date, reason, status, requested_by, approved_by)
                 VALUES (?, 'raise', ?, ?, ?, ?, 'approved', ?, ?)",
                [(int)$eval['employee_id'], $raiseAmount, $eval['period'],
                 ($eval['period'] ? $eval['period'] . '-01' : date('Y-m-d')),
                 "زيادة بناءً على التقييم: {$eval['total_score']}",
                 $finalizedBy, $finalizedBy]
            );
            $adjId = (int)Database::lastInsertId();
        }
        if ($adjId) {
            Database::query("UPDATE evaluations SET adjustment_id = ? WHERE id = ?", [$adjId, $evaluationId]);
        }
        return true;
    }

    /** أهداف الموظف */
    public static function createGoal(int $employeeId, string $title, ?string $description, ?float $targetValue, ?string $unit, ?string $dueDate, ?int $createdBy = null): int
    {
        Database::query(
            "INSERT INTO employee_goals (employee_id, title, description, target_value, unit, due_date, created_by)
             VALUES (?,?,?,?,?,?,?)",
            [$employeeId, $title, $description, $targetValue, $unit, $dueDate, $createdBy]
        );
        return (int)Database::lastInsertId();
    }

    public static function getEmployeeGoals(int $employeeId, ?string $status = null): array
    {
        $where = "WHERE employee_id = ?";
        $params = [$employeeId];
        if ($status) {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        return Database::query(
            "SELECT * FROM employee_goals $where ORDER BY due_date IS NULL, due_date, id DESC",
            $params
        )->fetchAll();
    }

    public static function updateGoal(int $goalId, array $data): bool
    {
        $allowed = ['title','description','target_value','actual_value','unit','due_date','status'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (!$sets) {
            return false;
        }
        $params[] = $goalId;
        Database::query("UPDATE employee_goals SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        return true;
    }

    public static function deleteGoal(int $goalId): void
    {
        Database::query("DELETE FROM employee_goals WHERE id = ?", [$goalId]);
    }

    public static function getEvaluationGoals(int $evaluationId): array
    {
        return Database::query(
            "SELECT g.*, eg.achievement_rate, eg.notes AS eval_notes
             FROM evaluation_goals eg
             JOIN employee_goals g ON g.id = eg.goal_id
             WHERE eg.evaluation_id = ?",
            [$evaluationId]
        )->fetchAll();
    }

    public static function getEvaluationItems(int $evaluationId): array
    {
        return Database::query(
            "SELECT ei.*, pk.name AS kpi_name FROM evaluation_items ei
             JOIN performance_kpis pk ON pk.id = ei.kpi_id
             WHERE ei.evaluation_id = ?",
            [$evaluationId]
        )->fetchAll();
    }

    /**
     * تسليم عهدة جديدة للموظف مع التكامل المحاسبي والمخزني
     */
    public static function issueCustody(
        int $employeeId,
        int $itemId,
        ?int $warehouseId,
        ?string $serialNumber,
        float $qty,
        float $unitCost,
        ?string $dueDate,
        string $type,
        ?string $expiryDate,
        ?string $notes,
        int $issuedBy
    ): int {
        if (!self::isEmployeeFileOpen($employeeId)) {
            throw new \RuntimeException('ملف الموظف مغلق أو مأرشف، لا يمكن تسليم عهدة');
        }
        $employee = Database::query("SELECT full_name FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$employee) {
            throw new \RuntimeException('الموظف غير موجود');
        }
        $item = Database::query(
            "SELECT i.*, c.type AS category_type, u.id AS unit_id FROM items i
             JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
             WHERE i.id = ? AND i.is_active = 1",
            [$itemId]
        )->fetch();
        if (!$item) {
            throw new \RuntimeException('القطعة غير موجودة أو غير نشطة');
        }
        if (!in_array($item['category_type'], ['asset', 'tool', 'clothing'], true)) {
            throw new \RuntimeException('العهدة مقتصرة على الموجودات والأدوات والملابس');
        }
        if (!$warehouseId) {
            $wh = Database::query("SELECT id FROM warehouses WHERE is_active=1 ORDER BY id LIMIT 1")->fetch();
            $warehouseId = $wh ? (int)$wh['id'] : 0;
        }
        if ($warehouseId <= 0) {
            throw new \RuntimeException('لا يوجد مخزن نشط');
        }
        $balance = StockService::balance($warehouseId, $itemId);
        if ($balance < $qty - 0.0001) {
            throw new \RuntimeException('الكمية في المخزن غير كافية (متوفر: ' . $balance . ')');
        }
        $totalValue = round($qty * $unitCost, 2);
        if ($totalValue <= 0) {
            throw new \RuntimeException('يجب تحديد قيمة مالية للعهدة');
        }
        if (!in_array($type, ['fixed', 'consumable'], true)) {
            throw new \RuntimeException('نوع العهدة يجب أن يكون ثابتة أو استهلاكية');
        }
        if ($type === 'consumable' && empty($expiryDate)) {
            throw new \RuntimeException('يجب تحديد تاريخ انتهاء العهدة الاستهلاكية');
        }
        if ($expiryDate && $expiryDate < date('Y-m-d')) {
            throw new \RuntimeException('تاريخ الانتهاء لا يمكن أن يكون في الماضي');
        }

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO employee_custody
                 (employee_id, item_id, warehouse_id, serial_number, qty, unit_cost, total_value, status, type, due_date, expiry_date, notes, issued_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$employeeId, $itemId, $warehouseId, $serialNumber ?: null, $qty, $unitCost, $totalValue, 'active', $type, $dueDate ?: null, $expiryDate ?: null, $notes ?: null, $issuedBy]
            );
            $custodyId = (int)Database::lastInsertId();

            StockService::move($warehouseId, $itemId, 'out', $qty, $unitCost, 'CUSTODY-' . $custodyId, 'تسليم عهدة - ' . $employee['full_name'], null, (int)$item['unit_id']);

            $remaining = StockService::totalBalance($itemId);
            if ($remaining <= 0.0001) {
                Database::query("UPDATE items SET custody_status = 'in_custody' WHERE id = ?", [$itemId]);
            }

            AccountingService::postCustodyIssue($custodyId);

            Database::commit();
            Helpers::audit('تسليم عهدة', 'employee_custody', $custodyId, 'الموظف: ' . $employee['full_name'] . ' - القطعة: ' . $item['name']);
            return $custodyId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * إرجاع عهدة أو تسجيل تلف/فقد مع التكامل المحاسبي
     */
    public static function returnCustody(
        int $custodyId,
        string $status,
        ?string $condition,
        ?string $notes,
        int $returnedBy
    ): bool {
        if (!in_array($status, ['returned', 'damaged', 'lost'], true)) {
            return false;
        }
        $custody = Database::query(
            "SELECT ec.*, e.full_name AS employee_name, i.name AS item_name, i.unit_id FROM employee_custody ec
             JOIN employees e ON e.id = ec.employee_id
             JOIN items i ON i.id = ec.item_id
             WHERE ec.id = ? AND ec.status = 'active'",
            [$custodyId]
        )->fetch();
        if (!$custody) {
            return false;
        }
        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE employee_custody SET status = ?, returned_at = NOW(), returned_condition = ?, notes = CONCAT(COALESCE(notes,''), ' | ', ?), returned_by = ? WHERE id = ?",
                [$status, $condition ?: null, $notes ?: '', $returnedBy, $custodyId]
            );

            if ($status === 'returned') {
                StockService::move((int)$custody['warehouse_id'], (int)$custody['item_id'], 'return', (float)$custody['qty'], (float)$custody['unit_cost'], 'CUSTODY-RET-' . $custodyId, 'إرجاع عهدة - ' . $custody['employee_name'], null, (int)$custody['unit_id']);
                AccountingService::postCustodyReturn($custodyId);
                Database::query("UPDATE items SET custody_status = 'in_stock' WHERE id = ? AND custody_status = 'in_custody'", [(int)$custody['item_id']]);
            } else {
                AccountingService::postCustodyPenalty($custodyId, (float)$custody['total_value']);
                $adjId = self::createCustodyPenaltyAdjustment((int)$custody['employee_id'], (float)$custody['total_value'], $custody['item_name'], $custodyId, $returnedBy);
                if ($adjId) {
                    Database::query("UPDATE employee_custody SET penalty_adjustment_id = ? WHERE id = ?", [$adjId, $custodyId]);
                }
            }

            Database::commit();
            Helpers::audit('إرجاع/تسوية عهدة', 'employee_custody', $custodyId, 'الحالة: ' . $status . ' - ' . ($notes ?: ''));
            return true;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function createCustodyPenaltyAdjustment(int $employeeId, float $amount, string $itemName, int $custodyId, int $createdBy): ?int
    {
        Database::query(
            "INSERT INTO hr_adjustments (employee_id, adjustment_type, amount, effective_date, reason, status, requested_by, approved_by)
             VALUES (?, 'deduction', ?, ?, ?, 'approved', ?, ?)",
            [$employeeId, $amount, date('Y-m-d'), 'غرامة عهدة (تلف/فقد): ' . $itemName . ' #' . $custodyId, $createdBy, $createdBy]
        );
        return (int)Database::lastInsertId();
    }

    /**
     * التحقق من وجود عهد مفتوحة للموظف
     */
    public static function hasOutstandingCustody(int $employeeId): bool
    {
        $row = Database::query(
            "SELECT COUNT(*) AS c FROM employee_custody WHERE employee_id = ? AND status = 'active'",
            [$employeeId]
        )->fetch();
        return (int)$row['c'] > 0;
    }

    /**
     * قائمة العهد المرتبطة بموظف
     */
    public static function getEmployeeCustody(int $employeeId, ?string $status = null): array
    {
        $sql = "SELECT ec.*, i.name AS item_name, i.barcode, w.name AS warehouse_name, u.full_name AS issued_by_name
                FROM employee_custody ec
                JOIN items i ON i.id = ec.item_id
                LEFT JOIN warehouses w ON w.id = ec.warehouse_id
                LEFT JOIN users u ON u.id = ec.issued_by
                WHERE ec.employee_id = ?";
        $params = [$employeeId];
        if ($status) {
            $sql .= " AND ec.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY ec.issued_at DESC";
        $custody = Database::query($sql, $params)->fetchAll();
        // حساب الأيام المتبقية على الانتهاء
        foreach ($custody as &$c) {
            $c['days_to_expiry'] = null;
            if (!empty($c['expiry_date']) && $c['status'] === 'active') {
                $today = new \DateTime(date('Y-m-d'));
                $expiry = new \DateTime($c['expiry_date']);
                $c['days_to_expiry'] = (int)$today->diff($expiry)->format('%r%a');
            }
        }
        unset($c);
        return $custody;
    }

    /**
     * إنشاء تنبيهات للعهد القريبة من الاستحقاق أو المتأخرة
     */
    public static function checkCustodyAlerts(?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $dueSoonDays = (int)(Database::query("SELECT svalue FROM settings WHERE skey='custody_alert_days_before'")->fetch()['svalue'] ?? 7);
        $created = [];
        $overdue = Database::query(
            "SELECT ec.*, e.full_name, e.user_id, i.name AS item_name FROM employee_custody ec
             JOIN employees e ON e.id = ec.employee_id
             JOIN items i ON i.id = ec.item_id
             WHERE ec.status = 'active' AND ec.due_date IS NOT NULL AND ec.due_date < ?",
            [$date]
        )->fetchAll();
        foreach ($overdue as $c) {
            $exists = Database::query(
                "SELECT id FROM employee_custody_alerts WHERE custody_id = ? AND alert_type = 'overdue' AND alert_date = ?",
                [(int)$c['id'], $date]
            )->fetch();
            if (!$exists) {
                Database::query(
                    "INSERT INTO employee_custody_alerts (custody_id, employee_id, alert_date, alert_type)
                     VALUES (?,?,?,?)",
                    [(int)$c['id'], (int)$c['employee_id'], $date, 'overdue']
                );
                $created[] = ['type' => 'overdue', 'custody_id' => (int)$c['id'], 'employee' => $c['full_name']];
                self::notifyByLevel('custody_overdue', (int)$c['employee_id'], 'تأخير إرجاع عهدة', 'الموظف ' . $c['full_name'] . ' متأخر في إرجاع: ' . $c['item_name'], '/hr/custody', 1);
            }
        }
        $dueSoon = Database::query(
            "SELECT ec.*, e.full_name, e.user_id, i.name AS item_name FROM employee_custody ec
             JOIN employees e ON e.id = ec.employee_id
             JOIN items i ON i.id = ec.item_id
             WHERE ec.status = 'active' AND ec.due_date IS NOT NULL
               AND ec.due_date >= ? AND ec.due_date <= DATE_ADD(?, INTERVAL ? DAY)",
            [$date, $date, $dueSoonDays]
        )->fetchAll();
        foreach ($dueSoon as $c) {
            $exists = Database::query(
                "SELECT id FROM employee_custody_alerts WHERE custody_id = ? AND alert_type = 'due_soon' AND alert_date = ?",
                [(int)$c['id'], $date]
            )->fetch();
            if (!$exists) {
                Database::query(
                    "INSERT INTO employee_custody_alerts (custody_id, employee_id, alert_date, alert_type)
                     VALUES (?,?,?,?)",
                    [(int)$c['id'], (int)$c['employee_id'], $date, 'due_soon']
                );
                $created[] = ['type' => 'due_soon', 'custody_id' => (int)$c['id'], 'employee' => $c['full_name']];
                self::notifyByLevel('custody_due_soon', (int)$c['employee_id'], 'اقتراب استحقاق عهدة', 'الموظف ' . $c['full_name'] . ' يجب إرجاع: ' . $c['item_name'] . ' قبل ' . $c['due_date'], '/hr/custody', 1);
            }
        }

        // 3. تنبيهات انتهاء صلاحية العهد الاستهلاكية (بالضبط قبل N يوماً)
        $expiryAlertDays = (int)(Database::query("SELECT svalue FROM settings WHERE skey='custody_expiry_alert_days'")->fetch()['svalue'] ?? 15);
        $expiring = Database::query(
            "SELECT ec.*, e.full_name, e.user_id, e.email AS employee_email, i.name AS item_name
             FROM employee_custody ec
             JOIN employees e ON e.id = ec.employee_id
             JOIN items i ON i.id = ec.item_id
             WHERE ec.status = 'active' AND ec.type = 'consumable' AND ec.expiry_date IS NOT NULL
               AND ec.expiry_date = DATE_ADD(?, INTERVAL ? DAY)",
            [$date, $expiryAlertDays]
        )->fetchAll();
        foreach ($expiring as $c) {
            $exists = Database::query(
                "SELECT id FROM employee_custody_alerts WHERE custody_id = ? AND alert_type = 'expiring' AND alert_date = ?",
                [(int)$c['id'], $date]
            )->fetch();
            if (!$exists) {
                Database::query(
                    "INSERT INTO employee_custody_alerts (custody_id, employee_id, alert_date, alert_type)
                     VALUES (?,?,?,?)",
                    [(int)$c['id'], (int)$c['employee_id'], $date, 'expiring']
                );
                $created[] = ['type' => 'expiring', 'custody_id' => (int)$c['id'], 'employee' => $c['full_name']];
                self::notifyCustodyExpiry((int)$c['id'], (int)$c['employee_id'], $c['item_name'], (string)$c['expiry_date'], $c['full_name']);
            }
        }

        return $created;
    }

    /**
     * إرسال إشعارات Push + Email لانتهاء عهدة استهلاكية
     * - الموظف صاحب العهدة
     * - المستخدمين الإداريين (hr.custody.manage أو مستوى إداري 50+)
     */
    private static function notifyCustodyExpiry(int $custodyId, int $employeeId, string $itemName, string $expiryDate, string $employeeName): void
    {
        $title = 'قرب انتهاء صلاحية عهدة';
        $body = sprintf(
            'العهدة: %s (#%d) تنتهي صلاحيتها بتاريخ %s. يرجى استبدالها أو تجديدها.',
            $itemName,
            $custodyId,
            $expiryDate
        );
        $link = '/hr/custody';

        $employee = Database::query(
            "SELECT user_id, email FROM employees WHERE id = ?",
            [$employeeId]
        )->fetch();
        if ($employee) {
            if (!empty($employee['user_id'])) {
                Helpers::notify((int)$employee['user_id'], $title, $body, $link);
            }
            if (!empty($employee['email'])) {
                NotificationService::sendEmail($employee['email'], $title, $body, $employee['user_id'] ? (int)$employee['user_id'] : null);
            }
        }

        $managers = Database::query(
            "SELECT DISTINCT u.id, u.email
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1
               AND (r.level >= 50 OR u.role_id IN (
                   SELECT rp.role_id FROM role_permissions rp
                   JOIN permissions p ON p.id = rp.permission_id
                   WHERE p.code = 'hr.custody.manage'
               ))"
        )->fetchAll();
        foreach ($managers as $m) {
            Helpers::notify((int)$m['id'], $title, 'الموظف ' . $employeeName . ' - ' . $body, $link);
            if (!empty($m['email'])) {
                NotificationService::sendEmail($m['email'], $title, 'الموظف ' . $employeeName . ' - ' . $body, (int)$m['id']);
            }
        }
    }

    /** أرشفة ملف موظف بالكامل */
    public static function archiveEmployee(int $employeeId, ?int $changedBy = null): bool
    {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$employee) {
            return false;
        }
        if (self::hasOutstandingCustody($employeeId)) {
            return false;
        }
        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE employees SET file_status = 'archived', status = 'terminated' WHERE id = ?",
                [$employeeId]
            );
            if ($employee['user_id']) {
                Database::query("UPDATE users SET is_active = 0 WHERE id = ?", [(int)$employee['user_id']]);
            }
            Database::query(
                "INSERT INTO employee_file_status_logs (employee_id, old_status, new_status, reason, changed_by)
                 VALUES (?,?,?,?,?)",
                [$employeeId, $employee['file_status'], 'archived', 'أرشفة ملف الموظف', $changedBy]
            );
            EmployeeAuditService::log($employeeId, 'archive', 'file_status', $employee['file_status'], 'archived', $changedBy);
            Database::commit();
            Helpers::queueSync('employees', $employeeId, 'update', ['file_status' => 'archived', 'status' => 'terminated']);
            return true;
        } catch (\Throwable $e) {
            Database::rollBack();
            return false;
        }
    }

    /** مستندات وعقود الموظف */
    public static function getEmployeeDocuments(int $employeeId): array
    {
        return Database::query(
            "SELECT d.*, u.full_name AS uploaded_by_name
             FROM employee_documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.employee_id = ? AND d.deleted_at IS NULL
             ORDER BY d.id DESC",
            [$employeeId]
        )->fetchAll();
    }

    public static function storeEmployeeDocument(int $employeeId, array $data, ?string $filePath, ?int $uploadedBy): int
    {
        Database::query(
            "INSERT INTO employee_documents (employee_id, doc_type, title, file_path, issue_date, expiry_date, notes, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                $employeeId, $data['doc_type'] ?? 'other', $data['title'] ?? '',
                $filePath, $data['issue_date'] ?: null, $data['expiry_date'] ?: null,
                $data['notes'] ?? '', $uploadedBy
            ]
        );
        $id = (int) Database::lastInsertId();
        EmployeeAuditService::log($employeeId, 'document', 'document_id', null, $id, $uploadedBy);
        return $id;
    }

    public static function deleteEmployeeDocument(int $docId, int $employeeId, ?int $changedBy = null): bool
    {
        $doc = Database::query("SELECT * FROM employee_documents WHERE id = ? AND employee_id = ? AND deleted_at IS NULL", [$docId, $employeeId])->fetch();
        if (!$doc) {
            return false;
        }
        Database::query("UPDATE employee_documents SET deleted_at = NOW() WHERE id = ?", [$docId]);
        EmployeeAuditService::log($employeeId, 'document_delete', 'document_id', $doc['id'], null, $changedBy);
        return true;
    }

    /** عقود الموظف */
    public static function getEmployeeContracts(int $employeeId): array
    {
        return Database::query(
            "SELECT c.*, u.full_name AS created_by_name
             FROM employee_contracts c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.employee_id = ?
             ORDER BY c.start_date DESC",
            [$employeeId]
        )->fetchAll();
    }

    public static function storeEmployeeContract(int $employeeId, array $data, ?string $filePath, ?int $createdBy): int
    {
        $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        Database::query(
            "INSERT INTO employee_contracts (employee_id, contract_type, start_date, end_date, salary, position, department_id, benefits, file_path, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $employeeId, $data['contract_type'] ?? 'fixed', $data['start_date'],
                $data['end_date'] ?: null, $data['salary'] ?? 0, $data['position'] ?? null,
                $departmentId, $data['benefits'] ?? null, $filePath, $createdBy
            ]
        );
        $id = (int)Database::lastInsertId();

        // مزامنة بيانات العقد مع البيانات الشخصية للموظف
        $updateFields = [];
        $updateParams = [];
        if (isset($data['position']) && $data['position'] !== '') {
            $updateFields[] = 'position = ?';
            $updateParams[] = $data['position'];
        }
        if ($departmentId) {
            $updateFields[] = 'department_id = ?';
            $updateParams[] = $departmentId;
            $dept = Database::query("SELECT name FROM departments WHERE id = ?", [$departmentId])->fetch();
            if ($dept) {
                $updateFields[] = 'department = ?';
                $updateParams[] = $dept['name'];
            }
        }
        if (!empty($data['start_date'])) {
            $updateFields[] = 'hire_date = ?';
            $updateParams[] = $data['start_date'];
        }
        if (isset($data['salary']) && $data['salary'] > 0) {
            $updateFields[] = 'base_salary = ?';
            $updateParams[] = $data['salary'];
        }
        if ($updateFields) {
            $updateParams[] = $employeeId;
            Database::query(
                "UPDATE employees SET " . implode(', ', $updateFields) . " WHERE id = ?",
                $updateParams
            );
        }

        EmployeeAuditService::log($employeeId, 'contract', 'contract_id', null, $id, $createdBy);
        return $id;
    }

    public static function deleteEmployeeContract(int $contractId): void
    {
        Database::query("DELETE FROM employee_contracts WHERE id = ?", [$contractId]);
    }

    /** بيانات الملف الإلكتروني للموظف */
    public static function getEmployeeAttendance(int $employeeId, int $limit = 30): array
    {
        return Database::query(
            "SELECT * FROM attendance WHERE employee_id = ? ORDER BY checked_at DESC LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    /**
     * عدد أيام العمل الفعلية بناءً على البصمات في شهر محدد
     */
    public static function getEmployeeWorkedDaysInMonth(int $employeeId, int $year, int $month): int
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $row = Database::query(
            "SELECT COUNT(DISTINCT DATE(checked_at)) AS days
             FROM attendance
             WHERE employee_id = ? AND check_type = 'in' AND DATE(checked_at) BETWEEN ? AND ?",
            [$employeeId, $start, $end]
        )->fetch();
        return (int)($row['days'] ?? 0);
    }

    public static function getEmployeeLeaves(int $employeeId, int $limit = 50): array
    {
        return Database::query(
            "SELECT lr.*, u.full_name AS approved_by_name
             FROM leave_requests lr
             LEFT JOIN users u ON u.id = lr.approved_by
             WHERE lr.employee_id = ?
             ORDER BY lr.created_at DESC LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    public static function getEmployeeAdvances(int $employeeId, int $limit = 50): array
    {
        return Database::query(
            "SELECT sa.*, u.full_name AS approved_by_name
             FROM salary_advances sa
             LEFT JOIN users u ON u.id = sa.approved_by
             WHERE sa.employee_id = ?
             ORDER BY sa.created_at DESC LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    public static function getEmployeePayrolls(int $employeeId, int $limit = 24): array
    {
        return Database::query(
            "SELECT p.*, u.full_name AS approved_by_name
             FROM payrolls p
             LEFT JOIN users u ON u.id = p.approved_by
             WHERE p.employee_id = ?
             ORDER BY p.period DESC LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    public static function getEmployeeAdjustments(int $employeeId, int $limit = 50): array
    {
        return Database::query(
            "SELECT ha.*, rb.full_name AS requested_by_name, ab.full_name AS approved_by_name
             FROM hr_adjustments ha
             LEFT JOIN users rb ON rb.id = ha.requested_by
             LEFT JOIN users ab ON ab.id = ha.approved_by
             WHERE ha.employee_id = ?
             ORDER BY ha.created_at DESC LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    public static function getEmployeeEvaluations(int $employeeId, int $limit = 20): array
    {
        return Database::query(
            "SELECT e.*, u.full_name AS evaluator_name
             FROM evaluations e
             LEFT JOIN users u ON u.id = e.evaluated_by
             WHERE e.employee_id = ?
             ORDER BY e.id DESC LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    /** ========== طلبات العمل الإضافي ========== */

    public static function createOvertimeRequest(int $employeeId, string $date, float $hours, ?string $reason): int
    {
        if ($hours <= 0) {
            throw new \RuntimeException('عدد الساعات يجب أن يكون أكبر من صفر');
        }
        $amount = self::calculateOvertimeAmount($employeeId, $hours);
        Database::query(
            "INSERT INTO overtime_requests (employee_id, request_date, hours, reason, status, amount)
             VALUES (?,?,?,?,?,?)",
            [$employeeId, $date, $hours, $reason ?: '', 'pending', $amount]
        );
        $id = (int)Database::lastInsertId();
        self::recomputeOvertimeBiometricMatch($id);
        return $id;
    }

    public static function calculateOvertimeAmount(int $employeeId, float $hours): float
    {
        $employee = Database::query("SELECT base_salary FROM employees WHERE id = ?", [$employeeId])->fetch();
        $salary = (float)($employee['base_salary'] ?? 0);
        if ($salary <= 0) {
            return 0.0;
        }
        $hourlyRate = $salary / (30 * 8);
        $multiplier = 1.0;
        return round($hours * $hourlyRate * $multiplier, 2);
    }

    public static function getBiometricOvertimeHours(int $employeeId, string $date): float
    {
        $records = Database::query(
            "SELECT check_type, checked_at FROM attendance
             WHERE employee_id = ? AND DATE(checked_at) = ?
             ORDER BY checked_at ASC",
            [$employeeId, $date]
        )->fetchAll();
        if (!$records) {
            return 0.0;
        }
        $firstIn = null;
        $lastOut = null;
        foreach ($records as $r) {
            if ($r['check_type'] === 'in' && $firstIn === null) {
                $firstIn = new \DateTime((string)$r['checked_at']);
            }
            if ($r['check_type'] === 'out') {
                $lastOut = new \DateTime((string)$r['checked_at']);
            }
        }
        if (!$firstIn || !$lastOut || $lastOut <= $firstIn) {
            return 0.0;
        }
        $totalMinutes = (int)floor(($lastOut->getTimestamp() - $firstIn->getTimestamp()) / 60);
        $shift = self::getEmployeeShift($employeeId, $date);
        $shiftMinutes = 0;
        if ($shift && !empty($shift['start_time']) && !empty($shift['end_time'])) {
            $start = new \DateTime($date . ' ' . $shift['start_time']);
            $end = new \DateTime($date . ' ' . $shift['end_time']);
            if ($end > $start) {
                $shiftMinutes = (int)floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
            }
        }
        $overtimeMinutes = max(0, $totalMinutes - $shiftMinutes);
        return round($overtimeMinutes / 60, 2);
    }

    public static function recomputeOvertimeBiometricMatch(int $overtimeId): void
    {
        $req = Database::query("SELECT * FROM overtime_requests WHERE id = ?", [$overtimeId])->fetch();
        if (!$req) {
            return;
        }
        $hours = (float)$req['hours'];
        $bioHours = self::getBiometricOvertimeHours((int)$req['employee_id'], (string)$req['request_date']);
        $match = $bioHours >= $hours;
        Database::query(
            "UPDATE overtime_requests SET biometric_hours = ?, biometric_match = ? WHERE id = ?",
            [$bioHours, $match ? 1 : 0, $overtimeId]
        );
    }

    public static function postOvertimeToPayroll(int $overtimeId): void
    {
        $req = Database::query("SELECT * FROM overtime_requests WHERE id = ?", [$overtimeId])->fetch();
        if (!$req || $req['status'] !== 'fully_approved' || !$req['biometric_match']) {
            return;
        }
        $employeeId = (int)$req['employee_id'];
        $period = date('Y-m', strtotime((string)$req['request_date']));
        $payroll = Database::query("SELECT id FROM payrolls WHERE employee_id = ? AND period = ?", [$employeeId, $period])->fetch();
        $payrollId = $payroll ? (int)$payroll['id'] : self::generatePayroll($employeeId, $period, 1);
        if (!$payrollId) {
            return;
        }
        $exists = (int)Database::query(
            "SELECT COUNT(*) FROM payroll_overtime WHERE overtime_request_id = ?",
            [$overtimeId]
        )->fetchColumn();
        if ($exists) {
            return;
        }
        $employee = Database::query("SELECT base_salary FROM employees WHERE id = ?", [$employeeId])->fetch();
        $salary = (float)($employee['base_salary'] ?? 0);
        $hourlyRate = $salary > 0 ? $salary / (30 * 8) : 0;
        $multiplier = 1.0;
        $hours = (float)$req['hours'];
        $amount = round($hours * $hourlyRate * $multiplier, 2);
        Database::query(
            "INSERT INTO payroll_overtime (payroll_id, overtime_request_id, hours, hourly_rate, multiplier, amount, notes)
             VALUES (?,?,?,?,?,?,?)",
            [$payrollId, $overtimeId, $hours, $hourlyRate, $multiplier, $amount, 'Overtime ' . $req['request_date']]
        );
        self::generatePayroll($employeeId, $period, 1);
    }

    public static function getEmployeeOvertime(int $employeeId, int $limit = 50): array
    {
        return Database::query(
            "SELECT o.*, u1.full_name AS manager_name, u2.full_name AS grand_manager_name, u3.full_name AS rejected_by_name
             FROM overtime_requests o
             LEFT JOIN users u1 ON u1.id = o.manager_approved_by
             LEFT JOIN users u2 ON u2.id = o.grand_manager_approved_by
             LEFT JOIN users u3 ON u3.id = o.rejected_by
             WHERE o.employee_id = ?
             ORDER BY o.created_at DESC LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    public static function getOvertimeAttendance(int $employeeId, string $date): array
    {
        return Database::query(
            "SELECT id, check_type, checked_at, late_minutes, early_minutes, overtime_minutes, source, within_geofence
             FROM attendance
             WHERE employee_id = ? AND DATE(checked_at) = ?
             ORDER BY checked_at ASC",
            [$employeeId, $date]
        )->fetchAll();
    }


    /** ========== إدارة الورديات ========== */

    public static function getShifts(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM shifts";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY is_active DESC, start_time, id";
        return Database::query($sql)->fetchAll();
    }

    public static function getShift(int $id): ?array
    {
        $row = Database::query("SELECT * FROM shifts WHERE id = ?", [$id])->fetch();
        return $row ?: null;
    }

    public static function getEmployeeShift(int $employeeId, ?string $date = null): ?array
    {
        $date = $date ?: date('Y-m-d');
        $override = Database::query(
            "SELECT es.shift_id, s.name, s.start_time, s.end_time, s.is_active
             FROM employee_shifts es
             JOIN shifts s ON s.id = es.shift_id
             WHERE es.employee_id = ? AND es.shift_date = ?",
            [$employeeId, $date]
        )->fetch();
        if ($override) {
            return [
                'id' => (int)$override['shift_id'],
                'name' => (string)$override['name'],
                'start_time' => (string)$override['start_time'],
                'end_time' => (string)$override['end_time'],
                'is_active' => (bool)$override['is_active'],
                'is_override' => true,
            ];
        }
        $default = Database::query(
            "SELECT s.id, s.name, s.start_time, s.end_time, s.is_active
             FROM employees e
             LEFT JOIN shifts s ON s.id = e.shift_id
             WHERE e.id = ?",
            [$employeeId]
        )->fetch();
        if ($default && !empty($default['id'])) {
            return [
                'id' => (int)$default['id'],
                'name' => (string)$default['name'],
                'start_time' => (string)$default['start_time'],
                'end_time' => (string)$default['end_time'],
                'is_active' => (bool)$default['is_active'],
                'is_override' => false,
            ];
        }
        return [
            'id' => 0,
            'name' => 'Default',
            'start_time' => (string)Settings::get('default_work_start_time', '08:00'),
            'end_time' => (string)Settings::get('default_work_end_time', '17:00'),
            'is_active' => true,
            'is_override' => false,
        ];
    }

    public static function setEmployeeDefaultShift(int $employeeId, ?int $shiftId): void
    {
        if ($shiftId !== null) {
            $shift = self::getShift($shiftId);
            if (!$shift) {
                throw new \RuntimeException('الوردية غير موجودة');
            }
        }
        Database::query(
            "UPDATE employees SET shift_id = ? WHERE id = ?",
            [$shiftId, $employeeId]
        );
    }

    public static function setEmployeeShiftForDate(int $employeeId, string $date, int $shiftId, ?int $userId = null): void
    {
        $shift = self::getShift($shiftId);
        if (!$shift) {
            throw new \RuntimeException('الوردية غير موجودة');
        }
        Database::query(
            "INSERT INTO employee_shifts (employee_id, shift_date, shift_id, created_by)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id), created_by = VALUES(created_by), updated_at = NOW()",
            [$employeeId, $date, $shiftId, $userId]
        );
    }

    public static function notifyEmployeeShiftChanged(int $employeeId, string $title, string $body, ?string $link = null): void
    {
        $emp = Database::query("SELECT user_id, full_name FROM employees WHERE id = ?", [$employeeId])->fetch();
        if ($emp && !empty($emp['user_id'])) {
            Helpers::notify((int)$emp['user_id'], $title, $body, $link);
        }
    }

    /**
     * عدد الموظفين التابعين مباشرة لمدير معين
     */
    public static function getSubordinateCount(int $managerId): int
    {
        return (int)Database::query(
            "SELECT COUNT(*) FROM employees WHERE manager_id = ? AND file_status = 'open'",
            [$managerId]
        )->fetchColumn();
    }

    /**
     * قائمة الموظفين التابعين مباشرة لمدير معين
     */
    public static function getSubordinates(int $managerId): array
    {
        return Database::query(
            "SELECT id, full_name, emp_code, department_id, job_id, status, base_salary
             FROM employees
             WHERE manager_id = ? AND file_status = 'open'
             ORDER BY full_name",
            [$managerId]
        )->fetchAll();
    }
}

