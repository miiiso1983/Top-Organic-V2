<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response};
use App\Models\ItemPriceHistory;
use App\Core\PreparedItemCostService;

/**
 * متحكم إدارة تحديث أسعار المواد (Item Price Management)
 * - يسجّل السعر الجديد في item_price_history مع تاريخ النفاذ.
 * - يحدّث items.cost_price ليعكس السعر الحالي.
 * - يعيد حساب تكاليف الأصناف المحضرة التي تستخدم هذه المادة.
 */
class ItemPriceController extends Controller
{
    /** عرض النموذج + سجل التحديثات */
    public function index(): void
    {
        $items = Database::query(
            "SELECT i.id, i.name, i.barcode, i.cost_price, i.unit_id, u.name AS unit_name
             FROM items i
             LEFT JOIN units u ON u.id = i.unit_id
             WHERE i.is_active = 1 AND i.is_prepared = 0
             ORDER BY i.name"
        )->fetchAll();

        $history = Database::query(
            "SELECT h.*, i.name AS item_name, i.barcode, u.name AS unit_name, us.full_name AS user_name
             FROM item_price_history h
             JOIN items i ON i.id = h.item_id
             LEFT JOIN units u ON u.id = i.unit_id
             LEFT JOIN users us ON us.id = h.created_by
             ORDER BY h.created_at DESC
             LIMIT 100"
        )->fetchAll();

        $this->view('inventory/item_prices', [
            'title' => 'تحديث أسعار المواد',
            'items' => $items,
            'history' => $history,
        ]);
    }

    /** حفظ تحديث السعر الجديد */
    public function store(): void
    {
        $itemId = (int) $this->input('item_id', 0);
        $priceInput = $this->input('new_price', '');
        $effectiveDate = trim((string) $this->input('effective_date', ''));
        $notes = trim((string) $this->input('notes', ''));

        if ($itemId <= 0) {
            $this->flash('danger', 'يجب اختيار الصنف.');
            Response::redirect('/inventory/prices');
        }

        if (!is_numeric($priceInput) || (float) $priceInput <= 0) {
            $this->flash('danger', 'يجب إدخال سعر صحيح أكبر من صفر.');
            Response::redirect('/inventory/prices');
        }

        if ($effectiveDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate)) {
            $effectiveDate = date('Y-m-d');
        }

        $price = (float) $priceInput;

        // 1. تسجيل السعر في الجدول التاريخي (مع تاريخ النفاذ)
        ItemPriceHistory::record($itemId, $price, $effectiveDate, $notes, Auth::id());

        // 2. تحديث سعر الكلفة الحالي في items
        Database::query("UPDATE items SET cost_price = ? WHERE id = ?", [$price, $itemId]);

        // 3. إعادة حساب تكاليف الأصناف المحضرة التي تستخدم هذه المادة
        PreparedItemCostService::recalculateAll(Auth::id());

        Helpers::audit(
            'item_price.update',
            'item_price_history',
            $itemId,
            "تحديث سعر الصنف #{$itemId} إلى {$price} بتاريخ {$effectiveDate}"
        );

        $this->flash('success', 'تم تحديث السعر وإعادة حساب تكاليف الأصناف المحضرة.');
        Response::redirect('/inventory/prices');
    }
}
