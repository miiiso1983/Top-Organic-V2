<?php
namespace App\Controllers;
use App\Core\{Controller, Auth, Response, Database, SystemActivityReport};
final class SystemActivityController extends Controller
{
    public function index(): void
    {
        if (!Auth::can('admin.audit')) Response::abort(403, 'ليس لديك صلاحية عرض سجل أحداث النظام.');
        try { $filters = SystemActivityReport::filters($_GET); }
        catch (\InvalidArgumentException $e) { Response::abort(422, $e->getMessage()); }
        $this->view('admin/activity', [
            'title' => 'سجل أحداث النظام', 'filters' => $filters, 'report' => SystemActivityReport::page($filters),
            'actors' => Database::query('SELECT id, full_name FROM users ORDER BY full_name, id')->fetchAll(),
            'branches' => Database::query('SELECT id,name FROM branches ORDER BY name')->fetchAll(),
        ]);
    }
}
