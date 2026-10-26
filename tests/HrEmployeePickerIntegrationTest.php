<?php
/** MySQL integration test: only empty, connection-scoped TEMPORARY tables are written.
 * Bootstrap Database connection and candidate autoload first; run CLI on a schema with the application tables.
 * Real employee data is never copied. Notifications are stubbed before application classes load.
 */
namespace App\Core {
    class Helpers {
        public static array $notifications=[];
        private static int $counter=0;
        public static function docNumber(...$args): string { return 'TEST-'.(++self::$counter); }
        public static function notify(...$args): void {self::$notifications[]=$args;}
        public static function audit(...$args): void {}
        public static function queueSync(...$args): void {}
        public static function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
        public static function money($v): string { return number_format((float)$v,2); }
    }
    class Settings { public static function get($key,$default=null): mixed { return $default; } }
    class TestRedirect extends \RuntimeException {}
    class Response {
        public static array $last=[];
        public static int $status=200;
        public static function json(array $data,int $status=200):void { self::$last=$data; self::$status=$status; }
        public static function redirect($url): never { throw new TestRedirect($url); }
        public static function abort($status,$message=''): never { throw new \RuntimeException($message); }
    }
}
namespace App\Core\Workflow {
    class PushService { public static function queue(...$args): void {} }
    class AuditLogger { public static function log(...$args): void {} }
}
namespace {
    use App\Core\{Database,WorkflowEngine,HrWorkflowService,AdvancePolicyService,HrService};
    if(PHP_SAPI!=='cli')exit(1);
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
    // Fail closed before any data write unless every base table is shadowed on this connection.
    foreach($tables as $t){
        $name=str_replace('`','``',$t[0]);
        $ddl=$db->query("SHOW CREATE TABLE `$name`")->fetch(PDO::FETCH_NUM)[1];
        if(!str_starts_with($ddl,'CREATE TEMPORARY TABLE '))throw new RuntimeException('Isolation failed: '.$name);
        if((int)$db->query("SELECT COUNT(*) FROM `$name`")->fetchColumn()!==0)throw new RuntimeException('Temporary table not empty: '.$name);
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

    foreach([1=>'مدير النظام',2=>'مدير قسم',3=>'موظف',4=>'مدير قسم الموارد البشرية',5=>'مدير مالي'] as $id=>$name)seed('roles',['id'=>$id,'name'=>$name,'level'=>$id===3?10:60]);
    foreach([1=>3,2=>2,3=>2,4=>4,5=>2,6=>3,7=>2,8=>5] as $id=>$role){
        seed('users',['id'=>$id,'role_id'=>$role,'username'=>'team-test-'.$id,'full_name'=>'Synthetic '.$id,'is_active'=>1]);
        seed('employees',['id'=>$id,'user_id'=>$id,'full_name'=>'Synthetic Employee '.$id,'emp_code'=>'TEAM'.$id,'manager_id'=>[1=>2,2=>3,5=>3,6=>5][$id]??null,'department_id'=>$id===7?2:1,'status'=>'active','file_status'=>'open','base_salary'=>1000000,'hire_date'=>'2026-01-01','attendance_type'=>'flexible','required_hours'=>8]);
    }
    foreach([1=>'Synthetic A',2=>'Synthetic B'] as $id=>$name)seed('departments',['id'=>$id,'name'=>$name]);
    foreach([1=>2,2=>3,3=>null,5=>3,6=>5,7=>null] as $id=>$parent)seed('org_positions',['id'=>$id,'employee_id'=>$id,'parent_id'=>$parent,'title'=>'Test '.$id,'is_active'=>1]);
    seed('permissions',['id'=>900,'code'=>'hr.payslip.view','name'=>'Payroll','module'=>'hr']);seed('role_permissions',['role_id'=>5,'permission_id'=>900]);
    // Every production table has been shadowed above. New tables MUST also be temporary.
    $migration=file_get_contents(__DIR__.'/../scripts/migrate_hr_team.php');
    $migration=str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE IF NOT EXISTS',$migration);
    eval('?>'.$migration);
    eval('?>'.$migration);
    $svc=fn($u)=>new App\Core\HrTeamService($u);
    $manager=$svc(2);$employee=$svc(1);$other=$svc(5);$hr=$svc(4);$upper=$svc(3);$finance=$svc(8);
    $picker=$manager->employees(['options'=>'1'])['rows'];
    check(array_map('intval',array_column($picker,'id'))===[1,2],'Picker leaked employees outside manager scope');
    check(array_keys($picker[0])===['id','full_name','emp_code'],'Picker leaked private employee fields');
    check($manager->employees(['employee_id'=>6])['total']===0,'ID filter bypassed scope');
    check($manager->employees(['employee_id'=>1])['total']===1,'Exact employee filter failed');
    seed('hr_employee_cases',['id'=>1,'employee_id'=>1,'kind'=>'notice','subject'=>'Synthetic picker','body'=>'Synthetic','status'=>'issued','created_by'=>2,'request_key'=>'synthetic_picker_0001']);
    check(count($manager->cases(['options'=>'1'])['rows'])===1,'Manager case picker missing employee');
    check($other->cases(['options'=>'1'])['rows']===[],'Cross-team case picker leak');
    check($manager->cases(['employee_id'=>6])['total']===0,'Case employee filter ignored');
    check(count($manager->cases(['employee_id'=>1],true)['rows'])===1,'Export ignored employee selection');
    for($i=100;$i<220;$i++)seed('employees',['id'=>$i,'full_name'=>'Repeated name','emp_code'=>'PICK'.$i,'status'=>'active','file_status'=>'open']);
    check(count($finance->employees(['options'=>'1','page_size'=>10])['rows'])===128,'Picker truncated to table page');
    check((int)$finance->employees(['employee_id'=>219])['rows'][0]['id']===219,'Duplicate-name selection failed');
    echo json_encode(['ok'=>true,'scope'=>'employee picker, exact selection, export filter, authorization','tables_verified_temporary'=>count($tables),'picker_rows'=>128])."\n";
}
