<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Helpers, Response, ExportHelper};
use App\Models\ItemCard;

class ItemCardController extends Controller
{
    /** قائمة بطاقات الأصناف */
    public function index(): void
    {
        $search = trim((string) $this->input('search', ''));
        $items = ItemCard::list($search);
        $this->view('inventory/item_cards_index', [
            'title' => 'بطاقات تعريف الأصناف',
            'items' => $items,
            'search' => $search,
        ]);
    }

    /** تصدير قائمة بطاقات الأصناف إلى Excel أو PDF */
    public function exportList(): void
    {
        $search = trim((string) $this->input('search', ''));
        $format = (string) $this->input('format', 'pdf');
        $items = ItemCard::list($search);
        foreach ($items as &$it) {
            $it['card_no'] = $it['card_no'] ?? '-';
            $it['barcode'] = $it['barcode'] ?? '-';
            $it['warehouse_name'] = $it['warehouse_name'] ?? '-';
            $it['kitchen_name'] = $it['kitchen_name'] ?? '-';
            $it['prep_time'] = ((int)($it['prep_time_minutes'] ?? 0)) ? ((int)$it['prep_time_minutes'] . ' دقيقة') : '-';
        }
        unset($it);

        $headers = ['رقم البطاقة', 'الباركود', 'اسم الصنف', 'التصنيف', 'نوع التصنيف', 'الوحدة', 'المستودع الرئيسي', 'مطبخ التحضير', 'مدة التحضير', 'الرصيد الإجمالي'];
        $rows = [];
        foreach ($items as $it) {
            $rows[] = [
                $it['card_no'],
                $it['barcode'],
                $it['name'],
                $it['category_name'] ?? '-',
                $it['category_type'] ?? '-',
                $it['unit_name'] ?? '-',
                $it['warehouse_name'],
                $it['kitchen_name'],
                $it['prep_time'],
                (float) $it['total_qty'],
            ];
        }

        $title = 'بطاقات تعريف الأصناف';
        $period = $search !== '' ? 'بحث: ' . $search : 'جميع الأصناف';

        if ($format === 'excel') {
            ExportHelper::excel('item_cards_' . date('Ymd') . '.xlsx', [[
                'title' => $title,
                'report_title' => $title,
                'period' => $period,
                'headers' => $headers,
                'rows' => $rows,
            ]]);
        } else {
            ExportHelper::pdf('item_cards_' . date('Ymd') . '.pdf', [
                'title' => $title,
                'period' => $period,
                'headers' => $headers,
                'rows' => $rows,
            ]);
        }
    }

    /** عرض بطاقة صنف واحد */
    public function show(string $id): void
    {
        $itemId = (int) $id;
        $item = ItemCard::get($itemId);
        if (!$item) {
            Response::abort(404, 'الصنف غير موجود');
        }
        $cardNo = ItemCard::ensureCardNo($itemId);
        $item['card_no'] = $cardNo;
        $movements = ItemCard::movements($itemId);
        $this->view('inventory/item_cards_show', [
            'title' => 'بطاقة تعريف: ' . $item['name'],
            'item' => $item,
            'movements' => $movements,
        ]);
    }

    /** نموذج تعديل بيانات البطاقة */
    public function edit(string $id): void
    {
        $itemId = (int) $id;
        $item = ItemCard::get($itemId);
        if (!$item) {
            Response::abort(404, 'الصنف غير موجود');
        }
        $item['card_no'] = ItemCard::ensureCardNo($itemId);
        $warehouses = Database::query("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name")->fetchAll();
        $kitchenSections = Database::query("SELECT * FROM kitchen_sections WHERE is_active = 1 ORDER BY name")->fetchAll();
        $this->view('inventory/item_cards_edit', [
            'title' => 'تحديث بطاقة: ' . $item['name'],
            'item' => $item,
            'warehouses' => $warehouses,
            'kitchenSections' => $kitchenSections,
        ]);
    }

    /** حفظ تعديلات البطاقة */
    public function update(string $id): void
    {
        $itemId = (int) $id;
        $item = ItemCard::get($itemId);
        if (!$item) {
            Response::abort(404, 'الصنف غير موجود');
        }
        $data = [
            'description' => trim((string) $this->input('description', '')),
            'main_warehouse_id' => $this->input('main_warehouse_id', ''),
            'kitchen_section_id' => $this->input('kitchen_section_id', ''),
            'prep_time_minutes' => $this->input('prep_time_minutes', ''),
            'card_no' => trim((string) $this->input('card_no', '')),
        ];
        if ($data['card_no'] === '') {
            $data['card_no'] = ItemCard::ensureCardNo($itemId);
        }
        ItemCard::update($itemId, $data);
        Helpers::audit('item_card.update', 'items', $itemId, 'تحديث بطاقة تعريف الصنف');
        $this->flash('success', 'تم تحديث بطاقة التعريف');
        Response::redirect('/inventory/item-cards/' . $itemId);
    }

    /** تصدير بطاقة الصنف إلى PDF */
    public function exportPdf(string $id): void
    {
        $itemId = (int) $id;
        $item = ItemCard::get($itemId);
        if (!$item) {
            Response::abort(404, 'الصنف غير موجود');
        }
        $item['card_no'] = ItemCard::ensureCardNo($itemId);
        $movements = array_slice(ItemCard::movements($itemId), 0, 10);
        ExportHelper::itemCardPdf('item_card_' . $item['card_no'] . '.pdf', [
            'item' => $item,
            'movements' => $movements,
        ]);
    }
}
