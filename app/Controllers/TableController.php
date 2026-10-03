<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, ReservationService, WaitingListService};
use App\Models\PreparationKitchen;

class TableController extends Controller
{
    public function index(): void
    {
        $tables = Database::query(
            "SELECT t.*, f.name AS floor_name,
               (SELECT o.order_no FROM orders o
                WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.id DESC LIMIT 1) AS current_order
             FROM dining_tables t
             LEFT JOIN floors f ON f.id = t.floor_id
             ORDER BY f.sort_order, t.floor, t.name"
        )->fetchAll();
        $floors = Database::query(
            "SELECT f.*, pk.name AS preparation_kitchen_name
             FROM floors f
             LEFT JOIN preparation_kitchens pk ON pk.id = f.preparation_kitchen_id
             WHERE f.is_active=1 ORDER BY f.sort_order, f.id"
        )->fetchAll();
        $preparationKitchens = PreparationKitchen::all();

        $this->view('sales/tables', [
            'title'               => 'الطاولات',
            'tables'              => $tables,
            'floors'              => $floors,
            'preparationKitchens' => $preparationKitchens,
        ]);
    }

    public function store(): void
    {
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم الطاولة مطلوب');
            Response::redirect('/tables');
        }
        $capacity = (int)$this->input('capacity', 4);
        if ($capacity < 1) {
            $capacity = 1;
        }
        Database::query(
            "INSERT INTO dining_tables (name, hall, capacity, seats, floor_id, status)
             VALUES (?,?,?,?,?,?)",
            [
                $name,
                (string)$this->input('hall'),
                $capacity,
                $capacity,
                $this->input('floor_id') ?: null,
                'available',
            ]
        );
        $this->flash('success', "تم إضافة الطاولة $name");
        Response::redirect('/tables');
    }

    public function edit(string $id): void
    {
        $table = Database::query("SELECT * FROM dining_tables WHERE id = ?", [(int)$id])->fetch();
        if (!$table) {
            Response::redirect('/tables');
        }
        $floors = Database::query("SELECT * FROM floors WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
        $this->view('sales/tables_edit', ['title' => 'تعديل طاولة', 'table' => $table, 'floors' => $floors]);
    }

    public function update(string $id): void
    {
        $table = Database::query("SELECT * FROM dining_tables WHERE id = ?", [(int)$id])->fetch();
        if (!$table) {
            Response::redirect('/tables');
        }
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم الطاولة مطلوب');
            Response::redirect('/tables/' . (int)$id . '/edit');
        }
        $capacity = (int)$this->input('capacity', 4);
        if ($capacity < 1) {
            $capacity = 1;
        }
        Database::query(
            "UPDATE dining_tables
             SET name = ?, hall = ?, capacity = ?, seats = ?, floor_id = ?
             WHERE id = ?",
            [
                $name,
                (string)$this->input('hall'),
                $capacity,
                $capacity,
                $this->input('floor_id') ?: null,
                (int)$id,
            ]
        );
        $this->flash('success', "تم تحديث الطاولة $name");
        Response::redirect('/tables');
    }

    public function destroy(string $id): void
    {
        $table = Database::query("SELECT name FROM dining_tables WHERE id = ?", [(int)$id])->fetch();
        if ($table) {
            Database::query("DELETE FROM dining_tables WHERE id = ?", [(int)$id]);
            $this->flash('success', "تم حذف الطاولة {$table['name']}");
        }
        Response::redirect('/tables');
    }

    public function updateStatus(string $id): void
    {
        $status = (string)$this->input('status');
        if (in_array($status, ['available', 'occupied', 'reserved', 'cleaning', 'bill_requested'], true)) {
            Database::query("UPDATE dining_tables SET status = ? WHERE id = ?", [$status, (int)$id]);
            if ($status === 'available') {
                WaitingListService::onTableAvailable((int)$id);
            }
        }
        if ($this->isAjax()) {
            Response::json(['ok' => true, 'message' => 'تم تحديث حالة الطاولة']);
            return;
        }
        Response::redirect('/tables');
    }

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function setCleaning(string $id): void
    {
        if (!Auth::can('floor_plan.manage')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        Database::query("UPDATE dining_tables SET status = 'cleaning' WHERE id = ?", [(int)$id]);
        Response::json(['ok' => true, 'message' => 'تم تحويل الطاولة للتنظيف']);
    }

    public function merge(): void
    {
        if (!Auth::can('floor_plan.merge')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        $ids = array_map('intval', (array)($this->jsonInput()['table_ids'] ?? []));
        try {
            $parentId = ReservationService::mergeTables($ids, Auth::id());
            Response::json(['ok' => true, 'parent_id' => $parentId]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function split(string $id): void
    {
        if (!Auth::can('floor_plan.merge')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        try {
            ReservationService::splitTables((int)$id, Auth::id());
            Response::json(['ok' => true, 'message' => 'تم تقسيم الطاولة']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** ربط طاولة بطابق/صالة (للتحديث السريع) */
    public function assignFloor(string $id): void
    {
        Database::query(
            "UPDATE dining_tables SET floor_id = ? WHERE id = ?",
            [(int)$this->input('floor_id'), (int)$id]
        );
        $this->flash('success', 'تم تحديث طابق الطاولة');
        Response::redirect('/tables');
    }

    /** إدارة الطوابق/الصالات */
    public function storeFloor(): void
    {
        Database::query(
            "INSERT INTO floors (name, sort_order, preparation_kitchen_id) VALUES (?,?,?)",
            [
                (string)$this->input('name'),
                (int)$this->input('sort_order', 0),
                (int)$this->input('preparation_kitchen_id') ?: null,
            ]
        );
        $floorId = (int)Database::lastInsertId();
        $defaultMenu = Database::query("SELECT id FROM menus WHERE is_default = 1 LIMIT 1")->fetch();
        if ($defaultMenu) {
            Database::query(
                "INSERT IGNORE INTO menu_floor_assignments (menu_id, floor_id) VALUES (?,?)",
                [(int)$defaultMenu['id'], $floorId]
            );
        }
        $this->flash('success', 'تم إضافة الطابق');
        Response::redirect('/tables');
    }

    /** تحديث مطبخ التحضير المرتبط بطابق/صالة */
    public function updateFloor(string $id): void
    {
        $floor = Database::query("SELECT * FROM floors WHERE id = ?", [(int)$id])->fetch();
        if (!$floor) {
            $this->flash('danger', 'الطابق غير موجود');
            Response::redirect('/tables');
        }
        Database::query(
            "UPDATE floors SET preparation_kitchen_id = ? WHERE id = ?",
            [(int)$this->input('preparation_kitchen_id') ?: null, (int)$id]
        );
        $this->flash('success', 'تم تحديث مطبخ الصالة');
        Response::redirect('/tables');
    }

    /** حذف طابق/صالة ونقل طاولاته إلى "بدون طابق" */
    public function destroyFloor(string $id): void
    {
        $floor = Database::query("SELECT * FROM floors WHERE id = ?", [(int)$id])->fetch();
        if (!$floor) {
            $this->flash('danger', 'الطابق غير موجود');
            Response::redirect('/tables');
        }
        Database::beginTransaction();
        try {
            Database::query("UPDATE dining_tables SET floor_id = NULL WHERE floor_id = ?", [(int)$id]);
            Database::query("DELETE FROM menu_floor_assignments WHERE floor_id = ?", [(int)$id]);
            Database::query("DELETE FROM floors WHERE id = ?", [(int)$id]);
            Database::commit();
            $this->flash('success', 'تم حذف الطابق');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'تعذر حذف الطابق: ' . $e->getMessage());
        }
        Response::redirect('/tables');
    }
}
