<?php
declare(strict_types=1);

namespace App\Core;

/**
 * خدمات إدارة الموارد البشرية
 */
class HrService
{
    /**
     * يوم العمل = التاريخ التقويمي للبصمة.
     */

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
        if (($employee['status'] ?? '') === 'suspended') {
            self::rejectSuspendedAttendance($employeeId, $employee, $source, $deviceId, $type);
            throw new \RuntimeException('أنت موقوف عن العمل حالياً، ولا يمكنك تسجيل الحضور أو الانصراف خلال فترة الإيقاف');
        }
        $within = 0;
        $radius = 200;
        $globalRadius = (int)Settings::get('attendance_global_radius_meters', 0);
        $usedCenter = null;
        $distance = null;
        $hasCenter = false;
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
                    $hasCenter = true;
                }
            }
            // Fallback: إعداد الجيوفينس العامة من الإعدادات
            if (!$hasCenter) {
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
                        $hasCenter = true;
                    }
                }
            }
        }

        $strict = Database::query("SELECT svalue FROM settings WHERE skey='attendance_strict_geofence'")->fetch();
        $strictEnabled = ($strict['svalue'] ?? '1') === '1';
        // إعفاء الموظف من التحقق من الموقع الجغرافي (موظفون لا يشترط دوامهم بالموقع المكتبي)
        $geofenceExempt = !empty($employee['geofence_exempt']);
        if ($geofenceExempt) {
            $strictEnabled = false;
        }

        if ($strictEnabled && !$hasCenter) {
            $error = 'لم يُضبط مركز الجيوفينس (إحداثيات الفرع أو النطاق العام). لا يمكن تسجيل الحضور في الوضع الصارم. راجع إدارة الفروع أو الإعدادات.';
            self::logGeofenceFailure($employeeId, $lat, $lng, null, null, $radius, $error);
            throw new \RuntimeException($error);
        }

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
                // استخدام الفترات المتقطعة إن وجدت، وإلا الفترة الواحدة
                $segments = $shift['segments'] ?? [];
                if (empty($segments) && !empty($shift['start_time'])) {
                    $segments = [['start_time' => $shift['start_time'], 'end_time' => $shift['end_time']]];
                }
                if (!empty($segments)) {
                    // إيجاد أقرب فترة لوقت البصمة
                    $closestSeg = self::findClosestSegment($segments, $checkedAt, $today);
                    $segStart = new \DateTime($today . ' ' . $closestSeg['start_time']);
                    $segEnd = new \DateTime($today . ' ' . $closestSeg['end_time']);
                    if ($checkType === 'in') {
                        if ($checkedAt > $segStart) {
                            $lateMinutes = max(0, (int)floor(($checkedAt->getTimestamp() - $segStart->getTimestamp()) / 60));
                        }
                    } else {
                        if ($checkedAt < $segEnd) {
                            $earlyMinutes = max(0, (int)floor(($segEnd->getTimestamp() - $checkedAt->getTimestamp()) / 60));
                        } elseif ($checkedAt > $segEnd) {
                            $overtimeMinutes = max(0, (int)floor(($checkedAt->getTimestamp() - $segEnd->getTimestamp()) / 60));
                        }
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
        return ['id' => $id, 'within_geofence' => (bool)$within, 'radius' => $radius, 'geofence_exempt' => $geofenceExempt];
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

    /** تسجيل محاولة بصمة مرفوضة بسبب إيقاف الموظف، وإشعار قسم الموارد البشرية */
    public static function rejectSuspendedAttendance(int $employeeId, array $employee, string $source, ?string $deviceId, string $type): void
    {
        try {
            $payload = json_encode([
                'source' => $source, 'device_id' => $deviceId, 'check_type' => $type, 'attempted_at' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE);
            Database::query(
                "INSERT INTO employee_audit_logs (employee_id, action, field_name, old_value, new_value, changed_by)
                 VALUES (?, 'suspended_attendance_attempt', 'status', 'suspended', ?, NULL)",
                [$employeeId, $payload]
            );
        } catch (\Throwable $e) {
            // silent
        }
        try {
            $title = 'محاولة بصمة مرفوضة لموظف موقوف';
            $body = 'حاول الموظف ' . ($employee['full_name'] ?? '') . ' تسجيل ' . ($type === 'out' ? 'انصراف' : 'حضور') . ' أثناء فترة إيقافه، وتم رفض المحاولة تلقائياً';
            $link = '/hr/employees/' . $employeeId;
            $hrRoleId = (int)(Database::query("SELECT id FROM roles WHERE name = 'مدير موارد بشرية' LIMIT 1")->fetchColumn() ?? 0);
            if ($hrRoleId) {
                Helpers::notifyRole($hrRoleId, $title, $body, $link);
            }
            $users = Database::query(
                "SELECT DISTINCT u.id FROM users u
                 JOIN role_permissions rp ON rp.role_id = u.role_id
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE p.code = 'hr.view' AND u.is_active = 1"
            )->fetchAll();
            foreach ($users as $u) {
                Helpers::notify((int)$u['id'], $title, $body, $link);
            }
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
            $employeeId = (int) $e['id'];
            $dow = (int) $alertDate->format('w');
            // لا يُنشأ تنبيه في يوم العطلة الأسبوعية الخاصة بالموظف
            if ($e['weekly_off_day'] !== null && $dow === (int)$e['weekly_off_day']) {
                continue;
            }
            // لا يُنشأ تنبيه إذا كان اليوم خارج أيام عمل الوردية
            $shift = self::getEmployeeShift($employeeId, $date);
            if ($shift && empty($shift['is_work_day'])) {
                continue;
            }

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
                [$employeeId, $date]
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
     * التأكد من إنشاء تنبيه لموظف سجّل دخولاً ولم يسجّل خروجاً بعد نهاية الوردية.
     * يُشغَّل من cron بشكل دوري (مثلاً كل 30 دقيقة بعد انتهاء الورديات).
     */
    public static function checkMissingClockOuts(?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $delay = (int)(Database::query("SELECT svalue FROM settings WHERE skey='missing_clockout_alert_delay_minutes'")->fetch()['svalue'] ?? 30);
        $now = new \DateTime();
        $alertDate = new \DateTime($date);
        $defaultEnd = (string)(Database::query("SELECT svalue FROM settings WHERE skey='default_work_end_time'")->fetch()['svalue'] ?? '17:00');

        $records = Database::query(
            "SELECT a.id, a.employee_id, a.check_type, a.checked_at, a.source,
                    e.emp_code, e.full_name, e.department
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             WHERE e.file_status = 'open'
               AND DATE(a.checked_at) BETWEEN DATE_SUB(?, INTERVAL 1 DAY) AND DATE_ADD(?, INTERVAL 1 DAY)
             ORDER BY a.checked_at",
            [$date, $date]
        )->fetchAll();

        if (!$records) {
            return [];
        }

        $employeeIds = array_values(array_unique(array_map(fn($r) => (int)$r['employee_id'], $records)));
        $shiftMap = self::loadShiftMap($employeeIds, date('Y-m-d', strtotime($date . ' -1 day')), date('Y-m-d', strtotime($date . ' +1 day')));
        $groups = self::pairAndGroupAttendance($records, $shiftMap, $date);

        $created = [];
        foreach ($groups as $g) {
            if ($g['last_out'] !== null || empty($g['first_in'])) {
                continue;
            }
            $employeeId = (int)$g['employee_id'];
            $shift = self::getEmployeeShift($employeeId, $date);
            $endTime = (!empty($shift['end_time']) && $shift['end_time'] !== '00:00:00') ? (string)$shift['end_time'] : $defaultEnd;
            $expectedEnd = (clone $alertDate)->setTime((int)substr($endTime, 0, 2), (int)substr($endTime, 3, 2));
            $inDateTime = new \DateTime((string)$g['first_in']);
            if ($expectedEnd <= $inDateTime) {
                $expectedEnd->modify('+1 day');
            }
            $alertThreshold = (clone $expectedEnd)->modify("+{$delay} minutes");

            if ($now < $alertThreshold) {
                continue;
            }

            $exists = Database::query(
                "SELECT id FROM attendance_alerts WHERE employee_id = ? AND alert_date = ? AND alert_type = 'missing_clockout' LIMIT 1",
                [$employeeId, $date]
            )->fetch();
            if ($exists) {
                continue;
            }

            Database::query(
                "INSERT INTO attendance_alerts (employee_id, alert_date, alert_type) VALUES (?,?,?)",
                [$employeeId, $date, 'missing_clockout']
            );
            $created[] = [
                'alert_id' => (int)Database::lastInsertId(),
                'employee_id' => $employeeId,
                'employee_name' => (string)$g['full_name'],
            ];

            self::notifyByLevel(
                'missing_clockout',
                $employeeId,
                "نسيان بصمة خروج: " . Helpers::e($g['full_name']),
                "سجّل الموظف بصمة دخول لكن لم يسجّل خروجاً بعد انتهاء الوردية: " . $endTime,
                '/hr/attendance?date=' . $date,
                1
            );
        }
        return $created;
    }

    /**
     * تسوية بصمات الخروج المنسية تلقائياً في نهاية اليوم.
     * تُسجّل بصمة خروج بمصدر 'auto' عند وقت انتهاء الوردية (أو وقت افتراضي)،
     * وتُنشئ تنبيه missing_clockout لمراجعة المدير.
     */
    public static function autoCloseMissingClockOuts(?string $date = null): array
    {
        $date = $date ?? date('Y-m-d', strtotime('-1 day'));
        $autoEnabled = (Database::query("SELECT svalue FROM settings WHERE skey='auto_clockout_enabled'")->fetch()['svalue'] ?? '1') === '1';
        if (!$autoEnabled) {
            return ['closed' => 0, 'alerts' => 0];
        }

        $defaultEnd = (string)(Database::query("SELECT svalue FROM settings WHERE skey='default_work_end_time'")->fetch()['svalue'] ?? '17:00');
        $now = new \DateTime();

        $records = Database::query(
            "SELECT a.id, a.employee_id, a.check_type, a.checked_at, a.source,
                    e.emp_code, e.full_name, e.department
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             WHERE e.file_status = 'open'
               AND DATE(a.checked_at) BETWEEN DATE_SUB(?, INTERVAL 1 DAY) AND DATE_ADD(?, INTERVAL 1 DAY)
             ORDER BY a.checked_at",
            [$date, $date]
        )->fetchAll();

        if (!$records) {
            return ['closed' => 0, 'alerts' => 0];
        }

        $employeeIds = array_values(array_unique(array_map(fn($r) => (int)$r['employee_id'], $records)));
        $shiftMap = self::loadShiftMap($employeeIds, date('Y-m-d', strtotime($date . ' -1 day')), date('Y-m-d', strtotime($date . ' +1 day')));
        $groups = self::pairAndGroupAttendance($records, $shiftMap, $date);

        $closed = 0;
        $alerts = 0;
        foreach ($groups as $g) {
            if ($g['last_out'] !== null || empty($g['first_in_id'])) {
                continue;
            }
            $employeeId = (int)$g['employee_id'];
            $inRecord = Database::query("SELECT * FROM attendance WHERE id = ?", [(int)$g['first_in_id']])->fetch();
            if (!$inRecord) {
                continue;
            }
            $inDateTime = new \DateTime((string)$inRecord['checked_at']);

            $shift = self::getEmployeeShift($employeeId, $date);
            $isFixed = !$shiftMap[$employeeId] || ($shiftMap[$employeeId]['default']['attendance_type'] ?? 'shift') === 'shift';

            if ($isFixed) {
                $endTime = (!empty($shift['end_time']) && $shift['end_time'] !== '00:00:00') ? (string)$shift['end_time'] : $defaultEnd;
                $outDateTime = (new \DateTime($date . ' ' . $endTime));
                if ($outDateTime <= $inDateTime) {
                    $outDateTime->modify('+1 day');
                }
            } else {
                $requiredHours = (float)($shiftMap[$employeeId]['default']['required_hours'] ?? 8);
                $outDateTime = clone $inDateTime;
                $outDateTime->modify('+' . (int)round($requiredHours * 3600) . ' seconds');
                if ($outDateTime > $now) {
                    $outDateTime = $now;
                }
            }

            Database::query(
                "INSERT INTO attendance (employee_id, check_type, checked_at, latitude, longitude, photo, source, within_geofence)
                 VALUES (?, 'out', ?, NULL, NULL, NULL, 'auto', 1)",
                [$employeeId, $outDateTime->format('Y-m-d H:i:s')]
            );
            $closed++;

            $exists = Database::query(
                "SELECT id FROM attendance_alerts WHERE employee_id = ? AND alert_date = ? AND alert_type = 'missing_clockout' LIMIT 1",
                [$employeeId, $date]
            )->fetch();
            if (!$exists) {
                Database::query(
                    "INSERT INTO attendance_alerts (employee_id, alert_date, alert_type) VALUES (?,?,?)",
                    [$employeeId, $date, 'missing_clockout']
                );
                $alerts++;
            }

            self::notifyByLevel(
                'missing_clockout',
                $employeeId,
                "تسوية تلقائية لبصمة خروج: " . Helpers::e($g['full_name']),
                "تم تسجيل خروج تلقائي للموظف في " . $outDateTime->format('H:i') . " لأن البصمة الأصلية مفقودة. يرجى المراجعة.",
                '/hr/attendance?date=' . $date,
                1
            );
        }
        return ['closed' => $closed, 'alerts' => $alerts];
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
    public static function calculatePayrollComponents(int $employeeId, string $period, ?int $payrollId = null, ?string $asOfDate = null): array
    {
        $employee = Database::query("SELECT * FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$employee || !self::isEmployeeFileOpen($employeeId)) {
            return [];
        }
        $base = (float) $employee['base_salary'];

        // دعم احتساب الراتب حتى تاريخ معين (Pro-rata) للشهر الجاري غير المكتمل بعد.
        // إذا كانت الفترة المطلوبة هي الشهر الحالي ولم يُحدَّد تاريخ صريح، يُحتسب الراتب تلقائياً حتى تاريخ اليوم.
        $periodStart = $period . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $today = date('Y-m-d');
        $effectiveAsOfDate = $asOfDate ?: (($period === date('Y-m')) ? $today : null);
        if ($effectiveAsOfDate !== null) {
            $effectiveAsOfDate = max($periodStart, min($effectiveAsOfDate, $periodEnd));
        }
        $isPartialMonth = $effectiveAsOfDate !== null && $effectiveAsOfDate < $periodEnd;
        $adjustmentsEndDate = $isPartialMonth ? $effectiveAsOfDate : $periodEnd;

        // المكافئات والخصومات المعتمدة في الفترة (حتى تاريخ الاحتساب عند التفعيل الجزئي للشهر)
        $adjustments = Database::query(
            "SELECT adjustment_type, COALESCE(SUM(amount),0) AS total FROM hr_adjustments
             WHERE employee_id = ? AND status = 'approved' AND (period = ? OR (period IS NULL AND effective_date BETWEEN ? AND ?))
             GROUP BY adjustment_type",
            [$employeeId, $period, $periodStart, $adjustmentsEndDate]
        )->fetchAll();
        $adjMap = array_column($adjustments, 'total', 'adjustment_type');

        // دعم التسويات القديمة في salary_adjustments (إن وجدت)
        $legacyAdjustments = Database::query(
            "SELECT adj_type, COALESCE(SUM(amount),0) AS total FROM salary_adjustments
             WHERE employee_id = ? AND status = 'approved' AND adj_date BETWEEN ? AND ?
             GROUP BY adj_type",
            [$employeeId, $periodStart, $adjustmentsEndDate]
        )->fetchAll();
        $legacyMap = array_column($legacyAdjustments, 'total', 'adj_type');

        $bonuses = (float) ($adjMap['bonus'] ?? 0) + (float) ($legacyMap['bonus'] ?? 0);
        // تخفيض الراتب لم يعد خصماً مستقلاً؛ يُدمج في الأساس المعدّل أدناه وتُشتق منه المعدلات
        // (يبقى مرصوداً بنوعه الخاص في hr_adjustments للتدقيق)
        $deductions = (float) ($adjMap['deduction'] ?? 0) + (float) ($legacyMap['deduction'] ?? 0);
        $raises = (float) ($adjMap['raise'] ?? 0) + (float) ($legacyMap['raise'] ?? 0);
        $penalties = (float) ($adjMap['penalty'] ?? 0) + (float) ($legacyMap['penalty'] ?? 0);
        // إضافي تيبس: مستحق يُرفع عبر التسويات المالية (hr_adjustments)
        $tipsAddition = (float) ($adjMap['tips_addition'] ?? 0);
        // تسوية عدد أيام دوام: يُعاد حساب قيمتها دائماً من (عدد الأيام المعتمدة × الراتب اليومي الحالي للأساس المعدّل) أدناه بعد حساب $dailySalary
        // لضمان انعكاس أي زيادة/تخفيض لاحق على قيمتها فوراً عند كل إعادة توليد، بدلاً من تجميدها عند لحظة اعتمادها فقط
        $workDaysSettlementDays = (float) Database::query(
            "SELECT COALESCE(SUM(days),0) FROM hr_adjustments
             WHERE employee_id = ? AND adjustment_type = 'work_days_settlement' AND status = 'approved'
               AND (period = ? OR (period IS NULL AND effective_date BETWEEN ? AND ?))",
            [$employeeId, $period, $periodStart, $adjustmentsEndDate]
        )->fetchColumn();

        // أقساط السلف المستحقة في الفترة (القصيرة والطويلة الأجل)
        $advanceBreakdown = self::getAdvanceInstallments($employeeId, $period);
        $advances = $advanceBreakdown['total'];
        $longTermAdvances = $advanceBreakdown['long'];

        // احتساب أيام الدوام والغياب من البصمات/الورديات/الإجازات (أو من التنبيهات حسب الإعداد)
        // تُحدَّد حتى تاريخ الاحتساب (اليوم الحالي) عند احتساب الشهر الجاري قبل انتهائه
        $attendance = self::calculateAttendanceSummary($employeeId, $period, $effectiveAsOfDate);
        $absenceDays = (int) $attendance['deducted_days'];

        // الأساس المعدّل الشهري = (الأساسي الاسمي + الزيادات) − تخفيض الراتب المعتمد الخاص بهذه الفترة فقط (لا يتراكم من الأشهر السابقة)
        // ومعدل اليومية يُشتق منه ويُستخدم في خصم الغياب وكل المكونات المعتمدة على المعدل (إضافي، تسوية أيام دوام...)
        $salaryReduction = self::salaryReductionTotal($employeeId, $period);
        $adjustedMonthlyBase = max(0.0, $base + $raises - $salaryReduction);
        $dailySalary = $adjustedMonthlyBase / 30;
        $absenceDeduction = $absenceDays * $dailySalary;
        // قيمة تسوية أيام الدوام = عدد الأيام المعتمدة × الراتب اليومي الحالي (المشتق من الأساس المعدّل أعلاه)، وليس من قيمة مجمدة محفوظة وقت الإنشاء
        $workDaysSettlement = round($workDaysSettlementDays * $dailySalary, 2);

        // الراتب المستحق حتى تاريخ الاحتساب: (الأساسي الاسمي − التخفيض) بنسبة أيام الاحتساب للشهر الجزئي
        // (الزيادات تبقى في عمودها المستقل وتُضاف ضمن الاستحقاقات أدناه؛ تُخصم منها أيام الغياب كالمعتاد)
        $baseAfterReduction = max(0.0, $base - $salaryReduction);
        $accruedBase = $baseAfterReduction;
        // الزيادات (raises) تُنسب بنفس معامل تناسب الأساسي للشهر الجزئي (لا تُضاف كاملة بغض النظر عن عدد الأيام المستحقة)
        $accruedRaises = $raises;
        if ($isPartialMonth) {
            $proratedFactor = $attendance['mode'] === 'attendance'
                ? ((int) $attendance['expected_days']) / 30
                : ((int) date('j', strtotime($effectiveAsOfDate)) / (int) date('t', strtotime($periodStart)));
            $accruedBase = round($baseAfterReduction * $proratedFactor, 2);
            $accruedRaises = round($raises * $proratedFactor, 2);
        }

        // البدلات الثابتة للموظف
        $allowances = (float) Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM employee_allowances
             WHERE employee_id = ? AND is_active = 1",
            [$employeeId]
        )->fetchColumn();

        // ساعات الإضافي المرتبطة بمسير الراتب (إذا أُدخلت)
        $overtime = 0;
        $overtimeDays = 0;
        if ($payrollId) {
            $overtime = (float) Database::query(
                "SELECT COALESCE(SUM(amount),0) FROM payroll_overtime WHERE payroll_id = ?",
                [$payrollId]
            )->fetchColumn();
            // عدد أيام الإضافي = عدد سجلات الإضافي المعتمدة المرتبطة بمسير الراتب
            $overtimeDays = (int) Database::query(
                "SELECT COUNT(*) FROM payroll_overtime WHERE payroll_id = ?",
                [$payrollId]
            )->fetchColumn();
        }

        // تأمين المستحقات: يُحتسب حصرياً بناءً على إعداد ملف الموظف (نسبة insurance_percentage وعدد مرات insurance_occurrences)، افتراضياً 0% لمن ليس له إعداد يدوي
        $securityDepositResult = self::calculateSecurityDeposit($employeeId, $period, $base, $payrollId ?: null);
        $securityDeposit = $securityDepositResult['amount'];
        $securityDepositOccurrenceCount = $securityDepositResult['occurrence_count'];

        // تذاكر السفر (مكافآت سنوية) للفترة
        $travelTickets = self::calculateMonthlyTravelTickets($employeeId, $period);

        // التحميلات الشهرية (ورقة تحميلات Excel)
        $loadings = self::calculateMonthlyLoadings($employeeId, $period);

        // التيبس (مبلغ كامل يُقسم تناسبيًا على أيام الدوام؛ الأيام الفعلية من البصمات عند غياب الإدخال اليدوي)
        $tips = self::calculateMonthlyTips($employeeId, $period, $attendance['mode'] === 'attendance' ? (int) $attendance['working_days'] : null);

        // الضمان الاجتماعي (استقطاع شهري ثابت)
        $socialSecurity = self::calculateMonthlySocialSecurity($employeeId, $period);

        // خدمات السكن (استقطاع شهري ثابت من ملف الموظف)، مع عدم الاستقطاع إذا كان الموظف معفياً (المبلغ المحدد يبقى محفوظاً في الملف ويُستأنف استقطاعه فور رفع الإعفاء)
        $housingDeduction = !empty($employee['housing_exempt']) ? 0.0 : (float) ($employee['housing_monthly_deduction'] ?? 0);

        // إجمالي الاستحقاقات قبل الضريبة (يُستخدم الراتب المستحق حتى تاريخ الاحتساب عند التفعيل الجزئي للشهر)
        $totalEarnings = $accruedBase + $accruedRaises + $bonuses + $allowances + $overtime + $travelTickets + $tipsAddition + $workDaysSettlement;
        $totalDeductionsBeforeTax = $deductions + $penalties + $advances + $absenceDeduction + $loadings + $tips + $socialSecurity + $housingDeduction;
        $taxableIncome = max(0, $totalEarnings - $totalDeductionsBeforeTax);
        $taxAmount = self::calculateTax($taxableIncome);

        $totalDeductions = $totalDeductionsBeforeTax + $taxAmount + $securityDeposit;
        $net = $totalEarnings - $totalDeductions;

        // التقريب إلى أقرب 1000 كما في Excel (ROUND(..., -3))
        $roundedNet = round($net, -3);
        $roundingAdjustment = $roundedNet - $net;
        // عند التقريب لأعلى: نخفض الاستقطاعات بقيمة الفرق؛ عند التقريب لأسفل: نزيدها.
        $totalDeductions -= $roundingAdjustment;
        $net = $roundedNet;

        // إذا كان صافي الراتب بالسالب: يُصفَّر الصافي، ويُرحَّل مقدار العجز كسلفة على الموظف
        // تُخصَم تلقائياً من راتب الشهر التالي (انظر syncPayrollDeficitAdvance/getAdvanceInstallments)
        $deficitAmount = 0.0;
        if ($net < 0) {
            $deficitAmount = -$net;
            $totalDeductions -= $deficitAmount;
            $net = 0.0;
        }

        return [
            'employee' => $employee,
            'base_salary' => $accruedBase,
            'full_base_salary' => $base,
            'is_partial_month' => $isPartialMonth,
            'as_of_date' => $effectiveAsOfDate,
            'raises' => $raises,
            'bonuses' => $bonuses,
            'allowances' => $allowances,
            'overtime' => $overtime,
            'overtime_days' => $overtimeDays,
            'deductions' => $deductions,
            'penalties' => $penalties,
            'salary_reduction' => $salaryReduction,
            'advances' => $advances,
            'long_term_advances' => $longTermAdvances,
            'security_deposit' => $securityDeposit,
            'security_deposit_occurrence_count' => $securityDepositOccurrenceCount,
            'travel_tickets' => $travelTickets,
            'loadings' => $loadings,
            'tips' => $tips,
            'tips_addition' => $tipsAddition,
            'work_days_settlement' => $workDaysSettlement,
            'social_security' => $socialSecurity,
            'housing_deduction' => $housingDeduction,
            'rounding_adjustment' => $roundingAdjustment,
            'absence_days' => $absenceDays,
            'absence_deduction' => $absenceDeduction,
            'expected_days' => (int) $attendance['expected_days'],
            'working_days' => (int) $attendance['working_days'],
            'leave_days' => (int) $attendance['leave_days'],
            'unpaid_leave_days' => (int) $attendance['unpaid_leave_days'],
            'attendance_mode' => $attendance['mode'],
            'tax_amount' => $taxAmount,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => $net,
            'deficit_amount' => $deficitAmount,
        ];
    }

    /**
     * ملخص الحضور للموظف في فترة الراتب:
     * أيام العمل المتوقعة (حسب الوردية والعطل الرسمية) مقابل أيام الدوام الفعلية من البصمات والإجازات المعتمدة.
     * الوضع 'alerts' يعود للسلوك القديم (عدّ تنبيهات عدم البصمة).
     */
    public static function calculateAttendanceSummary(int $employeeId, string $period, ?string $asOfDate = null): array
    {
        $periodStart = $period . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        if ($asOfDate !== null) {
            // تحديد أيام الدوام/الغياب حتى تاريخ معين فقط (احتساب جزئي للشهر الجاري)
            $periodEnd = max($periodStart, min($asOfDate, $periodEnd));
        }
        $mode = (string) Settings::get('payroll_absence_mode', 'attendance');
        $result = [
            'mode' => $mode,
            'expected_days' => 0,
            'working_days' => 0,
            'leave_days' => 0,
            'unpaid_leave_days' => 0,
            'absence_days' => 0,
            'deducted_days' => 0,
        ];

        if ($mode !== 'attendance') {
            $alerts = (int) Database::query(
                "SELECT COUNT(*) FROM attendance_alerts
                 WHERE employee_id = ? AND alert_type = 'missing_clockin' AND status IN ('new','acknowledged')
                 AND alert_date BETWEEN ? AND ?",
                [$employeeId, $periodStart, $periodEnd]
            )->fetchColumn();
            $result['mode'] = 'alerts';
            $result['absence_days'] = $alerts;
            $result['deducted_days'] = $alerts;
            return $result;
        }

        $employee = Database::query("SELECT hire_date, weekly_off_day FROM employees WHERE id = ?", [$employeeId])->fetch();
        // بداية الاحتساب من تاريخ المباشرة إن كانت ضمن الفترة
        $effectiveStart = $periodStart;
        if (!empty($employee['hire_date']) && $employee['hire_date'] > $periodStart) {
            $effectiveStart = $employee['hire_date'];
        }
        if ($effectiveStart > $periodEnd) {
            return $result;
        }
        $employeeWeeklyOff = ($employee && $employee['weekly_off_day'] !== null) ? (int)$employee['weekly_off_day'] : null;

        // العطل الرسمية (تواريخ محددة + أيام أسبوعية)
        $specificHolidays = Database::query(
            "SELECT date FROM hr_holidays WHERE is_active = 1 AND date IS NOT NULL AND date BETWEEN ? AND ?",
            [$periodStart, $periodEnd]
        )->fetchAll(\PDO::FETCH_COLUMN, 0);
        $holidayMap = array_flip($specificHolidays);
        $weeklyHolidays = array_flip(Database::query(
            "SELECT day_of_week FROM hr_holidays WHERE is_active = 1 AND day_of_week IS NOT NULL"
        )->fetchAll(\PDO::FETCH_COLUMN, 0));

        // أيام الدوام الفعلية من البصمات (مع تجميع حسب يوم العمل والوردية)
        $rangeFrom = date('Y-m-d', strtotime($periodStart . ' -1 day'));
        $rangeTo = date('Y-m-d', strtotime($periodEnd . ' +1 day'));
        $records = Database::query(
            "SELECT a.employee_id, a.id, a.check_type, a.checked_at, a.source
             FROM attendance a
             WHERE a.employee_id = ? AND DATE(a.checked_at) BETWEEN ? AND ?
             ORDER BY a.checked_at",
            [$employeeId, $rangeFrom, $rangeTo]
        )->fetchAll();
        $presentDates = [];
        if ($records) {
            $shiftMap = self::loadShiftMap([$employeeId], $rangeFrom, $rangeTo);
            $groups = self::pairAndGroupAttendance($records, $shiftMap, null, $periodStart, $periodEnd);
            foreach ($groups as $g) {
                $presentDates[$g['work_date']] = true;
            }
        }

        // الإجازات المعتمدة (مدفوعة / غير مدفوعة)
        $leaves = Database::query(
            "SELECT leave_type, date_from, date_to FROM leave_requests
             WHERE employee_id = ? AND status = 'approved' AND leave_type <> 'hourly'
             AND date_to >= ? AND date_from <= ?",
            [$employeeId, $periodStart, $periodEnd]
        )->fetchAll();
        $paidLeave = [];
        $unpaidLeave = [];
        foreach ($leaves as $l) {
            $cur = new \DateTime(max($l['date_from'], $periodStart));
            $last = new \DateTime(min($l['date_to'], $periodEnd));
            while ($cur <= $last) {
                $d = $cur->format('Y-m-d');
                if (in_array($l['leave_type'], ['unpaid', 'unpaid_short', 'unpaid_long'], true)) {
                    $unpaidLeave[$d] = true;
                } else {
                    $paidLeave[$d] = true;
                }
                $cur->modify('+1 day');
            }
        }

        // المرور على أيام الفترة لتحديد المتوقع والغياب
        $expected = 0;
        $absence = 0;
        $leaveDays = 0;
        $unpaidDays = 0;
        $realToday = date('Y-m-d');
        $cur = new \DateTime($effectiveStart);
        $last = new \DateTime($periodEnd);
        while ($cur <= $last) {
            $date = $cur->format('Y-m-d');
            $dow = (int) $cur->format('w');
            $cur->modify('+1 day');
            // اليوم الحالي (اليوم نفسه) لا يُحتسب متوقعاً/غياباً حتى ينتهي فعلياً
            if ($date === $realToday) {
                continue;
            }
            if (isset($holidayMap[$date]) || isset($weeklyHolidays[$dow])) {
                continue;
            }
            // تطبيق يوم العطلة الأسبوعية الخاص بالموظف (إذا حُدد)
            if ($employeeWeeklyOff !== null && $dow === $employeeWeeklyOff) {
                continue;
            }
            $shift = self::getEmployeeShift($employeeId, $date);
            if (!$shift || empty($shift['is_work_day'])) {
                continue;
            }
            $expected++;
            if (isset($presentDates[$date])) {
                continue;
            }
            if (isset($paidLeave[$date])) {
                $leaveDays++;
            } elseif (isset($unpaidLeave[$date])) {
                $unpaidDays++;
            } else {
                $absence++;
            }
        }

        // أيام الدوام الفعلية = كل أيام الحضور الفعلي المسجّلة بالبصمة (بما فيها الدوام في يوم العطلة الأسبوعية إن وُجد،
        // لأنه دوام فعلي يُحتسب ويُدفع أجره وليس مستثنى)؛ يطابق هذا عدد أيام البصمة الظاهر في صفحة التقارير.
        $workingDays = count($presentDates);

        $result['expected_days'] = $expected;
        $result['working_days'] = $workingDays;
        $result['leave_days'] = $leaveDays;
        $result['unpaid_leave_days'] = $unpaidDays;
        $result['absence_days'] = $absence;
        $result['deducted_days'] = $absence + $unpaidDays;
        return $result;
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

    /**
     * حساب تأمين المستحقات للموظف في فترة معينة.
     * يستقطع 25% من الراتب الأساسي في شهرين حسب تاريخ المباشرة.
     */
    /**
     * تأمين المستحقات: يُحتسب حصرياً بناءً على إعداد ملف الموظف (نسبة insurance_percentage وعدد مرات insurance_occurrences).
     * القيمة الافتراضية لمن ليس له إعداد يدوي هي 0% (لا يُستقطع شيءً تلقائياً ما لم تُغيّر يدوياً).
     * كل شهر: المبلغ = الراتب الأساسي × النسبة%، ويتوقف تلقائياً بعد استهلاك عدد المرات المحدد (باستخدام occurrence_count للسماح بتسويات دفعة واحدة عند زيادة الراتب).
     */
    public static function calculateSecurityDeposit(int $employeeId, string $period, float $base, ?int $payrollId = null): array
    {
        $employee = Database::query("SELECT insurance_percentage, insurance_occurrences FROM employees WHERE id = ?", [$employeeId])->fetch();
        $percentage = (float) ($employee['insurance_percentage'] ?? 0);
        $occurrences = (int) ($employee['insurance_occurrences'] ?? 0);
        if ($percentage <= 0 || $occurrences <= 0) {
            return ['amount' => 0.0, 'occurrence_count' => 1];
        }
        // إذا كان لهذه الفترة سجل موجود بالفعل (مثلاً تسوية دفعة واحدة بسبب زيادة راتب من handleSecurityDepositOnSalaryIncrease)، يُعاد مبلغه وعدد مراته كما هما دون إعادة الحساب
        if ($payrollId) {
            $existing = Database::query("SELECT amount, occurrence_count FROM employee_security_deposits WHERE employee_id = ? AND payroll_id = ?", [$employeeId, $payrollId])->fetch();
            if ($existing) {
                return ['amount' => (float) $existing['amount'], 'occurrence_count' => (int) $existing['occurrence_count']];
            }
        }
        // تسوية معلقة (لم تُربط بمسير راتب بعد) لنفس الفترة بسبب زيادة راتب: تُعاد مبلغها وعدد مراتها كما هما
        $pendingSettlement = Database::query("SELECT amount, occurrence_count FROM employee_security_deposits WHERE employee_id = ? AND period = ? AND payroll_id IS NULL", [$employeeId, $period])->fetch();
        if ($pendingSettlement) {
            return ['amount' => (float) $pendingSettlement['amount'], 'occurrence_count' => (int) $pendingSettlement['occurrence_count']];
        }
        // عدد المرات المستهلكة فعلياً = مجموع occurrence_count للفترات السابقة (قد تتجاوز 1 للفترة الواحدة إذا تمت تسوية كامل المتبقي دفعة واحدة عند زيادة الراتب)
        $usedCount = (int) Database::query(
            "SELECT COALESCE(SUM(occurrence_count),0) FROM employee_security_deposits WHERE employee_id = ? AND period < ?",
            [$employeeId, $period]
        )->fetchColumn();
        if ($usedCount >= $occurrences) {
            return ['amount' => 0.0, 'occurrence_count' => 1];
        }
        return ['amount' => round($base * $percentage / 100, 2), 'occurrence_count' => 1];
    }

    /** إجمالي رصيد التأمينات المحبوسة للموظف */
    public static function getSecurityDepositBalance(int $employeeId): float
    {
        return (float) Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM employee_security_deposits WHERE employee_id = ? AND status = 'held'",
            [$employeeId]
        )->fetchColumn();
    }



    /** هل الموظف معفيّ من استقطاع خدمات السكن؟ */
    public static function isHousingExempt(int $employeeId): bool
    {
        $v = Database::query("SELECT housing_exempt FROM employees WHERE id = ?", [$employeeId])->fetchColumn();
        return !empty($v);
    }

    /** يُعيد قيمة استقطاع السكن الفعلية مع مراعاة إعفاء الموظف (صفر دائماً إذا كان معفياً) */
    public static function effectiveHousingDeduction(int $employeeId, float $requested): float
    {
        return self::isHousingExempt($employeeId) ? 0.0 : $requested;
    }

    /**
     * مزامنة حقل "نوع السكن" في ملف الموظف مع وحدة إدارة السكن (employee_housing_assignments):
     * اختيار أي سكن غير "سكن خارجي" يُظهر الموظف في قائمة "الموظفون المسكّنون حالياً" بصفحة إدارة السكن.
     */
    public static function syncHousingAssignment(int $employeeId, ?string $housingType, float $deductionAmount): void
    {
        // يُحفظ المبلغ الفعلي دائماً في وحدة السكن/ملف الموظف بغض النظر عن حالة الإعفاء، ويُطبّق الإعفاء فقط عند احتساب الراتب (calculatePayrollComponents)
        // لضمان عودة الاستقطاع بالمبلغ الصحيح فور رفع الإعفاء دون فقدان القيمة المحددة
        $active = Database::query(
            "SELECT id, site_id FROM employee_housing_assignments WHERE employee_id = ? AND status = 'active'",
            [$employeeId]
        )->fetch();

        // بدون سكن أو "سكن خارجي" (على نفقته الخاص) => إنهاء أي تسكين نشط حالي في وحدة السكن
        if (!$housingType || $housingType === '' || $housingType === 'external') {
            if ($active) {
                Database::query(
                    "UPDATE employee_housing_assignments SET status = 'ended', ended_date = ? WHERE id = ?",
                    [date('Y-m-d'), $active['id']]
                );
            }
            return;
        }

        $site = Database::query("SELECT id FROM housing_sites WHERE site_key = ?", [$housingType])->fetch();
        if (!$site) return;

        if ($active && (int)$active['site_id'] === (int)$site['id']) {
            // نفس الموقع الحالي: تحديث مبلغ الاستقطاع فقط دون إنشاء تسكين جديد
            Database::query(
                "UPDATE employee_housing_assignments SET deduction_amount = ? WHERE id = ?",
                [$deductionAmount, $active['id']]
            );
            return;
        }

        // تغيّر الموقع أو لا يوجد تسكين سابق: إنهاء القديم (إن وجد) وإنشاء تسكين جديد على مستوى الموقع
        if ($active) {
            Database::query(
                "UPDATE employee_housing_assignments SET status = 'ended', ended_date = ? WHERE id = ?",
                [date('Y-m-d'), $active['id']]
            );
        }
        Database::query(
            "INSERT INTO employee_housing_assignments (employee_id, site_id, deduction_amount, assigned_date, status, created_by)
             VALUES (?, ?, ?, ?, 'active', ?)",
            [$employeeId, $site['id'], $deductionAmount, date('Y-m-d'), (int)(Auth::id() ?? 0)]
        );
    }

    /** سجل استقطاعات وإرجاعات تأمين المستحقات للموظف */
    public static function getEmployeeSecurityDeposits(int $employeeId): array
    {
        return Database::query(
            "SELECT d.*, p.period AS payroll_period, u.full_name AS refunded_by_name
             FROM employee_security_deposits d
             LEFT JOIN payrolls p ON p.id = d.payroll_id
             LEFT JOIN users u ON u.id = d.refunded_by
             WHERE d.employee_id = ? ORDER BY d.created_at DESC",
            [$employeeId]
        )->fetchAll();
    }

    /**
     * معالجة زيادة راتب موظف لديه جدول استقطاع تأمين مستحقات سارٍ لم يكتمل بعد (نسبة/مرات ملف الموظف):
     * يعاد احتساب المبلغ المتبقي لكامل الأشهر المتبقية وفق الراتب الجديد، ويُخصم بالكامل دفعة واحدة
     * في نفس شهر الزيادة (يُغلق جدول الاستقطاع فوراً بدلاً من الاستمرار بالمبلغ القديم الأقل).
     * لا يفعل شيئاً إذا لم تكن الزيادة حقيقية (الراتب الجديد أقل أو يساوي القديم) أو لم يكن هناك جدول استقطاع فعّال لم يكتمل بعد.
     */
    public static function handleSecurityDepositOnSalaryIncrease(int $employeeId, float $oldBaseSalary, float $newBaseSalary, string $period): void
    {
        if ($newBaseSalary <= $oldBaseSalary) {
            return;
        }
        $employee = Database::query(
            "SELECT insurance_percentage, insurance_occurrences FROM employees WHERE id = ?",
            [$employeeId]
        )->fetch();
        $percentage = (float) ($employee['insurance_percentage'] ?? 0);
        $occurrences = (int) ($employee['insurance_occurrences'] ?? 0);
        if ($percentage <= 0 || $occurrences <= 0) {
            return;
        }
        $usedCount = (int) Database::query(
            "SELECT COALESCE(SUM(occurrence_count),0) FROM employee_security_deposits WHERE employee_id = ? AND period < ?",
            [$employeeId, $period]
        )->fetchColumn();
        $remainingOccurrences = $occurrences - $usedCount;
        if ($remainingOccurrences <= 0) {
            return; // جدول استقطاع تأمين المستحقات مكتمل بالفعل
        }

        // المبلغ المتبقي المستحق بالكامل وفق الراتب الجديد = عدد الأشهر المتبقية × (الراتب الجديد × النسبة)
        $lumpSum = round($remainingOccurrences * $newBaseSalary * $percentage / 100, 2);
        if ($lumpSum <= 0) {
            return;
        }

        $note = sprintf(
            'تسوية استقطاع تأمين مستحقات بسبب زيادة الراتب من %s إلى %s — تحصيل %d شهر متبقٍّ دفعة واحدة',
            number_format($oldBaseSalary, 0), number_format($newBaseSalary, 0), $remainingOccurrences
        );

        // يُربط payroll_id لاحقاً بمسير الراتب الفعلي عند توليده عبر syncSecurityDeposit()
        $existingForPeriod = Database::query(
            "SELECT id FROM employee_security_deposits WHERE employee_id = ? AND period = ? AND payroll_id IS NULL",
            [$employeeId, $period]
        )->fetch();
        if ($existingForPeriod) {
            Database::query(
                "UPDATE employee_security_deposits SET amount = ?, occurrence_count = ?, is_settlement = 1, base_salary_snapshot = ?, reason = ? WHERE id = ?",
                [$lumpSum, $remainingOccurrences, $newBaseSalary, $note, (int)$existingForPeriod['id']]
            );
        } else {
            Database::query(
                "INSERT INTO employee_security_deposits (employee_id, payroll_id, period, amount, occurrence_count, is_settlement, base_salary_snapshot, reason) VALUES (?,NULL,?,?,?,1,?,?)",
                [$employeeId, $period, $lumpSum, $remainingOccurrences, $newBaseSalary, $note]
            );
        }
    }


    /**
     * حساب التيبس الشهري للموظف في فترة محددة (متناسب مع أيام الدوام).
     * إذا لم تُحدد أيام الدوام في السجل (0) تُستخدم الأيام الفعلية من البصمات إن توفرت.
     */
    public static function calculateMonthlyTips(int $employeeId, string $period, ?int $actualWorkingDays = null): float
    {
        $record = Database::query(
            "SELECT full_amount, working_days FROM employee_monthly_tips WHERE employee_id = ? AND period = ?",
            [$employeeId, $period]
        )->fetch();
        if (!$record) {
            return 0.0;
        }
        $full = (float) $record['full_amount'];
        $days = (int) $record['working_days'];
        if ($days <= 0 && $actualWorkingDays !== null && $actualWorkingDays > 0) {
            $days = $actualWorkingDays;
        }
        if ($days <= 0 || $days > 30) {
            $days = 30;
        }
        return (float) round($full / 30 * $days, -3);
    }

    /** سجل التيبس الشهري للموظف */
    public static function getEmployeeMonthlyTipsRecords(int $employeeId): array
    {
        return Database::query(
            "SELECT t.*, u.full_name AS created_by_name
             FROM employee_monthly_tips t
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.employee_id = ? ORDER BY t.period DESC",
            [$employeeId]
        )->fetchAll();
    }

    /** تعيين/تحديث مبلغ التيبس الشهري للموظف */
    public static function setEmployeeMonthlyTips(
        int $employeeId,
        string $period,
        float $fullAmount,
        int $workingDays,
        ?string $notes = null,
        ?int $userId = null
    ): bool {
        // 0 = احتساب الأيام تلقائيًا من البصمات عند توليد الراتب
        if ($workingDays < 0 || $workingDays > 30) {
            $workingDays = 30;
        }
        $prorated = (float) round($fullAmount / 30 * ($workingDays > 0 ? $workingDays : 30), -3);
        $existing = Database::query(
            "SELECT id FROM employee_monthly_tips WHERE employee_id = ? AND period = ?",
            [$employeeId, $period]
        )->fetch();
        if ($existing) {
            Database::query(
                "UPDATE employee_monthly_tips SET full_amount = ?, working_days = ?, prorated_amount = ?, notes = ?, created_by = ? WHERE id = ?",
                [$fullAmount, $workingDays, $prorated, $notes, $userId, (int)$existing['id']]
            );
        } else {
            Database::query(
                "INSERT INTO employee_monthly_tips (employee_id, period, full_amount, working_days, prorated_amount, notes, created_by) VALUES (?,?,?,?,?,?,?)",
                [$employeeId, $period, $fullAmount, $workingDays, $prorated, $notes, $userId]
            );
        }
        return true;
    }

    /** حذف سجل تيبس شهري */
    public static function deleteEmployeeMonthlyTip(int $recordId): bool
    {
        Database::query("DELETE FROM employee_monthly_tips WHERE id = ?", [$recordId]);
        return true;
    }

    /** حساب الضمان الاجتماعي الشهري للموظف في فترة محددة */
    public static function calculateMonthlySocialSecurity(int $employeeId, string $period): float
    {
        return (float) Database::query(
            "SELECT COALESCE(amount,0) FROM employee_monthly_social_security WHERE employee_id = ? AND period = ?",
            [$employeeId, $period]
        )->fetchColumn();
    }

    /** سجل الضمان الاجتماعي الشهري للموظف */
    public static function getEmployeeMonthlySocialSecurityRecords(int $employeeId): array
    {
        return Database::query(
            "SELECT s.*, u.full_name AS created_by_name
             FROM employee_monthly_social_security s
             LEFT JOIN users u ON u.id = s.created_by
             WHERE s.employee_id = ? ORDER BY s.period DESC",
            [$employeeId]
        )->fetchAll();
    }

    /** تعيين/تحديث مبلغ الضمان الاجتماعي الشهري للموظف */
    public static function setEmployeeMonthlySocialSecurity(
        int $employeeId,
        string $period,
        float $amount,
        ?string $notes = null,
        ?int $userId = null
    ): bool {
        $existing = Database::query(
            "SELECT id FROM employee_monthly_social_security WHERE employee_id = ? AND period = ?",
            [$employeeId, $period]
        )->fetch();
        if ($existing) {
            Database::query(
                "UPDATE employee_monthly_social_security SET amount = ?, notes = ?, created_by = ? WHERE id = ?",
                [$amount, $notes, $userId, (int)$existing['id']]
            );
        } else {
            Database::query(
                "INSERT INTO employee_monthly_social_security (employee_id, period, amount, notes, created_by) VALUES (?,?,?,?,?)",
                [$employeeId, $period, $amount, $notes, $userId]
            );
        }
        return true;
    }

    /** حذف سجل ضمان اجتماعي شهري */
    public static function deleteEmployeeMonthlySocialSecurity(int $recordId): bool
    {
        Database::query("DELETE FROM employee_monthly_social_security WHERE id = ?", [$recordId]);
        return true;
    }

    /** خطط الأعداد حسب الوظيفة (والقسم المشتق منها) والفترة مع الأعداد الفعلية */
    public static function getHeadcountPlans(string $period): array
    {
        $plans = Database::query(
            "SELECT h.*, d.name AS department_name, j.title AS job_title,
                    (SELECT COUNT(*) FROM employees e
                      WHERE e.file_status = 'open'
                        AND ((h.job_id IS NOT NULL AND e.job_id = h.job_id)
                             OR (h.job_id IS NULL AND e.department_id = h.department_id))
                    ) AS actual_count,
                    (SELECT COALESCE(SUM(p.net_salary),0) FROM payrolls p JOIN employees e ON e.id = p.employee_id
                      WHERE p.period = h.period
                        AND ((h.job_id IS NOT NULL AND e.job_id = h.job_id)
                             OR (h.job_id IS NULL AND e.department_id = h.department_id))
                    ) AS actual_net_salary
             FROM hr_headcount_plans h
             JOIN departments d ON d.id = h.department_id
             LEFT JOIN jobs j ON j.id = h.job_id
             WHERE h.period = ?
             ORDER BY d.name, j.title",
            [$period]
        )->fetchAll();
        return $plans;
    }

    public static function getHeadcountPlan(int $id): ?array
    {
        $row = Database::query(
            "SELECT h.*, d.name AS department_name, j.title AS job_title FROM hr_headcount_plans h
             JOIN departments d ON d.id = h.department_id
             LEFT JOIN jobs j ON j.id = h.job_id
             WHERE h.id = ?",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    /** حفظ خطة الأعداد حسب الوظيفة؛ يُشتق القسم تلقائياً من ربط الوظيفة بالقسم */
    public static function saveHeadcountPlan(int $jobId, string $period, int $plannedCount, float $budgetSalary, ?string $notes = null, ?int $userId = null): bool
    {
        $job = Database::query("SELECT id, department_id FROM jobs WHERE id = ? AND is_active = 1", [$jobId])->fetch();
        if (!$job || empty($job['department_id'])) {
            return false;
        }
        $departmentId = (int)$job['department_id'];
        $existing = Database::query(
            "SELECT id FROM hr_headcount_plans WHERE job_id = ? AND period = ?",
            [$jobId, $period]
        )->fetch();
        if ($existing) {
            Database::query(
                "UPDATE hr_headcount_plans SET department_id = ?, planned_count = ?, budget_salary = ?, notes = ?, created_by = ? WHERE id = ?",
                [$departmentId, $plannedCount, $budgetSalary, $notes, $userId, (int)$existing['id']]
            );
        } else {
            Database::query(
                "INSERT INTO hr_headcount_plans (department_id, job_id, period, planned_count, budget_salary, notes, created_by) VALUES (?,?,?,?,?,?,?)",
                [$departmentId, $jobId, $period, $plannedCount, $budgetSalary, $notes, $userId]
            );
        }
        return true;
    }

    public static function deleteHeadcountPlan(int $id): bool
    {
        Database::query("DELETE FROM hr_headcount_plans WHERE id = ?", [$id]);
        return true;
    }



    /** حساب تذاكر السفر الشهرية للموظف في فترة محددة */
    public static function calculateMonthlyTravelTickets(int $employeeId, string $period): float
    {
        return (float) Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM employee_travel_tickets WHERE employee_id = ? AND period = ? AND status = 'active'",
            [$employeeId, $period]
        )->fetchColumn();
    }

    /** سجل تذاكر السفر للموظف */
    public static function getEmployeeTravelTickets(int $employeeId, int $limit = 50): array
    {
        return Database::query(
            "SELECT t.*, u.full_name AS created_by_name FROM employee_travel_tickets t
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.employee_id = ? ORDER BY t.period DESC, t.created_at DESC LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    /** إضافة تذكرة سفر للموظف */
    public static function addEmployeeTravelTicket(
        int $employeeId,
        string $period,
        float $amount,
        ?string $departureDate = null,
        ?string $returnDate = null,
        ?string $travelMethod = null,
        ?string $notes = null,
        ?int $userId = null
    ): bool {
        Database::query(
            "INSERT INTO employee_travel_tickets (employee_id, period, departure_date, return_date, duration, travel_method, amount, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [
                $employeeId, $period, $departureDate, $returnDate,
                ($departureDate && $returnDate ? max(0, (int)date_diff(date_create($departureDate), date_create($returnDate))->format('%a')) : null),
                $travelMethod, $amount, $notes, $userId
            ]
        );
        return true;
    }

    /** حذف تذكرة سفر */
    public static function deleteEmployeeTravelTicket(int $recordId): bool
    {
        Database::query("DELETE FROM employee_travel_tickets WHERE id = ?", [$recordId]);
        return true;
    }

    /** حساب التحميلات الشهرية للموظف في فترة محددة (فئة التحميلات فقط) */
    public static function calculateMonthlyLoadings(int $employeeId, string $period, string $category = 'تحميلات'): float
    {
        return (float) Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM employee_monthly_loadings WHERE employee_id = ? AND period = ? AND category = ?",
            [$employeeId, $period, $category]
        )->fetchColumn();
    }

    /** سجل التحميلات الشهرية للموظف */
    public static function getEmployeeLoadings(int $employeeId, int $limit = 50): array
    {
        return Database::query(
            "SELECT l.*, u.full_name AS created_by_name FROM employee_monthly_loadings l
             LEFT JOIN users u ON u.id = l.created_by
             WHERE l.employee_id = ? ORDER BY l.period DESC, l.created_at DESC LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    /** تعيين/تحديث مبلغ تحميل شهري للموظف (فئة واحدة لكل فترة) */
    public static function setEmployeeMonthlyLoading(
        int $employeeId,
        string $period,
        string $category,
        float $amount,
        ?string $notes = null,
        ?int $userId = null
    ): bool {
        $existing = Database::query(
            "SELECT id FROM employee_monthly_loadings WHERE employee_id = ? AND period = ? AND category = ?",
            [$employeeId, $period, $category]
        )->fetch();
        if ($existing) {
            Database::query(
                "UPDATE employee_monthly_loadings SET amount = ?, notes = ?, created_by = ? WHERE id = ?",
                [$amount, $notes, $userId, (int)$existing['id']]
            );
        } else {
            Database::query(
                "INSERT INTO employee_monthly_loadings (employee_id, period, category, amount, notes, created_by) VALUES (?,?,?,?,?,?)",
                [$employeeId, $period, $category, $amount, $notes, $userId]
            );
        }
        return true;
    }

    /** حذف سجل تحميل شهري */
    public static function deleteEmployeeMonthlyLoading(int $recordId): bool
    {
        Database::query("DELETE FROM employee_monthly_loadings WHERE id = ?", [$recordId]);
        return true;
    }


    /** مزامنة سجل تأمين المستحقات مع مرتب الفترة */
    public static function syncSecurityDeposit(int $payrollId, int $employeeId, string $period, float $amount, int $occurrenceCount = 1): void
    {
        $existing = Database::query(
            "SELECT id FROM employee_security_deposits WHERE employee_id = ? AND payroll_id = ?",
            [$employeeId, $payrollId]
        )->fetch();
        // تسوية معلقة (payroll_id IS NULL) لنفس الفترة أُنشئت مسبقاً من handleSecurityDepositOnSalaryIncrease() قبل توليد مسير الراتب
        $pending = Database::query(
            "SELECT id FROM employee_security_deposits WHERE employee_id = ? AND period = ? AND payroll_id IS NULL",
            [$employeeId, $period]
        )->fetch();
        if ($amount <= 0) {
            if ($existing) {
                Database::query(
                    "DELETE FROM employee_security_deposits WHERE id = ? AND status = 'held'",
                    [(int) $existing['id']]
                );
            }
            if ($pending) {
                Database::query("DELETE FROM employee_security_deposits WHERE id = ? AND status = 'held'", [(int) $pending['id']]);
            }
            return;
        }
        if ($existing) {
            Database::query(
                "UPDATE employee_security_deposits SET amount = ?, period = ?, occurrence_count = ? WHERE id = ? AND status = 'held'",
                [$amount, $period, $occurrenceCount, (int) $existing['id']]
            );
        } elseif ($pending) {
            // ربط التسوية المعلقة بمسير الراتب الفعلي الآن بدلاً من إنشاء سجل مكرر
            Database::query(
                "UPDATE employee_security_deposits SET payroll_id = ?, amount = ?, occurrence_count = ? WHERE id = ? AND status = 'held'",
                [$payrollId, $amount, $occurrenceCount, (int) $pending['id']]
            );
        } else {
            Database::query(
                "INSERT INTO employee_security_deposits (employee_id, payroll_id, period, amount, occurrence_count, reason) VALUES (?,?,?,?,?,?)",
                [$employeeId, $payrollId, $period, $amount, $occurrenceCount, 'تأمين مستحقات']
            );
        }
    }

    /** إرجاع تأمين المستحقات منخفضة كمكافأة تلقائية */
    public static function refundSecurityDeposit(int $employeeId, string $reason, ?int $userId = null): bool
    {
        $balance = self::getSecurityDepositBalance($employeeId);
        if ($balance <= 0) {
            return false;
        }
        $period = date('Y-m');
        $note = 'إرجاع تأمين مستحقات: ' . $reason;
        Database::query(
            "INSERT INTO hr_adjustments (employee_id, adjustment_type, amount, effective_date, period, reason, status, requested_by, approved_by, approved_at) VALUES (?, 'bonus', ?, CURDATE(), ?, ?, 'approved', ?, ?, NOW())",
            [$employeeId, $balance, $period, $note, $userId, $userId]
        );
        $adjustmentId = (int) Database::lastInsertId();
        Database::query(
            "UPDATE employee_security_deposits SET status = 'refunded', refunded_at = NOW(), refunded_by = ? WHERE employee_id = ? AND status = 'held'",
            [$userId, $employeeId]
        );
        Helpers::queueSync('hr_adjustments', $adjustmentId, 'insert');
        return true;
    }

    /** توليد مرتب للموظف في فترة محددة */
    public static function generatePayroll(int $employeeId, string $period, ?int $generatedBy = null): int
    {
        if (!self::isEmployeeFileOpen($employeeId)) {
            return 0;
        }
        self::ensurePayrollDeficitColumns();
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
                "UPDATE payrolls SET base_salary = ?, raises = ?, salary_reduction = ?, bonuses = ?, allowances = ?, overtime = ?, overtime_days = ?, deductions = ?, penalties = ?, advances = ?, long_term_advances = ?, security_deposit = ?, rounding_adjustment = ?, tax_amount = ?, absence_days = ?, absence_deduction = ?, expected_days = ?, working_days = ?, leave_days = ?, unpaid_leave_days = ?, total_earnings = ?, total_deductions = ?, net_salary = ?, travel_tickets = ?, loadings = ?, tips = ?, social_security = ?, deficit_amount = ?, tips_addition = ?, work_days_settlement = ?, housing_deduction = ?, generated_by = ?
                 WHERE id = ?",
                [$comp['base_salary'], $comp['raises'], $comp['salary_reduction'] ?? 0, $comp['bonuses'], $comp['allowances'], $comp['overtime'], $comp['overtime_days'], $comp['deductions'], $comp['penalties'], $comp['advances'], $comp['long_term_advances'], $comp['security_deposit'], $comp['rounding_adjustment'], $comp['tax_amount'], $comp['absence_days'], $comp['absence_deduction'], $comp['expected_days'], $comp['working_days'], $comp['leave_days'], $comp['unpaid_leave_days'], $comp['total_earnings'], $comp['total_deductions'], $comp['net_salary'], $comp['travel_tickets'], $comp['loadings'], $comp['tips'], $comp['social_security'], $comp['deficit_amount'], $comp['tips_addition'], $comp['work_days_settlement'], $comp['housing_deduction'], $generatedBy, (int)$exists['id']]
            );
            $payrollId = (int) $exists['id'];
        } else {
            Database::query(
                "INSERT INTO payrolls (employee_id, period, base_salary, raises, salary_reduction, bonuses, allowances, overtime, overtime_days, deductions, penalties, advances, long_term_advances, security_deposit, rounding_adjustment, tax_amount, absence_days, absence_deduction, expected_days, working_days, leave_days, unpaid_leave_days, total_earnings, total_deductions, net_salary, travel_tickets, loadings, tips, social_security, deficit_amount, tips_addition, work_days_settlement, housing_deduction, generated_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$employeeId, $period, $comp['base_salary'], $comp['raises'], $comp['salary_reduction'] ?? 0, $comp['bonuses'], $comp['allowances'], $comp['overtime'], $comp['overtime_days'], $comp['deductions'], $comp['penalties'], $comp['advances'], $comp['long_term_advances'], $comp['security_deposit'], $comp['rounding_adjustment'], $comp['tax_amount'], $comp['absence_days'], $comp['absence_deduction'], $comp['expected_days'], $comp['working_days'], $comp['leave_days'], $comp['unpaid_leave_days'], $comp['total_earnings'], $comp['total_deductions'], $comp['net_salary'], $comp['travel_tickets'], $comp['loadings'], $comp['tips'], $comp['social_security'], $comp['deficit_amount'], $comp['tips_addition'], $comp['work_days_settlement'], $comp['housing_deduction'], $generatedBy]
            );
            $payrollId = (int) Database::lastInsertId();
        }
        self::syncPayrollAllowances($payrollId, $employeeId);
        self::markRepaymentsPaid($employeeId, $period, $payrollId);
        self::syncSecurityDeposit($payrollId, $employeeId, $period, $comp['security_deposit'], (int)($comp['security_deposit_occurrence_count'] ?? 1));
        self::syncPayrollDeficitAdvance($payrollId, $employeeId, $period, (float) $comp['deficit_amount']);
        return $payrollId;
    }

    /**
     * مزامنة سلفة العجز التلقائية: عندما يكون صافي الراتب بالسالب يُصفَّر الصافي (0)
     * ويُنشأ سجل سلفة على الموظف بقيمة العجز، تُخصَم تلقائياً من راتب الشهر التالي.
     */
    public static function syncPayrollDeficitAdvance(int $payrollId, int $employeeId, string $period, float $deficitAmount): void
    {
        self::ensurePayrollDeficitColumns();
        $existing = Database::query(
            "SELECT * FROM salary_advances WHERE source_payroll_id = ?",
            [$payrollId]
        )->fetch();
        $nextPeriod = date('Y-m', strtotime($period . '-01 +1 month'));
        $reason = 'ترحيل عجز راتب فترة ' . $period . ' (سلفة تلقائية)';

        if ($deficitAmount <= 0) {
            // إذا زال العجز عند إعادة الاحتساب ولم يُسدَّد منه شيء بعد، نلغي السلفة التلقائية
            if ($existing && (float) $existing['paid_amount'] <= 0) {
                Database::query("DELETE FROM advance_repayment_schedule WHERE advance_id = ?", [(int) $existing['id']]);
                Database::query("DELETE FROM salary_advances WHERE id = ?", [(int) $existing['id']]);
            }
            return;
        }

        if ($existing) {
            $advanceId = (int) $existing['id'];
            $paidAmount = (float) $existing['paid_amount'];
            Database::query(
                "UPDATE salary_advances SET amount = ?, remaining_amount = ?, reason = ? WHERE id = ?",
                [$deficitAmount, max(0, $deficitAmount - $paidAmount), $reason, $advanceId]
            );
            Database::query(
                "UPDATE advance_repayment_schedule SET period = ?, amount = ? WHERE advance_id = ? AND status = 'pending'",
                [$nextPeriod, $deficitAmount, $advanceId]
            );
        } else {
            Database::query(
                "INSERT INTO salary_advances
                    (employee_id, amount, advance_type, installments, reason, status, paid_at, paid_amount, remaining_amount, request_source, source_payroll_id)
                 VALUES (?, ?, 'short', 1, ?, 'paid', NOW(), 0, ?, 'system', ?)",
                [$employeeId, $deficitAmount, $reason, $deficitAmount, $payrollId]
            );
            $advanceId = (int) Database::lastInsertId();
            Database::query(
                "INSERT INTO advance_repayment_schedule (advance_id, period, amount, status) VALUES (?, ?, ?, 'pending')",
                [$advanceId, $nextPeriod, $deficitAmount]
            );
        }
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
            // التسويات المالية تُحتسب ضمن مسير الرواتب الشهري؛
            // قيد استحقاق الراتب هو المرجع المحاسبي الوحيد لتجنب المضاعفة.
            // إذا كان هناك مسير راتب (مسودة) مولّد مسبقاً لنفس فترة التسوية، يُعاد احتسابه
            // فوراً ليعكس التسوية الجديدة في الراتب المعروض فور الاعتماد مباشرة
            // (الرواتب المعتمدة/المدفوعة لا تُمس هنا، يجب إلغاء الاعتماد أولاً)
            $adjPeriod = $adj['period'] ?: date('Y-m', strtotime((string)$adj['effective_date']));
            $draftPayroll = Database::query(
                "SELECT id FROM payrolls WHERE employee_id = ? AND period = ? AND status = 'draft'",
                [(int)$adj['employee_id'], $adjPeriod]
            )->fetch();
            if ($draftPayroll) {
                self::generatePayroll((int)$adj['employee_id'], $adjPeriod, $approverId);
            }

            // ملاحظة: "تخفيض راتب" لا يُعدّل base_salary نهائياً في ملف الموظف؛
            // يُحتسب ضمن الأساس المعدّل في calculatePayrollComponents() لشهره المحدد (period/effective_date) حصرياً دون تراكم للأشهر اللاحقة،
            // وإعادة توليد المسودة أعلاه تعكسه فوراً (والإلغاء يُخرجه تلقائياً من الاحتساب)
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
            $typeLabels = ['bonus'=>'مكافأة','deduction'=>'خصم','raise'=>'زيادة','penalty'=>'جزاء','tips_addition'=>'إضافي تيبس','salary_reduction'=>'تخفيض راتب','work_days_settlement'=>'تسوية أيام دوام'];
            Helpers::notify(
                (int)$emp['user_id'],
                'نتيجة طلب تسوية',
                'تم ' . ($decision === 'approved' ? 'الموافقة' : 'الرفض') . ' على طلب ' . ($typeLabels[$adj['adjustment_type']] ?? $adj['adjustment_type']),
                '/hr/adjustments'
            );
        }
        return true;
    }

    /**
     * إلغاء تسوية مالية معتمدة سابقاً (مكافأة/خصم/زيادة/جزاء...) قبل اعتماد راتب فترتها نهائياً.
     * يعكس أي قيد محاسبي منشور مباشرة على التسوية نفسها (مثل خصم تلف/فقد عهدة)، ويعيد توليد
     * كشف الراتب تلقائياً إن كان لا يزال مسودة ليعكس إزالة التسوية فوراً. لا يحذف السجل نهائياً
     * (حذف ناعم) للحفاظ على أثر التدقيق.
     */
    public static function cancelAdjustment(int $adjustmentId, string $reason, int $userId): array
    {
        $adj = Database::query("SELECT * FROM hr_adjustments WHERE id = ?", [$adjustmentId])->fetch();
        if (!$adj) {
            return ['status' => 'error', 'message' => 'التسوية غير موجودة'];
        }
        if ($adj['status'] !== 'approved') {
            return ['status' => 'error', 'message' => 'لا يمكن إلغاء إلا تسوية معتمدة'];
        }
        if (trim($reason) === '') {
            return ['status' => 'error', 'message' => 'سبب الإلغاء مطلوب'];
        }
        $period = $adj['period'] ?: date('Y-m', strtotime((string)$adj['effective_date']));
        $payroll = Database::query(
            "SELECT id, status FROM payrolls WHERE employee_id = ? AND period = ?",
            [(int)$adj['employee_id'], $period]
        )->fetch();
        if ($payroll && $payroll['status'] !== 'draft') {
            $stateLabel = $payroll['status'] === 'paid' ? 'مصروف بالفعل' : 'معتمد';
            return ['status' => 'error', 'message' => "لا يمكن الإلغاء لأن كشف راتب هذه الفترة $stateLabel؛ يجب إلغاء اعتماد الراتب أولاً"];
        }
        Database::beginTransaction();
        try {
            if (!empty($adj['journal_entry_id'])) {
                AccountingService::reverse((int)$adj['journal_entry_id'], 'إلغاء تسوية مالية رقم ' . $adjustmentId);
            }
            Database::query(
                "UPDATE hr_adjustments SET status = 'cancelled', cancelled_by = ?, cancelled_at = NOW(), cancel_reason = ? WHERE id = ?",
                [$userId, $reason, $adjustmentId]
            );
            Helpers::queueSync('hr_adjustments', $adjustmentId, 'update', ['status' => 'cancelled']);
            if ($payroll) {
                self::generatePayroll((int)$adj['employee_id'], $period, $userId);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
        $emp = Database::query("SELECT user_id, full_name FROM employees WHERE id = ?", [(int)$adj['employee_id']])->fetch();
        if ($emp && $emp['user_id']) {
            $typeLabels = ['bonus'=>'مكافأة','deduction'=>'خصم','raise'=>'زيادة','penalty'=>'جزاء','tips_addition'=>'إضافي تيبس','salary_reduction'=>'تخفيض راتب','work_days_settlement'=>'تسوية أيام دوام'];
            Helpers::notify(
                (int)$emp['user_id'],
                'إلغاء تسوية مالية',
                'تم إلغاء ' . ($typeLabels[$adj['adjustment_type']] ?? $adj['adjustment_type']) . ' المعتمدة سابقاً. السبب: ' . $reason,
                '/hr/adjustments'
            );
        }
        return ['status' => 'ok'];
    }

    /**
     * إنشاء سجل تسوية (خصم/مكافأة/...) من بيانات طلب Workflow عام.
     * يُستخدم لربط قوالب مثل deduction_request بجدول hr_adjustments.
     * @return int|null معرف hr_adjustments أو null عند نقص البيانات.
     */
    public static function createAdjustmentFromWorkflowData(array $data, int $userId, string $adjustmentType = 'deduction'): ?int
    {
        $employeeId = (int)($data['employee_id'] ?? $data['employee'] ?? $data['emp_id'] ?? $data['emp'] ?? 0);
        $amount = (float)($data['amount'] ?? $data['deduction_amount'] ?? $data['value'] ?? 0);
        if (!$employeeId || $amount <= 0) {
            return null;
        }

        $reason = (string)($data['reason'] ?? $data['notes'] ?? $data['description'] ?? '');
        $period = (string)($data['period'] ?? $data['month'] ?? '');
        $effective = (string)($data['effective_date'] ?? $data['date'] ?? $data['deduction_date'] ?? '');
        if (!$effective) {
            $effective = $period ? $period . '-01' : date('Y-m-d');
        }
        if (!$period && $effective) {
            $period = substr($effective, 0, 7);
        }

        Database::query(
            "INSERT INTO hr_adjustments
             (employee_id, adjustment_type, amount, period, effective_date, reason, status, requested_by, admin_level_required)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [
                $employeeId,
                $adjustmentType,
                $amount,
                $period ?: null,
                $effective,
                $reason,
                'pending',
                $userId,
                1,
            ]
        );
        $adjId = (int)Database::lastInsertId() ?: null;
        if ($adjId) {
            error_log("[HR Workflow] Created adjustment from workflow: id={$adjId} employee_id={$employeeId} type={$adjustmentType} amount={$amount} effective_date={$effective}");
        }
        return $adjId;
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
    public static function changeFileStatus(int $employeeId, string $newStatus, string $reason, ?int $changedBy = null, ?string $effectiveDate = null): bool
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
            if ($effectiveDate) {
                Database::query(
                    "INSERT INTO employee_file_status_logs (employee_id, old_status, new_status, reason, changed_by, created_at)
                     VALUES (?,?,?,?,?,?)",
                    [$employeeId, $employee['file_status'], $newStatus, $reason, $changedBy, $effectiveDate . ' 00:00:00']
                );
            } else {
                Database::query(
                    "INSERT INTO employee_file_status_logs (employee_id, old_status, new_status, reason, changed_by)
                     VALUES (?,?,?,?,?)",
                    [$employeeId, $employee['file_status'], $newStatus, $reason, $changedBy]
                );
            }
            // إرجاع تأمين المستحقات تلقائيًا عند إغلاق/أرشفة الملف
            if (in_array($newStatus, ['closed', 'archived'], true)) {
                self::refundSecurityDeposit($employeeId, $reason, $changedBy);
            }
            Database::commit();
            Helpers::queueSync('employees', $employeeId, 'update', ['file_status' => $newStatus]);
            return true;
        } catch (\Throwable $e) {
            Database::rollBack();
            return false;
        }
    }

    /** ========== المباشرات والإغلاقات ========== */

    /** قائمة المباشرات (الموظفون الذين تاريخ مباشرتهم ضمن الفترة) */
    public static function getStarters(string $period): array
    {
        $from = $period . '-01';
        $to = date('Y-m-t', strtotime($from));
        return Database::query(
            "SELECT e.id, e.emp_code, e.full_name, e.phone, e.nationality, e.residence, e.department, e.position,
                    e.base_salary, e.hire_date, e.file_status, e.status, b.name AS branch_name
             FROM employees e
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE e.hire_date BETWEEN ? AND ?
             ORDER BY e.hire_date, e.full_name",
            [$from, $to]
        )->fetchAll();
    }

    /** قائمة الإغلاقات (الملفات التي أُغلقت/أُرشفت ضمن الفترة) */
    public static function getClosures(string $period): array
    {
        $from = $period . '-01 00:00:00';
        $to = date('Y-m-t', strtotime($period . '-01')) . ' 23:59:59';
        return Database::query(
            "SELECT l.id AS log_id, l.new_status, l.reason, DATE(l.created_at) AS close_date,
                    e.id, e.emp_code, e.full_name, e.phone, e.department, e.position, e.base_salary, e.hire_date,
                    e.file_status, e.status, b.name AS branch_name, u.full_name AS changed_by_name
             FROM employee_file_status_logs l
             JOIN employees e ON e.id = l.employee_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN users u ON u.id = l.changed_by
             WHERE l.new_status IN ('closed','archived') AND l.created_at BETWEEN ? AND ?
             ORDER BY l.created_at, e.full_name",
            [$from, $to]
        )->fetchAll();
    }

    /**
     * استيراد صف مباشرة: إنشاء موظف جديد أو تحديث بيانات المباشرة لموظف موجود
     * @return string 'created' | 'updated'
     */
    public static function importStarterRow(array $row, ?int $userId = null): string
    {
        $code = trim((string)($row['emp_code'] ?? ''));
        $name = trim((string)($row['full_name'] ?? ''));
        $phone = trim((string)($row['phone'] ?? ''));
        $hireDate = ImportHelper::parseExcelDate($row['hire_date'] ?? null);
        if ($name === '') {
            throw new \RuntimeException('الاسم الكامل مطلوب');
        }
        if (!$hireDate) {
            throw new \RuntimeException('تاريخ المباشرة غير صالح');
        }
        $baseSalary = isset($row['base_salary']) && $row['base_salary'] !== '' ? (float)$row['base_salary'] : null;
        $nationality = trim((string)($row['nationality'] ?? '')) ?: null;
        $residence = trim((string)($row['residence'] ?? '')) ?: null;
        $departmentName = trim((string)($row['department'] ?? ''));
        $jobTitle = trim((string)($row['job'] ?? ''));
        $branchName = trim((string)($row['branch_name'] ?? ''));

        $departmentId = null;
        if ($departmentName !== '') {
            $departmentId = Database::query(
                "SELECT id FROM departments WHERE is_active = 1 AND (name = ? OR name = CONCAT('قسم ', ?)) LIMIT 1",
                [$departmentName, $departmentName]
            )->fetchColumn() ?: null;
        }
        $jobId = null;
        if ($jobTitle !== '') {
            $jobId = Database::query(
                "SELECT id FROM jobs WHERE is_active = 1 AND title = ? " . ($departmentId ? "ORDER BY (department_id = " . (int)$departmentId . ") DESC" : "") . " LIMIT 1",
                [$jobTitle]
            )->fetchColumn() ?: null;
        }
        $branchId = null;
        if ($branchName !== '') {
            $branchId = Database::query("SELECT id FROM branches WHERE name = ? LIMIT 1", [$branchName])->fetchColumn() ?: null;
        }

        $existing = null;
        if ($code !== '') {
            $existing = Database::query("SELECT * FROM employees WHERE emp_code = ? LIMIT 1", [$code])->fetch();
        }
        if (!$existing && $phone !== '') {
            $existing = Database::query("SELECT * FROM employees WHERE phone = ? LIMIT 1", [$phone])->fetch();
        }

        if ($existing) {
            $employeeId = (int)$existing['id'];
            $fields = ['hire_date = ?'];
            $params = [$hireDate];
            if ($baseSalary !== null) { $fields[] = 'base_salary = ?'; $params[] = $baseSalary; }
            if ($phone !== '') { $fields[] = 'phone = ?'; $params[] = $phone; }
            if ($nationality) { $fields[] = 'nationality = ?'; $params[] = $nationality; }
            if ($residence) { $fields[] = 'residence = ?'; $params[] = $residence; }
            if ($departmentName !== '') { $fields[] = 'department = ?'; $params[] = $departmentName; }
            if ($departmentId) { $fields[] = 'department_id = ?'; $params[] = $departmentId; }
            if ($jobTitle !== '') { $fields[] = 'position = ?'; $params[] = $jobTitle; }
            if ($jobId) { $fields[] = 'job_id = ?'; $params[] = $jobId; }
            if ($branchId) { $fields[] = 'branch_id = ?'; $params[] = $branchId; }
            $params[] = $employeeId;
            Database::query("UPDATE employees SET " . implode(', ', $fields) . " WHERE id = ?", $params);
            // إعادة فتح الملف إذا كان مغلقًا (مباشرة جديدة)
            if ($existing['file_status'] !== 'open') {
                Database::query("UPDATE employees SET file_status = 'open', status = 'active' WHERE id = ?", [$employeeId]);
                Database::query(
                    "INSERT INTO employee_file_status_logs (employee_id, old_status, new_status, reason, changed_by, created_at)
                     VALUES (?,?,?,?,?,?)",
                    [$employeeId, $existing['file_status'], 'open', 'مباشرة (استيراد)', $userId, $hireDate . ' 00:00:00']
                );
            }
            Helpers::queueSync('employees', $employeeId, 'update', ['hire_date' => $hireDate]);
            return 'updated';
        }

        if ($code === '') {
            $code = Helpers::docNumber('EMP', 'employees', 'emp_code');
        }
        Database::query(
            "INSERT INTO employees (emp_code, full_name, phone, nationality, residence, position, department, department_id, job_id,
             base_salary, hire_date, branch_id, file_status, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'open','active')",
            [$code, $name, $phone ?: null, $nationality, $residence, $jobTitle ?: null, $departmentName ?: null,
             $departmentId, $jobId, $baseSalary ?? 0, $hireDate, $branchId]
        );
        $employeeId = (int)Database::lastInsertId();
        Database::query(
            "INSERT INTO employee_file_status_logs (employee_id, old_status, new_status, reason, changed_by, created_at)
             VALUES (?,?,?,?,?,?)",
            [$employeeId, 'open', 'open', 'مباشرة (استيراد)', $userId, $hireDate . ' 00:00:00']
        );
        Helpers::queueSync('employees', $employeeId, 'insert', ['emp_code' => $code, 'full_name' => $name]);
        return 'created';
    }

    /**
     * استيراد صف إغلاق: إغلاق/أرشفة ملف الموظف بتاريخ فعلي وسبب
     * @return string 'closed' | 'skipped'
     */
    public static function importClosureRow(array $row, ?int $userId = null): string
    {
        $code = trim((string)($row['emp_code'] ?? ''));
        $employee = $code !== '' ? Database::query("SELECT * FROM employees WHERE emp_code = ? LIMIT 1", [$code])->fetch() : null;
        if (!$employee) {
            throw new \RuntimeException('كود الموظف ' . $code . ' غير موجود');
        }
        $closeDate = ImportHelper::parseExcelDate($row['close_date'] ?? null) ?: date('Y-m-d');
        $reason = trim((string)($row['reason'] ?? '')) ?: 'إغلاق (استيراد)';
        $newStatus = trim((string)($row['file_status'] ?? 'closed'));
        if (!in_array($newStatus, ['closed', 'archived'], true)) {
            $newStatus = 'closed';
        }
        $empStatus = trim((string)($row['status'] ?? 'resigned'));
        if (!in_array($empStatus, ['terminated', 'resigned', 'suspended'], true)) {
            $empStatus = 'resigned';
        }
        $employeeId = (int)$employee['id'];
        if ($employee['file_status'] === $newStatus) {
            return 'skipped';
        }
        if (self::hasOutstandingCustody($employeeId)) {
            throw new \RuntimeException('الموظف ' . $code . ' لديه عهد غير مسددة');
        }
        if (!self::changeFileStatus($employeeId, $newStatus, $reason, $userId, $closeDate)) {
            throw new \RuntimeException('فشل إغلاق ملف الموظف ' . $code);
        }
        Database::query("UPDATE employees SET status = ? WHERE id = ?", [$empStatus, $employeeId]);
        return 'closed';
    }


    /** إنشاء جدول سداد للسلفة (قصيرة أو طويلة الأجل) */
    public static function generateRepaymentSchedule(int $advanceId): void
    {
        $advance = Database::query("SELECT * FROM salary_advances WHERE id = ?", [$advanceId])->fetch();
        if (!$advance || (int)$advance['installments'] <= 0) {
            return;
        }
        // نحذف الأقساط المستقبلية فقط ونبقي المسدد؛ نحسب المبلغ المتبقي بما فيه الأقساط المسددة
        Database::query("DELETE FROM advance_repayment_schedule WHERE advance_id = ? AND status = 'pending'", [$advanceId]);
        $amount = (float)$advance['amount'];
        $paidAmount = (float)($advance['paid_amount'] ?? 0);
        $installments = (int)$advance['installments'];
        // بداية السداد من تاريخ الصرف الفعلي؛ إذا لم يُصرف بعد نستخدم تاريخ الاعتماد
        $startAt = $advance['paid_at'] ?: ($advance['approved_at'] ?: date('Y-m-d H:i:s'));
        $start = date('Y-m', strtotime($startAt));

        // السلف طويلة الأجل: القسط الشهري محدد سلفاً (مبلغ ثابت أو نسبة من الراتب محسوبة عند تقديم الطلب)؛
        // القسط الأخير يحمل الفرق المتبقي (الكسر) بدلاً من الأول.
        $fixedInstallment = (float)($advance['installment_amount'] ?? 0);
        if ($fixedInstallment > 0 && $installments > 0) {
            $last = round($amount - ($fixedInstallment * ($installments - 1)), 2);
            for ($i = 0; $i < $installments; $i++) {
                $period = date('Y-m', strtotime($start . ' + ' . $i . ' months'));
                $installment = $i === $installments - 1 ? $last : $fixedInstallment;
                Database::query(
                    "INSERT IGNORE INTO advance_repayment_schedule (advance_id, period, amount)
                     VALUES (?,?,?)",
                    [$advanceId, $period, $installment]
                );
            }
        } else {
            // سلوك افتراضي (قديم): تقسيم المبلغ بالتساوي على عدد الأقساط
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
        }
        Database::query(
            "UPDATE salary_advances SET remaining_amount = ? - ? WHERE id = ?",
            [$amount, $paidAmount, $advanceId]
        );
    }

    /**
     * الأقساط المستحقة للموظف في فترة محددة (السلف طويلة + قصيرة الأجل) — بعد الصرف فقط
     * يُعيد تفصيلاً بحسب النوع: ['short' => .., 'long' => .., 'total' => ..]
     */
    public static function getAdvanceInstallments(int $employeeId, string $period): array
    {
        // أقساط السلف طويلة الأجل المجدولة التي صُرِفت
        $long = (float) Database::query(
            "SELECT COALESCE(SUM(s.amount),0) FROM advance_repayment_schedule s
             JOIN salary_advances a ON a.id = s.advance_id
             WHERE a.employee_id = ? AND a.status IN ('paid','settled')
             AND s.period = ? AND s.status = 'pending'",
            [$employeeId, $period]
        )->fetchColumn();

        // السلف قصيرة الأجل التي صُرِفت في نفس الفترة ولم تُسدد بعد
        // يُستبعد منها ما له جدول سداد مُعلَّق (مثل سلفة العجز المؤجلة للشهر التالي) حتى لا تُخصم مرتين وتتراكم
        $monthStart = $period . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));
        $short = (float) Database::query(
            "SELECT COALESCE(SUM(remaining_amount),0) FROM salary_advances
             WHERE employee_id = ? AND advance_type = 'short'
             AND status IN ('paid','settled')
             AND remaining_amount > 0
             AND NOT EXISTS (SELECT 1 FROM advance_repayment_schedule s WHERE s.advance_id = salary_advances.id AND s.status = 'pending')
             AND DATE(paid_at) BETWEEN ? AND ?",
            [$employeeId, $monthStart, $monthEnd]
        )->fetchColumn();

        return ['short' => $short, 'long' => $long, 'total' => $short + $long];
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
             AND status IN ('paid','settled')
             AND remaining_amount > 0
             AND NOT EXISTS (SELECT 1 FROM advance_repayment_schedule s WHERE s.advance_id = salary_advances.id AND s.status = 'pending')
             AND DATE(paid_at) BETWEEN ? AND ?",
            [$employeeId, $monthStart, $monthEnd]
        );

        // تحديث السلف التي سُددت بالكامل بعد هذا المسير إلى "مسددة"
        Database::query(
            "UPDATE salary_advances SET status = 'settled'
             WHERE id IN (SELECT advance_id FROM advance_repayment_schedule WHERE payroll_id = ?)
             AND remaining_amount <= 0",
            [$payrollId]
        );
    }

    /** مؤشرات الأداء القابلة للتطبيق على وظيفة موظف معيّن (العامة + الخاصة بوظيفته) */
    public static function getApplicableKpiIds(int $employeeId): array
    {
        $jobId = (int)Database::query("SELECT job_id FROM employees WHERE id = ?", [$employeeId])->fetchColumn();
        $rows = Database::query(
            "SELECT pk.id FROM performance_kpis pk
             LEFT JOIN performance_kpi_jobs pkj ON pkj.kpi_id = pk.id
             WHERE pk.is_active = 1 AND (pkj.kpi_id IS NULL OR pkj.job_id = ?)
             GROUP BY pk.id",
            [$jobId ?: 0]
        )->fetchAll();
        return array_map(fn($r) => (int)$r['id'], $rows);
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

    /**
     * حفظ/تحديث تقييم مؤشرات أداء (KPI) مباشرة من شاشة تقرير الأداء الوظيفي.
     * إذا كان هناك تقييم مسودة سابق لنفس الموظف/الفترة يُحدَّث بدلاً من إنشاء تكرار؛
     * إذا كان آخر تقييم معتمداً يُنشأ تقييم مسودة جديد لحفظ أثر التعديل دون المساس بالمعتمد.
     */
    public static function saveKpiEvaluation(int $employeeId, string $period, array $kpiScores, ?int $userId = null): array
    {
        $total = 0; $weight = 0;
        foreach ($kpiScores as $row) {
            $total += (float)$row['score'] * (float)$row['weight'];
            $weight += (float)$row['weight'];
        }
        $kpiScore = $weight > 0 ? round($total / $weight, 2) : 0;

        $existing = Database::query(
            "SELECT id, status, manager_score, admin_score, self_score FROM evaluations WHERE employee_id = ? AND period = ? ORDER BY id DESC LIMIT 1",
            [$employeeId, $period]
        )->fetch();

        $others = $existing ? array_filter([
            (float)$existing['manager_score'], (float)$existing['admin_score'], (float)$existing['self_score'],
        ], fn ($s) => $s > 0) : [];
        $scores = array_filter(array_merge([$kpiScore], $others), fn ($s) => $s > 0);
        $totalScore = $scores ? round(array_sum($scores) / count($scores), 2) : $kpiScore;

        if ($existing && $existing['status'] === 'draft') {
            $evalId = (int)$existing['id'];
            Database::query(
                "UPDATE evaluations SET kpi_score = ?, total_score = ?, evaluated_by = ? WHERE id = ?",
                [$kpiScore, $totalScore, $userId, $evalId]
            );
            Database::query("DELETE FROM evaluation_items WHERE evaluation_id = ?", [$evalId]);
            $status = 'updated';
        } else {
            Database::query(
                "INSERT INTO evaluations (employee_id, period, evaluation_type, evaluated_by, total_score, kpi_score) VALUES (?,?,?,?,?,?)",
                [$employeeId, $period, 'monthly', $userId, $totalScore, $kpiScore]
            );
            $evalId = (int)Database::lastInsertId();
            $status = 'created';
        }
        foreach ($kpiScores as $row) {
            Database::query(
                "INSERT INTO evaluation_items (evaluation_id, kpi_id, score, weight) VALUES (?,?,?,?)",
                [$evalId, (int)$row['kpi_id'], (float)$row['score'], (float)$row['weight']]
            );
        }
        return ['id' => $evalId, 'kpi_score' => $kpiScore, 'total_score' => $totalScore, 'status' => $status];
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
     * تذكير يومي (من 1 إلى 4 من كل شهر) لكل مدير لديه مرؤوسون لم يُقيَّموا بعد
     * عن أداء الشهر المنتهي. مرة واحدة فقط في اليوم لكل مدير (يُستدعى من cron).
     */
    public static function sendMonthlyKpiEvaluationReminders(): int
    {
        $day = (int)date('j');
        if ($day < 1 || $day > 4) {
            return 0;
        }
        $period = date('Y-m', strtotime('first day of last month'));

        $managerIds = array_unique(array_merge(
            array_map('intval', array_column(Database::query(
                "SELECT DISTINCT manager_id AS id FROM employees WHERE manager_id IS NOT NULL AND status = 'active'"
            )->fetchAll(), 'id')),
            array_map('intval', array_column(Database::query(
                "SELECT DISTINCT op.employee_id AS id FROM org_positions op
                 JOIN org_positions child ON child.parent_id = op.id AND child.is_active = 1
                 WHERE op.employee_id IS NOT NULL AND op.is_active = 1"
            )->fetchAll(), 'id'))
        ));
        if (!$managerIds) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($managerIds), '?'));
        $managers = Database::query(
            "SELECT id, user_id FROM employees WHERE id IN ($placeholders) AND user_id IS NOT NULL AND status = 'active'",
            $managerIds
        )->fetchAll();

        $sent = 0;
        foreach ($managers as $m) {
            $userId = (int)$m['user_id'];
            $alreadyToday = Database::query(
                "SELECT 1 FROM notifications WHERE user_id = ? AND action_type = 'kpi_eval_reminder' AND DATE(created_at) = CURDATE() LIMIT 1",
                [$userId]
            )->fetch();
            if ($alreadyToday) {
                continue;
            }
            $subordinates = WorkflowEngine::subordinateEmployees($userId);
            if (!$subordinates) {
                continue;
            }
            $subIds = array_map('intval', array_column($subordinates, 'id'));
            $subPlaceholders = implode(',', array_fill(0, count($subIds), '?'));
            $doneIds = array_column(Database::query(
                "SELECT DISTINCT employee_id FROM evaluations
                 WHERE period = ? AND evaluation_type = 'monthly' AND employee_id IN ($subPlaceholders)",
                array_merge([$period], $subIds)
            )->fetchAll(), 'employee_id');
            $pendingCount = count($subIds) - count($doneIds);
            if ($pendingCount <= 0) {
                continue;
            }
            Helpers::notify(
                $userId,
                'تذكير بتقييم أداء الفريق',
                "لديك $pendingCount موظف بانتظار تقييم الأداء عن شهر $period",
                '/hr/evaluations?period=' . $period,
                null, null, [], null, 'kpi_evaluation', null, 'kpi_eval_reminder', [], true
            );
            $sent++;
        }
        return $sent;
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
        $employee = Database::query("SELECT weekly_off_day FROM employees WHERE id = ?", [$employeeId])->fetch();
        $weeklyOff = ($employee && $employee['weekly_off_day'] !== null) ? (int)$employee['weekly_off_day'] : null;

        $dates = Database::query(
            "SELECT DISTINCT DATE(checked_at) AS d
             FROM attendance
             WHERE employee_id = ? AND check_type = 'in' AND DATE(checked_at) BETWEEN ? AND ?",
            [$employeeId, $start, $end]
        )->fetchAll(\PDO::FETCH_COLUMN, 0);

        $count = 0;
        foreach ($dates as $d) {
            if ($weeklyOff !== null && (int)date('w', strtotime($d)) === $weeklyOff) {
                continue;
            }
            $count++;
        }
        return $count;
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
        $existing = Database::query(
            "SELECT id FROM overtime_requests WHERE employee_id = ? AND request_date = ?",
            [$employeeId, $date]
        )->fetch();
        if ($existing) {
            throw new \RuntimeException('يوجد طلب عمل إضافي مسجل مسبقاً لهذا اليوم');
        }
        $period = date('Y-m', strtotime($date));
        $amount = self::calculateOvertimeAmount($employeeId, $hours, $period);
        try {
            Database::query(
                "INSERT INTO overtime_requests (employee_id, request_date, hours, reason, status, amount)
                 VALUES (?,?,?,?,?,?)",
                [$employeeId, $date, $hours, $reason ?: '', 'pending', $amount]
            );
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                throw new \RuntimeException('يوجد طلب عمل إضافي مسجل مسبقاً لهذا اليوم');
            }
            throw $e;
        }
        $id = (int)Database::lastInsertId();
        self::recomputeOvertimeBiometricMatch($id);
        return $id;
    }

    /** ========== طلبات نسيان البصمة (Missing Attendance) ========== */

    /**
     * إنشاء طلب نسيان بصمة حضور/انصراف.
     *
     * @param array{request_type:string,clock_in_at:?string,clock_out_at:?string,reason:?string} $payload
     */
    public static function createMissingAttendanceRequest(int $employeeId, array $payload): int
    {
        if (!self::isEmployeeFileOpen($employeeId)) {
            throw new \RuntimeException('ملف الموظف مغلق أو مأرشف');
        }
        $requestType = (string)($payload['request_type'] ?? 'in');
        if (!in_array($requestType, ['in', 'out', 'both'], true)) {
            throw new \RuntimeException('نوع البصمة يجب أن يكون دخول أو خروج أو كلاهما');
        }
        $clockInAt = !empty($payload['clock_in_at']) ? (string)$payload['clock_in_at'] : null;
        $clockOutAt = !empty($payload['clock_out_at']) ? (string)$payload['clock_out_at'] : null;

        if ($requestType === 'in' && !$clockInAt) {
            throw new \RuntimeException('تاريخ/وقت بصمة الدخول مطلوب');
        }
        if ($requestType === 'out' && !$clockOutAt) {
            throw new \RuntimeException('تاريخ/وقت بصمة الخروج مطلوب');
        }
        if ($requestType === 'both' && (!$clockInAt || !$clockOutAt)) {
            throw new \RuntimeException('يجب تحديد تاريخ/وقت بصمة الدخول والخروج معاً');
        }
        if ($clockInAt && !self::isValidDateTime($clockInAt)) {
            throw new \RuntimeException('تاريخ/وقت بصمة الدخول غير صالح');
        }
        if ($clockOutAt && !self::isValidDateTime($clockOutAt)) {
            throw new \RuntimeException('تاريخ/وقت بصمة الخروج غير صالح');
        }
        if ($clockInAt && $clockOutAt && $clockOutAt <= $clockInAt) {
            throw new \RuntimeException('وقت الخروج يجب أن يكون بعد وقت الدخول');
        }

        Database::query(
            "INSERT INTO missing_attendance_requests
             (employee_id, request_type, clock_in_at, clock_out_at, reason, status)
             VALUES (?,?,?,?,?,?)",
            [
                $employeeId, $requestType,
                $clockInAt, $clockOutAt,
                (string)($payload['reason'] ?? ''),
                'pending',
            ]
        );
        return (int)Database::lastInsertId();
    }

    private static function isValidDateTime(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value) === 1;
    }

    /**
     * تطبيق طلب نسيان البصمة المعتمد: إدراج أو تحديث سجلات attendance.
     *
     * @return array<int,array{id:int,type:string,action:string}>
     */
    public static function applyMissingAttendanceRequest(int $requestId): array
    {
        $req = Database::query("SELECT * FROM missing_attendance_requests WHERE id = ?", [$requestId])->fetch();
        if (!$req) {
            throw new \RuntimeException('طلب نسيان البصمة غير موجود');
        }
        $employeeId = (int)$req['employee_id'];
        $requestType = (string)$req['request_type'];
        $result = [];

        if (in_array($requestType, ['in', 'both'], true) && !empty($req['clock_in_at'])) {
            $result[] = self::upsertMissingAttendanceRecord($employeeId, 'in', (string)$req['clock_in_at']);
        }
        if (in_array($requestType, ['out', 'both'], true) && !empty($req['clock_out_at'])) {
            $result[] = self::upsertMissingAttendanceRecord($employeeId, 'out', (string)$req['clock_out_at']);
        }

        // حل أي تنبيهات مفتوحة لنفس اليوم إن وُجدت
        $affectedDates = array_values(array_unique(array_map(
            static fn($r) => date('Y-m-d', strtotime($r['checked_at'] ?? 'now')),
            $result
        )));
        foreach ($affectedDates as $date) {
            Database::query(
                "UPDATE attendance_alerts SET status = 'resolved'
                 WHERE employee_id = ? AND alert_date = ? AND status = 'new'",
                [$employeeId, $date]
            );
            // إعادة احتساب التأخير/الخروج المبكر/الإضافي لنفس اليوم بناءً على البصمة المضافة/المعدلة
            self::recalculateAttendanceForDate($employeeId, $date);
        }

        return $result;
    }

    /**
     * إدراج بصمة غير موجودة أو تحديث بصمة موجودة بنفس اليوم ونفس النوع.
     */
    private static function upsertMissingAttendanceRecord(int $employeeId, string $type, string $checkedAt): array
    {
        $date = date('Y-m-d', strtotime($checkedAt));
        $existing = Database::query(
            "SELECT id FROM attendance
             WHERE employee_id = ? AND check_type = ? AND DATE(checked_at) = ?
             ORDER BY checked_at ASC LIMIT 1",
            [$employeeId, $type, $date]
        )->fetch();

        // تأكد من أن عمود source يدعم 'request'
        self::ensureAttendanceSourceSupportsRequest();

        if ($existing) {
            Database::query(
                "UPDATE attendance SET checked_at = ?, source = 'request', within_geofence = 1, notes = ?
                 WHERE id = ?",
                [$checkedAt, 'تم التعديل عبر طلب نسيان بصمة', (int)$existing['id']]
            );
            return ['id' => (int)$existing['id'], 'type' => $type, 'action' => 'updated', 'checked_at' => $checkedAt];
        }

        Database::query(
            "INSERT INTO attendance (employee_id, check_type, checked_at, latitude, longitude, photo, source, within_geofence, notes)
             VALUES (?, ?, ?, NULL, NULL, NULL, 'request', 1, ?)",
            [$employeeId, $type, $checkedAt, 'تم الإدراج عبر طلب نسيان بصمة']
        );
        return [
            'id' => (int)Database::lastInsertId(),
            'type' => $type,
            'action' => 'inserted',
            'checked_at' => $checkedAt,
        ];
    }

    /** تأكد من وجود أعمدة ترحيل عجز الراتب إلى سلفة (deficit_amount / source_payroll_id) */
    private static function ensurePayrollDeficitColumns(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $col = Database::query("SHOW COLUMNS FROM payrolls LIKE 'deficit_amount'")->fetch();
            if (!$col) {
                Database::query("ALTER TABLE payrolls ADD COLUMN deficit_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
            }
            $col = Database::query("SHOW COLUMNS FROM salary_advances LIKE 'source_payroll_id'")->fetch();
            if (!$col) {
                Database::query("ALTER TABLE salary_advances ADD COLUMN source_payroll_id INT NULL");
            }
        } catch (\Throwable $e) {
            // تجاهل الخطأ — العملية ستفشل لاحقاً إذا استمرت المشكلة
        }
    }

    /** تأكد من أن عمود source في attendance يدعم 'request' */
    private static function ensureAttendanceSourceSupportsRequest(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $col = Database::query("SHOW COLUMNS FROM attendance LIKE 'source'")->fetch();
            if ($col && strpos((string)$col['Type'], 'request') === false) {
                Database::query("ALTER TABLE attendance MODIFY COLUMN source ENUM('mobile','web','device','request') DEFAULT 'mobile'");
            }
        } catch (\Throwable $e) {
            // تجاهل الخطأ — الإدراج سيفشل لاحقاً إذا استمرت المشكلة
        }
    }

    /**
     * سعر الساعة الإضافية يُحسب بناءً على أيام الدوام المتوقعة (مع مراعاة العطلة الأسبوعية).
     */
    /**
     * مجموع تخفيضات الراتب المعتمدة الخاصة بفترة الاحتساب المحددة فقط (شهر واحد، وليس تراكمياً).
     * التخفيض يطبَّق حصرياً على الشهر المستهدف بـ period (أو الذي يقع effective_date ضمنه عند عدم تحديد period)؛
     * الإلغاء أو الرفض يُخرجه تلقائياً لأن الجمع يقتصر على المعتمدة، ولا يُحمَّل على أي شهر غير شهره المحدد.
     */
    public static function salaryReductionTotal(int $employeeId, string $period): float
    {
        $periodStart = $period . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        return (float) Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM hr_adjustments
             WHERE employee_id = ? AND adjustment_type = 'salary_reduction' AND status = 'approved'
               AND (period = ? OR (period IS NULL AND effective_date BETWEEN ? AND ?))",
            [$employeeId, $period, $periodStart, $periodEnd]
        )->fetchColumn();
    }

    /**
     * الأساس الشهري المعدّل = (الأساسي الاسمي + زيادات الفترة) − تخفيض راتب الفترة نفسها فقط (غير تراكمي).
     * المرجع الموحد لاشتقاق معدلات اليومية/الساعة في الرواتب والإضافي وتسويات الأيام.
     */
    public static function adjustedMonthlyBase(int $employeeId, string $period): float
    {
        $row = Database::query("SELECT base_salary FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$row) {
            return 0.0;
        }
        $periodStart = $period . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $raises = (float) Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM hr_adjustments
             WHERE employee_id = ? AND adjustment_type = 'raise' AND status = 'approved'
               AND (period = ? OR (period IS NULL AND effective_date BETWEEN ? AND ?))",
            [$employeeId, $period, $periodStart, $periodEnd]
        )->fetchColumn();
        $raises += (float) Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM salary_adjustments
             WHERE employee_id = ? AND adj_type = 'raise' AND status = 'approved' AND adj_date BETWEEN ? AND ?",
            [$employeeId, $periodStart, $periodEnd]
        )->fetchColumn();
        return max(0.0, (float) $row['base_salary'] + $raises - self::salaryReductionTotal($employeeId, $period));
    }

    private static function overtimeHourlyRate(int $employeeId, ?string $period = null): float
    {
        $period = $period ?: date('Y-m');
        // معدل الساعة يُشتق من الأساس المعدّل (الأساسي + الزيادات − تخفيض الراتب) لا الاسمي الخام
        $salary = self::adjustedMonthlyBase($employeeId, $period);
        if ($salary <= 0) {
            return 0.0;
        }
        $summary = self::calculateAttendanceSummary($employeeId, $period);
        $expectedDays = (int)($summary['expected_days'] ?? 0);
        if ($expectedDays <= 0) {
            $expectedDays = 30;
        }
        return $salary / ($expectedDays * 8);
    }

    public static function calculateOvertimeAmount(int $employeeId, float $hours, ?string $period = null): float
    {
        if ($hours <= 0) {
            return 0.0;
        }
        $hourlyRate = self::overtimeHourlyRate($employeeId, $period);
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
        // حساب إجمالي وقت العمل من أزواج الدخول/الخروج (يدعم الورديات المتقطعة)
        $totalMinutes = 0;
        $openIn = null;
        foreach ($records as $r) {
            $ts = strtotime((string)$r['checked_at']);
            if ($r['check_type'] === 'in') {
                $openIn = $ts;
            } elseif ($r['check_type'] === 'out' && $openIn !== null) {
                $totalMinutes += max(0, (int)floor(($ts - $openIn) / 60));
                $openIn = null;
            }
        }
        if ($totalMinutes <= 0) {
            return 0.0;
        }
        $shift = self::getEmployeeShift($employeeId, $date);
        $shiftMinutes = 0;
        if ($shift) {
            // استخدام إجمالي دقائق الوردية من الفترات المتقطعة
            if (!empty($shift['id']) && $shift['id'] > 0) {
                $shiftMinutes = self::shiftTotalMinutes($shift['id']);
            }
            // fallback إلى الفترة الواحدة القديمة
            if ($shiftMinutes <= 0 && !empty($shift['start_time']) && !empty($shift['end_time'])) {
                $start = new \DateTime($date . ' ' . $shift['start_time']);
                $end = new \DateTime($date . ' ' . $shift['end_time']);
                if ($end > $start) {
                    $shiftMinutes = (int)floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
                }
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
        $period = date('Y-m', strtotime((string)$req['request_date']));
        $hourlyRate = self::overtimeHourlyRate($employeeId, $period);
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
        $shifts = Database::query($sql)->fetchAll();

        $workDays = [];
        $rows = Database::query("SELECT shift_id, day_of_week FROM shift_work_days")->fetchAll();
        foreach ($rows as $r) {
            $workDays[(int)$r['shift_id']][(int)$r['day_of_week']] = true;
        }

        foreach ($shifts as &$s) {
            $s['work_days'] = array_keys($workDays[(int)$s['id']] ?? []);
        }
        unset($s);

        return $shifts;
    }

    public static function getShift(int $id): ?array
    {
        $row = Database::query("SELECT * FROM shifts WHERE id = ?", [$id])->fetch();
        return $row ?: null;
    }

    public static function getEmployeeShift(int $employeeId, ?string $date = null): ?array
    {
        $date = $date ?: date('Y-m-d');
        $dow = (int)date('w', strtotime($date));

        $override = Database::query(
            "SELECT es.shift_id, s.name, s.start_time, s.end_time, s.is_active
             FROM employee_shifts es
             JOIN shifts s ON s.id = es.shift_id
             WHERE es.employee_id = ? AND es.shift_date = ?",
            [$employeeId, $date]
        )->fetch();
        if ($override) {
            $shiftId = (int)$override['shift_id'];
            $isWorkDay = self::isShiftWorkDay($shiftId, $dow);
            return [
                'id' => $shiftId,
                'name' => (string)$override['name'],
                'start_time' => (string)$override['start_time'],
                'end_time' => (string)$override['end_time'],
                'segments' => self::getShiftSegments($shiftId),
                'is_active' => (bool)$override['is_active'],
                'is_override' => true,
                'is_work_day' => $isWorkDay,
                'day_of_week' => $dow,
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
            $shiftId = (int)$default['id'];
            $isWorkDay = self::isShiftWorkDay($shiftId, $dow);
            return [
                'id' => $shiftId,
                'name' => (string)$default['name'],
                'start_time' => (string)$default['start_time'],
                'end_time' => (string)$default['end_time'],
                'segments' => self::getShiftSegments($shiftId),
                'is_active' => (bool)$default['is_active'],
                'is_override' => false,
                'is_work_day' => $isWorkDay,
                'day_of_week' => $dow,
            ];
        }
        return [
            'id' => 0,
            'name' => 'Default',
            'start_time' => (string)Settings::get('default_work_start_time', '08:00'),
            'end_time' => (string)Settings::get('default_work_end_time', '17:00'),
            'segments' => [],
            'is_active' => true,
            'is_override' => false,
            'is_work_day' => true,
            'day_of_week' => $dow,
        ];
    }

    /** جلب فترات الوردية (الفترات المتقطعة) */
    public static function getShiftSegments(int $shiftId): array
    {
        if ($shiftId <= 0) return [];
        $rows = Database::query(
            "SELECT start_time, end_time, segment_order FROM shift_segments WHERE shift_id = ? ORDER BY segment_order",
            [$shiftId]
        )->fetchAll();
        return array_map(fn($r) => [
            'start_time' => substr((string)$r['start_time'], 0, 5),
            'end_time'   => substr((string)$r['end_time'], 0, 5),
        ], $rows);
    }

    /** حساب إجمالي دقائق الوردية من فتراتها */
    public static function shiftTotalMinutes(int $shiftId): int
    {
        if ($shiftId <= 0) return 0;
        $segments = self::getShiftSegments($shiftId);
        if (!$segments) return 0;
        $total = 0;
        foreach ($segments as $seg) {
            $start = strtotime($seg['start_time']);
            $end = strtotime($seg['end_time']);
            $diff = $end - $start;
            if ($diff < 0) $diff += 86400; // وردية ليلية
            $total += (int)($diff / 60);
        }
        return $total;
    }

    private static function isShiftWorkDay(int $shiftId, int $dayOfWeek): bool
    {
        $row = Database::query(
            "SELECT 1 FROM shift_work_days WHERE shift_id = ? AND day_of_week = ? LIMIT 1",
            [$shiftId, $dayOfWeek]
        )->fetch();
        return (bool)$row;
    }

    /** إيجاد أقرب فترة عمل لوقت البصمة */
    private static function findClosestSegment(array $segments, \DateTime $checkedAt, string $date): array
    {
        $bestSeg = $segments[0];
        $bestDiff = PHP_INT_MAX;
        foreach ($segments as $seg) {
            $segStart = new \DateTime($date . ' ' . $seg['start_time']);
            $segEnd = new \DateTime($date . ' ' . $seg['end_time']);
            // معالجة الوردية الليلية (end < start)
            if ($segEnd <= $segStart) {
                $segEnd->modify('+1 day');
            }
            $diffStart = abs($checkedAt->getTimestamp() - $segStart->getTimestamp());
            $diffEnd = abs($checkedAt->getTimestamp() - $segEnd->getTimestamp());
            $diff = min($diffStart, $diffEnd);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $bestSeg = $seg;
            }
        }
        return $bestSeg;
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
        // إعادة احتساب تأخير/انصراف مبكر/إضافي لبصمات هذا اليوم بناءً على الوردية الجديدة فوراً
        self::recalculateAttendanceForDate($employeeId, $date);
    }

    /**
     * يعيد احتساب دقائق التأخير/الخروج المبكر/الإضافي لكل بصمات موظف في يوم محدد
     * بناءً على الوردية الحالية لذلك اليوم (افتراضية أو معدّلة). تُستدعى بعد أي
     * تغيير لوردية اليوم (تعيين مباشر من المدير أو اعتماد طلب تغيير وردية) حتى
     * تنعكس الوردية الجديدة فوراً على حساب البصمة دون انتظار إعادة تسجيل الحضور.
     */
    public static function recalculateAttendanceForDate(int $employeeId, string $date): void
    {
        $employee = Database::query("SELECT attendance_type FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$employee) return;
        if (($employee['attendance_type'] ?? '') === 'flexible') return;

        $rows = Database::query(
            "SELECT id, check_type, checked_at FROM attendance WHERE employee_id = ? AND DATE(checked_at) = ?",
            [$employeeId, $date]
        )->fetchAll();
        if (!$rows) return;

        $shift = self::getEmployeeShift($employeeId, $date);
        if (!$shift) return;

        $segments = $shift['segments'] ?? [];
        if (empty($segments) && !empty($shift['start_time'])) {
            $segments = [['start_time' => $shift['start_time'], 'end_time' => $shift['end_time']]];
        }
        if (empty($segments)) return;

        foreach ($rows as $row) {
            $lateMinutes = 0;
            $earlyMinutes = 0;
            $overtimeMinutes = 0;
            $checkedAt = new \DateTime((string)$row['checked_at']);
            $closestSeg = self::findClosestSegment($segments, $checkedAt, $date);
            $segStart = new \DateTime($date . ' ' . $closestSeg['start_time']);
            $segEnd = new \DateTime($date . ' ' . $closestSeg['end_time']);
            if ($row['check_type'] === 'in') {
                if ($checkedAt > $segStart) {
                    $lateMinutes = max(0, (int)floor(($checkedAt->getTimestamp() - $segStart->getTimestamp()) / 60));
                }
            } else {
                if ($checkedAt < $segEnd) {
                    $earlyMinutes = max(0, (int)floor(($segEnd->getTimestamp() - $checkedAt->getTimestamp()) / 60));
                } elseif ($checkedAt > $segEnd) {
                    $overtimeMinutes = max(0, (int)floor(($checkedAt->getTimestamp() - $segEnd->getTimestamp()) / 60));
                }
            }
            Database::query(
                "UPDATE attendance SET late_minutes = ?, early_minutes = ?, overtime_minutes = ? WHERE id = ?",
                [$lateMinutes, $earlyMinutes, $overtimeMinutes, (int)$row['id']]
            );
        }
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

    /* ===== أدوات تجميع الحضور (النظام الهجين) ===== */

    /**
     * تحميل بيانات الورديات الافتراضية والمعدّلة لمجموعة موظفين.
     * @return array<int, array{default: array, overrides: array}>
     */
    public static function loadShiftMap(array $employeeIds, string $from, string $to): array
    {
        if (!$employeeIds) {
            return [];
        }
        $employeeIds = array_values(array_map('intval', $employeeIds));
        $in = implode(',', array_fill(0, count($employeeIds), '?'));

        $map = [];
        $defaults = Database::query(
            "SELECT e.id, e.attendance_type, e.shift_id, s.name AS shift_name, s.start_time, s.end_time
             FROM employees e
             LEFT JOIN shifts s ON s.id = e.shift_id
             WHERE e.id IN ($in)",
            $employeeIds
        )->fetchAll();
        foreach ($defaults as $r) {
            $map[(int)$r['id']] = [
                'default' => [
                    'attendance_type' => $r['attendance_type'],
                    'shift_id' => (int)($r['shift_id'] ?? 0),
                    'shift_name' => $r['shift_name'] ?? '-',
                    'start_time' => $r['start_time'],
                    'end_time' => $r['end_time'],
                ],
                'overrides' => [],
            ];
        }

        $overrides = Database::query(
            "SELECT es.employee_id, es.shift_date, es.shift_id, s.name AS shift_name, s.start_time, s.end_time
             FROM employee_shifts es
             JOIN shifts s ON s.id = es.shift_id
             WHERE es.employee_id IN ($in) AND es.shift_date BETWEEN ? AND ?",
            array_merge($employeeIds, [$from, $to])
        )->fetchAll();
        foreach ($overrides as $r) {
            $map[(int)$r['employee_id']]['overrides'][$r['shift_date']] = [
                'shift_id' => (int)$r['shift_id'],
                'shift_name' => $r['shift_name'] ?? '-',
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time'],
            ];
        }

        // جلب فترات الوردية لكل وردية مستخدمة (افتراضية أو تجاوز)
        $allShiftIds = [];
        foreach ($map as $entry) {
            $sid = (int)($entry['default']['shift_id'] ?? 0);
            if ($sid > 0) $allShiftIds[$sid] = true;
            foreach ($entry['overrides'] as $ov) {
                $sid2 = (int)($ov['shift_id'] ?? 0);
                if ($sid2 > 0) $allShiftIds[$sid2] = true;
            }
        }
        if ($allShiftIds) {
            $segIn = implode(',', array_fill(0, count($allShiftIds), '?'));
            $segRows = Database::query(
                "SELECT shift_id, start_time, end_time, segment_order FROM shift_segments WHERE shift_id IN ($segIn) ORDER BY shift_id, segment_order",
                array_keys($allShiftIds)
            )->fetchAll();
            $segMap = [];
            foreach ($segRows as $sr) {
                $segMap[(int)$sr['shift_id']][] = [
                    'start_time' => substr((string)$sr['start_time'], 0, 5),
                    'end_time'   => substr((string)$sr['end_time'], 0, 5),
                ];
            }
            foreach ($map as &$entry) {
                $sid = (int)($entry['default']['shift_id'] ?? 0);
                $entry['default']['segments'] = $segMap[$sid] ?? [];
                foreach ($entry['overrides'] as &$ov) {
                    $sid2 = (int)($ov['shift_id'] ?? 0);
                    $ov['segments'] = $segMap[$sid2] ?? [];
                }
                unset($ov);
            }
            unset($entry);
        }

        return $map;
    }

    /**
     * يحسب يوم العمل للبصمة بناءً على وقت بدء الوردية.
     * إذا كانت البصمة قبل وقت بدء الوردية بأكثر من ساعات السماح، تُحسب لليوم السابق.
     */
    private static function computeWorkDate(string $checkedAt, ?string $startTime, int $graceHours = 4): string
    {
        if (!$startTime || $startTime === '00:00:00') {
            return date('Y-m-d', strtotime($checkedAt));
        }
        $recordDate = date('Y-m-d', strtotime($checkedAt));
        $recordTs = strtotime($checkedAt);
        $startTs = strtotime($recordDate . ' ' . $startTime);
        $grace = $graceHours * 3600;
        if ($recordTs >= $startTs - $grace) {
            return $recordDate;
        }
        return date('Y-m-d', strtotime($recordDate . ' -1 day'));
    }

    /**
     * تنسيق وقت البصمة: إذا كان تاريخ البصمة مختلفاً عن يوم العمل يُظهر التاريخ كاملاً.
     */
    private static function formatAttendanceTime(?string $checkedAt, string $workDate): string
    {
        if (!$checkedAt) {
            return '-';
        }
        $tsDate = date('Y-m-d', strtotime($checkedAt));
        $time = date('H:i', strtotime($checkedAt));
        return $tsDate === $workDate ? $time : $time . ' (' . $tsDate . ')';
    }

    private static function formatSecondsAsHours(int $seconds): string
    {
        $h = (int)floor($seconds / 3600);
        $m = (int)floor(($seconds % 3600) / 60);
        return sprintf('%02d:%02d', $h, $m);
    }

    private static function initAttendanceGroup(int $empId, array $info, string $workDate): array
    {
        return [
            'employee_id' => $empId,
            'emp_code' => $info['emp_code'] ?? '',
            'full_name' => $info['full_name'] ?? '',
            'department' => $info['department'] ?? '',
            'work_date' => $workDate,
            'pairs' => [],
            'total_seconds' => 0,
            'sources' => [],
            'first_in' => null,
            'last_out' => null,
            'first_in_id' => null,
            'first_in_source' => null,
        ];
    }

    private static function addAttendancePair(array $inRecord, array $outRecord, array &$group, string $workDate, int $diffSeconds): void
    {
        $group['total_seconds'] += $diffSeconds;
        $group['pairs'][] = [
            'in' => self::formatAttendanceTime((string)$inRecord['checked_at'], $workDate),
            'out' => self::formatAttendanceTime((string)$outRecord['checked_at'], $workDate),
        ];
        $inTs = (string)$inRecord['checked_at'];
        if ($group['first_in'] === null || $inTs < $group['first_in']) {
            $group['first_in'] = $inTs;
            $group['first_in_id'] = (int)($inRecord['id'] ?? 0);
            $group['first_in_source'] = (string)($inRecord['source'] ?? '');
        }
        $outTs = (string)$outRecord['checked_at'];
        if ($group['last_out'] === null || $outTs > $group['last_out']) {
            $group['last_out'] = $outTs;
        }
        if (!empty($inRecord['source'])) {
            $group['sources'][$inRecord['source']] = true;
        }
        if (!empty($outRecord['source'])) {
            $group['sources'][$outRecord['source']] = true;
        }
    }

    private static function addOrphanAttendanceIn(array $record, array &$group, string $workDate): void
    {
        $group['pairs'][] = [
            'in' => self::formatAttendanceTime((string)$record['checked_at'], $workDate),
            'out' => '-',
        ];
        $ts = (string)$record['checked_at'];
        if ($group['first_in'] === null || $ts < $group['first_in']) {
            $group['first_in'] = $ts;
            $group['first_in_id'] = (int)($record['id'] ?? 0);
            $group['first_in_source'] = (string)($record['source'] ?? '');
        }
        if (!empty($record['source'])) {
            $group['sources'][$record['source']] = true;
        }
    }

    private static function addOrphanAttendanceOut(array $record, array &$group, string $workDate): void
    {
        $group['pairs'][] = [
            'in' => '-',
            'out' => self::formatAttendanceTime((string)$record['checked_at'], $workDate),
        ];
        $ts = (string)$record['checked_at'];
        if ($group['last_out'] === null || $ts > $group['last_out']) {
            $group['last_out'] = $ts;
        }
        if (!empty($record['source'])) {
            $group['sources'][$record['source']] = true;
        }
    }

    private static function pairAttendanceRecords(array $records, string $workDate, array &$group): void
    {
        $openIn = null;
        foreach ($records as $r) {
            $type = (string)$r['check_type'];
            if ($type === 'in') {
                if ($openIn !== null) {
                    self::addOrphanAttendanceIn($openIn, $group, $workDate);
                }
                $openIn = $r;
            } elseif ($type === 'out') {
                if ($openIn !== null) {
                    $diff = max(0, strtotime((string)$r['checked_at']) - strtotime((string)$openIn['checked_at']));
                    self::addAttendancePair($openIn, $r, $group, $workDate, $diff);
                    $openIn = null;
                } else {
                    self::addOrphanAttendanceOut($r, $group, $workDate);
                }
            }
        }
        if ($openIn !== null) {
            self::addOrphanAttendanceIn($openIn, $group, $workDate);
        }
    }

    /**
     * تجميع بصمات الحضور في أيام عمل وتوزيعها على زوج دخول/خروج لكل يوم.
     * - الموظف ذو الوردية الثابتة: يوم العمل يُحسب من وقت بدء الوردية.
     * - الموظف المرن: يوم العمل = تاريخ أول دخول في الزوج.
     *
     * @param array $records صفوف attendance مع employee_id و check_type و checked_at و source.
     * @param array $shiftMap ناتج loadShiftMap().
     * @param string|null $filterDate يوم عمل محدد (اختياري).
     * @param string $filterFrom نطاق أيام عمل من (اختياري).
     * @param string $filterTo نطاق أيام عمل إلى (اختياري).
     * @return array صفوف جاهزة للعرض (pairs, total_hours, sources, ...).
     */
    public static function pairAndGroupAttendance(
        array $records,
        array $shiftMap,
        ?string $filterDate = null,
        string $filterFrom = '',
        string $filterTo = ''
    ): array {
        $byEmp = [];
        foreach ($records as $r) {
            $id = (int)$r['employee_id'];
            $byEmp[$id][] = $r;
        }

        $groups = [];
        foreach ($byEmp as $empId => $empRecords) {
            usort($empRecords, function ($a, $b) {
                $cmp = strcmp((string)$a['checked_at'], (string)$b['checked_at']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
            });

            $info = [
                'emp_code' => $empRecords[0]['emp_code'] ?? '',
                'full_name' => $empRecords[0]['full_name'] ?? '',
                'department' => $empRecords[0]['department'] ?? '',
            ];

            $shift = $shiftMap[$empId] ?? null;
            $isFixed = $shift && ($shift['default']['attendance_type'] ?? 'shift') !== 'flexible';

            if ($isFixed) {
                $byWorkDate = [];
                foreach ($empRecords as $r) {
                    $recordDate = date('Y-m-d', strtotime((string)$r['checked_at']));
                    // سجلات طلبات نسيان البصمة تُعيَّن لتاريخها التقويمي مباشرة
                    if (($r['source'] ?? '') === 'request') {
                        $workDate = $recordDate;
                    } else {
                        $startTime = $shift['overrides'][$recordDate]['start_time'] ?? $shift['default']['start_time'] ?? null;
                        $workDate = self::computeWorkDate((string)$r['checked_at'], $startTime);
                    }
                    $byWorkDate[$workDate][] = $r;
                }
                foreach ($byWorkDate as $workDate => $dateRecords) {
                    $key = $empId . '|' . $workDate;
                    if (!isset($groups[$key])) {
                        $groups[$key] = self::initAttendanceGroup($empId, $info, $workDate);
                    }
                    self::pairAttendanceRecords($dateRecords, $workDate, $groups[$key]);
                }
            } else {
                $openIn = null;
                foreach ($empRecords as $r) {
                    $type = (string)$r['check_type'];
                    if ($type === 'in') {
                        if ($openIn !== null) {
                            $workDate = date('Y-m-d', strtotime((string)$openIn['checked_at']));
                            $key = $empId . '|' . $workDate;
                            if (!isset($groups[$key])) {
                                $groups[$key] = self::initAttendanceGroup($empId, $info, $workDate);
                            }
                            self::addOrphanAttendanceIn($openIn, $groups[$key], $workDate);
                        }
                        $openIn = $r;
                    } elseif ($type === 'out') {
                        if ($openIn !== null) {
                            $diff = max(0, strtotime((string)$r['checked_at']) - strtotime((string)$openIn['checked_at']));
                            $workDate = date('Y-m-d', strtotime((string)$openIn['checked_at']));
                            $key = $empId . '|' . $workDate;
                            if (!isset($groups[$key])) {
                                $groups[$key] = self::initAttendanceGroup($empId, $info, $workDate);
                            }
                            self::addAttendancePair($openIn, $r, $groups[$key], $workDate, $diff);
                            $openIn = null;
                        } else {
                            $workDate = date('Y-m-d', strtotime((string)$r['checked_at']));
                            $key = $empId . '|' . $workDate;
                            if (!isset($groups[$key])) {
                                $groups[$key] = self::initAttendanceGroup($empId, $info, $workDate);
                            }
                            self::addOrphanAttendanceOut($r, $groups[$key], $workDate);
                        }
                    }
                }
                if ($openIn !== null) {
                    $workDate = date('Y-m-d', strtotime((string)$openIn['checked_at']));
                    $key = $empId . '|' . $workDate;
                    if (!isset($groups[$key])) {
                        $groups[$key] = self::initAttendanceGroup($empId, $info, $workDate);
                    }
                    self::addOrphanAttendanceIn($openIn, $groups[$key], $workDate);
                }
            }
        }

        $result = [];
        foreach ($groups as $g) {
            $wd = $g['work_date'];
            if ($filterDate && $wd !== $filterDate) {
                continue;
            }
            if ($filterFrom && $wd < $filterFrom) {
                continue;
            }
            if ($filterTo && $wd > $filterTo) {
                continue;
            }
            $g['total_hours'] = self::formatSecondsAsHours($g['total_seconds']);
            $g['sources'] = array_keys($g['sources']);
            $result[] = $g;
        }

        usort($result, function ($a, $b) {
            if ($a['full_name'] !== $b['full_name']) {
                return strcmp($a['full_name'], $b['full_name']);
            }
            return strcmp($a['work_date'], $b['work_date']);
        });

        return $result;
    }

}

