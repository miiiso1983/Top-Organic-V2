<?php
namespace App\Controllers;

use App\Core\{Controller, Response};
use App\Models\PreparationKitchen;
use App\Models\Printer;

class PreparationKitchenController extends Controller
{
    public function index(): void
    {
        $this->view('admin/preparation_kitchens', [
            'title'        => 'مراكز التحضير',
            'kitchens'     => PreparationKitchen::all(),
            'printerTypes' => Printer::types(),
        ]);
    }

    public function store(): void
    {
        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم مركز التحضير مطلوب');
            Response::redirect('/admin/preparation-kitchens');
            return;
        }
        PreparationKitchen::create($name, (int) $this->input('sort_order', 0));
        $this->flash('success', 'تم إضافة مركز التحضير');
        Response::redirect('/admin/preparation-kitchens');
    }

    public function update(string $id): void
    {
        $kitchen = PreparationKitchen::find((int) $id);
        if (!$kitchen) {
            Response::abort(404, 'مركز التحضير غير موجود');
        }
        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم مركز التحضير مطلوب');
            Response::redirect('/admin/preparation-kitchens');
            return;
        }
        PreparationKitchen::update((int) $id, $name, (int) $this->input('sort_order', 0));
        $this->flash('success', 'تم تحديث مركز التحضير');
        Response::redirect('/admin/preparation-kitchens');
    }

    public function delete(string $id): void
    {
        PreparationKitchen::delete((int) $id);
        $this->flash('success', 'تم حذف مركز التحضير');
        Response::redirect('/admin/preparation-kitchens');
    }
}
