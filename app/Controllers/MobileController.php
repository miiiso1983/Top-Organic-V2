<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Response, ApiAuth, Database, HrService, WorkflowEngine};

/**
 * بوابة تطبيق الموبايل (صفحات PWA محسّنة للهاتف)
 */
class MobileController extends Controller
{
    public function index(): void
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::redirect('/login?redirect=/mobile');
            return;
        }
        $emp = Database::query(
            "SELECT e.id, e.full_name, d.name AS department_name
             FROM employees e LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.user_id = ? LIMIT 1",
            [(int)$userId]
        )->fetch();
        $isManager = false;
        if ($emp) {
            $isManager = count(WorkflowEngine::subordinateEmployees((int)$userId)) > 0;
        }
        $isHousingGuard = $emp && trim((string)($emp['department_name'] ?? '')) === 'الحراسة';

        $this->view('mobile/index', [
            'title' => 'Top Organic Mobile',
            'api_token' => ApiAuth::issueToken((int)$userId),
            'is_manager' => $isManager,
            'is_housing_guard' => $isHousingGuard,
            'department_name' => $emp['department_name'] ?? null,
        ]);
    }
}
