<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth};

/**
 * التأكيد والتذكير — متابعة حجوزات اليوم التالي
 */
class BookingController extends Controller
{
    public function tomorrowReminders(): void
    {
        $branchId = (int)(Auth::branchId() ?? 0);
        $params = [];
        $branchWhere = '';
        if ($branchId) {
            $branchWhere = " AND r.branch_id = ?";
            $params[] = $branchId;
        }

        $reservations = Database::query(
            "SELECT r.id, r.reservation_no, r.customer_name, r.customer_phone,
                    r.guests, r.reserved_at, r.status, r.notes,
                    t.name AS table_name, t.floor AS table_floor
             FROM reservations r
             LEFT JOIN dining_tables t ON t.id = r.table_id
             WHERE DATE(r.reserved_at) = CURDATE() + INTERVAL 1 DAY
               AND r.status NOT IN ('cancelled','no_show','rejected')
               $branchWhere
             ORDER BY r.reserved_at ASC",
            $params
        )->fetchAll();

        $statusLabels = ['pending'=>'بانتظار التأكيد','confirmed'=>'مؤكد','seated'=>'تم الجلوس','cancelled'=>'ملغي','no_show'=>'لم يحضر','waiting'=>'قائمة انتظار','rejected'=>'مرفوض','completed'=>'مكتمل'];
        $statusBadge  = ['pending'=>'warning','confirmed'=>'success','seated'=>'primary','cancelled'=>'danger','no_show'=>'secondary','waiting'=>'info','rejected'=>'dark','completed'=>'success'];

        $this->view('sales/booking_reminders', [
            'title'        => 'التأكيد والتذكير — حجوزات الغد',
            'reservations' => $reservations,
            'count'        => count($reservations),
            'highPriority' => array_filter($reservations, fn($r) => (int)$r['guests'] > 9),
            'statusLabels' => $statusLabels,
            'statusBadge'  => $statusBadge,
        ]);
    }
}
