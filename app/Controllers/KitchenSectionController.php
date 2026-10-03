<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response};

class KitchenSectionController extends Controller
{
    public function index(): void
    {
        $sections = Database::query(
            "SELECT * FROM kitchen_sections WHERE is_active = 1 ORDER BY sort_order, id"
        )->fetchAll();
        $this->view('kitchen/sections', [
            'title'    => 'أقسام المطبخ',
            'sections' => $sections,
        ]);
    }

    public function store(): void
    {
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم القسم مطلوب');
            Response::redirect('/kitchen/sections');
        }
        Database::query(
            "INSERT INTO kitchen_sections (branch_id, name, sort_order) VALUES (?,?,?)",
            [Auth::branchId(), $name, (int)$this->input('sort_order', 0)]
        );
        Helpers::audit('إضافة قسم مطبخ', 'kitchen_sections', (int)Database::lastInsertId(), $name);
        $this->flash('success', 'تم إضافة قسم المطبخ');
        Response::redirect('/kitchen/sections');
    }

    public function update(string $id): void
    {
        $sectionId = (int)$id;
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم القسم مطلوب');
            Response::redirect('/kitchen/sections');
        }
        Database::query(
            "UPDATE kitchen_sections SET name = ?, sort_order = ? WHERE id = ?",
            [$name, (int)$this->input('sort_order', 0), $sectionId]
        );
        Helpers::audit('تعديل قسم مطبخ', 'kitchen_sections', $sectionId, $name);
        $this->flash('success', 'تم تحديث القسم');
        Response::redirect('/kitchen/sections');
    }

    public function delete(string $id): void
    {
        $sectionId = (int)$id;
        $section = Database::query("SELECT name FROM kitchen_sections WHERE id = ?", [$sectionId])->fetch();
        if (!$section) {
            $this->flash('danger', 'القسم غير موجود');
            Response::redirect('/kitchen/sections');
        }
        Database::query("UPDATE kitchen_sections SET is_active = 0 WHERE id = ?", [$sectionId]);
        Helpers::audit('حذف قسم مطبخ', 'kitchen_sections', $sectionId, $section['name']);
        $this->flash('success', 'تم حذف القسم');
        Response::redirect('/kitchen/sections');
    }
}
