<?php
namespace App\Controllers;

use App\Core\{Controller, Database, StockService, ApprovalService, Auth};

class DashboardController extends Controller
{
    public function index(): void
    {
        $today = date('Y-m-d');

        $sales = ['orders_count' => 0, 'total' => 0];
        $byType = [];
        $activeOrders = 0;
        $reservationsToday = 0;
        $topItems = [];
        if (Auth::can('sales.view')) {
            $sales = Database::query(
                "SELECT COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS total
                 FROM orders WHERE DATE(created_at) = ? AND status != 'cancelled'",
                [$today]
            )->fetch();

            $byType = Database::query(
                "SELECT order_type, COUNT(*) AS c FROM orders
                 WHERE DATE(created_at) = ? AND status != 'cancelled'
                 GROUP BY order_type",
                [$today]
            )->fetchAll();

            $activeOrders = Database::query(
                "SELECT COUNT(*) AS c FROM orders
                 WHERE status IN ('pending','confirmed','preparing','ready','delivering')"
            )->fetch()['c'];

            $topItems = Database::query(
                "SELECT mi.name, SUM(oi.qty) AS sold
                 FROM order_items oi
                 JOIN menu_items mi ON mi.id = oi.menu_item_id
                 JOIN orders o ON o.id = oi.order_id
                 WHERE DATE(o.created_at) = ? AND o.status != 'cancelled'
                 GROUP BY mi.id ORDER BY sold DESC LIMIT 5",
                [$today]
            )->fetchAll();
        }

        if (Auth::can('reservations.manage')) {
            $reservationsToday = Database::query(
                "SELECT COUNT(*) AS c FROM reservations
                 WHERE DATE(reserved_at) = ? AND status IN ('pending','confirmed')",
                [$today]
            )->fetch()['c'];
        }

        $expensesToday = 0;
        if (Auth::can('finance.view')) {
            $expensesToday = Database::query(
                "SELECT COALESCE(SUM(amount),0) AS total FROM expenses
                 WHERE expense_date = ? AND status IN ('approved','paid')",
                [$today]
            )->fetch()['total'];
        }

        $lowStock = Auth::can('inventory.view') ? StockService::lowStock() : [];
        $pendingApprovals = Auth::can('approvals.decide') ? ApprovalService::pendingForCurrentUser() : [];

        $procurement = [];
        if (Auth::can('procurement.dashboard') || Auth::can('material_request.view')) {
            $procurement = [
                'pending_mr' => (int)Database::query(
                    "SELECT COUNT(*) AS c FROM material_requests WHERE status IN ('submitted','pending') AND deleted_at IS NULL"
                )->fetch()['c'],
                'pending_pr' => (int)Database::query(
                    "SELECT COUNT(*) AS c FROM purchase_requests WHERE status = 'pending'"
                )->fetch()['c'],
                'open_po' => (int)Database::query(
                    "SELECT COUNT(*) AS c FROM purchase_orders WHERE status IN ('sent','partially_received')"
                )->fetch()['c'],
                'today_receipts' => (float)Database::query(
                    "SELECT COALESCE(SUM(gri.qty * gri.unit_price),0) AS t
                     FROM goods_receipts gr JOIN goods_receipt_items gri ON gri.receipt_id = gr.id
                     WHERE DATE(gr.created_at) = ?"
                , [$today])->fetch()['t'],
                'unpaid_invoices' => (float)Database::query(
                    "SELECT COALESCE(SUM(remaining_amount),0) AS t FROM purchase_invoices WHERE payment_status IN ('unpaid','partial')"
                )->fetch()['t'],
            ];
        }

        $this->view('dashboard/index', [
            'title'             => 'لوحة المعلومات',
            'sales'             => $sales,
            'byType'            => $byType,
            'activeOrders'      => $activeOrders,
            'reservationsToday' => $reservationsToday,
            'expensesToday'     => $expensesToday,
            'lowStock'          => $lowStock,
            'pendingApprovals'  => $pendingApprovals,
            'topItems'          => $topItems,
            'procurement'       => $procurement,
        ]);
    }
}
