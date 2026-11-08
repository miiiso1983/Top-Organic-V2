<?php
namespace App\Core;

/** Apply resignation to current housing and access; historical records remain intact. */
final class EmployeeSeparationService
{
    public static function enforce(int $employeeId): bool
    {
        $db=Database::connect();$owner=!$db->inTransaction();
        if($owner)$db->beginTransaction();
        try {
            $e=Database::query('SELECT id,status,user_id,housing_type,housing_monthly_deduction FROM employees WHERE id=? FOR UPDATE',[$employeeId])->fetch();
            if(!$e || $e['status']!=='resigned') { if($owner)$db->commit();return false; }
            $ended=Database::query("UPDATE employee_housing_assignments SET status='ended',ended_date=? WHERE employee_id=? AND status='active'",[date('Y-m-d'),$employeeId])->rowCount();
            Database::query('UPDATE employees SET housing_type=NULL,housing_monthly_deduction=0 WHERE id=?',[$employeeId]);
            if($ended || $e['housing_type']!==null || (float)$e['housing_monthly_deduction']!==0.0)
                EmployeeAuditService::log($employeeId,'update','housing_type',$e['housing_type'],null);
            if(!empty($e['user_id'])) {
                $uid=(int)$e['user_id'];
                $disabled=Database::query('UPDATE users SET is_active=0 WHERE id=? AND is_active<>0',[$uid])->rowCount();
                Database::query('UPDATE push_subscriptions SET is_active=0 WHERE user_id=? AND is_active<>0',[$uid]);
                Database::query('UPDATE ptt_receiver_devices SET is_active=0 WHERE user_id=? AND is_active<>0',[$uid]);
                if($disabled)EmployeeAuditService::log($employeeId,'update','user_access','active','disabled: resigned');
            }
            if($owner)$db->commit();return true;
        } catch(\Throwable $e) { if($owner && $db->inTransaction())$db->rollBack();throw $e; }
    }

    /** Lock the employee alongside housing writes, so resignation cannot race with assignment. */
    public static function assertHousingEligible(int $employeeId): void
    {
        $e=Database::query('SELECT status FROM employees WHERE id=? FOR UPDATE',[$employeeId])->fetch();
        if(!$e || $e['status']==='resigned')throw new \RuntimeException('لا يمكن تسكين موظف مستقيل');
    }
}
