<?php
/** Actual API controller with an in-memory database; never writes employee punches. */
namespace App\Core {
    class ApiAuth { public static function id(): int {return 7;} }
    class Response { public static array $data=[];public static int $status=200;public static function json(array $data,int $status=200):void {self::$data=$data;self::$status=$status;} }
    class HrService {
        public static int $calls=0;
        public static function recordAttendance(...$args):array {self::$calls++;return ['within_geofence'=>1];}
    }
}
namespace {
    require __DIR__.'/../app/Core/Database.php';
    require __DIR__.'/../app/Core/Controller.php';
    require __DIR__.'/../app/Controllers/ApiController.php';
    use App\Core\{Database,Response,HrService};
    $db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $db->sqliteCreateFunction('NOW',fn()=>'2026-11-09 20:45:00');$db->sqliteCreateFunction('CURDATE',fn()=>'2026-11-09');
    (new ReflectionProperty(Database::class,'instance'))->setValue(null,$db);
    $db->exec('CREATE TABLE employees (id INTEGER,user_id INTEGER,branch_id INTEGER,geofence_exempt INTEGER,reference_photo TEXT)');
    $db->exec('INSERT INTO employees VALUES(30,7,NULL,0,NULL)');
    $db->exec('CREATE TABLE settings (skey TEXT,svalue TEXT)');
    $db->exec('CREATE TABLE attendance (id INTEGER PRIMARY KEY,employee_id INTEGER,check_type TEXT,checked_at TEXT)');
    function check(bool $v,string $m):void {if(!$v)throw new RuntimeException($m);}
    function punch(int $id,string $type,string $at,int $employee=30):void {Database::query('INSERT INTO attendance VALUES(?,?,?,?)',[$id,$employee,$type,$at]);}
    function submit(string $type,bool $allowed):void {
        $before=HrService::$calls;$_POST=['check_type'=>$type];(new App\Controllers\ApiController())->attendanceCheck();
        check(Response::$status===($allowed?200:409),'Unexpected API response for '.$type);
        check(HrService::$calls===$before+($allowed?1:0),'Attendance writer called incorrectly');
    }
    punch(1,'in','2026-11-09 11:17:59');punch(2,'out','2026-11-16 20:00:00');
    submit('out',true);submit('in',false); // Haider: future exit must not hide today's entry.
    punch(3,'out','2026-11-09 21:00:00');
    (new App\Controllers\ApiController())->attendanceStatus();
    check(Response::$data['data']['clock_in']==='2026-11-09 11:17:59' && Response::$data['data']['clock_out']===null,'Future same-day exit displayed');
    punch(4,'out','2026-11-09 20:30:00');submit('out',false);submit('in',true);
    punch(5,'in','2026-11-18 12:00:00');submit('out',false);submit('in',true);
    $db->exec('DELETE FROM attendance');punch(6,'out','2026-11-10 04:00:00');submit('out',false);submit('in',true);
    $db->exec('DELETE FROM attendance');punch(7,'in','2026-11-08 23:00:00');submit('out',true); // Overnight remains supported.
    $db->exec('DELETE FROM attendance');punch(8,'in','2026-11-09 20:45:00');punch(9,'out','2026-11-09 20:45:00');submit('out',false);
    punch(10,'in','2026-11-09 20:45:00');submit('out',true); // Deterministic order within one second.
    punch(11,'out','2026-11-09 20:45:00',99);submit('out',true); // Another employee cannot affect this session.
    $db->exec('DELETE FROM attendance');submit('out',false);submit('in',true);
    echo "PASS: future entry/exit cannot alter current state, future times hidden from daily status, actual exit prevents duplicates, overnight entry allowed, equal-time ordering deterministic, employee scope preserved.\n";
}
