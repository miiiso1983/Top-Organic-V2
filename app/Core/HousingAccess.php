<?php
namespace App\Core;

/** Read access is broader than assignment/financial administration. */
final class HousingAccess
{
    public static function isGuard(): bool {
        return trim((string)Database::query("SELECT d.name FROM employees e JOIN departments d ON d.id=e.department_id WHERE e.user_id=?",[Auth::id()])->fetchColumn()) === 'الحراسة';
    }
    public static function canView(): bool { return Auth::can('hr.housing.manage') || self::isGuard(); }
    public static function canEdit(array $employee): bool {
        return ($employee['file_status'] ?? '') === 'open' && (Auth::can('hr.housing.manage') || (self::isGuard() && trim((string)($employee['department_name'] ?? '')) === 'الحراسة'));
    }
    public static function employees(bool $residentsOnly=false): array {
        if(!self::canView())throw new \RuntimeException('ليس لديك صلاحية عرض السكن',403);
        $rows=Database::query("SELECT e.id,e.full_name,e.emp_code,e.file_status,COALESCE(d.name,e.department) department_name,
            a.id assignment_id,a.site_id,s.name site_name,a.apartment_id,ap.apartment_number,a.room_id,r.room_number,a.deduction_amount
            FROM employees e LEFT JOIN departments d ON d.id=e.department_id
            LEFT JOIN employee_housing_assignments a ON a.employee_id=e.id AND a.status='active'
            LEFT JOIN housing_sites s ON s.id=a.site_id
            LEFT JOIN housing_apartments ap ON ap.id=a.apartment_id LEFT JOIN housing_rooms r ON r.id=a.room_id
            WHERE ".($residentsOnly ? "a.id IS NOT NULL AND s.site_key <> 'external'" : "(e.file_status='open' OR a.id IS NOT NULL)")." ORDER BY e.full_name,e.id")->fetchAll();
        $guard=self::isGuard();$manage=Auth::can('hr.housing.manage');
        foreach($rows as &$e){$e['can_edit']=$e['file_status']==='open' && ($manage || ($guard && trim((string)$e['department_name'])==='الحراسة'));if(!$manage)unset($e['deduction_amount']);}
        return $rows;
    }
}
