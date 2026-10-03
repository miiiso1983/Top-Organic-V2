<?php
namespace App\Core;

/**
 * تقارير الحجوزات والاستقبال والكول سنتر
 */
class ReservationReportService
{
    private static function baseSql(string $from, string $to, ?int $branchId = null, ?string $q = null): array
    {
        $sql = "FROM reservations r
                LEFT JOIN branches b ON b.id = r.branch_id
                LEFT JOIN dining_tables t ON t.id = r.table_id
                LEFT JOIN reservation_types rt ON rt.code = r.reservation_type
                WHERE DATE(r.reserved_at) BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($branchId) {
            $sql .= " AND r.branch_id = ?";
            $params[] = $branchId;
        }
        if ($q) {
            $sql .= " AND (r.customer_name LIKE ? OR r.customer_phone LIKE ? OR r.reservation_no LIKE ?)";
            $like = '%' . $q . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        return [$sql, $params];
    }

    public static function summary(string $from, string $to, ?int $branchId = null, ?string $q = null): array
    {
        [$base, $params] = self::baseSql($from, $to, $branchId, $q);
        $row = Database::query("SELECT COUNT(*) AS total, COALESCE(SUM(r.guests),0) AS total_guests,
                                       COALESCE(SUM(CASE WHEN r.status='seated' THEN r.guests ELSE 0 END),0) AS seated_guests,
                                       COUNT(CASE WHEN r.status='cancelled' THEN 1 END) AS cancelled,
                                       COUNT(CASE WHEN r.status='no_show' THEN 1 END) AS no_show,
                                       COUNT(CASE WHEN r.status='waiting' THEN 1 END) AS waiting,
                                       COUNT(CASE WHEN r.status='confirmed' THEN 1 END) AS confirmed,
                                       COUNT(CASE WHEN r.status='seated' THEN 1 END) AS seated
                                " . $base, $params)->fetch();
        $capacity = Database::query(
            "SELECT COALESCE(SUM(COALESCE(t.max_capacity, t.capacity, t.seats)),0) AS total
             FROM dining_tables t WHERE t.allows_reservation != 0 AND (? = 0 OR t.branch_id = ?)",
            [$branchId ?: 0, $branchId ?: 0]
        )->fetch();
        $total = (int)$row['total'];
        $seated = (int)$row['seated'];
        $cap = (int)$capacity['total'];
        $days = max(1, (int)((new \DateTime($to))->diff(new \DateTime($from))->days + 1));
        return [
            'total' => $total,
            'confirmed' => (int)$row['confirmed'],
            'seated' => $seated,
            'cancelled' => (int)$row['cancelled'],
            'no_show' => (int)$row['no_show'],
            'waiting' => (int)$row['waiting'],
            'total_guests' => (int)$row['total_guests'],
            'seated_guests' => (int)$row['seated_guests'],
            'occupancy_rate' => $cap > 0 ? round($seated / ($cap * $days) * 100, 2) : 0,
            'avg_party_size' => $total > 0 ? round((int)$row['total_guests'] / $total, 1) : 0,
        ];
    }

    public static function byBranch(string $from, string $to): array
    {
        return Database::query(
            "SELECT b.id, b.name AS branch_name, COUNT(*) AS total,
                    COALESCE(SUM(guests),0) AS guests,
                    COUNT(CASE WHEN r.status='seated' THEN 1 END) AS seated,
                    COUNT(CASE WHEN r.status='cancelled' THEN 1 END) AS cancelled,
                    COUNT(CASE WHEN r.status='no_show' THEN 1 END) AS no_show
             FROM reservations r
             LEFT JOIN branches b ON b.id = r.branch_id
             WHERE DATE(r.reserved_at) BETWEEN ? AND ?
             GROUP BY b.id, b.name ORDER BY total DESC",
            [$from, $to]
        )->fetchAll();
    }

    public static function byPeriod(string $from, string $to, ?int $branchId = null): array
    {
        [$base, $params] = self::baseSql($from, $to, $branchId);
        return Database::query(
            "SELECT DATE(r.reserved_at) AS day, COUNT(*) AS total,
                    COALESCE(SUM(guests),0) AS guests,
                    COUNT(CASE WHEN r.status='seated' THEN 1 END) AS seated
             " . $base . " GROUP BY DATE(r.reserved_at) ORDER BY day",
            $params
        )->fetchAll();
    }

    public static function byType(string $from, string $to, ?int $branchId = null): array
    {
        [$base, $params] = self::baseSql($from, $to, $branchId);
        return Database::query(
            "SELECT r.reservation_type, COALESCE(rt.name_ar, r.reservation_type) AS type_name,
                    COUNT(*) AS total, COALESCE(SUM(guests),0) AS guests,
                    COUNT(CASE WHEN r.status='seated' THEN 1 END) AS seated,
                    COUNT(CASE WHEN r.status='cancelled' THEN 1 END) AS cancelled
             " . $base . " GROUP BY r.reservation_type, rt.name_ar ORDER BY total DESC",
            $params
        )->fetchAll();
    }

    public static function occupancyByTable(string $from, string $to, ?int $branchId = null): array
    {
        $sql = "SELECT t.name AS table_name, t.floor, t.section,
                       COUNT(CASE WHEN r.status IN ('confirmed','seated') THEN 1 END) AS reservations,
                       COUNT(CASE WHEN r.status='seated' THEN 1 END) AS occupied,
                       COALESCE(SUM(CASE WHEN r.status='seated' THEN r.guests ELSE 0 END),0) AS guests,
                       COALESCE(SUM(TIMESTAMPDIFF(MINUTE, r.checked_in_at, r.checked_out_at)),0) AS minutes
                FROM dining_tables t
                LEFT JOIN reservations r ON r.table_id = t.id AND DATE(r.reserved_at) BETWEEN ? AND ?
                WHERE t.allows_reservation != 0";
        $params = [$from, $to];
        if ($branchId) {
            $sql .= " AND t.branch_id = ?";
            $params[] = $branchId;
        }
        $sql .= " GROUP BY t.id ORDER BY occupied DESC, reservations DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function topCustomers(string $from, string $to, ?int $branchId = null, int $limit = 10): array
    {
        [$base, $params] = self::baseSql($from, $to, $branchId);
        return Database::query(
            "SELECT r.customer_name, r.customer_phone, COUNT(*) AS visits,
                    COALESCE(SUM(guests),0) AS guests,
                    COUNT(CASE WHEN r.status='seated' THEN 1 END) AS seated_visits
             " . $base . " GROUP BY r.customer_phone, r.customer_name HAVING r.customer_phone IS NOT NULL
             ORDER BY visits DESC LIMIT " . (int)$limit,
            $params
        )->fetchAll();
    }

    public static function cancelled(string $from, string $to, ?int $branchId = null): array
    {
        [$base, $params] = self::baseSql($from, $to, $branchId);
        return Database::query(
            "SELECT r.id, r.reservation_no, r.customer_name, r.customer_phone, r.guests, r.reserved_at,
                    r.cancellation_reason, b.name AS branch_name
             " . $base . " AND r.status='cancelled' ORDER BY r.reserved_at DESC",
            $params
        )->fetchAll();
    }

    public static function noShow(string $from, string $to, ?int $branchId = null): array
    {
        [$base, $params] = self::baseSql($from, $to, $branchId);
        return Database::query(
            "SELECT r.id, r.reservation_no, r.customer_name, r.customer_phone, r.guests, r.reserved_at, b.name AS branch_name
             " . $base . " AND r.status='no_show' ORDER BY r.reserved_at DESC",
            $params
        )->fetchAll();
    }

    public static function avgOccupancyDuration(string $from, string $to, ?int $branchId = null): array
    {
        [$base, $params] = self::baseSql($from, $to, $branchId);
        return Database::query(
            "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, r.checked_in_at, r.checked_out_at)),0) AS avg_minutes,
                    COALESCE(SUM(TIMESTAMPDIFF(MINUTE, r.checked_in_at, r.checked_out_at)),0) AS total_minutes
             " . $base . " AND r.status='completed' AND r.checked_in_at IS NOT NULL AND r.checked_out_at IS NOT NULL",
            $params
        )->fetch();
    }

    public static function peakHours(string $from, string $to, ?int $branchId = null): array
    {
        [$base, $params] = self::baseSql($from, $to, $branchId);
        return Database::query(
            "SELECT HOUR(r.reserved_at) AS hour, COUNT(*) AS total,
                    COALESCE(SUM(guests),0) AS guests
             " . $base . " GROUP BY HOUR(r.reserved_at) ORDER BY hour",
            $params
        )->fetchAll();
    }

    public static function callCenterPerformance(string $from, string $to, ?int $branchId = null): array
    {
        $sql = "SELECT u.full_name, COUNT(*) AS total,
                       COUNT(CASE WHEN r.status='confirmed' THEN 1 END) AS confirmed,
                       COUNT(CASE WHEN r.status='seated' THEN 1 END) AS seated,
                       COUNT(CASE WHEN r.status='cancelled' THEN 1 END) AS cancelled,
                       COUNT(CASE WHEN r.status='no_show' THEN 1 END) AS no_show
                FROM reservations r
                LEFT JOIN branches b ON b.id = r.branch_id
                LEFT JOIN users u ON u.id = r.created_by
                WHERE DATE(r.reserved_at) BETWEEN ? AND ?
                  AND r.source IN ('call_center','web','online')
                  AND r.created_by IS NOT NULL";
        $params = [$from, $to];
        if ($branchId) {
            $sql .= " AND r.branch_id = ?";
            $params[] = $branchId;
        }
        $sql .= " GROUP BY u.id, u.full_name ORDER BY total DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function receptionPerformance(string $from, string $to, ?int $branchId = null): array
    {
        $logs = Database::query(
            "SELECT u.full_name, COUNT(*) AS total,
                    COUNT(CASE WHEN rl.action='checked_in' THEN 1 END) AS check_ins,
                    COUNT(CASE WHEN rl.action='checked_out' THEN 1 END) AS check_outs,
                    COUNT(CASE WHEN rl.action='waitlisted' THEN 1 END) AS waitlisted
             FROM reservation_logs rl
             LEFT JOIN reservations r ON r.id = rl.reservation_id
             LEFT JOIN users u ON u.id = rl.actor_id
             WHERE rl.action IN ('checked_in','checked_out','waitlisted')
               AND DATE(rl.created_at) BETWEEN ? AND ?
               " . ($branchId ? " AND r.branch_id = ?" : "") . "
             GROUP BY u.id, u.full_name ORDER BY total DESC",
            $branchId ? [$from, $to, $branchId] : [$from, $to]
        )->fetchAll();
        return $logs;
    }

    public static function vipReport(string $from, string $to, ?int $branchId = null): array
    {
        [$base, $params] = self::baseSql($from, $to, $branchId);
        return Database::query(
            "SELECT r.id, r.reservation_no, r.customer_name, r.customer_phone, r.guests, r.reserved_at,
                    r.status, b.name AS branch_name, t.name AS table_name
             " . $base . " AND (r.is_vip = 1 OR r.reservation_type = 'vip') ORDER BY r.reserved_at DESC",
            $params
        )->fetchAll();
    }

    public static function status(string $from, string $to, ?int $branchId = null): array
    {
        [$base, $params] = self::baseSql($from, $to, $branchId);
        return Database::query(
            "SELECT r.status, COUNT(*) AS total
             " . $base . " GROUP BY r.status ORDER BY total DESC",
            $params
        )->fetchAll();
    }
}
