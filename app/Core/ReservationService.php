<?php
namespace App\Core;

/**
 * خدمة إدارة الحجوزات - Reservation Management Service
 */
class ReservationService
{
    private const STATUSES = ['pending','confirmed','seated','cancelled','no_show','waiting','rejected','completed'];

    public static function generateReservationNo(): string
    {
        return Helpers::docNumber('RES', 'reservations', 'reservation_no');
    }

    /** إنشاء حجز جديد */
    public static function create(array $data, int $userId): int
    {
        $validated = self::validate($data);
        $typeCode = (string)($validated['reservation_type'] ?? 'regular');
        $type = ReservationTypeService::getByCode($typeCode) ?: ['code' => $typeCode, 'min_guests' => 1, 'max_guests' => 99, 'default_guests' => 2, 'min_advance_hours' => 0, 'duration_minutes' => 90, 'requires_approval' => 0, 'workflow_template_id' => null, 'permission_code' => null];
        $validated = ReservationTypeService::applyTypeRules($type, $validated);

        $guests = (int)$validated['guests'];
        $autoConfirmMax = (int) Settings::get('reservation_auto_confirm_max_guests', 8);
        $status = ($guests <= $autoConfirmMax) ? 'confirmed' : 'pending';
        if (!empty($data['force_status']) && in_array($data['force_status'], self::STATUSES, true)) {
            $status = $data['force_status'];
        }
        $tableId = !empty($validated['table_id']) ? (int)$validated['table_id'] : null;
        if ($tableId && $status === 'confirmed' && !self::isTableAvailable($tableId, $validated['reserved_at'], $validated['duration_minutes'])) {
            throw new \RuntimeException('الطاولة محجوزة في نفس الموعد');
        }

        $duration = (int)$validated['duration_minutes'];
        $estimatedEnd = self::estimatedEnd($validated['reserved_at'], $duration);
        $branchId = (int)($validated['branch_id'] ?? Auth::branchId() ?? 0);

        Database::beginTransaction();
        try {
            $partySize = (int)($validated['party_size'] ?? $guests);
            $expectedAt = $validated['expected_arrival_at'] ?? $validated['reserved_at'];
            $isVip = !empty($validated['is_vip']) ? 1 : 0;
            Database::query(
                "INSERT INTO reservations
                 (reservation_no, customer_id, customer_name, customer_phone, customer_email, is_vip, branch_id, table_id, guests, party_size,
                  reserved_at, expected_arrival_at, duration_minutes, estimated_end, reservation_type, source, status, notes, customer_notes, call_center_notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    self::generateReservationNo(),
                    $validated['customer_id'] ?? null,
                    $validated['customer_name'],
                    $validated['customer_phone'] ?? null,
                    $validated['customer_email'] ?? null,
                    $isVip,
                    $branchId > 0 ? $branchId : null,
                    $tableId,
                    $guests,
                    $partySize,
                    $validated['reserved_at'],
                    $expectedAt,
                    $duration,
                    $estimatedEnd,
                    $validated['reservation_type'] ?? 'dine_in',
                    $validated['source'] ?? 'call_center',
                    $status,
                    $validated['notes'] ?? null,
                    $validated['customer_notes'] ?? null,
                    $validated['call_center_notes'] ?? null,
                    $userId,
                ]
            );
            $id = (int)Database::lastInsertId();
            if ($tableId && in_array($status, ['confirmed','pending'], true)) {
                Database::query("UPDATE dining_tables SET status = 'reserved' WHERE id = ?", [$tableId]);
            }
            if (!$tableId && in_array($status, ['confirmed','pending'], true)) {
                $autoTableId = self::autoAssignTable($id, $guests, $validated['reserved_at'], $duration, $typeCode, $branchId > 0 ? $branchId : null);
                if ($autoTableId) {
                    $tableId = $autoTableId;
                }
            }
            if ($status === 'waiting') {
                WaitingListService::updatePriorityScore($id);
            }
            self::log($id, 'created', $userId, null, $status, 'إنشاء حجز جديد - نوع: ' . $type['name_ar'] ?? $typeCode);
            self::notifyManagers('reservation_created', $id, 'حجز جديد', 'تم إنشاء حجز جديد: ' . $validated['customer_name'] . ' (' . ($type['name_ar'] ?? $typeCode) . ')');
            if ($status === 'pending' && ($type['requires_approval'] || $guests > 8)) {
                self::submitForApproval($id, $userId, $type);
            }
            Database::commit();
            return $id;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** تعديل الحجز */
    public static function update(int $id, array $data, int $userId): bool
    {
        $res = self::get($id);
        if (!$res) {
            throw new \RuntimeException('الحجز غير موجود');
        }
        $validated = self::validate($data, false);
        $oldStatus = $res['status'];
        $oldTableId = $res['table_id'] ? (int)$res['table_id'] : null;
        $newTableId = isset($validated['table_id']) ? ((int)$validated['table_id'] ?: null) : $oldTableId;
        $newDate = $validated['reserved_at'] ?? $res['reserved_at'];
        $duration = isset($validated['duration_minutes']) ? (int)$validated['duration_minutes'] : (int)$res['duration_minutes'];
        $estimatedEnd = self::estimatedEnd($newDate, $duration);

        if ($newTableId && $newTableId !== $oldTableId && in_array($res['status'], ['confirmed','pending'], true)) {
            if (!self::isTableAvailable($newTableId, $newDate, $duration, $id)) {
                throw new \RuntimeException('الطاولة الجديدة غير متاحة في هذا الموعد');
            }
        }

        $fields = [];
        $values = [];
        foreach ([
            'customer_id','customer_name','customer_phone','customer_email','branch_id','guests',
            'reserved_at','duration_minutes','reservation_type','source','notes','customer_notes','call_center_notes'
        ] as $f) {
            if (array_key_exists($f, $validated)) {
                $fields[] = "$f = ?";
                $values[] = $validated[$f] !== '' ? $validated[$f] : null;
            }
        }
        if (!$fields) {
            return true;
        }
        $fields[] = "estimated_end = ?";
        $fields[] = "table_id = ?";
        $values[] = $estimatedEnd;
        $values[] = $newTableId;
        $values[] = $id;

        Database::beginTransaction();
        try {
            Database::query("UPDATE reservations SET " . implode(', ', $fields) . " WHERE id = ?", $values);
            if ($oldTableId && $oldTableId !== $newTableId) {
                self::releaseTable($oldTableId);
            }
            if ($newTableId && in_array($res['status'], ['confirmed','pending'], true)) {
                Database::query("UPDATE dining_tables SET status = 'reserved' WHERE id = ?", [$newTableId]);
            }
            self::log($id, 'updated', $userId, $oldStatus, $res['status'], 'تعديل بيانات الحجز');
            Database::commit();
            return true;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** تأكيد الحجز */
    public static function confirm(int $id, int $userId): bool
    {
        return self::setStatus($id, 'confirmed', $userId, 'تم تأكيد الحجز');
    }

    /** تحويل إلى جالس */
    public static function seat(int $id, int $userId): bool
    {
        return self::setStatus($id, 'seated', $userId, 'تم استقبال الضيوف');
    }

    /** إكمال الحجز */
    public static function complete(int $id, int $userId): bool
    {
        return self::setStatus($id, 'completed', $userId, 'تم إكمال الحجز');
    }

    /** إلغاء الحجز */
    public static function cancel(int $id, string $reason, int $userId): bool
    {
        return self::setStatus($id, 'cancelled', $userId, 'إلغاء: ' . $reason, ['cancellation_reason' => $reason]);
    }

    /** رفض الحجز */
    public static function reject(int $id, string $reason, int $userId): bool
    {
        return self::setStatus($id, 'rejected', $userId, 'رفض: ' . $reason, ['rejection_reason' => $reason]);
    }

    /** تحويل إلى قائمة الانتظار */
    public static function moveToWaiting(int $id, int $userId): bool
    {
        $res = self::get($id);
        if (!$res) {
            throw new \RuntimeException('الحجز غير موجود');
        }
        Database::beginTransaction();
        try {
            $position = (int)Database::query("SELECT COALESCE(MAX(waitlist_position),0)+1 FROM reservations WHERE status='waiting'")->fetchColumn();
            Database::query(
                "UPDATE reservations SET status='waiting', table_id=NULL, waitlist_position=?, updated_at=NOW() WHERE id=?",
                [$position, $id]
            );
            if ($res['table_id']) {
                self::releaseTable((int)$res['table_id']);
            }
            self::log($id, 'waitlisted', $userId, $res['status'], 'waiting', 'نقل إلى قائمة الانتظار');
            Database::commit();
            return true;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** إعادة جدولة الحجز */
    public static function reschedule(int $id, string $newReservedAt, int $userId, ?int $duration = null): bool
    {
        $res = self::get($id);
        if (!$res) {
            throw new \RuntimeException('الحجز غير موجود');
        }
        if (in_array($res['status'], ['cancelled','rejected','completed'], true)) {
            throw new \RuntimeException('لا يمكن إعادة جدولة حجز منتهٍ');
        }
        $duration = $duration ?? (int)$res['duration_minutes'];
        $tableId = $res['table_id'] ? (int)$res['table_id'] : null;
        if ($tableId && !self::isTableAvailable($tableId, $newReservedAt, $duration, $id)) {
            throw new \RuntimeException('الطاولة غير متاحة في الموعد الجديد');
        }
        $estimatedEnd = self::estimatedEnd($newReservedAt, $duration);
        Database::query(
            "UPDATE reservations SET reserved_at=?, estimated_end=?, updated_at=NOW() WHERE id=?",
            [$newReservedAt, $estimatedEnd, $id]
        );
        self::log($id, 'rescheduled', $userId, $res['status'], $res['status'], 'إعادة الجدولة إلى: ' . $newReservedAt);
        return true;
    }

    /** تسجيل دخول الضيوف */
    public static function checkIn(int $id, int $userId): bool
    {
        $res = self::get($id);
        if (!$res || !in_array($res['status'], ['confirmed','seated'], true)) {
            throw new \RuntimeException('لا يمكن تسجيل الدخول لهذا الحجز');
        }
        Database::query("UPDATE reservations SET checked_in_at=NOW(), status='seated', updated_at=NOW() WHERE id=?", [$id]);
        if ($res['table_id']) {
            Database::query("UPDATE dining_tables SET status='occupied' WHERE id=?", [(int)$res['table_id']]);
        }
        self::log($id, 'checked_in', $userId, $res['status'], 'seated', 'تسجيل دخول الضيوف');
        return true;
    }

    /** تسجيل خروج الضيوف */
    public static function checkOut(int $id, int $userId): bool
    {
        $res = self::get($id);
        if (!$res) {
            throw new \RuntimeException('الحجز غير موجود');
        }
        Database::query("UPDATE reservations SET checked_out_at=NOW(), status='completed', updated_at=NOW() WHERE id=?", [$id]);
        if ($res['table_id']) {
            self::releaseTable((int)$res['table_id']);
        }
        self::log($id, 'checked_out', $userId, $res['status'], 'completed', 'تسجيل خروج الضيوف');
        return true;
    }

    /** عدم حضور العميل (No Show) */
    public static function noShow(int $id, int $userId): bool
    {
        return self::setStatus($id, 'no_show', $userId, 'لم يحضر العميل');
    }

    /** إضافة/تحديث ملاحظات الحجز */
    public static function addNotes(int $id, string $notes, int $userId): bool
    {
        $res = self::get($id);
        if (!$res) {
            throw new \RuntimeException('الحجز غير موجود');
        }
        Database::query(
            "UPDATE reservations SET notes = ?, updated_at = NOW() WHERE id = ?",
            [$notes, $id]
        );
        self::log($id, 'updated', $userId, $res['status'], $res['status'], 'تحديث ملاحظات: ' . $notes);
        return true;
    }

    /** لوحة الاستقبال: استعلام حجوزات اليوم مصنّفة */
    public static function receptionDashboard(?int $branchId = null, ?string $date = null): array
    {
        $date = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $graceLate = date('Y-m-d H:i:s', strtotime('-15 minutes'));

        $sql = "SELECT r.*, t.name AS table_name, t.floor, t.section, b.name AS branch_name,
                       rt.name_ar AS type_name, rt.name_en AS type_name_en, rt.icon AS type_icon, rt.color AS type_color
                FROM reservations r
                LEFT JOIN dining_tables t ON t.id = r.table_id
                LEFT JOIN branches b ON b.id = r.branch_id
                LEFT JOIN reservation_types rt ON rt.code = r.reservation_type
                WHERE (DATE(r.reserved_at) = ? OR r.status = 'seated')
                  AND r.status IN ('pending','confirmed','seated','waiting','cancelled','no_show')
                  AND (? = 0 OR r.branch_id = ?)
                ORDER BY r.reserved_at";
        $params = [$date, $branchId ?: 0, $branchId ?: 0];
        $rows = Database::query($sql, $params)->fetchAll();

        $categories = [
            'confirmed' => [],
            'current' => [],
            'upcoming' => [],
            'waiting' => [],
            'late' => [],
            'cancelled' => [],
        ];
        foreach ($rows as $r) {
            $status = $r['status'];
            $reservedAt = $r['reserved_at'];
            $isToday = date('Y-m-d', strtotime($reservedAt)) === $date;

            if ($status === 'seated') {
                $categories['current'][] = $r;
            } elseif ($status === 'waiting') {
                $categories['waiting'][] = $r;
            } elseif ($status === 'cancelled') {
                if ($isToday) $categories['cancelled'][] = $r;
            } elseif ($status === 'no_show') {
                if ($isToday) $categories['cancelled'][] = $r;
            } elseif ($status === 'confirmed') {
                if ($reservedAt < $graceLate) {
                    $categories['late'][] = $r;
                } elseif ($reservedAt > $now) {
                    $categories['upcoming'][] = $r;
                }
                $categories['confirmed'][] = $r;
            } elseif ($status === 'pending') {
                if ($isToday) $categories['upcoming'][] = $r;
            }
        }

        return [
            'date' => $date,
            'now' => $now,
            'branch_id' => $branchId,
            'categories' => $categories,
            'all' => $rows,
        ];
    }

    /** تغيير الحالة العام */
    private static function setStatus(int $id, string $newStatus, int $userId, string $notes, array $extra = []): bool
    {
        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new \RuntimeException('حالة غير صالحة');
        }
        $res = self::get($id);
        if (!$res) {
            throw new \RuntimeException('الحجز غير موجود');
        }
        $oldStatus = $res['status'];
        $setFields = ['status = ?'];
        $values = [$newStatus];
        foreach ($extra as $col => $val) {
            $setFields[] = "$col = ?";
            $values[] = $val;
        }
        $setFields[] = 'updated_at = NOW()';
        $values[] = $id;

        Database::beginTransaction();
        try {
            Database::query("UPDATE reservations SET " . implode(', ', $setFields) . " WHERE id = ?", $values);
            if ($res['table_id']) {
                $tableStatus = match ($newStatus) {
                    'seated' => 'occupied',
                    'confirmed' => 'reserved',
                    default => 'available',
                };
                Database::query("UPDATE dining_tables SET status = ? WHERE id = ?", [$tableStatus, (int)$res['table_id']]);
                if ($tableStatus === 'available') {
                    self::assignNextWaiting($res['table_id'] ? (int)$res['table_id'] : 0);
                }
            }
            $action = match ($newStatus) {
                'confirmed' => 'confirmed',
                'seated' => 'seated',
                'cancelled' => 'cancelled',
                'rejected' => 'rejected',
                'completed' => 'completed',
                default => 'updated',
            };
            self::log($id, $action, $userId, $oldStatus, $newStatus, $notes);
            if ($newStatus === 'confirmed') {
                self::notifyCustomer($id, 'تم تأكيد حجزك', 'تم تأكيد حجزك بنجاح. نتطلع لزيارتك.');
                self::notifyManagers('reservation_confirmed', $id, 'تأكيد حجز', 'تم تأكيد حجز: ' . $res['customer_name']);
            }
            if ($newStatus === 'cancelled') {
                self::notifyCustomer($id, 'إلغاء حجز', 'تم إلغاء حجزك. السبب: ' . ($extra['cancellation_reason'] ?? ''));
                self::notifyManagers('reservation_cancelled', $id, 'إلغاء حجز', 'تم إلغاء حجز: ' . $res['customer_name']);
            }
            Database::commit();
            return true;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function get(int $id): ?array
    {
        return Database::query("SELECT * FROM reservations WHERE id = ?", [$id])->fetch() ?: null;
    }

    public static function getWithDetails(int $id): ?array
    {
        $row = Database::query(
            "SELECT r.*, t.name AS table_name, t.floor, t.section, b.name AS branch_name,
                     rt.name_ar AS type_name, rt.icon AS type_icon, rt.color AS type_color, rt.requirements AS type_requirements,
                     rt.price_per_person, rt.deposit_amount, rt.deposit_required
             FROM reservations r
             LEFT JOIN dining_tables t ON t.id = r.table_id
             LEFT JOIN branches b ON b.id = r.branch_id
             LEFT JOIN reservation_types rt ON rt.code = r.reservation_type
             WHERE r.id = ?",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function list(array $filters = []): array
    {
        $sql = "SELECT r.*, t.name AS table_name, t.floor, t.section, b.name AS branch_name
                FROM reservations r
                LEFT JOIN dining_tables t ON t.id = r.table_id
                LEFT JOIN branches b ON b.id = r.branch_id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filters['status'];
        } else {
            $sql .= " AND r.status NOT IN ('cancelled','no_show')";
        }
        if (!empty($filters['branch_id'])) {
            $sql .= " AND r.branch_id = ?";
            $params[] = (int)$filters['branch_id'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND r.reserved_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND r.reserved_at <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['q'])) {
            $sql .= " AND (r.customer_name LIKE ? OR r.customer_phone LIKE ? OR r.reservation_no LIKE ?)";
            $q = '%' . $filters['q'] . '%';
            $params[] = $q; $params[] = $q; $params[] = $q;
        }
        $sql .= " ORDER BY r.reserved_at DESC LIMIT 500";
        return Database::query($sql, $params)->fetchAll();
    }

    /** إرسال الحجز لـ Workflow عند الحاجة لموافقة إدارية */
    public static function submitForApproval(int $reservationId, int $userId, ?array $type = null): void
    {
        $type = $type ?? [];
        $templateCode = 'reservation_approval';
        $templateId = !empty($type['workflow_template_id']) ? (int)$type['workflow_template_id'] : null;
        if ($templateId) {
            $template = WorkflowTemplateService::getTemplate($templateId);
        } else {
            $template = WorkflowTemplateService::getTemplateByCode($templateCode);
        }
        if (!$template) return;
        $res = self::get($reservationId);
        if (!$res) return;
        $requestId = WorkflowEngine::createRequest((int)$template['id'], $userId, [
            'title' => 'موافقة على حجز: ' . $res['customer_name'],
            'guests' => $res['guests'],
            'reservation_type' => $res['reservation_type'] ?? 'dine_in',
            'reserved_at' => $res['reserved_at'],
            'notes' => $res['notes'] ?? '',
            'reservation_id' => $reservationId,
        ], true);
        WorkflowEngine::submitRequest($requestId, $userId);
        Database::query("UPDATE reservations SET workflow_request_id = ? WHERE id = ?", [$requestId, $reservationId]);
    }

    /** تطبيق نتيجة Workflow على الحجز */
    public static function applyWorkflowOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $reservationId = (int)($request['data']['reservation_id'] ?? 0);
        if (!$reservationId) return;
        $res = self::get($reservationId);
        if (!$res || $res['status'] !== 'pending') return;
        if ($status === 'approved') {
            self::confirm($reservationId, $actorId);
            self::log($reservationId, 'confirmed', $actorId, 'pending', 'confirmed', 'موافقة Workflow: ' . $notes);
        } elseif ($status === 'rejected') {
            self::reject($reservationId, $notes ?: 'رفض من Workflow', $actorId);
            self::log($reservationId, 'rejected', $actorId, 'pending', 'rejected', 'رفض Workflow: ' . $notes);
        }
    }

    public static function findAvailableTables(int $guests, string $reservedAt, int $duration = 90, ?int $excludeReservationId = null, ?int $branchId = null): array
    {
        $params = [$guests, $guests];
        $sql = "SELECT t.*, COALESCE(t.merged_capacity, t.max_capacity, t.capacity, t.seats) AS max_guests
                FROM dining_tables t
                WHERE t.allows_reservation = 1
                  AND (t.parent_table_id IS NULL)
                  AND t.status IN ('available','reserved','merged')
                  AND COALESCE(t.min_capacity,1) <= ?
                  AND COALESCE(t.merged_capacity, t.max_capacity, t.capacity, t.seats) >= ?";
        if ($branchId) {
            $sql .= " AND t.branch_id = ?";
            $params[] = $branchId;
        }
        $tables = Database::query($sql, $params)->fetchAll();
        $out = [];
        foreach ($tables as $t) {
            if (self::isTableAvailable((int)$t['id'], $reservedAt, $duration, $excludeReservationId)) {
                $out[] = $t;
            }
        }
        return $out;
    }

    /** اقتراح أفضل طاولة تلقائياً */
    public static function suggestBestTable(int $guests, string $reservedAt, int $duration = 90, ?string $typeCode = 'regular', ?int $branchId = null, ?int $excludeReservationId = null): ?array
    {
        $type = ReservationTypeService::getByCode($typeCode ?: 'regular') ?: [];
        $isVip = stripos($typeCode ?: '', 'vip') !== false || ($type['permission_code'] ?? '') === 'reservations.vip.manage';
        $tables = self::findAvailableTables($guests, $reservedAt, $duration, $excludeReservationId, $branchId);
        if (empty($tables)) return null;

        $vipPriority = (bool)Settings::get('floor_plan_vip_priority', 1);
        $best = null;
        $bestScore = PHP_INT_MAX;
        foreach ($tables as $t) {
            $capacity = (int)($t['max_guests'] ?? 0);
            $score = abs($capacity - $guests); // أقرب سعة للعدد

            // VIP: تفضيل الطاولات الأكبر/الأكثر خصوصية
            if ($isVip && $vipPriority) {
                $score -= ($capacity >= $guests + 2 ? 2 : 0);
            }

            // أنواع كبيرة: تفضيل طاولات بعيدة عن المدخل/الضوضاء (نفترض الأقسام الخاصة)
            if (in_array($typeCode, ['meeting','events','wedding','corporate'], true)) {
                $score -= ($capacity >= $guests + 4 ? 3 : 0);
            }

            // تفضيل الطاولات ذات الأسماء المرتبة (أقرب للمدخل غالباً)
            if (preg_match('/^\d+$/', $t['name'])) {
                $score -= 0.5;
            }

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $t;
            }
        }
        return $best;
    }

    /** تعيين طاولة تلقائياً لحجز إذا كان الإعداد مفعّلاً */
    public static function autoAssignTable(int $reservationId, int $guests, string $reservedAt, int $duration, ?string $typeCode, ?int $branchId): ?int
    {
        if (!Settings::get('floor_plan_auto_assign', 1)) return null;
        $table = self::suggestBestTable($guests, $reservedAt, $duration, $typeCode, $branchId, $reservationId);
        if (!$table) return null;
        Database::query(
            "UPDATE reservations SET table_id = ? WHERE id = ?",
            [(int)$table['id'], $reservationId]
        );
        Database::query("UPDATE dining_tables SET status = 'reserved' WHERE id = ?", [(int)$table['id']]);
        return (int)$table['id'];
    }

    /** دمج طاولات متجاورة */
    public static function mergeTables(array $tableIds, int $userId): int
    {
        if (count($tableIds) < 2) {
            throw new \RuntimeException('يجب اختيار طاولتين على الأقل للدمج');
        }
        $tables = Database::query(
            "SELECT * FROM dining_tables WHERE id IN (" . implode(',', array_fill(0, count($tableIds), '?')) . ") ORDER BY id",
            $tableIds
        )->fetchAll();
        if (count($tables) < 2) {
            throw new \RuntimeException('الطاولات غير موجودة');
        }
        $floor = $tables[0]['floor'];
        foreach ($tables as $t) {
            if ($t['floor'] !== $floor) {
                throw new \RuntimeException('لا يمكن دمج طاولات من طوابق مختلفة');
            }
            if (!in_array($t['status'], ['available','reserved'], true)) {
                throw new \RuntimeException('الطاولة ' . $t['name'] . ' غير متاحة للدمج');
            }
        }

        $parent = $tables[0];
        $totalCapacity = 0;
        foreach ($tables as $t) {
            $totalCapacity += (int)($t['merged_capacity'] ?? $t['max_capacity'] ?? $t['capacity'] ?? $t['seats'] ?? 0);
        }

        Database::beginTransaction();
        try {
            foreach ($tables as $t) {
                if ((int)$t['id'] === (int)$parent['id']) {
                    Database::query(
                        "UPDATE dining_tables SET merged_capacity = ?, status = 'merged' WHERE id = ?",
                        [$totalCapacity, $parent['id']]
                    );
                } else {
                    Database::query(
                        "UPDATE dining_tables SET parent_table_id = ?, status = 'merged' WHERE id = ?",
                        [$parent['id'], $t['id']]
                    );
                }
            }
            Helpers::audit('tables_merged', 'dining_tables', $parent['id'], 'دمج طاولات: ' . implode(',', array_column($tables, 'name')));
            Database::commit();
            return (int)$parent['id'];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** تقسيم طاولة مدمجة */
    public static function splitTables(int $parentTableId, int $userId): bool
    {
        $parent = Database::query("SELECT * FROM dining_tables WHERE id = ?", [$parentTableId])->fetch();
        if (!$parent || $parent['status'] !== 'merged') {
            throw new \RuntimeException('الطاولة غير مدمجة');
        }
        $children = Database::query("SELECT id FROM dining_tables WHERE parent_table_id = ?", [$parentTableId])->fetchAll();
        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE dining_tables SET status = 'available', merged_capacity = NULL WHERE id = ?",
                [$parentTableId]
            );
            Database::query(
                "UPDATE dining_tables SET parent_table_id = NULL, status = 'available' WHERE parent_table_id = ?",
                [$parentTableId]
            );
            Helpers::audit('tables_split', 'dining_tables', $parentTableId, 'تقسيم الطاولة ' . $parent['name']);
            Database::commit();
            WaitingListService::onTableAvailable($parentTableId);
            foreach ($children as $child) {
                WaitingListService::onTableAvailable((int)$child['id']);
            }
            return true;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function isTableAvailable(int $tableId, string $reservedAt, int $duration, ?int $excludeReservationId = null): bool
    {
        $start = new \DateTime($reservedAt);
        $end = clone $start;
        $end->modify("+{$duration} minutes");
        $sql = "SELECT id FROM reservations
                WHERE table_id = ? AND status IN ('pending','confirmed','seated')
                  AND reserved_at < ? AND estimated_end > ?";
        $params = [$tableId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')];
        if ($excludeReservationId) {
            $sql .= " AND id != ?";
            $params[] = $excludeReservationId;
        }
        return !Database::query($sql, $params)->fetch();
    }

    private static function releaseTable(int $tableId): void
    {
        Database::query("UPDATE dining_tables SET status = 'available' WHERE id = ?", [$tableId]);
        WaitingListService::onTableAvailable($tableId);
    }

    private static function assignNextWaiting(int $tableId): void
    {
        $next = Database::query(
            "SELECT id FROM reservations WHERE status='waiting' ORDER BY waitlist_position, reserved_at LIMIT 1"
        )->fetch();
        if ($next) {
            self::confirm((int)$next['id'], 0);
            Database::query("UPDATE reservations SET table_id = ? WHERE id = ?", [$tableId, (int)$next['id']]);
            Database::query("UPDATE dining_tables SET status = 'reserved' WHERE id = ?", [$tableId]);
        }
    }

    private static function estimatedEnd(string $reservedAt, int $duration): string
    {
        $dt = new \DateTime($reservedAt);
        $dt->modify("+{$duration} minutes");
        return $dt->format('Y-m-d H:i:s');
    }

    public static function log(int $reservationId, string $action, ?int $actorId, ?string $oldStatus, ?string $newStatus, ?string $notes): void
    {
        $actorName = '';
        if ($actorId) {
            $u = Database::query("SELECT full_name FROM users WHERE id = ?", [$actorId])->fetch();
            $actorName = $u['full_name'] ?? '';
        }
        Database::query(
            "INSERT INTO reservation_logs (reservation_id, action, actor_id, actor_name, old_status, new_status, notes)
             VALUES (?,?,?,?,?,?,?)",
            [$reservationId, $action, $actorId ?: null, $actorName, $oldStatus, $newStatus, $notes]
        );
        Helpers::audit($action, 'reservations', $reservationId, $notes);
    }

    private static function notifyManagers(string $eventType, int $reservationId, string $title, string $body): void
    {
        $res = self::getWithDetails($reservationId);
        if (!$res) return;
        $link = '/reservations/' . $reservationId;
        $managers = Database::query(
            "SELECT DISTINCT u.id
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1
               AND (r.level >= 30 OR u.role_id IN (
                   SELECT rp.role_id FROM role_permissions rp
                   JOIN permissions p ON p.id = rp.permission_id
                   WHERE p.code IN ('reservations.manage','reservations.approve')
               ))"
        )->fetchAll();
        foreach ($managers as $m) {
            Helpers::notify((int)$m['id'], $title, $body, $link);
        }
    }

    private static function notifyCustomer(int $reservationId, string $title, string $body): void
    {
        $res = self::get($reservationId);
        if (!$res || empty($res['customer_id'])) return;
        $cust = Database::query("SELECT user_id, email FROM customers WHERE id = ?", [(int)$res['customer_id']])->fetch();
        if ($cust && !empty($cust['user_id'])) {
            Helpers::notify((int)$cust['user_id'], $title, $body, '/reservations');
        }
        if ($res['customer_email']) {
            NotificationService::sendEmail($res['customer_email'], $title, $body, $cust['user_id'] ?? null);
        }
    }

    /** ==================== الحجز العام (Public Booking) ==================== */

    /** إنشاء حجز عام من العميل */
    public static function publicBook(array $data): int
    {
        if (!Settings::get('reservation_public_enabled', 1)) {
            throw new \RuntimeException('الحجز العام معطل حالياً');
        }
        self::validatePublicBooking($data);
        $otpEnabled = (int)Settings::get('reservation_otp_enabled', 0);
        if ($otpEnabled && empty($data['otp'])) {
            throw new \RuntimeException('رمز التحقق مطلوب');
        }
        if ($otpEnabled && !self::verifyOtp((string)$data['customer_phone'], (string)$data['otp'])) {
            throw new \RuntimeException('رمز التحقق غير صحيح أو منتهي');
        }
        $status = (string)Settings::get('reservation_public_status', 'pending');
        if (!in_array($status, ['pending','confirmed'], true)) {
            $status = 'pending';
        }
        return self::create(array_merge($data, [
            'source' => 'web',
            'force_status' => $status,
            'table_id' => $data['table_id'] ?? null,
        ]), 0);
    }

    /** التحقق من قواعد الحجز العام */
    public static function validatePublicBooking(array $data): void
    {
        $required = ['customer_name','customer_phone','guests','reserved_at','branch_id','reservation_type'];
        foreach ($required as $f) {
            if (empty($data[$f])) {
                throw new \RuntimeException('الحقل ' . $f . ' مطلوب');
            }
        }
        $typeCode = (string)$data['reservation_type'];
        $type = ReservationTypeService::getByCode($typeCode);
        if (!$type || !$type['is_active']) {
            throw new \RuntimeException('نوع الحجز غير موجود أو غير مفعل');
        }
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $data['reserved_at']) ?: \DateTime::createFromFormat('Y-m-d H:i:s', $data['reserved_at']);
        if (!$dt) {
            throw new \RuntimeException('تاريخ/وقت الحجز غير صالح');
        }
        $now = new \DateTime();
        if ($dt < $now) {
            throw new \RuntimeException('لا يمكن الحجز في تاريخ/وقت سابق');
        }
        $maxDays = (int)($type['max_advance_days'] ?? Settings::get('reservation_max_advance_days', 30));
        $maxDate = (clone $now)->modify("+{$maxDays} days");
        if ($dt > $maxDate) {
            throw new \RuntimeException('لا يمكن الحجز قبل أكثر من ' . $maxDays . ' يوم');
        }
        $diffHours = ($dt->getTimestamp() - $now->getTimestamp()) / 3600;
        $minAdvance = (int)($type['min_advance_hours'] ?? 0);
        if ($diffHours < $minAdvance) {
            throw new \RuntimeException('يجب الحجز قبل ' . $minAdvance . ' ساعة على الأقل لنوع ' . $type['name_ar']);
        }
        $time = $dt->format('H:i');
        $open = (string)Settings::get('reservation_opening_time', '09:00');
        $close = (string)Settings::get('reservation_closing_time', '23:00');
        if ($time < $open || $time >= $close) {
            throw new \RuntimeException('الوقت خارج أوقات العمل (' . $open . ' - ' . $close . ')');
        }
        $branchId = (int)$data['branch_id'];
        $guests = (int)$data['guests'];
        if ($guests < (int)$type['min_guests'] || $guests > (int)$type['max_guests']) {
            throw new \RuntimeException('عدد الضيوف لنوع ' . $type['name_ar'] . ' يجب أن يكون بين ' . $type['min_guests'] . ' و ' . $type['max_guests']);
        }
        $duration = (int)($data['duration_minutes'] ?? $type['duration_minutes'] ?? Settings::get('reservation_public_default_duration', 90));
        $end = (clone $dt)->modify("+{$duration} minutes");
        $closeDt = \DateTime::createFromFormat('Y-m-d H:i', $dt->format('Y-m-d') . ' ' . $close);
        if ($end > $closeDt) {
            throw new \RuntimeException('الحجز يتجاوز وقت الإغلاق');
        }
        $capacity = self::getBranchCapacity($branchId);
        if ($capacity <= 0) {
            throw new \RuntimeException('لا توجد طاولات قابلة للحجز في هذا الفرع');
        }
        $slot = self::countReservationsInSlot($branchId, $dt->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
        $maxPerSlot = (int)Settings::get('reservation_max_per_slot', 5);
        $peakHours = (string)Settings::get('reservation_peak_hours', '');
        if ($peakHours && self::isPeakHour($time, $peakHours)) {
            $maxPerSlot = max(1, (int)($maxPerSlot * 0.7));
        }
        if ($slot['count'] >= $maxPerSlot) {
            throw new \RuntimeException('الفترة الزمنية المختارة ممتلئة، يرجى اختيار وقت آخر');
        }
        if (($slot['total_guests'] + $guests) > $capacity) {
            throw new \RuntimeException('عدد الضيوف يتجاوز الطاقة الاستيعابية المتاحة في هذا الوقت');
        }
        $window = (int)Settings::get('reservation_duplicate_window_hours', 24);
        $duplicate = Database::query(
            "SELECT id FROM reservations
             WHERE customer_phone = ? AND branch_id = ? AND status NOT IN ('cancelled','rejected','completed')
               AND reserved_at >= DATE_SUB(?, INTERVAL ? HOUR) AND reserved_at <= DATE_ADD(?, INTERVAL ? HOUR)
             LIMIT 1",
            [$data['customer_phone'], $branchId, $dt->format('Y-m-d H:i:s'), $window, $dt->format('Y-m-d H:i:s'), $window]
        )->fetch();
        if ($duplicate) {
            throw new \RuntimeException('يوجد حجز سابق بنفس الرقم في نفس الفترة الزمنية');
        }
    }

    /** إرجاع الفترات الزمنية المتاحة للحجز العام */
    public static function getPublicSlots(int $branchId, string $date, int $guests, ?string $typeCode = null): array
    {
        $type = $typeCode ? ReservationTypeService::getByCode($typeCode) : null;
        $duration = (int)($type['duration_minutes'] ?? 90);
        $open = (string)Settings::get('reservation_opening_time', '09:00');
        $close = (string)Settings::get('reservation_closing_time', '23:00');
        $interval = (int)Settings::get('reservation_time_interval', 30);
        $maxPerSlot = (int)Settings::get('reservation_max_per_slot', 5);
        $capacity = self::getBranchCapacity($branchId);
        $peakHours = (string)Settings::get('reservation_peak_hours', '');
        $slots = [];
        $start = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $open);
        $end = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $close);
        $now = new \DateTime();
        while ($start < $end) {
            $slotEnd = (clone $start)->modify("+{$duration} minutes");
            if ($slotEnd > $end) break;
            $timeStr = $start->format('H:i');
            $isPeak = self::isPeakHour($timeStr, $peakHours);
            $slotMax = $isPeak ? max(1, (int)($maxPerSlot * 0.7)) : $maxPerSlot;
            $current = self::countReservationsInSlot($branchId, $start->format('Y-m-d H:i:s'), $slotEnd->format('Y-m-d H:i:s'));
            $remaining = max(0, min($slotMax - $current['count'], $capacity - $current['total_guests']));
            $slots[] = [
                'time' => $timeStr,
                'available' => $remaining > 0 && $start > $now,
                'remaining' => max(0, $remaining),
                'peak' => $isPeak,
                'count' => $current['count'],
                'total_guests' => $current['total_guests'],
            ];
            $start->modify("+{$interval} minutes");
        }
        return $slots;
    }

    /** إرسال OTP للحجز العام */
    public static function sendOtp(string $phone, ?string $email): string
    {
        $otp = sprintf('%04d', random_int(0, 9999));
        $expires = (new \DateTime())->modify('+10 minutes')->format('Y-m-d H:i:s');
        Database::query(
            "INSERT INTO reservation_otp (phone, email, otp, expires_at) VALUES (?,?,?,?)",
            [$phone, $email ?: null, $otp, $expires]
        );
        if ($email) {
            NotificationService::sendEmail($email, 'رمز التحقق من الحجز', 'رمز التحقق الخاص بك هو: ' . $otp, null);
        }
        return $otp;
    }

    /** التحقق من OTP */
    public static function verifyOtp(string $phone, string $otp): bool
    {
        $row = Database::query(
            "SELECT id FROM reservation_otp
             WHERE phone = ? AND otp = ? AND verified_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1",
            [$phone, $otp]
        )->fetch();
        if ($row) {
            Database::query("UPDATE reservation_otp SET verified_at = NOW() WHERE id = ?", [(int)$row['id']]);
            return true;
        }
        return false;
    }

    public static function getBranchCapacity(int $branchId): int
    {
        $row = Database::query(
            "SELECT COALESCE(SUM(COALESCE(max_capacity, capacity, seats)),0) AS total
             FROM dining_tables
             WHERE (branch_id = ? OR ? = 0) AND allows_reservation != 0",
            [$branchId, $branchId]
        )->fetch();
        return (int)$row['total'];
    }

    public static function countReservationsInSlot(int $branchId, string $start, string $end): array
    {
        $row = Database::query(
            "SELECT COUNT(*) AS c, COALESCE(SUM(guests),0) AS total_guests
             FROM reservations
             WHERE branch_id = ? AND status IN ('pending','confirmed','seated')
               AND reserved_at < ? AND estimated_end > ?",
            [$branchId, $end, $start]
        )->fetch();
        return ['count' => (int)$row['c'], 'total_guests' => (int)$row['total_guests']];
    }

    private static function isPeakHour(string $time, string $peakHours): bool
    {
        if (!$peakHours || !str_contains($peakHours, '-')) return false;
        [$from, $to] = explode('-', $peakHours, 2);
        return $time >= trim($from) && $time < trim($to);
    }

    private static function validate(array $data, bool $requireAll = true): array
    {
        $out = [];
        $required = ['customer_name','guests','reserved_at'];
        foreach ($required as $f) {
            if ($requireAll && (!isset($data[$f]) || $data[$f] === '')) {
                throw new \RuntimeException('الحقل ' . $f . ' مطلوب');
            }
            $out[$f] = $data[$f] ?? null;
        }
        $optional = ['customer_id','customer_phone','customer_email','is_vip','branch_id','table_id','duration_minutes','reservation_type','source','notes','customer_notes','call_center_notes','party_size','expected_arrival_at'];
        foreach ($optional as $f) {
            if (isset($data[$f])) {
                $out[$f] = $data[$f] !== '' ? $data[$f] : null;
            }
        }
        if (isset($out['is_vip'])) {
            $out['is_vip'] = (int)(bool)$out['is_vip'];
        }
        if (isset($out['party_size'])) {
            $out['party_size'] = (int)$out['party_size'];
            if ($out['party_size'] < 1) {
                throw new \RuntimeException('عدد الأفراد يجب أن يكون 1 على الأقل');
            }
        }
        $out['duration_minutes'] = (int)($out['duration_minutes'] ?? Settings::get('reservation_default_duration', 90));
        if ($out['duration_minutes'] < 15) {
            throw new \RuntimeException('المدة يجب أن تكون 15 دقيقة على الأقل');
        }
        // دعم تنسيق datetime-local المرسل من المتصفح (Y-m-d\TH:i)
        if (!empty($out['reserved_at'])) {
            $out['reserved_at'] = str_replace('T', ' ', $out['reserved_at']);
        }
        if (!empty($out['expected_arrival_at'])) {
            $out['expected_arrival_at'] = str_replace('T', ' ', $out['expected_arrival_at']);
        }
        if (!\DateTime::createFromFormat('Y-m-d H:i:s', $out['reserved_at']) && !\DateTime::createFromFormat('Y-m-d H:i', $out['reserved_at'])) {
            throw new \RuntimeException('تاريخ/وقت الحجز غير صالح');
        }
        return $out;
    }
}
