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
    $key=0;$data=function($kind,$extra=[])use(&$key){return ['kind'=>$kind,'subject'=>'Synthetic subject','body'=>'Synthetic instructions','request_key'=>'synthetic_request_'.(++$key)]+$extra;};
    check(isset($manager->actions($manager->employee(1))['warning']),'Direct manager actions missing');
    check(!isset($employee->actions($employee->employee(1))['warning']),'Self warning creation allowed');
    check(!isset($manager->actions($manager->employee(1))['payslip']),'Manager salary exposed');
    check(isset($finance->actions($finance->employee(1))['payslip']),'Finance payroll missing');
    rejected(fn()=>$other->perform(1,'notice',$data('notice')),'Cross-manager action allowed');
    rejected(fn()=>$manager->archive(6,[]),'Cross-team archive allowed');
    rejected(fn()=>$manager->archive(1,['type'=>'payroll']),'Manager payroll leak');
    $wp=(int)Database::query("SELECT id FROM permissions WHERE code='hr.team.warning'")->fetchColumn();
    Database::query('DELETE FROM role_permissions WHERE role_id=2 AND permission_id=?',[$wp]);
    eval('?>'.$migration);
    rejected(fn()=>$svc(2)->createCase(1,$data('warning')),'Revoked permission ignored or migration regranted it');
    seed('role_permissions',['role_id'=>2,'permission_id'=>$wp]);
    $payload=$data('notice');$notice=$manager->createCase(1,$payload);
    check($manager->createCase(1,$payload)===$notice,'Retry duplicated notice');
    check($employee->detail($notice,true)['employee_read_at']!==null,'Read receipt absent');
    rejected(fn()=>$other->detail($notice),'Cross-team notice leak');
    $n=$manager->createCase(1,$data('notice_request'));
    check($upper->detail($n)['kind']==='notice_request','Upper manager cannot view escalation');
    $w=$manager->createCase(1,$data('warning',['warning_type'=>'written']));
    rejected(fn()=>$employee->detail($w),'Pending warning leaked to employee');
    check($employee->cases(['kind'=>'warning'])['total']===0,'Pending warning leaked in list');
    rejected(fn()=>$manager->decide($w,'approve',''),'Requester approved warning');
    $upper->decide($w,'approve','Synthetic acceptance');
    check((int)$db->query('SELECT COUNT(*) FROM employee_warnings')->fetchColumn()===1,'Warning ledger not created');
    rejected(fn()=>$upper->decide($w,'approve',''),'Warning approved twice');
    check($employee->detail($w)['status']==='approved','Approved warning missing');
    $w2=$manager->createCase(1,$data('warning'));$upper->decide($w2,'reject','Synthetic reason');
    rejected(fn()=>$employee->detail($w2),'Rejected warning leaked');
    $img='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jK1sAAAAASUVORK5CYII=';
    rejected(fn()=>$manager->createCase(1,$data('photo_loading',['images'=>[base64_encode('<script>alert(1)</script>')]])),'Invalid photo accepted');
    $photo=$manager->createCase(1,$data('photo_loading',['images'=>[$img]]));
    $fid=(int)$manager->detail($photo)['files'][0]['id'];
    rejected(fn()=>$other->file($fid),'Attachment leak');
    check($employee->file($fid)['mime']==='image/png','Employee attachment missing');
    check((int)$db->query('SELECT COUNT(*) FROM hr_adjustments')->fetchColumn()===0,'Photo created deduction');
    rejected(fn()=>$manager->createCase(1,$data('transfer',['new_manager_id'=>7])),'Cross-department transfer allowed');

    // Existing actions use the same persisted ledgers and approval engine.
    foreach(['mobile.evaluations','hr.view'] as $perm){seed('permissions',['code'=>$perm,'name'=>$perm,'module'=>'hr']);seed('role_permissions',['role_id'=>2,'permission_id'=>(int)$db->lastInsertId()]);}
    $manager=$svc(2);
    $specs=[
        'hr_leave'=>['employee_id','request_type','leave_type','date_from','date_to','reason'],
        'hr_missing_attendance'=>['employee_id','request_type','clock_in_at','clock_out_at','reason'],
        'deduction_request'=>['employee_id','deduction_amount','reason'],
        'hr_overtime'=>['employee_id','request_date','hours','reason'],
        'suspension_request'=>['employee_id','suspension_date','suspension_type','reason','notes'],
    ];
    $tid=100;
    foreach($specs as $code=>$fields){
        $tid++;seed('approval_templates',['id'=>$tid,'code'=>$code,'name'=>$code,'is_active'=>1,'fields_json'=>json_encode(array_map(fn($n)=>['name'=>$n,'type'=>'text','label'=>$n,'required'=>false],$fields))]);
        seed('approval_steps',['template_id'=>$tid,'step_order'=>1,'title'=>'Direct approval','approver_type'=>'direct_manager']);
        seed('template_allowed_roles',['template_id'=>$tid,'role_id'=>2,'can_create'=>1]);
    }
    HrWorkflowService::register();
    $b=$data('leave',['leave_type'=>'unpaid','date_from'=>'2026-11-01','date_to'=>'2026-11-02','reason'=>'Synthetic']);
    $rid=$manager->perform(1,'hr_leave',$b)['request_id'];
    check($manager->perform(1,'hr_leave',$b)['request_id']===$rid,'Leave retry duplicated');
    check((int)Database::query('SELECT requester_employee_id FROM requests WHERE id=?',[$rid])->fetchColumn()===1,'Request uses manager instead of target employee');
    check((int)$db->query('SELECT COUNT(*) FROM leave_requests')->fetchColumn()===1,'Leave ledger missing');
    $manager->perform(1,'hr_missing_attendance',$data('missing',['request_type'=>'both','clock_in_at'=>'2026-11-03T20:00','clock_out_at'=>'2026-11-04T04:00','reason'=>'Synthetic night shift']));
    check((int)$db->query('SELECT COUNT(*) FROM missing_attendance_requests')->fetchColumn()===1,'Missing attendance ledger absent');
    $ded=$data('deduction',['deduction_amount'=>100,'reason'=>'Synthetic']);$dr=$manager->perform(1,'deduction_request',$ded);
    $manager->perform(1,'deduction_request',$ded);check((int)$db->query('SELECT COUNT(*) FROM hr_adjustments')->fetchColumn()===1,'Deduction retry duplicated');
    $manager->perform(1,'hr_overtime',$data('overtime',['request_date'=>'2026-11-05','hours'=>2,'reason'=>'Synthetic']));
    check((int)$db->query('SELECT COUNT(*) FROM overtime_requests')->fetchColumn()===1,'Overtime ledger missing');
    $manager->perform(1,'suspension_request',$data('suspension',['suspension_date'=>'2026-11-08','suspension_type'=>'temporary','reason'=>'Synthetic','notes'=>'Test only']));
    seed('performance_kpis',['id'=>1,'name'=>'Synthetic KPI','weight'=>100,'is_active'=>1]);
    $manager->perform(1,'evaluation',$data('evaluation',['period'=>'2026-10','kpi_1'=>80,'notes'=>'Synthetic evaluation']));
    check((int)$db->query('SELECT COUNT(*) FROM evaluations')->fetchColumn()===1,'Evaluation not saved');
    seed('shifts',['id'=>1,'name'=>'Synthetic shift','start_time'=>'08:00:00','end_time'=>'16:00:00','is_active'=>1]);
    $manager->perform(1,'shift',$data('shift',['shift_id'=>1]));
    // Pending legacy and graph requests keep their approvers after transfer.
    seed('approval_templates',['id'=>500,'code'=>'test_transfer_pending','name'=>'Synthetic pending','fields_json'=>'[]']);
    seed('approval_steps',['id'=>500,'template_id'=>500,'step_order'=>1,'title'=>'Direct','approver_type'=>'direct_manager']);
    seed('requests',['id'=>500,'template_id'=>500,'requester_id'=>1,'requester_employee_id'=>1,'requester_position_id'=>1,'status'=>'pending','current_step'=>1,'data_json'=>'{}','workflow_version_id'=>500]);
    seed('workflow_nodes',['id'=>500,'version_id'=>500,'node_key'=>'test','node_type'=>'approval','title'=>'Synthetic','config_json'=>'{"approver":{"type":"direct_manager"}}']);
    seed('payrolls',['id'=>500,'employee_id'=>1,'period'=>'2026-09','base_salary'=>1000000,'net_salary'=>900000,'status'=>'paid']);
    seed('employee_monthly_tips',['employee_id'=>1,'period'=>'2026-09','full_amount'=>100000,'prorated_amount'=>100000]);
    seed('hr_adjustments',['employee_id'=>1,'adjustment_type'=>'deduction','amount'=>100,'period'=>'2026-09','effective_date'=>'2026-09-01','status'=>'approved','requested_by'=>2]);
    $before=hash('sha256',json_encode($db->query('SELECT * FROM payrolls')->fetchAll()));
    $t=$manager->createCase(1,$data('transfer',['new_manager_id'=>5]));
    rejected(fn()=>$manager->createCase(1,$data('transfer',['new_manager_id'=>5])),'Duplicate pending transfer allowed');
    rejected(fn()=>$hr->decide($t,'approve',''),'HR approved before receiving manager');
    $other->decide($t,'approve','Accepted');
    check((int)$db->query('SELECT manager_id FROM employees WHERE id=1')->fetchColumn()===2,'Manager changed before HR');
    $hr->decide($t,'approve','Approved');
    check((int)$db->query('SELECT manager_id FROM employees WHERE id=1')->fetchColumn()===5,'Employee manager not updated');
    check((int)$db->query('SELECT parent_id FROM org_positions WHERE id=1')->fetchColumn()===5,'Org parent not updated');
    check(!in_array(1,array_map('intval',array_column($manager->employees([])['rows'],'id')),true),'Old manager still sees employee');
    check(in_array(1,array_map('intval',array_column($other->employees([])['rows'],'id')),true),'New manager cannot see employee');
    check(hash('sha256',json_encode($db->query('SELECT * FROM payrolls')->fetchAll()))===$before,'Payroll history changed');
    check(App\Core\Workflow\ApproverResolver::resolve(['type'=>'direct_manager'],WorkflowEngine::getRequest(500))===[2],'Graph pending approver changed');
    $step=WorkflowEngine::resolveSteps(500,1,500)[0];$_SESSION['user_id']=2;check(WorkflowEngine::userCanDecideStep($step,WorkflowEngine::getRequest(500)),'Old legacy approver lost rights');$_SESSION['user_id']=5;check(!WorkflowEngine::userCanDecideStep($step,WorkflowEngine::getRequest(500)),'New manager inherited pending approval');
    rejected(fn()=>$hr->decide($t,'approve',''),'Transfer applied twice');
    rejected(fn()=>$hr->createCase(5,$data('transfer',['new_manager_id'=>1])),'Management cycle allowed');
    check($finance->archive(1,['type'=>'tips'])['total']===1,'Tips archive missing');
    foreach(['warnings','deductions','payroll','leaves','attendance','advances','evaluations','tips','loadings'] as $type)$employee->archive(1,['type'=>$type]);
    rejected(fn()=>$manager->payroll(1,500),'Manager detailed payroll leak');
    check((float)$employee->payroll(1,500)['payroll']['net_salary']===900000.0,'Self detailed payroll missing');
    check((float)$finance->payroll(1,500)['payroll']['net_salary']===900000.0,'Finance detailed payroll missing');
    rejected(fn()=>$svc(6)->payroll(1,500),'Other employee payroll leak');
    require __DIR__.'/../scripts/migrate_advance_policy.php';
    $hr=$svc(4);
    for($day=1;$day<=min(20,(int)date('d'));$day++)foreach(['in'=>'08:00:00','out'=>'16:00:00'] as $type=>$time)seed('attendance',['employee_id'=>1,'check_type'=>$type,'checked_at'=>sprintf('%s-%02d %s',date('Y-m'),$day,$time),'source'=>'web']);
    $loan=$data('loan',['amount'=>1000,'reason'=>'Synthetic loan']);$a=$hr->perform(1,'hr_exceptional_advance',$loan);check($hr->perform(1,'hr_exceptional_advance',$loan)===$a,'Advance retry duplicated');
    $hr->perform(1,'hr_long_advance',$data('loan',['amount'=>2000,'installments'=>2,'reason'=>'Synthetic long loan']));
    rejected(fn()=>$other->perform(1,'hr_long_advance',$data('loan',['amount'=>100,'installments'=>2,'reason'=>'test'])),'Manager advanced loan allowed');
    for($i=0;$i<57;$i++)$other->createCase(1,$data('notice'));
    $p1=$employee->cases(['kind'=>'notice','page_size'=>10]);$p2=$employee->cases(['kind'=>'notice','page_size'=>10,'page'=>2]);
    check(count($p1['rows'])===10&&count($p2['rows'])===10,'Pagination wrong');
    check(count($employee->cases(['kind'=>'notice'],true)['rows'])===58,'Export truncated');
    check(count(array_intersect(array_column($p1['rows'],'id'),array_column($p2['rows'],'id')))===0,'Pagination duplicates');
    check(count(App\Core\Helpers::$notifications)>0,'No notifications emitted');
    echo json_encode(['ok'=>true,'assertions'=>'authorization, approval stages, idempotency, private images, full export, read receipts, transfer hierarchy, historical financial integrity, frozen pending approvers','tables'=>'synthetic temporary only'],JSON_PRETTY_PRINT)."\n";
}
