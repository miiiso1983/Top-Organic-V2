<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response};

class RecipeController extends Controller
{
    public function index(): void
    {
        $items = Database::query(
            "SELECT mi.id, mi.name, mi.price, mi.cost,
                    (SELECT COUNT(*) FROM recipes r
                     JOIN recipe_ingredients ri ON ri.recipe_id = r.id
                     WHERE r.menu_item_id = mi.id) AS ingredients_count
             FROM menu_items mi WHERE mi.is_active = 1 ORDER BY mi.name"
        )->fetchAll();

        $this->view('kitchen/recipes', ['title' => 'الوصفات وتكلفة الوجبات', 'items' => $items]);
    }

    public function edit(string $id): void
    {
        $menuItem = Database::query("SELECT * FROM menu_items WHERE id = ?", [(int)$id])->fetch();
        if (!$menuItem) { Response::abort(404, 'الصنف غير موجود'); }

        $recipe = Database::query("SELECT * FROM recipes WHERE menu_item_id = ?", [(int)$id])->fetch();
        $ingredients = [];
        if ($recipe) {
            $ingredients = Database::query(
                "SELECT ri.*, i.name, i.cost_price, u.name AS unit
                 FROM recipe_ingredients ri
                 JOIN items i ON i.id = ri.item_id
                 JOIN units u ON u.id = i.unit_id
                 WHERE ri.recipe_id = ?", [$recipe['id']]
            )->fetchAll();
        }
        $stockItems = Database::query(
            "SELECT i.id, i.name, i.cost_price, u.name AS unit FROM items i
             JOIN units u ON u.id = i.unit_id WHERE i.is_active = 1 ORDER BY i.name"
        )->fetchAll();

        $this->view('kitchen/recipe_edit', [
            'title'       => 'وصفة: ' . $menuItem['name'],
            'menuItem'    => $menuItem,
            'recipe'      => $recipe,
            'ingredients' => $ingredients,
            'stockItems'  => $stockItems,
        ]);
    }

    public function save(string $id): void
    {
        $menuItemId = (int)$id;
        $itemIds = (array)($_POST['item_id'] ?? []);
        $qtys    = (array)($_POST['qty'] ?? []);

        Database::beginTransaction();
        try {
            $recipe = Database::query("SELECT * FROM recipes WHERE menu_item_id = ?", [$menuItemId])->fetch();
            if (!$recipe) {
                Database::query("INSERT INTO recipes (menu_item_id, yield_qty) VALUES (?,1)", [$menuItemId]);
                $recipeId = (int)Database::lastInsertId();
            } else {
                $recipeId = (int)$recipe['id'];
                Database::query("DELETE FROM recipe_ingredients WHERE recipe_id = ?", [$recipeId]);
            }

            $totalCost = 0;
            foreach ($itemIds as $i => $itemId) {
                $qty = (float)($qtys[$i] ?? 0);
                if (!$itemId || $qty <= 0) continue;
                Database::query(
                    "INSERT INTO recipe_ingredients (recipe_id, item_id, qty) VALUES (?,?,?)",
                    [$recipeId, (int)$itemId, $qty]
                );
                $cost = (float) Database::query(
                    "SELECT cost_price FROM items WHERE id = ?", [(int)$itemId]
                )->fetch()['cost_price'];
                $totalCost += $cost * $qty;
            }

            // تحديث تكلفة الوجبة تلقائياً من مكوناتها
            Database::query("UPDATE menu_items SET cost = ? WHERE id = ?", [$totalCost, $menuItemId]);
            Database::commit();
            $this->flash('success', 'تم حفظ الوصفة وتحديث التكلفة');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/recipes/' . $menuItemId);
    }
}
