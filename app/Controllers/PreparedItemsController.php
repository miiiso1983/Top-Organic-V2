<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, CostMath};
use App\Models\ItemCostSheet;
use App\Core\{ImportPreparedItemsHelper, PreparedItemCostService};

class PreparedItemsController extends Controller
{
    /** قائمة الأصناف المحضرة مع تكلفة الوحدة */
    public function index(): void
    {
        $search = trim((string) $this->input('search', ''));
        $where = "WHERE i.item_type = 'prepared' OR c.type = 'prepared'";
        $params = [];
        if ($search !== '') {
            $where .= " AND (i.name LIKE ? OR i.barcode LIKE ?)";
            $like = '%' . $search . '%';
            $params = [$like, $like];
        }

        $items = Database::query(
            "SELECT i.*, c.name AS category_name, c.type AS category_type, u.name AS unit_name,
                    cs.id AS sheet_id, cs.yield_qty, cs.sale_price
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             LEFT JOIN units u ON u.id = i.unit_id
             LEFT JOIN item_cost_sheets cs ON cs.item_id = i.id
             $where
             ORDER BY i.name",
            $params
        )->fetchAll();

        foreach ($items as &$it) {
            if ($it['sheet_id']) {
                $lines = ItemCostSheet::getLines((int) $it['sheet_id']);
                $totalCost = array_sum(array_column($lines, 'line_cost'));
                $it['cost_per_unit'] = CostMath::unitCost($totalCost, (float)($it['yield_qty'] ?: 1));
            } else {
                $it['cost_per_unit'] = max((float)$it['standard_cost'], (float)$it['cost_price']);
            }
        }
        unset($it);

        $this->view('inventory/prepared_items_index', [
            'title' => 'الأصناف المحضرة',
            'items' => $items,
            'search' => $search,
        ]);
    }

    /** نموذج استيراد Excel */
    public function importForm(): void
    {
        $this->view('inventory/prepared_items_import', ['title' => 'استيراد الأصناف المحضرة']);
    }

    /** معالجة ملف الاستيراد */
    public function import(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/inventory/prepared-items/import');
        }
        $result = ImportPreparedItemsHelper::process($_FILES['import_file']['tmp_name'], Auth::id());
        $msg = 'تم استيراد ' . $result['created_sheets'] . ' قالب تكلفة، وإنشاء ' . $result['created_items'] . ' صنف، وتحديث ' . $result['updated_items'] . ' صنف.';
        if ($result['errors']) {
            $this->flash('warning', $msg . ' ملاحظات: ' . implode(' | ', array_slice($result['errors'], 0, 10)));
        } else {
            $this->flash('success', $msg);
        }
        Helpers::audit('prepared_items.import', 'item_cost_sheets', 0, 'استيراد الأصناف المحضرة من Excel');
        Response::redirect('/inventory/prepared-items');
    }

    /** عرض الصنف المحضر ومكوناته */
    public function show(string $id): void
    {
        $itemId = (int) $id;
        $item = Database::query(
            "SELECT i.*, c.name AS category_name, c.type AS category_type, u.name AS unit_name
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             LEFT JOIN units u ON u.id = i.unit_id
             WHERE i.id = ?",
            [$itemId]
        )->fetch();
        if (!$item) {
            Response::abort(404, 'الصنف غير موجود');
        }

        $sheet = ItemCostSheet::getByItemId($itemId);
        $lines = [];
        $totalCost = 0.0;
        $costPerUnit = 0.0;
        if ($sheet) {
            $lines = ItemCostSheet::getLines((int) $sheet['id']);
            $totalCost = array_sum(array_column($lines, 'line_cost'));
            $costPerUnit = CostMath::unitCost($totalCost, (float)($sheet['yield_qty'] ?: 1));
        }

        $this->view('inventory/prepared_items_show', [
            'title' => 'الصنف المحضر: ' . $item['name'],
            'item' => $item,
            'sheet' => $sheet,
            'lines' => $lines,
            'totalCost' => $totalCost,
            'costPerUnit' => $costPerUnit,
        ]);
    }

    /** إعادة حساب تكلفة الصنف المحضر */
    public function recalculate(string $id): void
    {
        $itemId = (int) $id;
        PreparedItemCostService::recalculate($itemId, Auth::id());
        $this->flash('success', 'تم إعادة حساب التكلفة');
        Helpers::audit('prepared_items.recalculate', 'items', $itemId, 'إعادة حساب تكلفة صنف محضر');
        Response::redirect('/inventory/prepared-items/' . $itemId);
    }
}
