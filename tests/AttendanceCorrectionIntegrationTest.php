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


    use App\Core\AttendanceCorrectionService as Correction;
    $migration=file_get_contents(__DIR__.'/../scripts/migrate_attendance_corrections.php');
    $migration=str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE IF NOT EXISTS',$migration);
    eval('?>'.$migration);
    foreach([1=>'مدير النظام',2=>'موظف'] as $id=>$name)seed('roles',['id'=>$id,'name'=>$name,'level'=>$id===1?100:10]);
    foreach([1=>2,2=>1,3=>2] as $id=>$role){
        seed('users',['id'=>$id,'role_id'=>$role,'username'=>'punch-test-'.$id,'full_name'=>'Synthetic '.$id,'is_active'=>1]);
        seed('employees',['id'=>$id,'user_id'=>$id,'full_name'=>'Synthetic Employee '.$id,'emp_code'=>'PUNCH'.$id,'manager_id'=>$id===1?2:null,'department_id'=>1,'status'=>'active','file_status'=>'open','attendance_type'=>'shift','shift_id'=>1]);
    }
    seed('departments',['id'=>1,'name'=>'Synthetic']);
    seed('shifts',['id'=>1,'name'=>'Night','start_time'=>'20:00:00','end_time'=>'04:00:00']);
    for($day=0;$day<7;$day++)seed('shift_work_days',['shift_id'=>1,'day_of_week'=>$day]);
    seed('org_positions',['id'=>1,'employee_id'=>1,'parent_id'=>2,'title'=>'Test staff','is_active'=>1]);
    seed('org_positions',['id'=>2,'employee_id'=>2,'parent_id'=>null,'title'=>'Test manager','is_active'=>1]);
    $fields=[];foreach(['employee_id','request_type','clock_in_at','clock_out_at','reason','correction_mode','original_in_at','original_out_at','shift_date'] as $name)$fields[]=['name'=>$name,'type'=>'text','required'=>false];
    seed('approval_templates',['id'=>1,'code'=>'hr_missing_attendance','name'=>'Synthetic missing punch','is_active'=>1,'fields_json'=>json_encode($fields)]);
    seed('template_allowed_roles',['template_id'=>1,'role_id'=>2,'can_create'=>1]);
    seed('approval_steps',['id'=>1,'template_id'=>1,'step_order'=>1,'title'=>'Direct','approver_type'=>'direct_manager','required_count'=>1,'can_return_for_edit'=>1,'is_final'=>1]);
    HrWorkflowService::register();
    $create=function(array $data)use($db){$data+=['employee_id'=>1,'request_type'=>'out','reason'=>'Synthetic correction'];$id=WorkflowEngine::createRequest(1,1,$data);WorkflowEngine::submitRequest($id,1);return $id;};
    $requestLedger=fn($wid)=>(int)Database::query('SELECT id FROM missing_attendance_requests WHERE workflow_request_id=?',[$wid])->fetchColumn();
    seed('attendance',['id'=>1,'employee_id'=>1,'check_type'=>'in','checked_at'=>'2026-10-15 20:00:00','source'=>'mobile','within_geofence'=>1,'notes'=>'original evidence']);
    seed('attendance',['id'=>2,'employee_id'=>1,'check_type'=>'out','checked_at'=>'2026-10-16 01:00:00','source'=>'mobile','within_geofence'=>1]);
    seed('attendance',['id'=>3,'employee_id'=>1,'check_type'=>'in','checked_at'=>'2026-10-16 20:00:00','source'=>'mobile']);
    seed('attendance',['id'=>4,'employee_id'=>1,'check_type'=>'out','checked_at'=>'2026-10-16 23:00:00','source'=>'mobile']);
    rejected(fn()=>Correction::timestamp('2026-02-30T12:00'),'Invalid calendar date accepted');
    rejected(fn()=>Correction::prepare(1,['request_type'=>'out','clock_out_at'=>'2026-10-16 04:00:00']),'Ambiguous same-day punches accepted');
    rejected(fn()=>Correction::prepare(3,['request_type'=>'out','clock_out_at'=>'2026-10-16 04:00:00','target_out_id'=>2]),'Other employee punch accepted');
    $data=['correction_mode'=>'correct','original_out_at'=>'2026-10-16T01:00','clock_out_at'=>'2026-10-16T04:00'];
    $wid=$create($data);$rid=$requestLedger($wid);
    check($rid>0,'Generic web submission must create linked ledger');
    rejected(fn()=>HrService::applyMissingAttendanceRequest($rid,2),'Applied before final approval');
    rejected(fn()=>WorkflowEngine::decide($wid,'approved','',3),'Wrong approver allowed');
    WorkflowEngine::decide($wid,'approved','',2);
    check(Database::query('SELECT checked_at FROM attendance WHERE id=2')->fetchColumn()==='2026-10-16 04:00:00','Target not corrected');
    check(Database::query('SELECT checked_at FROM attendance WHERE id=4')->fetchColumn()==='2026-10-16 23:00:00','Unrelated same-day punch changed');
    check((int)Database::query('SELECT early_minutes FROM attendance WHERE id=2')->fetchColumn()===0,'Overnight end treated as same-day early exit');
    $audit=Database::query('SELECT * FROM attendance_corrections WHERE attendance_id=2')->fetch();
    check($audit['original_at']==='2026-10-16 01:00:00'&&$audit['corrected_at']==='2026-10-16 04:00:00','Original time lost');
    check((int)$audit['approved_by']===2,'Approver not recorded');
    HrService::applyMissingAttendanceRequest($rid,2);
    check((int)Database::query('SELECT COUNT(*) FROM attendance_corrections')->fetchColumn()===1,'Repeated outcome duplicated application');
    $report=App\Core\HrReportService::attendance(['date_from'=>'2026-10-15','date_to'=>'2026-10-15']);
    check(count($report['rows'])===1&&$report['rows'][0]['total_hours']==='08:00','Overnight corrected report total/date');
    check(str_contains($report['rows'][0]['original_out'],'01:00:00')&&str_contains($report['rows'][0]['correction_details'],'طلب #'.$rid),'Report audit missing');
    $history=HrService::employeeHistory(1,'2026-10');
    check(count(array_filter($history['attendance'],fn($p)=>!empty($p['corrections'])))===1,'Mobile history audit missing');
    // Concurrent requests and optimistic version checks: failed final decision fully rolls back.
    $data=['request_type'=>'in','correction_mode'=>'correct','original_in_at'=>'2026-10-16 20:00:00','clock_in_at'=>'2026-10-16 19:55:00'];
    $w1=$create($data);$w2=$create($data);
    WorkflowEngine::decide($w1,'approved','',2);
    rejected(fn()=>WorkflowEngine::decide($w2,'approved','',2),'Stale request overwritten');
    check(WorkflowEngine::getRequest($w2)['status']==='pending','Failed application left workflow approved');
    check((int)Database::query('SELECT COUNT(*) FROM request_step_decisions WHERE request_id=?',[$w2])->fetchColumn()===0,'Failed application consumed approver decision');
    check(Database::query('SELECT last_error FROM attendance_correction_plans WHERE request_id=?',[$requestLedger($w2)])->fetchColumn()!==null,'Failure diagnostic missing');
    WorkflowEngine::decide($w2,'returned_for_edit','review',2);
    WorkflowEngine::resubmit($w2,['employee_id'=>1,'request_type'=>'in','correction_mode'=>'correct','original_in_at'=>'2026-10-16 19:55:00','clock_in_at'=>'2026-10-16 19:50:00','reason'=>'Updated correction'],1);
    WorkflowEngine::decide($w2,'approved','',2);
    check(Database::query('SELECT checked_at FROM attendance WHERE id=3')->fetchColumn()==='2026-10-16 19:50:00','Resubmission not synchronized');
    // Missing punch addition and both-punch transactions.
    $w=$create(['request_type'=>'both','correction_mode'=>'add','clock_in_at'=>'2026-10-18 20:00','clock_out_at'=>'2026-10-19 04:00']);
    WorkflowEngine::decide($w,'approved','',2);
    check((int)Database::query('SELECT COUNT(*) FROM attendance_corrections WHERE request_id=?',[$requestLedger($w)])->fetchColumn()===2,'Both punch addition incomplete');
    rejected(fn()=>$create(['request_type'=>'in','correction_mode'=>'add','clock_in_at'=>'2026-10-18 20:00']),'Duplicate punch addition accepted');
    $w=$create(['request_type'=>'in','correction_mode'=>'add','clock_in_at'=>'2026-10-20 20:00']);
    WorkflowEngine::decide($w,'rejected','not valid',2);
    check((int)Database::query("SELECT COUNT(*) FROM attendance WHERE checked_at='2026-10-20 20:00:00'")->fetchColumn()===0,'Rejected request affected attendance');
    // Legacy installed app: a unique candidate is frozen at submission rather than chosen on approval.
    $w=$create(['request_type'=>'in','clock_in_at'=>'2026-10-15 20:10:00']);WorkflowEngine::decide($w,'approved','',2);
    check(Database::query('SELECT checked_at FROM attendance WHERE id=1')->fetchColumn()==='2026-10-15 20:10:00','Legacy client compatibility');
    check(Database::query('SELECT notes FROM attendance WHERE id=1')->fetchColumn()==='original evidence','Original evidence overwritten');
    check((int)Database::query('SELECT late_minutes FROM attendance WHERE id=1')->fetchColumn()===10,'Late minutes not recalculated');

    // Both changes are atomic even if the second replacement overlaps another shift.
    rejected(fn()=>$create(['request_type'=>'both','correction_mode'=>'correct','original_in_at'=>'2026-10-15 20:10:00','original_out_at'=>'2026-10-16 04:00:00','clock_in_at'=>'2026-10-15 20:00:00','clock_out_at'=>'2026-10-16 21:00:00']),'Overlapping shift accepted');
    check(Database::query('SELECT checked_at FROM attendance WHERE id=1')->fetchColumn()==='2026-10-15 20:10:00','Partial both-punch update escaped rollback');
    // Dynamic workflow uses the same transactional application boundary.
    seed('workflow_definitions',['id'=>1,'code'=>'test_missing_graph','name'=>'Synthetic graph','request_type'=>'hr_missing_attendance','template_id'=>1,'priority'=>1,'is_active'=>1]);
    seed('workflow_versions',['id'=>1,'definition_id'=>1,'version_no'=>1,'status'=>'published','published_at'=>'2026-01-01 00:00:00']);
    seed('workflow_nodes',['id'=>1,'version_id'=>1,'node_key'=>'start','node_type'=>'start','title'=>'Start','config_json'=>'{}']);
    seed('workflow_nodes',['id'=>2,'version_id'=>1,'node_key'=>'approve','node_type'=>'approval','title'=>'Approve','config_json'=>'{"approver":{"type":"direct_manager"},"approval_mode":"any","required_approver":true,"on_reject":"final"}']);
    seed('workflow_nodes',['id'=>3,'version_id'=>1,'node_key'=>'end','node_type'=>'end','title'=>'End','config_json'=>'{}']);
    seed('workflow_edges',['version_id'=>1,'from_node_id'=>1,'to_node_id'=>2,'handle'=>'default']);
    seed('workflow_edges',['version_id'=>1,'from_node_id'=>2,'to_node_id'=>3,'handle'=>'default']);
    $w=$create(['request_type'=>'in','correction_mode'=>'correct','original_in_at'=>'2026-10-15 20:10:00','clock_in_at'=>'2026-10-15 20:05:00']);
    check((int)WorkflowEngine::getRequest($w)['workflow_version_id']===1,'Graph fixture not selected');
    Database::query("UPDATE attendance SET checked_at='2026-10-15 20:11:00' WHERE id=1");
    rejected(fn()=>WorkflowEngine::decide($w,'approved','',2),'Graph stale punch accepted');
    check(WorkflowEngine::getRequest($w)['status']==='pending','Graph failure left approved request');
    check(Database::query('SELECT status FROM workflow_tasks WHERE request_id=? AND assigned_to=2',[$w])->fetchColumn()==='pending','Graph failure consumed task');
    Database::query("UPDATE attendance SET checked_at='2026-10-15 20:10:00' WHERE id=1");
    WorkflowEngine::decide($w,'approved','',2);
    check(WorkflowEngine::getRequest($w)['status']==='approved','Graph final correction failed');
    // A late real exit remains raw evidence; approved correction fills the intended night shift.
    seed('attendance',['id'=>500,'employee_id'=>1,'check_type'=>'in','checked_at'=>'2026-10-23 20:00:00','source'=>'mobile']);
    seed('attendance',['id'=>501,'employee_id'=>1,'check_type'=>'out','checked_at'=>'2026-10-24 10:00:01','source'=>'mobile']);
    $f=['date_from'=>'2026-10-23','date_to'=>'2026-10-23'];
    $before=App\Core\HrReportService::attendance($f)['rows'][0];
    check(str_contains($before['check_out'],'10:00') && $before['total_hours']==='00:00' && $before['attendance_status']==='تجاوز حد احتساب الدوام','Report must zero exits more than six hours after scheduled end');
    $w=$create(['request_type'=>'out','correction_mode'=>'add','shift_date'=>'2026-10-23','clock_out_at'=>'2026-10-24 04:45:00']);
    WorkflowEngine::decide($w,'approved','',2);
    $after=App\Core\HrReportService::attendance($f)['rows'][0];
    check($after['total_hours']==='08:45' && $after['attendance_status']==='حضور مصحح' && str_contains($after['check_out'],'04:45'),'Approved time must be visible and counted within reporting limits');
    check(Database::query('SELECT checked_at FROM attendance WHERE id=501')->fetchColumn()==='2026-10-24 10:00:01','Late original punch lost');
    $mobile=HrService::employeeHistory(1,'2026-10')['attendance_shifts'];
    $mobile=array_values(array_filter($mobile,fn($r)=>$r['date']==='2026-10-23'))[0];
    check($mobile['status']==='حضور مصحح' && $mobile['total_hours']==='08:45' && str_contains($mobile['check_out'],'04:45'),'Mobile must agree with web');
    rejected(fn()=>$create(['request_type'=>'out','correction_mode'=>'add','shift_date'=>'2026-10-23','clock_out_at'=>'2026-10-24 20:01:00']),'Explicit correction crossed next shift');
    // The legacy cron must not fabricate a fixed-shift exit, even if enabled.
    seed('attendance',['id'=>502,'employee_id'=>1,'check_type'=>'in','checked_at'=>'2026-10-25 20:00:00','source'=>'mobile']);
    $count=(int)Database::query('SELECT COUNT(*) FROM attendance')->fetchColumn();
    HrService::autoCloseMissingClockOuts('2026-10-25');
    check((int)Database::query('SELECT COUNT(*) FROM attendance')->fetchColumn()===$count,'Cron fabricated fixed-shift exit');
    $pending=$create(['request_type'=>'out','correction_mode'=>'add','shift_date'=>'2026-10-25','clock_out_at'=>'2026-10-26 04:00:00']);
    Database::query("UPDATE shifts SET end_time='05:00:00' WHERE id=1");
    rejected(fn()=>WorkflowEngine::decide($pending,'approved','',2),'Changed shift silently approved');
    check(WorkflowEngine::getRequest($pending)['status']==='pending','Changed schedule consumed approval');
    Database::query("UPDATE shifts SET end_time='04:00:00' WHERE id=1");
    // Flexible report hours recover only after approving the replacement timestamp.
    Database::query("UPDATE employees SET attendance_type='flexible' WHERE id=1");
    seed('attendance',['id'=>600,'employee_id'=>1,'check_type'=>'in','checked_at'=>'2026-10-27 08:00:00','source'=>'mobile']);
    seed('attendance',['id'=>601,'employee_id'=>1,'check_type'=>'out','checked_at'=>'2026-10-28 03:00:00','source'=>'mobile']);
    $flexFilters=['date_from'=>'2026-10-27','date_to'=>'2026-10-27'];
    check(App\Core\HrReportService::attendance($flexFilters)['rows'][0]['total_hours']==='00:00','Flexible 19h must report zero');
    $w=$create(['request_type'=>'out','correction_mode'=>'correct','original_out_at'=>'2026-10-28 03:00:00','clock_out_at'=>'2026-10-28 01:00:00']);
    check(App\Core\HrReportService::attendance($flexFilters)['rows'][0]['total_hours']==='00:00','Pending correction must not affect report');
    WorkflowEngine::decide($w,'approved','',2);
    $corrected=App\Core\HrReportService::attendance($flexFilters)['rows'][0];
    check($corrected['total_hours']==='17:00' && str_contains($corrected['check_out'],'01:00') && str_contains($corrected['original_out'],'03:00'),'Flexible approved replacement must recompute and retain original audit');
    $flexMobile=array_values(array_filter(HrService::employeeHistory(1,'2026-10')['attendance_shifts'],fn($r)=>$r['date']==='2026-10-27'))[0];
    check($flexMobile['total_hours']==='17:00','Flexible mobile summary must match report after correction');
    Database::query("UPDATE employees SET attendance_type='shift' WHERE id=1");
    // Re-running migration retains the audit and is idempotent.
    eval('?>'.$migration);
    check((int)Database::query('SELECT COUNT(*) FROM attendance_corrections')->fetchColumn()===9,'Audit count changed');
    echo "PASS: immutable audit, exact target, overnight 8h and lateness, final approval only, authorization, stale/conflicting decisions rollback, retry, resubmission, both punches, reject, duplicate protection, old clients, report and mobile history.\n";
}
