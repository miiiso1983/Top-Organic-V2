<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, Helpers, ImportHelper};

class SupplierController extends Controller
{
    public function index(): void
    {
        $suppliers = Database::query(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) AS orders_count,
                    (SELECT ROUND(AVG((e.quality + e.delivery + e.pricing) / 3), 1)
                     FROM supplier_evaluations e WHERE e.supplier_id = s.id) AS avg_rating
             FROM suppliers s ORDER BY s.name"
        )->fetchAll();

        $this->view('purchasing/suppliers', [
            'title' => 'الموردون', 'suppliers' => $suppliers,
        ]);
    }

    public function store(): void
    {
        Database::query(
            "INSERT INTO suppliers (name, phone, email, address, contract_start, contract_end)
             VALUES (?,?,?,?,?,?)",
            [
                (string)$this->input('name'),
                (string)$this->input('phone'),
                (string)$this->input('email'),
                (string)$this->input('address'),
                $this->input('contract_start') ?: null,
                $this->input('contract_end') ?: null,
            ]
        );
        $this->flash('success', 'تم إضافة المورد');
        Response::redirect('/suppliers');
    }

    public function evaluate(string $id): void
    {
        $quality  = max(1, min(5, (int)$this->input('quality', 3)));
        $delivery = max(1, min(5, (int)$this->input('delivery', 3)));
        $pricing  = max(1, min(5, (int)$this->input('pricing', 3)));

        Database::query(
            "INSERT INTO supplier_evaluations (supplier_id, quality, delivery, pricing, notes, evaluated_by)
             VALUES (?,?,?,?,?,?)",
            [(int)$id, $quality, $delivery, $pricing, (string)$this->input('notes'), Auth::id()]
        );
        $avg = (int)round(($quality + $delivery + $pricing) / 3);
        Database::query("UPDATE suppliers SET rating = ? WHERE id = ?", [$avg, (int)$id]);
        $this->flash('success', 'تم تسجيل التقييم');
        Response::redirect('/suppliers');
    }

    public function downloadTemplate(): void
    {
        ImportHelper::downloadTemplate(
            ['name', 'phone', 'email', 'address', 'contract_start', 'contract_end'],
            'suppliers_template.xlsx',
            ['شركة الفرات', '07700000000', 'info@example.com', 'بغداد', '2026-01-01', '2027-01-01']
        );
    }

    public function importSuppliers(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/suppliers');
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/suppliers');
        }
        $rows = $read['rows'];
        $errors = array_merge(
            ImportHelper::validateRequired($rows, ['name']),
            ImportHelper::validateUnique($rows, 'name'),
            ImportHelper::validateNotInDb($rows, 'name', 'suppliers', 'name')
        );
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/suppliers');
        }
        Database::beginTransaction();
        try {
            $count = 0;
            foreach ($rows as $row) {
                Database::query(
                    "INSERT INTO suppliers (name, phone, email, address, contract_start, contract_end)
                     VALUES (?,?,?,?,?,?)",
                    [trim((string)$row['name']), trim((string)($row['phone'] ?? '')), trim((string)($row['email'] ?? '')),
                     trim((string)($row['address'] ?? '')), $row['contract_start'] ?? null, $row['contract_end'] ?? null]
                );
                $count++;
            }
            Database::commit();
            Helpers::audit('استيراد موردين', 'suppliers', 0, "created:$count");
            $this->flash('success', "تم استيراد $count مورد");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء الحفظ: ' . $e->getMessage());
        }
        Response::redirect('/suppliers');
    }
}
