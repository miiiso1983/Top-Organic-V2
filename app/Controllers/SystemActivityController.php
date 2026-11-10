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
            'choices' => SystemActivityReport::choices(),
            'title' => 'سجل أحداث النظام', 'filters' => $filters, 'report' => SystemActivityReport::page($filters),
            'actors' => Database::query('SELECT id, full_name FROM users ORDER BY full_name, id')->fetchAll(),
            'branches' => Database::query('SELECT id,name FROM branches ORDER BY name')->fetchAll(),
        ]);
    }
    public function export(): void
    {
        if (!Auth::can('admin.audit')) Response::abort(403, 'ليس لديك صلاحية تصدير سجل أحداث النظام.');
        try { $filters = SystemActivityReport::filters($_GET); }
        catch (\InvalidArgumentException $e) { Response::abort(422, $e->getMessage()); }
        // Release the session lock while building a potentially large export.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        set_time_limit(0);
        $file = null;
        try {
            $file = \App\Core\SystemActivityExcel::build(SystemActivityReport::exportRows($filters), $filters);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="system-activity-' . date('Y-m-d-His') . '.xlsx"');
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: ' . filesize($file));
            readfile($file);
        } catch (\Throwable $e) {
            error_log('System activity export failed: ' . get_class($e));
            Response::json(['error' => 'تعذر إنشاء ملف إكسل. يرجى إعادة المحاولة.'], 500);
        } finally { if ($file !== null && is_file($file)) unlink($file); }
    }
}
