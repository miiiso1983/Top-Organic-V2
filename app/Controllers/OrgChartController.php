<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response};

/**
 * الهيكل التنظيمي: شجرة مناصب قابلة للإضافة والتعديل والحذف
 */
class OrgChartController extends Controller
{
    public function index(): void
    {
        $positions = Database::query(
            "SELECT o.*, e.full_name AS linked_employee_name FROM org_positions o
             LEFT JOIN employees e ON e.id = o.employee_id
             WHERE o.is_active = 1 ORDER BY o.parent_id IS NULL DESC, o.parent_id, o.sort_order, o.id"
        )->fetchAll();
        $employees = Database::query(
            "SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name"
        )->fetchAll();

        $this->view('hr/orgchart', [
            'title' => 'الهيكل التنظيمي',
            'tree' => $this->buildTree($positions),
            'allPositions' => $positions,
            'employees' => $employees,
        ]);
    }

    /** بناء شجرة متداخلة من قائمة مسطّحة اعتماداً على parent_id */
    private function buildTree(array $rows, ?int $parentId = null): array
    {
        $branch = [];
        foreach ($rows as $row) {
            $rowParent = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
            if ($rowParent === $parentId) {
                $row['children'] = $this->buildTree($rows, (int)$row['id']);
                $branch[] = $row;
            }
        }
        return $branch;
    }

    public function store(): void
    {
        $title = trim((string)$this->input('title'));
        if ($title === '') {
            $this->flash('danger', 'اسم المنصب مطلوب');
            Response::redirect('/hr/orgchart');
        }
        $parentId = $this->input('parent_id') ?: null;
        $employeeId = $this->input('employee_id') ?: null;

        Database::query(
            "INSERT INTO org_positions (parent_id, title, employee_name, employee_id, sort_order)
             VALUES (?,?,?,?,?)",
            [$parentId ? (int)$parentId : null, $title,
             (string)$this->input('employee_name') ?: null,
             $employeeId ? (int)$employeeId : null,
             (int)$this->input('sort_order', 0)]
        );
        $this->flash('success', 'تمت إضافة المنصب إلى الهيكل التنظيمي');
        Response::redirect('/hr/orgchart');
    }

    public function update(string $id): void
    {
        $title = trim((string)$this->input('title'));
        if ($title === '') {
            $this->flash('danger', 'اسم المنصب مطلوب');
            Response::redirect('/hr/orgchart');
        }
        $parentId = $this->input('parent_id') ?: null;
        if ($parentId !== null && (int)$parentId === (int)$id) {
            $this->flash('danger', 'لا يمكن أن يكون المنصب أباً لنفسه');
            Response::redirect('/hr/orgchart');
        }
        $employeeId = $this->input('employee_id') ?: null;

        Database::query(
            "UPDATE org_positions SET parent_id = ?, title = ?, employee_name = ?, employee_id = ?, sort_order = ?
             WHERE id = ?",
            [$parentId ? (int)$parentId : null, $title,
             (string)$this->input('employee_name') ?: null,
             $employeeId ? (int)$employeeId : null,
             (int)$this->input('sort_order', 0), (int)$id]
        );
        $this->flash('success', 'تم تحديث المنصب');
        Response::redirect('/hr/orgchart');
    }

    public function destroy(string $id): void
    {
        // الحذف يشمل جميع المناصب الفرعية تلقائياً (ON DELETE CASCADE)
        Database::query("DELETE FROM org_positions WHERE id = ?", [(int)$id]);
        $this->flash('success', 'تم حذف المنصب وكل ما يتفرع منه');
        Response::redirect('/hr/orgchart');
    }
}
