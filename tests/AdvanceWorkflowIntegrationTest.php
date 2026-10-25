<?php
/** MySQL integration test: only empty, connection-scoped TEMPORARY tables are written.
 * Bootstrap Database connection and candidate autoload first; run CLI on a schema with the application tables.
 * Real employee data is never copied. Notifications are stubbed before application classes load.
 */
namespace App\Core {
    class Helpers {
        private static int $counter=0;
        public static function docNumber(...$args): string { return 'TEST-'.(++self::$counter); }
        public static function notify(...$args): void {}
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
    foreach([1=>'مدير النظام',3=>'مدير مالي',5=>'محاسب',10=>'موظف',25=>'مدير قسم',26=>'مدير قسم الموارد البشرية'] as $id=>$name)seed('roles',['id'=>$id,'name'=>$name]);
    foreach([1=>10,2=>25,3=>25,4=>26,5=>3,6=>5,7=>1] as $id=>$role){
        seed('users',['id'=>$id,'role_id'=>$role,'full_name'=>'Test User '.$id,'username'=>'test'.$id,'email'=>'test'.$id.'@example.invalid']);
        seed('employees',['id'=>$id,'user_id'=>$id,'manager_id'=>$id<3?$id+1:null,'full_name'=>'Test Employee '.$id,'emp_code'=>'TEST'.$id,'base_salary'=>1000000,'status'=>'active','file_status'=>'open','attendance_type'=>'flexible','required_hours'=>8,'hire_date'=>'2026-01-01','insurance_percentage'=>0,'weekly_off_day'=>null]);
    }
    require __DIR__.'/../scripts/migrate_advance_policy.php';
    require __DIR__.'/../scripts/migrate_advance_policy.php'; // repeatability
    HrWorkflowService::register();
    $month=date('Y-m');
    for($day=1;$day<=min(20,(int)date('d'));$day++) {
        foreach(['in'=>'08:00:00','out'=>'16:00:00'] as $type=>$time)seed('attendance',['employee_id'=>1,'check_type'=>$type,'checked_at'=>sprintf('%s-%02d %s',$month,$day,$time),'source'=>'web']);
    }
    // 500,000 was lent, 100,000 repaid: only the remaining 400,000 is debt.
    seed('salary_advances',['id'=>900,'employee_id'=>1,'amount'=>500000,'paid_amount'=>100000,'remaining_amount'=>400000,'status'=>'paid','advance_type'=>'long','installments'=>5,'paid_at'=>$month.'-01 10:00:00']);
    for($i=0;$i<4;$i++)seed('advance_repayment_schedule',['advance_id'=>900,'period'=>date('Y-m',strtotime($month.'-01 +'.$i.' months')),'amount'=>100000,'status'=>'pending']);
    $snapshot=AdvancePolicyService::snapshot(1);
    check($snapshot['outstanding_debt']===400000.0,'Entire remaining installment debt');
    check($snapshot['deductions']===0.0,'Monthly installment must not be deducted twice');
    check($snapshot['remaining']===250000.0,'Real biometric payroll computation feeds policy');
    $templates=[];foreach(Database::query('SELECT id,code FROM approval_templates')->fetchAll() as $t)$templates[$t['code']]=$t['id'];
    $create=function(string $code,int $actor=1,float $amount=200000,int $installments=1)use($templates):int {
        $id=WorkflowEngine::createRequest($templates[$code],$actor,['employee_id'=>1,'amount'=>$amount,'reason'=>'Synthetic test','installments'=>$installments]);
        WorkflowEngine::submitRequest($id,$actor);return $id;
    };
    // Existing day-13 rule is tested independently; integration uses current month after its opening date.
    $request=$create('hr_advance');
    $ledger=(int)HrWorkflowService::workflowRequestId('salary_advance',(int)WorkflowEngine::getRequest($request)['data']['entity_id']);
    check($ledger===$request,'Workflow submission creates one linked advance');
    rejected(fn()=>$create('hr_advance'),'Duplicate monthly request allowed');
    rejected(fn()=>$create('hr_exceptional_advance',1),'Non-HR exceptional request allowed');
    rejected(fn()=>WorkflowEngine::decide($request,'approved','',5),'Wrong approver allowed');
    WorkflowEngine::decide($request,'rejected','test',2);
    $request=$create('hr_advance');WorkflowEngine::cancelRequest($request,1);
    $request=$create('hr_advance');
    WorkflowEngine::decide($request,'returned_for_edit','test',2);
    WorkflowEngine::resubmit($request,['employee_id'=>1,'amount'=>180000,'reason'=>'Revised'],$actor=1);
    WorkflowEngine::decide($request,'approved','',2);
    check(WorkflowEngine::getRequest($request)['status']==='pending','Monthly requires HR second approval');
    WorkflowEngine::decide($request,'approved','',4);
    $req=WorkflowEngine::getRequest($request);$aid=(int)$req['data']['entity_id'];
    check($req['status']==='approved','Monthly approved after two stages');
    check((float)Database::query('SELECT amount FROM salary_advances WHERE id=?',[$aid])->fetchColumn()===180000.0,'Edited request must update accounting ledger');
    check((int)Database::query('SELECT COUNT(*) FROM journal_entries')->fetchColumn()===0,'Approvals must not create journal entries');
    $_SESSION=['user_id'=>6,'perms'=>['accounting.manage']];
    $controller=new App\Controllers\HrController();
    $_POST=['cash_register_id'=>1,'payment_method'=>'cash'];
    seed('cash_registers',['id'=>1,'name'=>'Test Cash','balance'=>2000000]);
    seed('accounts',['id'=>1,'code'=>'1150','name'=>'Test Receivable','is_active'=>1]);
    seed('accounts',['id'=>2,'code'=>'1000','name'=>'Test Cash','is_active'=>1]);
    controllerAction(fn()=>$controller->payAdvance((string)$aid));
    check((int)Database::query('SELECT COUNT(*) FROM cash_transactions')->fetchColumn()===0,'Payment before accountant authorization');
    controllerAction(fn()=>$controller->approveAdvanceForPayment((string)$aid));
    check(($_SESSION['flash']['type']??'')==='success','Accountant approval failed: '.($_SESSION['flash']['message']??''));
    check((int)Database::query('SELECT COUNT(*) FROM journal_entries')->fetchColumn()===0,'Accountant authorization created a journal');
    rejected(fn()=>$controller->printAdvanceReceipt((string)$aid),'Receipt before actual payment');
    Database::query('DELETE FROM accounts WHERE id=1');
    controllerAction(fn()=>$controller->payAdvance((string)$aid));
    check((int)Database::query('SELECT COUNT(*) FROM cash_transactions')->fetchColumn()===0,'Failed journal must roll back cash');
    check(Database::query('SELECT status FROM salary_advances WHERE id=?',[$aid])->fetchColumn()==='approved','Failed journal must roll back paid status');
    seed('accounts',['id'=>1,'code'=>'1150','name'=>'Test Receivable','is_active'=>1]);
    controllerAction(fn()=>$controller->payAdvance((string)$aid));
    check(($_SESSION['flash']['type']??'')==='success','Payment failed: '.($_SESSION['flash']['message']??''));
    controllerAction(fn()=>$controller->payAdvance((string)$aid));
    check((int)Database::query('SELECT COUNT(*) FROM cash_transactions')->fetchColumn()===1,'Double cash payment');
    check((int)Database::query('SELECT COUNT(*) FROM journal_entries')->fetchColumn()===1,'Exactly one journal at payment');
    check((float)Database::query('SELECT balance FROM cash_registers WHERE id=1')->fetchColumn()===1820000.0,'Cash balance');
    $balance=Database::query('SELECT SUM(debit)-SUM(credit) FROM journal_lines')->fetchColumn();check((float)$balance===0.0,'Balanced journal');
    ob_start();$controller->printAdvanceReceipt((string)$aid);$receipt=ob_get_clean();check(str_contains($receipt,'تم الصرف'),'Printed receipt');
    // Long-term has its own approvals, can coexist with monthly, and is not capped at 25%.
    $long=$create('hr_long_advance',4,600000,6);
    $formController = new class extends App\Controllers\WorkflowController {
        public array $captured = [];
        protected function view(string $viewTemplate, array $data = []): void { $this->captured = $data; }
    };
    $_SESSION=['user_id'=>4,'role_name'=>'مدير قسم الموارد البشرية','perms'=>[]];
    $formController->editRequestForm((string)$long);
    check($formController->captured['advanceLimit']['working_days']===20,'Edit form must use beneficiary entitlement, not HR requester salary');
    $_SESSION=['user_id'=>6,'perms'=>['accounting.manage']];

    foreach([2,3] as $actor){WorkflowEngine::decide($long,'approved','',$actor);check(WorkflowEngine::getRequest($long)['status']==='pending','Long requires HR final approval');}
    WorkflowEngine::decide($long,'approved','',4);check(WorkflowEngine::getRequest($long)['status']==='approved','Long final HR approval');
    $longAid=(int)WorkflowEngine::getRequest($long)['data']['entity_id'];
    controllerAction(fn()=>$controller->approveAdvanceForPayment((string)$longAid));
    controllerAction(fn()=>$controller->payAdvance((string)$longAid));
    check(($_SESSION['flash']['type']??'')==='success','Long payment failed: '.($_SESSION['flash']['message']??''));
    $schedule=Database::query('SELECT COUNT(*) AS n,SUM(amount) AS total,MIN(amount) AS smallest FROM advance_repayment_schedule WHERE advance_id=?',[$longAid])->fetch();
    check((int)$schedule['n']===6 && (float)$schedule['total']===600000.0 && (float)$schedule['smallest']>0,'Long schedule sums to actual loan with positive installments');
    Database::query("UPDATE salary_advances SET remaining_amount=0,status='settled' WHERE id=?",[$longAid]);
    // Release the month's monthly request only in this temporary fixture to test exceptional routing.
    Database::query("UPDATE requests SET status='cancelled' WHERE id=?",[$request]);
    Database::query("UPDATE salary_advances SET remaining_amount=0,status='settled' WHERE id=?",[$aid]);
    $exceptional=$create('hr_exceptional_advance',4,50000);
    foreach([2,3] as $actor)WorkflowEngine::decide($exceptional,'approved','',$actor);
    check(WorkflowEngine::getRequest($exceptional)['status']==='pending','Exceptional requires Finance final approval');
    WorkflowEngine::decide($exceptional,'approved','',5);
    check(WorkflowEngine::getRequest($exceptional)['status']==='approved','Exceptional final Finance approval');
    check(!AdvancePolicyService::canViewFinancial(6,1),'Unrelated employee cannot view financial data');
    check(AdvancePolicyService::canViewFinancial(1,2),'Direct manager can view financial data');
    rejected(fn()=>$create('hr_long_advance',1,600000,6),'Non-HR long-term request allowed');
    // Exercise the same API controllers used by both native platforms, with synthetic actors only.
    $api = new App\Controllers\ApiController();
    $as = function(int $id,array $permissions=[]):void {
        $u=Database::query('SELECT u.*,r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?',[$id])->fetch();
        (new ReflectionProperty(App\Core\ApiAuth::class,'user'))->setValue(null,$u);
        $_SESSION=['user_id'=>$id,'role_id'=>$u['role_id'],'role_name'=>$u['role_name'],'perms'=>$permissions];
        $_POST=[]; $_GET=[];
        App\Core\Response::$last=[]; App\Core\Response::$status=200;
    };
    $as(1); $api->workflowTemplates();
    $codes=array_column(App\Core\Response::$last['data'],'code');
    check(in_array('hr_advance',$codes,true) && !in_array('hr_long_advance',$codes,true) && !in_array('hr_exceptional_advance',$codes,true),'Employee mobile template visibility');
    $api->workflowTemplateDetail((string)$templates['hr_long_advance']);
    check(App\Core\Response::$status===403,'Employee direct template access blocked');
    $api->workflowTemplateDetail((string)$templates['hr_advance']);
    check(count(App\Core\Response::$last['data']['employees'])===1 && (int)App\Core\Response::$last['data']['employees'][0]['id']===1,'Monthly employee selector is self-only');
    $as(4); $api->workflowTemplates();
    $codes=array_column(App\Core\Response::$last['data'],'code');
    check(in_array('hr_long_advance',$codes,true) && in_array('hr_exceptional_advance',$codes,true),'HR mobile templates');
    $_POST=['template_id'=>$templates['hr_exceptional_advance'],'fields'=>['employee_id'=>1,'advance_type'=>'طويلة الأجل','amount'=>600000,'installments'=>6,'reason'=>'Synthetic legacy mobile']];
    $api->workflowStoreRequest();
    check((App\Core\Response::$last['ok']??false)===true,'Legacy mobile submission: '.json_encode(App\Core\Response::$last));
    $mobileId=(int)App\Core\Response::$last['id'];
    check(WorkflowEngine::getRequest($mobileId)['template_code']==='hr_long_advance','Old mobile long-term mapped to correct template');
    $as(1);$api->workflowMyRequests();check(in_array($mobileId,array_map('intval',array_column(App\Core\Response::$last['data'],'id')),true),'Beneficiary sees advance submitted by HR');
    $as(2); $api->workflowShowRequest((string)$mobileId);
    check(App\Core\Response::$last['data']['can_decide']===true,'Current graph approver sees mobile decision button');
    check(App\Core\Response::$last['data']['can_edit']===true,'Current graph approver can edit');
    check(in_array($mobileId,array_map('intval',array_column(WorkflowEngine::getVisibleRequests(2),'id')),true),'Graph approver sees inbox request');
    check(count(App\Core\Response::$last['data']['request']['employees'])===1,'Request does not expose unrelated employees');
    $as(3); $api->workflowShowRequest((string)$mobileId);
    check(App\Core\Response::$last['data']['can_decide']===false,'Future graph approver cannot decide early');
    $as(6); $api->workflowShowRequest((string)$mobileId);
    check(App\Core\Response::$status===403,'Unrelated account cannot read advance details');
    $as(2); $_POST=['decision'=>'approved']; $api->workflowDecide((string)$mobileId);
    $api->workflowShowRequest((string)$mobileId);check(App\Core\Response::$last['data']['can_decide']===false,'Previous approver cannot decide twice');
    $as(3); $api->workflowShowRequest((string)$mobileId);check(App\Core\Response::$last['data']['can_decide']===true,'Next graph stage becomes available');
    $_POST=['decision'=>'approved'];$api->workflowDecide((string)$mobileId);
    $as(4); $_POST=['decision'=>'approved'];$api->workflowDecide((string)$mobileId);
    $mobileAid=(int)WorkflowEngine::getRequest($mobileId)['data']['entity_id'];
    $as(1); $api->advanceDisbursements();check(App\Core\Response::$status===403,'Employee cash list denied');
    $api->advanceAuthorize((string)$mobileAid);check(App\Core\Response::$status===403,'Employee accountant action denied');
    $api->advancePay((string)$mobileAid);check(App\Core\Response::$status===403,'Employee payment denied');
    $as(6,['accounting.manage']);$api->advanceDisbursements();check(App\Core\Response::$last['ok']===true,'Accountant cash list available');
    $_POST=['payment_method'=>'cash','cash_register_id'=>1]; $api->advancePay((string)$mobileAid);
    check(App\Core\Response::$last['ok']===false,'Mobile cannot pay before accountant authorization');
    $api->advanceReceipt((string)$mobileAid);check(App\Core\Response::$status===404,'No mobile receipt before actual payment');
    $api->advanceAuthorize((string)$mobileAid);check(App\Core\Response::$last['ok']===true,'Mobile accountant authorization');
    $before=(int)Database::query('SELECT COUNT(*) FROM cash_transactions')->fetchColumn();
    $api->advancePay((string)$mobileAid);check(App\Core\Response::$last['ok']===true,'Mobile payment completes');
    $api->advancePay((string)$mobileAid);check(App\Core\Response::$last['ok']===false,'Mobile duplicate payment rejected');
    check((int)Database::query('SELECT COUNT(*) FROM cash_transactions')->fetchColumn()===$before+1,'Exactly one mobile payment');
    $api->advanceReceipt((string)$mobileAid);check(App\Core\Response::$last['ok']===true && (int)App\Core\Response::$last['data']['id']===$mobileAid,'Mobile receipt after actual payment');
    $as(1);$api->advanceReceipt((string)$mobileAid);check(App\Core\Response::$status===403,'Mobile receipt permission enforced');
    echo "PASS: mobile template permissions, legacy long routing, graph decision stages, financial privacy, accountant authorization and single payment.\n";

    Database::query('UPDATE employees SET manager_id=NULL WHERE id=1');
    rejected(fn()=>$create('hr_long_advance',4,600000,6),'Missing manager bypassed required approval');

    echo "PASS: synthetic MySQL workflow, debt, rejected/cancelled replacement, return/edit, three approval routes, accountant gate, single payment/journal, receipt. No real rows changed.\n";
}
