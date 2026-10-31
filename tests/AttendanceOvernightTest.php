<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/HrService.php';
require __DIR__ . '/../app/Core/AttendanceShiftPolicy.php';
use App\Core\HrService;
date_default_timezone_set('Asia/Baghdad');

function ensure(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function punches(array $events): array {
    $rows = [];
    foreach ($events as $i => $event) {
        $rows[] = ['id' => $i + 1, 'employee_id' => 1, 'emp_code' => 'TEST', 'full_name' => 'Test', 'department' => 'Test', 'check_type' => $event[0], 'checked_at' => $event[1], 'source' => $event[2] ?? 'mobile'];
    }
    return $rows;
}
function shift(?string $start,string $end='04:00:00',string $mode='shift',array $overrides=[]): array {
    return [1=>['default'=>['attendance_type'=>$mode,'shift_id'=>1,'start_time'=>$start,'end_time'=>$end,'shift_name'=>'Test'],'overrides'=>$overrides]];
}
function group(array $events,array $map): array {return HrService::pairAndGroupAttendance(array_reverse(punches($events)),$map);}
$night=shift('20:00:00');
foreach(['04:00:00'=>28800,'04:29:59'=>30599,'04:30:00'=>30600] as $time=>$seconds){
 $r=group([['in','2026-10-15 20:00:00'],['out','2026-10-16 '.$time]],$night);
 ensure(count($r)===1 && $r[0]['total_seconds']===$seconds,'Inclusive 30-minute exit grace');
 ensure($r[0]['work_date']==='2026-10-15','Night shift anchored to its start date');
}
$r=group([['in','2026-10-15 20:00:00'],['out','2026-10-16 04:30:01']],$night);
ensure($r[0]['pairs'][0]['out']==='-' && $r[0]['total_hours']==='-' && $r[0]['total_seconds']===0,'One second past grace cannot complete a shift');
ensure(count($r[0]['excluded_records'])===1,'Late raw exit retained for review');
$r=group([['in','2026-10-15 08:00:00'],['out','2026-10-16 10:00:00']],shift('08:00:00','16:00:00'));
ensure(array_sum(array_column($r,'total_seconds'))===0 && $r[0]['pairs'][0]['out']==='-','26-hour cross-shift pairing prohibited');
$r=group([['in','2026-10-15 20:00:00'],['in','2026-10-16 20:00:00'],['out','2026-10-17 04:00:00']],$night);
ensure(count($r)===2 && $r[0]['total_hours']==='-' && $r[1]['total_hours']==='08:00','Next shift never closes the old one');
$r=group([['in','2026-10-15 20:00:00'],['out','2026-10-16 04:45:00','request']],$night);
ensure($r[0]['total_hours']==='08:45','Approved missing punch replaces dash even outside automatic grace');
$r=group([['in','2026-10-15 08:00:00'],['out','2026-10-16 10:00:00','request']],shift('08:00:00','16:00:00'));
ensure(array_sum(array_column($r,'total_seconds'))===0,'Legacy approved source alone cannot bridge shifts');
$r=group([['in','2026-10-15 20:00:00'],['out','2026-10-16 04:00:00','auto']],$night);
ensure($r[0]['pairs'][0]['out']==='-' && $r[0]['total_seconds']===0,'Automatic exit is not an actual or approved punch');
$r=group([['in','2026-10-31 23:00:00']],shift('08:00:00','16:00:00'));
ensure($r[0]['work_date']==='2026-10-31' && $r[0]['total_hours']==='-','Out-of-window clock-in cannot disappear into next month');
$r=group([['in','2026-12-31 20:00:00'],['out','2027-01-01 04:00:00']],$night);
ensure($r[0]['work_date']==='2026-12-31' && $r[0]['total_hours']==='08:00','Year boundary');
$override=$night;$override[1]['overrides']['2026-10-16']=['shift_id'=>2,'start_time'=>'01:00:00','end_time'=>'13:00:00'];
$r=group([['in','2026-10-15 20:00:00'],['out','2026-10-16 04:00:00']],$override);
ensure($r[0]['total_hours']==='08:00','Following-day override must not steal overnight exit');
$r=group([['in','2026-10-15 00:00:00'],['out','2026-10-15 08:30:00']],shift('00:00:00','08:00:00'));
ensure($r[0]['total_hours']==='08:30','Midnight shift and grace');
$split=shift('08:00:00','18:00:00');$split[1]['default']['segments']=[['start_time'=>'08:00','end_time'=>'12:00'],['start_time'=>'14:00','end_time'=>'18:00']];
$r=group([['in','2026-10-15 08:00:00'],['out','2026-10-15 18:00:00']],$split);
ensure($r[0]['total_seconds']===0 && $r[0]['total_hours']==='-','Missing split-segment exit must not consume another segment exit');
$r=group([['in','2026-10-15 08:00:00'],['out','2026-10-15 12:00:00'],['in','2026-10-15 14:00:00'],['out','2026-10-15 18:00:00']],$split);
ensure($r[0]['total_hours']==='08:00','Separate segments exclude break');
foreach([[],shift(null),shift('08:00:00','08:00:00')] as $bad){
 $r=group([['in','2026-10-15 08:00:00'],['out','2026-10-15 16:00:00']],$bad);
 ensure($r[0]['total_hours']==='-' && $r[0]['invalid_shift'],'Missing/ambiguous fixed schedule requires review');
}
$r=group([['in','2026-10-15 08:00:00'],['out','2026-10-16 10:00:00']],shift(null,'','flexible'));
ensure($r[0]['total_hours']==='26:00','Flexible rules explicitly remain unchanged');
$r=group([['in','2026-10-15 20:00:00'],['out','2026-10-15 23:00:00'],['in','2026-10-15 23:30:00'],['out','2026-10-16 04:00:00']],$night);
ensure($r[0]['total_hours']==='07:30','Break within one night window');
echo "PASS: 30-minute inclusive grace, rejected 26h fixed shifts, late/auto exits, approved exceptions, overnight, split shifts, overrides, invalid schedules and unchanged flexible rules.\n";
