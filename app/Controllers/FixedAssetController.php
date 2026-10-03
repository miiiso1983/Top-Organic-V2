<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, Helpers, FixedAssetService, ImportHelper, CostCenterService};

class FixedAssetController extends Controller
{
    private function commonData(): array
    {
        return [
            'categories' => Database::query("SELECT id, name FROM asset_categories WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'branches' => Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'costCenters' => CostCenterService::dropdown(),
            'departments' => Database::query("SELECT id, title AS name FROM org_positions WHERE is_active = 1 ORDER BY title")->fetchAll(),
            'users' => Database::query("SELECT id, full_name AS name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll(),
        ];
    }

    public function index(): void
    {
        $filters = ['status' => $this->input('status'), 'branch_id' => $this->input('branch_id'), 'category_id' => $this->input('category_id'), 'q' => $this->input('q')];
        $assets = FixedAssetService::list(array_filter($filters, fn($v) => $v !== '' && $v !== null));
        $this->view('accounting/fixed_assets/index', array_merge($this->commonData(), [
            'title' => 'الأصول الثابتة', 'assets' => $assets, 'filters' => $filters,
        ]));
    }

    public function create(): void
    {
        $this->view('accounting/fixed_assets/form', array_merge($this->commonData(), ['title' => 'إضافة أصل ثابت', 'asset' => []]));
    }

    public function store(): void
    {
        $data = $_POST;
        try {
            $id = FixedAssetService::create($data, (int)Auth::id());
            $this->flash('success', 'تم إنشاء الأصل');
            Response::redirect('/accounting/fixed-assets/' . $id);
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
            Response::redirect('/accounting/fixed-assets/create');
        }
    }

    public function show(int $id): void
    {
        $asset = FixedAssetService::find($id);
        if (!$asset) { Response::abort(404, 'الأصل غير موجود'); }
        $this->view('accounting/fixed_assets/detail', array_merge($this->commonData(), [
            'title' => $asset['name'], 'asset' => $asset,
            'transactions' => FixedAssetService::transactions($id),
            'maintenance' => FixedAssetService::maintenanceHistory($id),
            'locations' => FixedAssetService::locationHistory($id),
            'bookValue' => FixedAssetService::bookValue($id),
            'depreciation' => Database::query("SELECT period, amount, journal_entry_id FROM depreciation_entries WHERE asset_id = ? ORDER BY period DESC", [$id])->fetchAll(),
        ]));
    }

    public function edit(int $id): void
    {
        $asset = FixedAssetService::find($id);
        if (!$asset) { Response::abort(404, 'الأصل غير موجود'); }
        $this->view('accounting/fixed_assets/form', array_merge($this->commonData(), ['title' => 'تعديل أصل', 'asset' => $asset]));
    }

    public function update(int $id): void
    {
        FixedAssetService::update($id, $_POST);
        $this->flash('success', 'تم تحديث الأصل');
        Response::redirect('/accounting/fixed-assets/' . $id);
    }

    public function storeTransaction(): void
    {
        $id = (int)$this->input('asset_id');
        $type = (string)$this->input('transaction_type');
        $data = $_POST;
        $userId = (int)Auth::id();
        try {
            if (in_array($type, ['sale', 'disposal'], true)) {
                FixedAssetService::dispose($id, $type, $data['transaction_date'], (float)($data['amount'] ?? 0), trim($data['notes'] ?? ''), $userId);
            } elseif ($type === 'revaluation') {
                FixedAssetService::revalue($id, (float)($data['amount'] ?? 0), $data['transaction_date'], trim($data['notes'] ?? ''), $userId);
            } elseif ($type === 'transfer') {
                FixedAssetService::transfer($id, $data, $userId);
            } else {
                FixedAssetService::recordTransaction($id, $data, $userId);
            }
            $this->flash('success', 'تم تسجيل الحركة');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/fixed-assets/' . $id);
    }

    public function storeMaintenance(): void
    {
        $id = (int)$this->input('asset_id');
        FixedAssetService::recordMaintenance($id, $_POST, (int)Auth::id());
        $this->flash('success', 'تم تسجيل الصيانة');
        Response::redirect('/accounting/fixed-assets/' . $id);
    }

    public function runDepreciation(): void
    {
        $period = (string)$this->input('period', date('Y-m'));
        $list = FixedAssetService::depreciateAll($period);
        $this->flash('success', 'تم إحلال إهلاك ' . count($list) . ' أصل');
        Response::redirect('/accounting/fixed-assets');
    }

    public function storeProductionUnits(): void
    {
        $id = (int)$this->input('asset_id');
        FixedAssetService::setProductionUnits($id, (string)$this->input('period'), (float)$this->input('units_produced'), (float)$this->input('total_estimated_units'), (int)Auth::id());
        $this->flash('success', 'تم تسجيل وحدات الإنتاج');
        Response::redirect('/accounting/fixed-assets/' . $id);
    }

    public function scan(): void
    {
        $code = (string)$this->input('code');
        $asset = $code ? FixedAssetService::scanByBarcode($code) : null;
        if ($asset) {
            Response::redirect('/accounting/fixed-assets/' . $asset['id']);
        }
        $this->view('accounting/fixed_assets/scan', ['title' => 'مسح باركود/QR', 'asset' => $asset, 'code' => $code]);
    }

    public function reports(): void
    {
        $period = (string)$this->input('period', date('Y-m'));
        $rows = Database::query(
            "SELECT fa.asset_no, fa.name, fa.purchase_cost, fa.accumulated_depreciation, fa.book_value, fa.depreciation_method, COALESCE(SUM(de.amount),0) AS period_amount
             FROM fixed_assets fa
             LEFT JOIN depreciation_entries de ON de.asset_id = fa.id AND de.period = ?
             GROUP BY fa.id ORDER BY fa.id DESC",
            [$period]
        )->fetchAll();
        $this->view('accounting/fixed_assets/reports', ['title' => 'تقارير الإهلاك والقيمة الدفترية', 'period' => $period, 'rows' => $rows]);
    }

    public function printQr(int $id): void
    {
        $asset = FixedAssetService::find($id);
        if (!$asset) { Response::abort(404); }
        $this->viewBare('accounting/fixed_assets/qr_print', ['asset' => $asset]);
    }

    public function importTemplate(): void
    {
        ImportHelper::downloadTemplate(
            ['name', 'category_name', 'purchase_date', 'purchase_cost', 'salvage_value', 'residual_value', 'life_years', 'depreciation_method', 'branch_name', 'department_name', 'responsible_name', 'location', 'barcode', 'qr_code', 'notes'],
            'assets_import_template.xlsx',
            ['فرن مطبخ', 'معدات', date('Y-m-d'), 5000, 500, 500, 5, 'straight_line', 'الفرع الرئيسي', 'المطبخ', 'أحمد علي', 'المخزن', '', '', '']
        );
    }

    public function importAssets(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/accounting/fixed-assets');
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/accounting/fixed-assets/import');
        }
        $rows = $read['rows'];
        $errors = ImportHelper::validateRequired($rows, ['name', 'purchase_cost', 'life_years']);
        $errors = array_merge($errors, ImportHelper::validateUnique($rows, 'barcode'));
        $methods = array_keys(FixedAssetService::METHODS);
        foreach ($rows as $idx => $row) {
            $line = $idx + 2;
            $method = strtolower(trim((string)($row['depreciation_method'] ?? 'straight_line')));
            if ($method && !in_array($method, $methods, true)) {
                $errors[] = "الصف $line: طريقة الإهلاك غير صالحة";
            }
            if (empty($row['purchase_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$row['purchase_date'])) {
                $errors[] = "الصف $line: تاريخ الشراء يجب أن يكون YYYY-MM-DD";
            }
        }
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/accounting/fixed-assets/import');
        }
        Database::beginTransaction();
        try {
            $userId = (int)Auth::id();
            $count = 0;
            foreach ($rows as $row) {
                $data = [
                    'name' => trim((string)$row['name']),
                    'category_id' => $this->lookupId('asset_categories', 'name', $row['category_name'] ?? ''),
                    'purchase_date' => (string)$row['purchase_date'],
                    'purchase_cost' => (float)($row['purchase_cost'] ?? 0),
                    'salvage_value' => (float)($row['salvage_value'] ?? 0),
                    'residual_value' => (float)($row['residual_value'] ?? 0),
                    'life_years' => (int)($row['life_years'] ?? 5),
                    'depreciation_method' => strtolower(trim((string)($row['depreciation_method'] ?? 'straight_line'))),
                    'branch_id' => $this->lookupId('branches', 'name', $row['branch_name'] ?? ''),
                    'department_id' => $this->lookupId('org_positions', 'title', $row['department_name'] ?? ''),
                    'responsible_user_id' => $this->lookupId('users', 'full_name', $row['responsible_name'] ?? ''),
                    'location' => trim((string)($row['location'] ?? '')),
                    'barcode' => trim((string)($row['barcode'] ?? '')),
                    'qr_code' => trim((string)($row['qr_code'] ?? '')),
                    'notes' => trim((string)($row['notes'] ?? '')),
                ];
                FixedAssetService::create($data, $userId);
                $count++;
            }
            Database::commit();
            $this->flash('success', "تم استيراد $count أصل");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء الحفظ: ' . $e->getMessage());
        }
        Response::redirect('/accounting/fixed-assets');
    }

    private function lookupId(string $table, string $column, $value): ?int
    {
        $v = trim((string)$value);
        if (!$v) return null;
        $row = Database::query("SELECT id FROM $table WHERE $column = ? LIMIT 1", [$v])->fetch();
        return $row ? (int)$row['id'] : null;
    }

    public function importForm(): void
    {
        $this->view('accounting/fixed_assets/import', ['title' => 'استيراد الأصول من Excel', 'errors' => ImportHelper::getErrors()]);
    }

    public function maintenanceAlerts(): void
    {
        $days = (int)$this->input('days', 30);
        $today = date('Y-m-d');
        $future = date('Y-m-d', strtotime("+$days days"));
        $rows = Database::query(
            "SELECT fa.id, fa.asset_no, fa.name, fa.location, m.maintenance_date, m.next_due_date, m.provider, m.cost
             FROM fixed_assets fa
             LEFT JOIN (
                 SELECT am.*
                 FROM asset_maintenance am
                 INNER JOIN (
                     SELECT asset_id, MAX(id) AS max_id
                     FROM asset_maintenance
                     GROUP BY asset_id
                 ) latest ON latest.asset_id = am.asset_id AND latest.max_id = am.id
             ) m ON m.asset_id = fa.id
             WHERE fa.status = 'active' AND (m.next_due_date IS NULL OR m.next_due_date <= ?)
             ORDER BY m.next_due_date IS NULL DESC, m.next_due_date ASC",
            [$future]
        )->fetchAll();
        $this->view('accounting/fixed_assets/alerts', ['title' => 'تنبيهات الصيانة', 'days' => $days, 'rows' => $rows, 'today' => $today]);
    }

}
