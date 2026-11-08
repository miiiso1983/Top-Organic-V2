<?php
namespace App\Core {
 class Helpers { public static function audit(...$a):void {} public static function queueSync(...$a):void {} }
 class Response { public static array $last=[];public static int $status=200;public static function json(array $d,int $s=200):void { self::$last=$d;self::$status=$s; } }
}
namespace {
 use App\Core\{Database,HrService,EmployeeSeparationService,ApiAuth,Auth};
    if(PHP_SAPI!=='cli')exit(1);
    ini_set('session.save_path',sys_get_temp_dir());session_start();
    $db=Database::connect();
    if($db->inTransaction())throw new RuntimeException('Test requires a fresh connection');
    $tables=$db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach($tables as $t){
        $name=str_replace('`','``',$t[0]);
        $ddl=$db->query("SHOW CREATE TABLE `$name`")->fetch(PDO::FETCH_NUM)[1];
        $ddl=preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$ddl);
        $ddl=preg_replace('/^  CONSTRAINT .*\n/m','',$ddl);
        $ddl=preg_replace('/,\n\)/',"\n)",$ddl);
        $db->exec($ddl);
    }
    // From here every application table name resolves to an empty temporary table, never production data.
    function seed(string $table,array $values):void {
        global $db;
        foreach($db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC) as $c){
            $n=$c['Field'];if(array_key_exists($n,$values)||$c['Null']==='YES'||$c['Default']!==null||str_contains($c['Extra'],'auto_increment'))continue;
            $type=$c['Type'];
            if(str_starts_with($type,'enum(')){preg_match("/enum\('([^']+)'/",$type,$m);$values[$n]=$m[1];}
            elseif(preg_match('/int|decimal|float|double|bit/',$type))$values[$n]=0;
            elseif(str_contains($type,'date')||str_contains($type,'timestamp'))$values[$n]=date('Y-m-d H:i:s');
            else $values[$n]='test-'.$n.'-'.($values['id']??uniqid());
        }
        Database::query('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($values)).'`) VALUES ('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));
    }
    function check(bool $ok,string $message):void { if(!$ok)throw new RuntimeException($message); }
    function rejected(callable $action,string $message):void {try{$action();}catch(RuntimeException $e){return;}throw new RuntimeException($message);}
    function controllerAction(callable $action):void { $_SESSION['flash']=[];try{$action();}catch(App\Core\TestRedirect $e){} }

    seed('roles',['id'=>1,'name'=>'مدير النظام','level'=>100]);
    foreach([1=>'active',2=>'active',3=>'resigned'] as $id=>$status){
        seed('users',['id'=>$id,'role_id'=>1,'username'=>'separation-'.$id,'password_hash'=>password_hash('synthetic-only',PASSWORD_DEFAULT),'full_name'=>'Synthetic '.$id,'is_active'=>1]);
        seed('employees',['id'=>$id,'user_id'=>$id,'full_name'=>'Synthetic '.$id,'emp_code'=>'SYN'.$id,'status'=>$status,'file_status'=>'open','housing_type'=>'yarmouk','housing_monthly_deduction'=>100]);
    }
    seed('housing_sites',['id'=>1,'name'=>'Synthetic housing','site_key'=>'yarmouk','monthly_deduction'=>100]);
    foreach([1=>1,2=>1,3=>2] as $id=>$eid)seed('employee_housing_assignments',['id'=>$id,'employee_id'=>$eid,'site_id'=>1,'deduction_amount'=>100,'assigned_date'=>'2026-10-01','status'=>'active']);
    seed('employee_housing_assignments',['id'=>4,'employee_id'=>1,'site_id'=>1,'deduction_amount'=>80,'assigned_date'=>'2026-09-01','ended_date'=>'2026-09-30','status'=>'ended']);
    foreach([1,2] as $id){
        seed('push_subscriptions',['id'=>$id,'user_id'=>$id,'endpoint'=>'synthetic-endpoint-'.$id,'is_active'=>1]);
        seed('ptt_receiver_devices',['device_id'=>'synthetic-'.$id,'user_id'=>$id,'token'=>str_repeat((string)$id,64),'environment'=>'production','is_active'=>1]);
    }
    $token=ApiAuth::issueToken(1);
    check(ApiAuth::validateToken($token)!==null,'Active app token rejected');
    check(Auth::attempt('separation-1','synthetic-only'),'Active login rejected');
    Database::query("UPDATE employees SET status='resigned' WHERE id=1");
    check(ApiAuth::validateToken($token)===null && ApiAuth::user()===null,'Existing token survived resignation or cached user leaked');
    check(!Auth::attempt('separation-1','synthetic-only'),'Resigned web login accepted before cleanup');
    $_POST=['username'=>'separation-1','password'=>'synthetic-only'];(new App\Controllers\ApiController())->login();
    check(App\Core\Response::$status===401,'Resigned mobile login accepted');
    Auth::refresh();check(!Auth::check(),'Existing web session survived resignation');
    check(EmployeeSeparationService::enforce(1),'Resigned enforcement skipped');
    check((int)Database::query("SELECT COUNT(*) FROM employee_housing_assignments WHERE employee_id=1 AND status='active'")->fetchColumn()===0,'Not all active housing ended');
    check(Database::query('SELECT ended_date FROM employee_housing_assignments WHERE id=4')->fetchColumn()==='2026-09-30','Historical housing changed');
    check((int)Database::query('SELECT COUNT(*) FROM employee_housing_assignments WHERE employee_id=1')->fetchColumn()===3,'Housing history deleted');
    check(Database::query('SELECT housing_type FROM employees WHERE id=1')->fetchColumn()===null && (float)Database::query('SELECT housing_monthly_deduction FROM employees WHERE id=1')->fetchColumn()===0.0,'Housing profile not cleared');
    check((int)Database::query('SELECT is_active FROM users WHERE id=1')->fetchColumn()===0,'User not disabled');
    check((int)Database::query('SELECT is_active FROM push_subscriptions WHERE id=1')->fetchColumn()===0,'Push still active');
    check((int)Database::query("SELECT is_active FROM ptt_receiver_devices WHERE user_id=1")->fetchColumn()===0,'PTT still active');
    $auditCount=(int)Database::query('SELECT COUNT(*) FROM employee_audit_logs')->fetchColumn();EmployeeSeparationService::enforce(1);
    check((int)Database::query('SELECT COUNT(*) FROM employee_audit_logs')->fetchColumn()===$auditCount,'Repeated cleanup duplicated audit');
    HrService::syncHousingAssignment(1,'yarmouk',500);
    check((int)Database::query("SELECT COUNT(*) FROM employee_housing_assignments WHERE employee_id=1 AND status='active'")->fetchColumn()===0,'Profile update re-housed resigned staff');
    rejected(fn()=>EmployeeSeparationService::assertHousingEligible(1),'Manual housing accepted resigned staff');
    HrService::syncExistingUserAccount(1,100,'Synthetic',null,1);
    check((int)Database::query('SELECT is_active FROM users WHERE id=1')->fetchColumn()===0,'Role sync re-enabled resigned account');
    HrService::syncEmployeeUserAccount(3,100,'Synthetic','SYN3',null,1,'synthetic-new-account','synthetic-only');
    $newUser=(int)Database::query('SELECT user_id FROM employees WHERE id=3')->fetchColumn();
    check((int)Database::query('SELECT is_active FROM users WHERE id=?',[$newUser])->fetchColumn()===0,'New account for resigned employee active');
    Database::query('UPDATE users SET is_active=1 WHERE id=1');
    check(ApiAuth::validateToken($token)===null && !Auth::attempt('separation-1','synthetic-only'),'Manual reactivation bypassed resigned status');
    EmployeeSeparationService::enforce(1);
    check(ApiAuth::validateToken(ApiAuth::issueToken(2))!==null,'Active employee token blocked');
    seed('users',['id'=>10,'role_id'=>1,'username'=>'standalone','full_name'=>'Synthetic system account','is_active'=>1]);
    check(ApiAuth::validateToken(ApiAuth::issueToken(10))!==null,'Standalone account blocked');
    check(!EmployeeSeparationService::enforce(2),'Active employee modified');
    check((int)Database::query('SELECT is_active FROM users WHERE id=2')->fetchColumn()===1,'Unrelated active account disabled');
    check(Database::query('SELECT status FROM employee_housing_assignments WHERE id=3')->fetchColumn()==='active','Active staff housing changed');
    HrService::syncHousingAssignment(2,'yarmouk',110);
    check((float)Database::query('SELECT deduction_amount FROM employee_housing_assignments WHERE id=3')->fetchColumn()===110.0,'Normal housing sync broken');
    // The caller owns rollback during an employee import/status transaction.
    $db->beginTransaction();Database::query("UPDATE employees SET status='resigned' WHERE id=2");EmployeeSeparationService::enforce(2);$db->rollBack();
    check(Database::query('SELECT status FROM employees WHERE id=2')->fetchColumn()==='active' && (int)Database::query('SELECT is_active FROM users WHERE id=2')->fetchColumn()===1,'Outer transaction rollback failed');
    echo "PASS: resigned login and existing tokens denied, all current housing ended, history retained, push disabled, idempotency, account/profile sync cannot reactivate, active staff unaffected, transaction rollback.\n";
}
