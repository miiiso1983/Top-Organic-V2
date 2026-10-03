<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Database};

class ReservationsModuleController extends Controller
{
    public function index(): void
    {
        $today = date('Y-m-d');
        $branchId = (int)($_GET['branch_id'] ?? Auth::branchId() ?? 0);

        $stats = Database::query(
            "SELECT
                COUNT(*) AS total_reservations,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) AS confirmed,
                COUNT(CASE WHEN status = 'seated' THEN 1 END) AS seated,
                COUNT(CASE WHEN status = 'waiting' THEN 1 END) AS waiting,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled,
                COUNT(CASE WHEN status = 'no_show' THEN 1 END) AS no_show
             FROM reservations
             WHERE DATE(reserved_at) = ? AND (? = 0 OR branch_id = ?)",
            [$today, $branchId, $branchId]
        )->fetch();

        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
        $tomorrow = ['total' => 0, 'large_groups' => 0];
        $tomorrowError = null;
        try {
            $tomorrow = Database::query(
                "SELECT
                    COUNT(*) AS total,
                    COUNT(CASE WHEN guests > 9 THEN 1 END) AS large_groups
                 FROM reservations
                 WHERE DATE(reserved_at) = ?
                   AND status NOT IN ('cancelled','no_show','rejected')
                   AND (? = 0 OR branch_id = ?)",
                [$tomorrowDate, $branchId, $branchId]
            )->fetch();
        } catch (\Throwable $e) {
            $tomorrowError = $e->getMessage();
        }

        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();

        $this->view('sales/reservations_module', [
            'title' => 'وحدة الحجوزات والاستقبال',
            'stats' => $stats,
            'branches' => $branches,
            'branch_id' => $branchId,
            'tomorrow' => $tomorrow,
            'tomorrow_error' => $tomorrowError,
        ]);
    }
}
