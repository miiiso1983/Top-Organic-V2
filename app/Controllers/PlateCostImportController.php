<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Response, Helpers, Decimal};
use App\Core\PlateCost\{ImportService, UnitMapper, RecalculationService};
use App\Models\{PlateCostImportRun, PlateCostDish, PlateCostDishMaterialBom, PlateCostPreparedComponentBom, PlateCostPurchasePrice};

class PlateCostImportController extends Controller
{
    private const UPLOAD_DIR = BASE_PATH . '/public/uploads/plate_cost_imports';

    public function index(): void
    {
        $runs = PlateCostImportRun::list();
        $this->view('inventory/plate_cost_imports_index', [
            'title' => 'استيراد تكلفة الأطباق',
            'runs' => $runs,
        ]);
    }

    public function store(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف صالح');
            Response::redirect('/inventory/plate-cost-imports');
        }

        $file = $_FILES['import_file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), ['xlsx', 'xls'], true)) {
            $this->flash('danger', 'يُسمح فقط بملفات Excel (.xlsx, .xls)');
            Response::redirect('/inventory/plate-cost-imports');
        }

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }
        $storedName = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $storedPath = self::UPLOAD_DIR . '/' . $storedName;
        move_uploaded_file($file['tmp_name'], $storedPath);

        try {
            $runId = ImportService::import($storedPath, $file['name'], Auth::id());
        } catch (\Throwable $e) {
            $this->flash('danger', 'حدث خطأ أثناء معالجة الملف: ' . $e->getMessage());
            Response::redirect('/inventory/plate-cost-imports');
        }
        $run = PlateCostImportRun::get($runId);

        if ($run['status'] === 'error') {
            $this->flash('danger', 'فشل الاستيراد: ' . $this->firstError($run));
        } elseif ($run['status'] === 'partial') {
            $this->flash('warning', 'تم الاستيراد مع ملاحظات: ' . $this->firstError($run));
        } else {
            $this->flash('success', 'تم استيراد الملف بنجاح');
        }

        Response::redirect('/inventory/plate-cost-imports/' . $runId);
    }

    public function show(int $id): void
    {
        $run = PlateCostImportRun::get($id);
        if (!$run) {
            Response::abort(404, 'تقرير الاستيراد غير موجود');
        }

        $dishes = PlateCostDish::getByRun($id);
        $materials = PlateCostDishMaterialBom::getByRun($id);
        $prepared = PlateCostPreparedComponentBom::getByRun($id);

        $this->view('inventory/plate_cost_imports_show', [
            'title' => 'تقرير استيراد تكلفة الأطباق',
            'run' => $run,
            'dishes' => $dishes,
            'materials' => $materials,
            'prepared' => $prepared,
            'errors' => json_decode($run['errors_json'] ?? '[]', true) ?: [],
            'warnings' => json_decode($run['warnings_json'] ?? '[]', true) ?: [],
        ]);
    }

    public function recipes(int $id): void
    {
        $run = PlateCostImportRun::get($id);
        if (!$run) {
            Response::abort(404, 'تقرير الاستيراد غير موجود');
        }
        $dishes = PlateCostDishMaterialBom::getByRunGroupedByDish($id);
        $this->view('inventory/plate_cost_recipes', [
            'title' => 'وصفات الأطباق - ' . $run['source_filename'],
            'run' => $run,
            'dishes' => $dishes,
        ]);
    }

    public function plateCost(int $id): void
    {
        $run = PlateCostImportRun::get($id);
        if (!$run) {
            Response::abort(404, 'تقرير الاستيراد غير موجود');
        }
        $dishName = $_GET['dish'] ?? null;
        $dishes = PlateCostDish::getByRun($id);
        $selected = $dishName ? PlateCostDish::getByName($id, $dishName) : null;
        $lines = $dishName ? PlateCostDishMaterialBom::getByDish($id, $dishName) : PlateCostDishMaterialBom::getByRun($id);
        $this->view('inventory/plate_cost_plate_cost', [
            'title' => 'تكلفة الأطباق - ' . $run['source_filename'],
            'run' => $run,
            'dishes' => $dishes,
            'selected' => $selected,
            'dishName' => $dishName,
            'lines' => $lines,
        ]);
    }

    public function updateDishLine(int $id): void
    {
        $lineId = (int) ($_POST['line_id'] ?? 0);
        $rate = self::pctToDecimal((string) ($_POST['department_waste_rate'] ?? '0'));
        $dishName = (string) ($_POST['dish_name'] ?? '');
        if ($lineId) {
            PlateCostDishMaterialBom::updateDepartmentWasteRate($lineId, $rate);
            RecalculationService::recalculateRun($id);
            $this->flash('success', 'تم تحديث الهدر وإعادة حساب تكلفة الأطباق');
        }
        Response::redirect('/inventory/plate-cost-imports/' . $id . '/plate-cost?dish=' . urlencode($dishName));
    }

    public function preparedCost(int $id): void
    {
        $run = PlateCostImportRun::get($id);
        if (!$run) {
            Response::abort(404, 'تقرير الاستيراد غير موجود');
        }
        $preparedName = $_GET['prepared'] ?? null;
        $preparedList = array_keys(PlateCostPreparedComponentBom::getByRunGroupedByPrepared($id));
        $selected = $preparedName ? ['prepared_name' => $preparedName] : null;
        $lines = $preparedName ? PlateCostPreparedComponentBom::getByPrepared($id, $preparedName) : PlateCostPreparedComponentBom::getByRun($id);
        $this->view('inventory/plate_cost_prepared_cost', [
            'title' => 'تكلفة المواد المحضرة - ' . $run['source_filename'],
            'run' => $run,
            'preparedList' => $preparedList,
            'selected' => $selected,
            'preparedName' => $preparedName,
            'lines' => $lines,
        ]);
    }

    public function updatePreparedLine(int $id): void
    {
        $lineId = (int) ($_POST['line_id'] ?? 0);
        $rate = self::pctToDecimal((string) ($_POST['department_waste_rate'] ?? '0'));
        $preparedName = (string) ($_POST['prepared_name'] ?? '');
        if ($lineId) {
            PlateCostPreparedComponentBom::updateDepartmentWasteRate($lineId, $rate);
            RecalculationService::recalculateRun($id);
            $this->flash('success', 'تم تحديث الهدر وإعادة حساب تكلفة المواد المحضرة والأطباق');
        }
        Response::redirect('/inventory/plate-cost-imports/' . $id . '/prepared-cost?prepared=' . urlencode($preparedName));
    }

    public function prices(int $id): void
    {
        $run = PlateCostImportRun::get($id);
        if (!$run) {
            Response::abort(404, 'تقرير الاستيراد غير موجود');
        }
        $prices = PlateCostPurchasePrice::getByRun($id);
        $materials = PlateCostDishMaterialBom::getByRun($id);
        $prepared = PlateCostPreparedComponentBom::getByRun($id);
        $unitMap = [];
        foreach ($materials as $m) {
            if (!isset($unitMap[$m['material_name']])) {
                $unitMap[$m['material_name']] = $m['unit'];
            }
        }
        foreach ($prepared as $p) {
            if (!isset($unitMap[$p['ingredient_name']])) {
                $unitMap[$p['ingredient_name']] = $p['unit'];
            }
        }
        foreach ($prices as &$p) {
            $p['unit'] = $unitMap[$p['material_name']] ?? null;
        }
        unset($p);
        $this->view('inventory/plate_cost_prices', [
            'title' => 'الأسعار والوحدات - ' . $run['source_filename'],
            'run' => $run,
            'prices' => $prices,
        ]);
    }

    public function updatePrice(int $id): void
    {
        $priceId = (int) ($_POST['price_id'] ?? 0);
        $unitPrice = (string) ($_POST['unit_price'] ?? '0');
        $rawWaste = self::pctToDecimal((string) ($_POST['raw_waste_rate'] ?? '0'));
        if ($priceId) {
            PlateCostPurchasePrice::updatePrice($priceId, $unitPrice, $rawWaste);
            RecalculationService::recalculateRun($id);
            $this->flash('success', 'تم تحديث السعر والهدر وإعادة حساب كافة التكاليف');
        }
        Response::redirect('/inventory/plate-cost-imports/' . $id . '/prices');
    }

    private static function pctToDecimal(string $value): string
    {
        return Decimal::of($value)->div('100')->toString(8);
    }

    private function firstError(array $run): string
    {
        $errors = json_decode($run['errors_json'] ?? '[]', true) ?: [];
        return $errors[0] ?? ($run['warnings_json'] ? 'راجع التحذيرات' : 'تفقد التقرير');
    }
}
