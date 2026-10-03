<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, StockService, ProductionService, MaterialRequestService, AccountingService};

class ProductionController extends Controller
{
    public function index(): void
    {
        $productions = Database::query(
            "SELECT p.*, i.name AS item_name FROM production_orders p
             JOIN items i ON i.id = p.item_id
             ORDER BY p.id DESC LIMIT 100"
        )->fetchAll();

        $preparedItems = Database::query(
            "SELECT i.* FROM items i
             JOIN item_categories c ON c.id = i.category_id
             WHERE c.type = 'prepared' AND i.is_active = 1 ORDER BY i.name"
        )->fetchAll();

        $allItems = Database::query(
            "SELECT * FROM items WHERE is_active = 1 ORDER BY name"
        )->fetchAll();

        $waste = Database::query(
            "SELECT w.*, i.name AS item_name FROM waste_log w
             JOIN items i ON i.id = w.item_id ORDER BY w.id DESC LIMIT 50"
        )->fetchAll();

        $this->view('kitchen/production', [
            'title'         => 'الإنتاج اليومي والهدر',
            'productions'   => $productions,
            'preparedItems' => $preparedItems,
            'allItems'      => $allItems,
            'waste'         => $waste,
        ]);
    }

    public function store(): void
    {
        $no = Helpers::docNumber('PRD', 'production_orders', 'production_no');
        $itemId = (int)$this->input('item_id');
        $recipeId = (int)$this->input('recipe_id', 0);
        if (!$recipeId) {
            $recipe = Database::query("SELECT id FROM production_recipes WHERE output_item_id = ? LIMIT 1", [$itemId])->fetch();
            $recipeId = (int)($recipe['id'] ?? 0);
        }
        Database::query(
            "INSERT INTO production_orders (production_no, item_id, recipe_id, planned_qty, production_date, status, cost_center_id, created_by)
             VALUES (?,?,?,?,?,'in_progress',?,?)",
            [$no, $itemId, $recipeId ?: null, (float)$this->input('planned_qty'),
             (string)$this->input('production_date', date('Y-m-d')), (int)$this->input('cost_center_id', 0) ?: null, Auth::id()]
        );
        $prodId = (int)Database::lastInsertId();
        if ($this->input('auto_request_materials') && $recipeId) {
            try {
                MaterialRequestService::createFromProductionOrder($prodId, (int)Auth::id());
                $this->flash('success', 'تم إنشاء أمر الإنتاج ' . $no . ' وطلب مواد تابع');
            } catch (\Throwable $e) {
                $this->flash('warning', 'تم إنشاء أمر الإنتاج لكن تعذر إنشاء طلب المواد: ' . $e->getMessage());
            }
        } else {
            $this->flash('success', 'تم إنشاء أمر الإنتاج ' . $no);
        }
        Response::redirect('/production');
    }

    public function complete(string $id): void
    {
        $prod = Database::query("SELECT * FROM production_orders WHERE id = ?", [(int)$id])->fetch();
        if (!$prod || $prod['status'] === 'done') { Response::redirect('/production'); }

        $actual = (float)$this->input('actual_qty');
        $waste  = max(0, (float)$prod['planned_qty'] - $actual);

        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE production_orders SET actual_qty = ?, waste_qty = ?, status='done' WHERE id = ?",
                [$actual, $waste, (int)$id]
            );
            // إدخال الكمية المنتجة لمخزن المطبخ
            StockService::move(2, (int)$prod['item_id'], 'in', $actual, 0, $prod['production_no'], 'إنتاج داخلي');
            if ($waste > 0) {
                Database::query(
                    "INSERT INTO waste_log (item_id, qty, reason, logged_by) VALUES (?,?,?,?)",
                    [(int)$prod['item_id'], $waste, 'فرق إنتاج ' . $prod['production_no'], Auth::id()]
                );
                $wasteId = (int)Database::lastInsertId();
                AccountingService::postWaste($wasteId);
            }
            AccountingService::postProductionConsumption((int)$id);
            Database::commit();
            $this->flash('success', 'تم إتمام الإنتاج وإدخال الكمية للمخزن');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/production');
    }

    public function logWaste(): void
    {
        $itemId = (int)$this->input('item_id');
        $qty    = (float)$this->input('qty');

        Database::query(
            "INSERT INTO waste_log (item_id, qty, reason, logged_by) VALUES (?,?,?,?)",
            [$itemId, $qty, (string)$this->input('reason'), Auth::id()]
        );
        $wasteId = (int)Database::lastInsertId();
        StockService::move(2, $itemId, 'out', $qty, 0, 'WASTE', 'هدر');
        AccountingService::postWaste($wasteId);
        $this->flash('success', 'تم تسجيل الهدر وخصمه من المخزون');
        Response::redirect('/production');
    }

    /** نموذج تصنيع من مخزن إلى مخزن */
    public function manufactureForm(): void
    {
        $recipes = Database::query(
            "SELECT pr.*, i.name AS output_name FROM production_recipes pr
             JOIN items i ON i.id = pr.output_item_id
             WHERE pr.is_active = 1 ORDER BY pr.name"
        )->fetchAll();
        $warehouses = Database::query("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name")->fetchAll();
        $this->view('inventory/manufacture', [
            'title' => 'تصنيع داخلي',
            'recipes' => $recipes, 'warehouses' => $warehouses,
        ]);
    }

    /** تصنيع بناءً على وصفة من مخزن إلى مخزن */
    public function manufacture(): void
    {
        try {
            ProductionService::manufactureAndTransfer(
                (int) $this->input('recipe_id'),
                (float) $this->input('qty'),
                (int) $this->input('from_warehouse_id'),
                (int) $this->input('to_warehouse_id')
            );
            $this->flash('success', 'تم التصنيع وتحويل الكمية بنجاح');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ في التصنيع: ' . $e->getMessage());
        }
        Response::redirect('/production/manufacture');
    }
}
