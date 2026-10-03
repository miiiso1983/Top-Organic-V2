<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Helpers, ImageUpload, ImportHelper};
use App\Models\PreparationKitchen;

class MenuController extends Controller
{
    public function index(): void
    {
        $menus = Database::query("SELECT * FROM menus WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll();
        $floors = Database::query("SELECT * FROM floors WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
        $assignments = Database::query(
            "SELECT menu_id, floor_id FROM menu_floor_assignments"
        )->fetchAll();
        $categories = Database::query(
            "SELECT mc.*, m.name AS menu_name FROM menu_categories mc
             JOIN menus m ON m.id = mc.menu_id
             WHERE mc.is_active = 1 AND m.is_active = 1 ORDER BY m.sort_order, m.id, mc.sort_order, mc.id"
        )->fetchAll();
        $items = Database::query(
            "SELECT mi.*, mc.name AS category_name, mc.menu_id, ks.name AS station_name, sec.name AS section_name, pk.name AS preparation_kitchen_name
             FROM menu_items mi
             JOIN menu_categories mc ON mc.id = mi.category_id
             JOIN menus m ON m.id = mc.menu_id
             LEFT JOIN kitchen_stations ks ON ks.id = mi.station_id
             LEFT JOIN kitchen_sections sec ON sec.id = mi.kitchen_section_id
             LEFT JOIN preparation_kitchens pk ON pk.id = mi.preparation_kitchen_id
             WHERE mi.is_active = 1 AND m.is_active = 1
             ORDER BY mc.sort_order, mi.name"
        )->fetchAll();
        $stations = Database::query("SELECT * FROM kitchen_stations WHERE is_active=1")->fetchAll();
        $sections = Database::query("SELECT * FROM kitchen_sections WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
        $preparationKitchens = PreparationKitchen::all();

        $this->view('sales/menu', [
            'title' => 'قوائم الطعام', 'menus' => $menus, 'floors' => $floors,
            'assignments' => $assignments, 'categories' => $categories,
            'items' => $items, 'stations' => $stations, 'sections' => $sections,
            'preparationKitchens' => $preparationKitchens,
        ]);
    }

    // ===================== القوائم (Menus) =====================

    public function storeMenu(): void
    {
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم المنيو مطلوب');
            Response::redirect('/menu');
        }
        $exists = Database::query(
            "SELECT id FROM menus WHERE name = ? AND is_active = 1",
            [$name]
        )->fetch();
        if ($exists) {
            $this->flash('danger', 'يوجد منيو بنفس الاسم');
            Response::redirect('/menu');
        }
        Database::query(
            "INSERT INTO menus (name, sort_order) VALUES (?,?)",
            [$name, (int)$this->input('sort_order', 0)]
        );
        $this->flash('success', 'تم إضافة المنيو');
        Response::redirect('/menu');
    }

    public function updateMenu(string $id): void
    {
        $menu = Database::query("SELECT * FROM menus WHERE id = ? AND is_active = 1", [(int)$id])->fetch();
        if (!$menu) { Response::abort(404, 'المنيو غير موجود'); }
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم المنيو مطلوب');
            Response::redirect('/menu');
        }
        $exists = Database::query(
            "SELECT id FROM menus WHERE name = ? AND id <> ? AND is_active = 1",
            [$name, (int)$id]
        )->fetch();
        if ($exists) {
            $this->flash('danger', 'يوجد منيو آخر بنفس الاسم');
            Response::redirect('/menu');
        }
        Database::query(
            "UPDATE menus SET name = ?, sort_order = ? WHERE id = ?",
            [$name, (int)$this->input('sort_order', 0), (int)$id]
        );
        Helpers::audit('تعديل منيو', 'menus', (int)$id);
        $this->flash('success', 'تم تحديث المنيو');
        Response::redirect('/menu');
    }

    public function assignFloors(string $id): void
    {
        $floorIds = array_map('intval', (array)($_POST['floor_id'] ?? []));
        Database::query("DELETE FROM menu_floor_assignments WHERE menu_id = ?", [(int)$id]);
        foreach ($floorIds as $floorId) {
            Database::query(
                "INSERT IGNORE INTO menu_floor_assignments (menu_id, floor_id) VALUES (?,?)",
                [(int)$id, $floorId]
            );
        }
        $this->flash('success', 'تم تحديث ربط المنيو بالطوابق');
        Response::redirect('/menu');
    }

    public function destroyMenu(string $id): void
    {
        $menu = Database::query("SELECT * FROM menus WHERE id = ? AND is_active = 1", [(int)$id])->fetch();
        if (!$menu) {
            $this->flash('error', 'المنيو غير موجود');
            Response::redirect('/menu');
        }
        if ($menu['is_default']) {
            $this->flash('error', 'لا يمكن حذف المنيو الافتراضي');
            Response::redirect('/menu');
        }
        Database::beginTransaction();
        try {
            Database::query("UPDATE menus SET is_active = 0 WHERE id = ?", [(int)$id]);
            Database::query("UPDATE menu_categories SET is_active = 0 WHERE menu_id = ?", [(int)$id]);
            Database::query(
                "UPDATE menu_items mi
                 JOIN menu_categories mc ON mc.id = mi.category_id
                 SET mi.is_active = 0
                 WHERE mc.menu_id = ?",
                [(int)$id]
            );
            Database::commit();
            Helpers::audit('حذف منيو', 'menus', (int)$id);
            $this->flash('success', 'تم حذف المنيو والأصناف والمواد المرتبطة به');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/menu');
    }

    // ===================== الأقسام (Categories) =====================

    public function storeCategory(): void
    {
        $menuId = (int)$this->input('menu_id');
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم التصنيف مطلوب');
            Response::redirect('/menu');
        }
        $exists = Database::query(
            "SELECT id FROM menu_categories WHERE menu_id = ? AND name = ? AND is_active = 1",
            [$menuId, $name]
        )->fetch();
        if ($exists) {
            $this->flash('danger', 'يوجد تصنيف بنفس الاسم في هذا المنيو');
            Response::redirect('/menu');
        }
        Database::query(
            "INSERT INTO menu_categories (menu_id, name, sort_order) VALUES (?,?,?)",
            [$menuId, $name, (int)$this->input('sort_order', 0)]
        );
        $this->flash('success', 'تم إضافة التصنيف');
        Response::redirect('/menu');
    }

    public function updateCategory(string $id): void
    {
        $category = Database::query("SELECT * FROM menu_categories WHERE id = ? AND is_active = 1", [(int)$id])->fetch();
        if (!$category) { Response::abort(404, 'التصنيف غير موجود'); }
        $menuId = (int)$this->input('menu_id');
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم التصنيف مطلوب');
            Response::redirect('/menu');
        }
        $exists = Database::query(
            "SELECT id FROM menu_categories WHERE menu_id = ? AND name = ? AND id <> ? AND is_active = 1",
            [$menuId, $name, (int)$id]
        )->fetch();
        if ($exists) {
            $this->flash('danger', 'يوجد تصنيف بنفس الاسم في هذا المنيو');
            Response::redirect('/menu');
        }
        Database::query(
            "UPDATE menu_categories SET menu_id = ?, name = ?, sort_order = ? WHERE id = ?",
            [$menuId, $name, (int)$this->input('sort_order', 0), (int)$id]
        );
        Helpers::audit('تعديل تصنيف منيو', 'menu_categories', (int)$id);
        $this->flash('success', 'تم تحديث التصنيف');
        Response::redirect('/menu');
    }

    public function destroyCategory(string $id): void
    {
        $category = Database::query("SELECT id, name FROM menu_categories WHERE id = ? AND is_active = 1", [(int)$id])->fetch();
        if (!$category) {
            $this->flash('error', 'التصنيف غير موجود');
            Response::redirect('/menu');
        }
        Database::query("UPDATE menu_categories SET is_active = 0 WHERE id = ?", [(int)$id]);
        Helpers::audit('حذف تصنيف منيو', 'menu_categories', (int)$id);
        $this->flash('success', 'تم حذف التصنيف');
        Response::redirect('/menu');
    }

    // ===================== الأصناف (Items) =====================

    public function storeItem(): void
    {
        try {
            $image = ImageUpload::fromMultipart('image', 'menu_items');
        } catch (\RuntimeException $e) {
            $this->flash('error', 'الصورة: ' . $e->getMessage());
            Response::redirect('/menu');
            return;
        }
        Database::query(
            "INSERT INTO menu_items (category_id, name, description, image, price, cost, prep_minutes, station_id, kitchen_section_id, preparation_kitchen_id)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                (int)$this->input('category_id'),
                (string)$this->input('name'),
                (string)$this->input('description'),
                $image,
                (float)$this->input('price'),
                (float)$this->input('cost', 0),
                (int)$this->input('prep_minutes', 10),
                $this->input('station_id') ?: null,
                $this->input('kitchen_section_id') ?: null,
                $this->input('preparation_kitchen_id') ?: null,
            ]
        );
        Helpers::audit('إضافة صنف منيو', 'menu_items', Database::lastInsertId());
        $this->flash('success', 'تم إضافة الصنف');
        Response::redirect('/menu');
    }

    public function updateItem(string $id): void
    {
        $item = Database::query("SELECT * FROM menu_items WHERE id = ?", [(int)$id])->fetch();
        if (!$item) { Response::abort(404, 'الصنف غير موجود'); }

        try {
            $image = ImageUpload::fromMultipart('image', 'menu_items') ?? $item['image'];
        } catch (\RuntimeException $e) {
            $this->flash('error', 'الصورة: ' . $e->getMessage());
            Response::redirect('/menu');
            return;
        }
        Database::query(
            "UPDATE menu_items SET category_id=?, name=?, description=?, image=?, price=?, cost=?, prep_minutes=?, station_id=?, kitchen_section_id=?, preparation_kitchen_id=?
             WHERE id = ?",
            [
                (int)$this->input('category_id', $item['category_id']),
                (string)$this->input('name', $item['name']),
                (string)$this->input('description', $item['description']),
                $image,
                (float)$this->input('price', $item['price']),
                (float)$this->input('cost', $item['cost']),
                (int)$this->input('prep_minutes', $item['prep_minutes']),
                $this->input('station_id') ?: null,
                $this->input('kitchen_section_id') ?: null,
                $this->input('preparation_kitchen_id') ?: null,
                (int)$id,
            ]
        );
        Helpers::queueSync('menu_items', (int)$id, 'update', [
            'price' => (float)$this->input('price', $item['price']),
            'cost'  => (float)$this->input('cost', $item['cost']),
        ]);
        Helpers::audit('تعديل صنف منيو', 'menu_items', (int)$id);
        $this->flash('success', 'تم تحديث الصنف');
        Response::redirect('/menu');
    }

    public function destroyItem(string $id): void
    {
        Database::query("UPDATE menu_items SET is_active = 0 WHERE id = ?", [(int)$id]);
        Helpers::audit('حذف صنف منيو', 'menu_items', (int)$id);
        $this->flash('success', 'تم حذف الصنف');
        Response::redirect('/menu');
    }

    /** تنزيل قالبة CSV للاستيراد */
    public function downloadTemplate(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="menu_template.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, "\xef\xbb\xbf");
        fputcsv($out, ['menu_name', 'category_name', 'item_name', 'price', 'cost', 'prep_minutes', 'description']);
        fputcsv($out, ['المنيو الرئيسي', 'المشاوي', 'كباب عراقي', 8000, 3800, 10, 'وصف الصنف']);
        fputcsv($out, ['المنيو الرئيسي', 'المقبلات', 'بطاطا حارة', 4000, 1500, 5, '']);
        fclose($out);
        exit;
    }

    /** استيراد أصناف من ملف CSV/Excel (يُحفظ كCSV) */
    public function importItems(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'لم يتم اختيار ملف');
            Response::redirect('/menu');
            return;
        }
        $tmp = $_FILES['import_file']['tmp_name'];
        $mime = mime_content_type($tmp) ?: '';
        $name = $_FILES['import_file']['name'] ?? '';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'csv' && $ext !== 'txt') {
            $this->flash('error', 'يُرجى حفظ الملف بصيغة CSV (من Excel: File → Save As → CSV UTF-8)');
            Response::redirect('/menu');
            return;
        }

        $fp = fopen($tmp, 'r');
        if (!$fp) {
            $this->flash('error', 'لا يمكن فتح الملف');
            Response::redirect('/menu');
            return;
        }

        $headers = array_map(fn($h) => strtolower(trim($h)), fgetcsv($fp, 0, ',', '"', '\\') ?: []);
        if (!in_array('menu_name', $headers) || !in_array('category_name', $headers) || !in_array('item_name', $headers) || !in_array('price', $headers)) {
            $this->flash('error', 'الملف يجب أن يحتوي على الأعمدة: menu_name, category_name, item_name, price');
            Response::redirect('/menu');
            return;
        }

        $idx = array_flip($headers);
        $created = 0;
        $line = 0;
        Database::beginTransaction();
        try {
            while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
                $line++;
                if (count($row) < count($headers)) { continue; }
                $menuName = trim($row[$idx['menu_name']] ?? '');
                $categoryName = trim($row[$idx['category_name']] ?? '');
                $itemName = trim($row[$idx['item_name']] ?? '');
                $price = (float)($row[$idx['price']] ?? 0);
                if ($menuName === '' || $categoryName === '' || $itemName === '' || $price <= 0) { continue; }

                $menu = Database::query("SELECT id FROM menus WHERE name = ? LIMIT 1", [$menuName])->fetch();
                if (!$menu) {
                    Database::query("INSERT INTO menus (name, sort_order) VALUES (?,?)", [$menuName, 0]);
                    $menuId = (int)Database::lastInsertId();
                } else {
                    $menuId = (int)$menu['id'];
                }

                $category = Database::query(
                    "SELECT id FROM menu_categories WHERE menu_id = ? AND name = ? LIMIT 1",
                    [$menuId, $categoryName]
                )->fetch();
                if (!$category) {
                    Database::query(
                        "INSERT INTO menu_categories (menu_id, name, sort_order) VALUES (?,?,?)",
                        [$menuId, $categoryName, 0]
                    );
                    $categoryId = (int)Database::lastInsertId();
                } else {
                    $categoryId = (int)$category['id'];
                }

                $exists = Database::query(
                    "SELECT id FROM menu_items WHERE category_id = ? AND name = ? AND is_active = 1 LIMIT 1",
                    [$categoryId, $itemName]
                )->fetch();
                if ($exists) { continue; }

                $cost = isset($idx['cost']) ? (float)($row[$idx['cost']] ?? 0) : 0;
                $prep = isset($idx['prep_minutes']) ? (int)($row[$idx['prep_minutes']] ?? 10) : 10;
                $description = isset($idx['description']) ? trim($row[$idx['description']] ?? '') : '';

                Database::query(
                    "INSERT INTO menu_items (category_id, name, description, price, cost, prep_minutes)
                     VALUES (?,?,?,?,?,?)",
                    [$categoryId, $itemName, $description, $price, $cost, $prep]
                );
                $created++;
            }
            Database::commit();
            Helpers::audit('استيراد أصناف من CSV', 'menu_items', 0, "created:$created");
            $this->flash('success', "تم استيراد $created صنف بنجاح");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('error', 'خطأ أثناء الاستيراد: ' . $e->getMessage());
        }
        fclose($fp);
        Response::redirect('/menu');
    }

    /** نموذج Excel لاستيراد أصناف المنيو */
    public function downloadTemplateExcel(): void
    {
        ImportHelper::downloadTemplate(
            ['menu_name', 'category_name', 'item_name', 'price', 'cost', 'prep_minutes', 'description'],
            'menu_template.xlsx',
            ['المنيو الرئيسي', 'المشاوي', 'كباب عراقي', 8000, 3800, 10, 'وصف الصنف']
        );
    }

    /** استيراد أصناف المنيو من Excel */
    public function importItemsExcel(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'لم يتم اختيار ملف');
            Response::redirect('/menu');
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('error', 'خطأ في قراءة الملف');
            Response::redirect('/menu');
        }
        $rows = $read['rows'];
        $errors = ImportHelper::validateRequired($rows, ['menu_name', 'category_name', 'item_name', 'price']);
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('error', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/menu');
        }
        Database::beginTransaction();
        try {
            $created = 0;
            foreach ($rows as $row) {
                $menuName = trim((string)$row['menu_name']);
                $categoryName = trim((string)$row['category_name']);
                $itemName = trim((string)$row['item_name']);
                $price = (float)($row['price'] ?? 0);
                if ($menuName === '' || $categoryName === '' || $itemName === '' || $price <= 0) { continue; }

                $menu = Database::query("SELECT id FROM menus WHERE name = ? LIMIT 1", [$menuName])->fetch();
                if (!$menu) {
                    Database::query("INSERT INTO menus (name, sort_order) VALUES (?,?)", [$menuName, 0]);
                    $menuId = (int)Database::lastInsertId();
                } else {
                    $menuId = (int)$menu['id'];
                }
                $category = Database::query("SELECT id FROM menu_categories WHERE menu_id = ? AND name = ? LIMIT 1", [$menuId, $categoryName])->fetch();
                if (!$category) {
                    Database::query("INSERT INTO menu_categories (menu_id, name, sort_order) VALUES (?,?,?)", [$menuId, $categoryName, 0]);
                    $categoryId = (int)Database::lastInsertId();
                } else {
                    $categoryId = (int)$category['id'];
                }
                $exists = Database::query("SELECT id FROM menu_items WHERE category_id = ? AND name = ? AND is_active = 1 LIMIT 1", [$categoryId, $itemName])->fetch();
                if ($exists) { continue; }
                $cost = isset($row['cost']) ? (float)$row['cost'] : 0;
                $prep = isset($row['prep_minutes']) ? (int)$row['prep_minutes'] : 10;
                $description = isset($row['description']) ? trim((string)$row['description']) : '';
                Database::query(
                    "INSERT INTO menu_items (category_id, name, description, price, cost, prep_minutes) VALUES (?,?,?,?,?,?)",
                    [$categoryId, $itemName, $description, $price, $cost, $prep]
                );
                $created++;
            }
            Database::commit();
            Helpers::audit('استيراد أصناف من Excel', 'menu_items', 0, "created:$created");
            $this->flash('success', "تم استيراد $created صنف بنجاح");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('error', 'خطأ أثناء الحفظ: ' . $e->getMessage());
        }
        Response::redirect('/menu');
    }
}
