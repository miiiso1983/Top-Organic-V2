<?php
declare(strict_types=1);
require __DIR__.'/../app/Core/Database.php';
require __DIR__.'/../app/Core/HrReportService.php';
use App\Core\{Database,HrReportService};
date_default_timezone_set('Asia/Baghdad');
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
(new ReflectionProperty(Database::class,'instance'))->setValue(null,$db);
$db->exec('CREATE TABLE employees (id INTEGER PRIMARY KEY, full_name TEXT, department TEXT, department_id INTEGER, branch_id INTEGER, status TEXT)');
$db->exec('CREATE TABLE departments (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec('CREATE TABLE attendance (employee_id INTEGER, check_type TEXT, checked_at TEXT)');
$db->exec("INSERT INTO departments VALUES (1,'الصالة'),(2,'المطبخ')");
$db->exec("INSERT INTO employees VALUES (1,'اسم متشابه','اسم قديم',1,1,'active'),(2,'اسم متشابه','قسم بلا ربط',NULL,1,'resigned'),(3,'موظف بلا بصمة','مطبخ قديم',2,2,'active')");
$db->exec("INSERT INTO attendance VALUES (1,'in','2024-02-28 23:55:00'),(1,'out','2024-02-29 06:00:00'),(1,'in','2024-03-01 00:00:00'),(2,'out','2024-02-28 12:00:00'),(2,'in','2024-02-29 23:59:59')");
// Repeated punches must not duplicate rows or hide any day after the old 5,000-record boundary.
$q=$db->prepare("INSERT INTO attendance VALUES (1,'in',?)");
for($i=0;$i<5100;$i++)$q->execute(['2024-02-28 23:55:00']);
function check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$f=['period'=>'2026-10','date_from'=>'2024-02-28','date_to'=>'2024-03-01'];
$r=HrReportService::getReport('attendance_matrix',$f);
check($r['headers']===['اسم الموظف','القسم','2024-02-28','2024-02-29','2024-03-01'],'Explicit dates, leap day, and month boundary');
check(count($r['rows'])===3,'All employees, including no punches and duplicate names');
check($r['rows'][0]===['full_name'=>'اسم متشابه','department'=>'الصالة','day_2024-02-28'=>'حاضر','day_2024-02-29'=>'غائب','day_2024-03-01'=>'حاضر'],'Clock-in date, overnight clock-out, midnight and canonical department');
check($r['rows'][1]['day_2024-02-28']==='غائب'&&$r['rows'][1]['day_2024-02-29']==='حاضر','Out-only, last second and distinct employee IDs');
check($r['rows'][2]['day_2024-02-28']==='غائب','Employee with zero attendance');
check(count(HrReportService::attendanceMatrix($f+['department_id'=>1])['rows'])===1,'Department filter');
check(count(HrReportService::attendanceMatrix($f+['branch_id'=>1])['rows'])===2,'Branch filter');
check(count(HrReportService::attendanceMatrix($f+['status'=>'resigned'])['rows'])===1,'Employee status filter');
check(HrReportService::attendanceMatrix($f+['employee_id'=>2])['rows'][0]['department']==='قسم بلا ربط','Exact ID filter and fallback department');
$single=HrReportService::attendanceMatrix(['date_from'=>'2024-02-29','employee_id'=>1]);
check(count($single['dates'])===1&&$single['rows'][0]['day_2024-02-29']==='غائب','Single day and exclusion of next-day midnight');
check(count(HrReportService::attendanceMatrix(['period'=>'2024-02'])['dates'])===29,'Full historical month');
check(count(HrReportService::attendanceMatrix([])['dates'])===(int)date('j'),'Default current month through today');
foreach([['date_from'=>'2024-02-30'],['date_from'=>'2024-03-02','date_to'=>'2024-03-01'],['period'=>'2024-13'],['date_from'=>'1900-01-01','date_to'=>'2099-12-31']] as $bad)check(isset(HrReportService::attendanceMatrix($bad)['error']),'Invalid or oversized range must not silently truncate');
check(HrReportService::attendanceMatrix(['date_from'=>'2099-01-01'])['rows'][0]['day_2099-01-01']==='لم يأتِ بعد','Future dates must not mark absence');
if(in_array('--export',$argv,true)){
 require getenv('TEST_VENDOR_AUTOLOAD') ?: __DIR__.'/../vendor/autoload.php';
 require __DIR__.'/../app/Core/ExportHelper.php';require __DIR__.'/../app/Core/Controller.php';require __DIR__.'/../app/Controllers/HrController.php';
 $_SESSION=['role_name'=>'مدير النظام','perms'=>[]];
 $_GET=$f+['type'=>'attendance_matrix','format'=>'excel'];
 (new App\Controllers\HrController())->exportReports();
}
echo "PASS: full employee coverage, duplicate names, 5100+ punches, clock-in-only, overnight, boundaries, date validation, filters, department names and future days.\n";
