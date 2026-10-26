<?php
// The prerequisite shadows EVERY application table with an empty temporary table before any writes.
require __DIR__.'/HrTeamIntegrationTest.php';
use App\Core\{Auth,Database,HousingAccess,PayrollPresentation};
seed('departments',['id'=>30,'name'=>'الحراسة']);
Database::query('UPDATE employees SET department_id=30 WHERE id=6');
seed('permissions',['id'=>1900,'code'=>'hr.housing.manage','name'=>'Housing','module'=>'hr']);
foreach([4,5] as $role)seed('role_permissions',['role_id'=>$role,'permission_id'=>1900]);
seed('housing_sites',['id'=>80,'site_key'=>'internal','name'=>'Synthetic housing']);
seed('housing_sites',['id'=>81,'site_key'=>'external','name'=>'External']);
foreach([1,2,6] as $eid)seed('employee_housing_assignments',['employee_id'=>$eid,'site_id'=>80,'status'=>'active','deduction_amount'=>100]);
seed('employee_housing_assignments',['employee_id'=>7,'site_id'=>81,'status'=>'active']);
Database::query("UPDATE employees SET file_status='closed' WHERE id=2");
function housingSession(int $id):void {
 $u=Database::query('SELECT u.role_id,r.name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?',[$id])->fetch();
 $_SESSION=['user_id'=>$id,'role_id'=>$u['role_id'],'role_name'=>$u['name'],'perms'=>Auth::effectivePermissions((int)$u['role_id'],$u['name'])];
}
housingSession(6);$rows=HousingAccess::employees(true);
check(count($rows)===3,'Guard must see all residents including closed files still assigned');
check(!in_array(7,array_column($rows,'id')),'External housing must not appear as resident');
foreach($rows as $e){check(!isset($e['deduction_amount']),'Guard financial leakage');check($e['can_edit']===($e['id']==6),'Read access expanded editing');}
foreach([4,8] as $id){housingSession($id);check(count(HousingAccess::employees(true))===3,'HR/finance missing residents');}
housingSession(1);rejected(fn()=>HousingAccess::employees(true),'Ordinary employee can read all residents');
$p=['employee_id'=>1,'period'=>'2026-10','base_salary'=>500000,'full_base_salary'=>1000000,'raises'=>50000,'salary_reduction'=>10000,'working_days'=>20,'required_hours'=>8,'security_deposit'=>12345,'insurance'=>99999,'net_salary'=>900000,'status'=>'approved'];
$p=PayrollPresentation::enrich($p,['hours'=>12,'count'=>2],1040000);
$c=array_column(PayrollPresentation::columns($p),null,'key');
check(count($c)===19 && $c['base']['value']===1040000.0,'Web salary projection mismatch');
check($c['insurance']['value']===12345.0,'Insurance column must use security_deposit');
check($c['overtime_request']['value']===1.5,'Overtime requested day conversion mismatch');
check($c['remaining']['days']===11 && $c['net']['value']===900000.0,'Projection changed stored net');
check($c['work_days_settlement']['label']==='تسوية أيام دوام','Web/mobile labels differ');
echo "PASS: all-department housing, HR/finance/guard visibility, no edit/financial expansion, exact payroll columns and stored net preserved.\n";
