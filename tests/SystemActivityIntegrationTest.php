<?php
// Run using work/attendance-corrections/guarded-test.php: writes only to connection-scoped temporary tables.
use App\Core\{Database, SystemActivityReport as Report, SystemActivityService as Activity};
if (PHP_SAPI !== 'cli') exit(1);
$db=Database::connect();
foreach (['users','employees','branches','audit_log','employee_audit_logs','request_history'] as $table) {
    $ddl=$db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $ddl=preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$ddl);
    $ddl=preg_replace('/^  CONSTRAINT .*\n/m','',$ddl);$ddl=preg_replace('/,\n\)/',"\n)",$ddl);$db->exec($ddl);
}
$ddl=file_get_contents(BASE_PATH.'/database/194_system_activity_events.sql');
$ddl=substr($ddl,strpos($ddl,'CREATE TABLE'),strpos($ddl,';')-strpos($ddl,'CREATE TABLE')+1);
$db->exec(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$ddl));
function check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
function seedActivity(string $table,array $values):void {
 global $db;
 foreach($db->query("DESCRIBE `$table`")->fetchAll() as $c){
  $n=$c['Field'];if(array_key_exists($n,$values)||$c['Null']==='YES'||$c['Default']!==null||str_contains($c['Extra'],'auto_increment'))continue;
  $t=$c['Type'];if(str_starts_with($t,'enum(')){preg_match("/enum\('([^']+)'/",$t,$m);$values[$n]=$m[1];}elseif(preg_match('/int|decimal|float|double/',$t))$values[$n]=1;elseif(preg_match('/date|time/',$t))$values[$n]='2026-11-11 09:00:00';else $values[$n]='synthetic';
 }
 $db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($values)).'`) VALUES ('.implode(',',array_fill(0,count($values),'?')).')')->execute(array_values($values));
}
seedActivity('branches',['id'=>1,'name'=>'فرع تجريبي']);
seedActivity('users',['id'=>1,'full_name'=>'مستخدم اختبار','branch_id'=>1]);
seedActivity('employees',['id'=>1,'full_name'=>'موظف متأثر تجريبي']);
foreach(range(1,31) as $i)seedActivity('audit_log',['id'=>$i,'user_id'=>1,'action'=>'تعديل قسم','entity'=>'departments','entity_id'=>7,'details'=>'fixture 100% literal','created_at'=>'2026-11-11 10:00:00']);
seedActivity('employee_audit_logs',['employee_id'=>1,'changed_by'=>1,'action'=>'update','field_name'=>'salary','old_value'=>'100','new_value'=>'200','created_at'=>'2026-11-11 12:00:00']);
seedActivity('request_history',['request_id'=>5,'actor_id'=>1,'actor_name'=>'اسم محفوظ','action'=>'approved','notes'=>'test approval','created_at'=>'2026-11-11 13:00:00']);
foreach(['web','mobile_api'] as $channel)seedActivity('system_activity_events',['user_id'=>1,'branch_id'=>1,'channel'=>$channel,'action'=>'ApiController.attendanceCheck','route'=>'/api/attendance/check','method'=>'POST','result'=>'rejected','http_status'=>409,'duration_ms'=>32,'created_at'=>'2026-11-11 14:00:00']);
$f=Report::filters(['from'=>'2026-11-11','to'=>'2026-11-11']);
$p=Report::page($f);check($p['total']===35 && count($p['rows'])===25 && $p['pages']===2,'Union count / pagination');
$p2=Report::page(array_merge($f,['page'=>2]));check(count($p2['rows'])===10,'Second page missing records');
$keys=fn($rows)=>array_map(fn($r)=>$r['source'].':'.$r['id'],$rows);
check(!array_intersect($keys($p['rows']),$keys($p2['rows'])),'Unstable duplicate page rows');
check(Report::page(array_merge($f,['source'=>'mobile_api']))['total']===1,'Mobile source filter');
check(Report::page(array_merge($f,['result'=>'rejected']))['total']===2,'Result filter');
check(Report::page(array_merge($f,['q'=>'متأثر']))['total']===1,'Target employee search');
check(Report::page(array_merge($f,['q'=>'100%']))['total']===31,'Literal percent search');
check(Report::page(array_merge($f,['q'=>"' OR 1=1 --"]))['total']===0,'SQL injection');
check(Report::page(array_merge($f,['entity'=>'departments','entity_id'=>7]))['total']===31,'Entity filter');
check(Report::page(array_merge($f,['from'=>'2026-11-12','to'=>'2026-11-12']))['total']===0,'Inclusive dates');
check(Report::page(array_merge($f,['user_id'=>2]))['total']===0,'Actor filter');
check(Report::page(array_merge($f,['branch_id'=>1]))['total']===35,'Branch filter');
foreach([['from'=>'2026-02-30'],['from'=>'2026-11-12','to'=>'2026-11-11'],['source'=>'injection']] as $bad){try{Report::filters($bad);throw new LogicException('Invalid filter accepted');}catch(InvalidArgumentException $expected){}}
check(Report::filters(['per_page'=>999999])['per_page']===25,'Unbounded page size');
check(!str_contains(Activity::safeDetails('secret-value','password_hash'),'secret-value'),'Sensitive employee field disclosed');
check(!str_contains(Activity::safeDetails('token=abc123 Bearer hello-world'),'abc123'),'Token leaked');
// Exercise the actual shutdown writer with synthetic identity and request metadata.
$_SESSION=['user_id'=>1,'branch_id'=>1];$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['HTTP_USER_AGENT']='Android';$_SERVER['HTTP_AUTHORIZATION']='Bearer NEVER_STORE';$_POST=['password'=>'NEVER_STORE','selfie'=>'NEVER_STORE'];
(new ReflectionProperty(Activity::class,'context'))->setValue(null,['route'=>'/api/attendance/check','operation'=>'ApiController.attendanceCheck','entity_id'=>1,'channel'=>'mobile_api','started'=>microtime(true),'actor'=>null,'branch'=>null]);
Activity::identify(1);Activity::outcome('rejected');http_response_code(409);Activity::finish();
$last=$db->query('SELECT * FROM system_activity_events ORDER BY id DESC LIMIT 1')->fetch();
check((int)$last['user_id']===1 && $last['result']==='rejected' && (int)$last['http_status']===409,'Writer actor/outcome');
check(!str_contains(json_encode($last),'NEVER_STORE'),'Request secrets persisted');
// Render view from the real candidate; assert escaping, details and page links.
$filters=$f;$report=Report::page($f);$actors=[['id'=>1,'full_name'=>'<script>unsafe()</script>']];$branches=[['id'=>1,'name'=>'Branch']];
ob_start();require BASE_PATH.'/app/Views/admin/activity.php';$html=ob_get_clean();
check(!str_contains($html,'<script>unsafe()'),'Unescaped user name');
check(str_contains($html,'page=2') && str_contains($html,'data-activity-actor'),'Missing navigation/search');
file_put_contents(dirname(BASE_PATH).'/activity-fixture.html','<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><body>'.$html.'</body></html>');
echo "PASS: union history, counts, stable pagination, dates, actor/target/branch/source/result filters, injection, secret exclusion, real event writer, escaped view.\n";
