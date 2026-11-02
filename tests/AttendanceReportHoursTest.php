<?php
declare(strict_types=1);
require __DIR__ . '/AttendanceOvernightTest.php';

function reportGroup(array $events, array $map): array {
    return HrService::attendanceReportGroups(punches($events), $map);
}
// Fully qualified because imports in the included test are file-scoped.
class_alias(App\Core\HrService::class, 'HrService');
$night = shift('20:00:00');
foreach (['03:00:00'=>'07:00','04:00:00'=>'08:00','04:00:01'=>'08:00','04:30:00'=>'08:30','04:30:01'=>'08:30','05:00:00'=>'09:00','10:00:00'=>'14:00','10:00:01'=>'00:00','19:00:00'=>'00:00'] as $exit=>$hours) {
    $g = reportGroup([['in','2026-10-15 20:00:00'],['out','2026-10-16 '.$exit]], $night)[0];
    ensure($g['total_hours'] === $hours, 'Strict shift-duration ceiling: '.$exit);
    ensure($g['pairs'][0]['in'] === '20:00' && str_contains($g['pairs'][0]['out'],substr($exit,0,5)) && str_contains($g['pairs'][0]['out'],'2026-10-16'), 'Actual overnight times stay visible');
}
$g = reportGroup([['in','2026-10-15 20:00:00']], $night)[0];
ensure($g['pairs'][0]['out']==='-' && $g['total_hours']==='-', 'Genuinely missing exit is not zero hours');
$g = reportGroup([['in','2026-10-15 20:00:00'],['out','2026-10-16 04:45:00','request'],['out','2026-10-16 05:00:00']], $night)[0];
ensure($g['total_hours']==='08:45' && count($g['pairs'])===1 && str_contains($g['pairs'][0]['out'],'04:45'), 'Approved time takes precedence over extra raw exit');
$g = reportGroup([['in','2026-10-15 08:00:00'],['out','2026-10-16 10:00:00']],shift('08:00:00','16:00:00'));
ensure(count($g)===2 && $g[0]['pairs'][0]['out']==='-' && $g[1]['pairs'][0]['out']==='10:00', 'Following shift punch shown separately, no fabricated 26h');
$events=[['in','2026-10-15 08:00:00'],['out','2026-10-16 10:00:00']];
$flex=shift(null,'','flexible');
ensure(reportGroup($events,$flex)[0]['total_hours']==='00:00', 'Flexible 26-hour session is zero with actual punches preserved');
$g=reportGroup([['in','2026-10-15 19:30:00'],['out','2026-10-16 04:00:00']],$night)[0];
ensure($g['total_hours']==='08:30', 'Early entry also counts toward elapsed shift duration');
$g=reportGroup([['in','2026-10-15 20:00:00'],['out','2026-10-16 04:00:00','auto']],$night)[0];
ensure($g['pairs'][0]['out']==='-', 'Auto-generated exits cannot masquerade as actual times');
$split=shift('08:00:00','18:00:00');
$split[1]['default']['segments']=[['start_time'=>'08:00:00','end_time'=>'12:00:00'],['start_time'=>'14:00:00','end_time'=>'18:00:00']];
$g=reportGroup([['in','2026-10-15 08:00:00'],['out','2026-10-15 12:00:00'],['in','2026-10-15 14:00:00'],['out','2026-10-15 18:00:00']],$split)[0];
ensure($g['total_hours']==='08:00', 'Split-shift break is excluded');
$g=reportGroup([['in','2026-10-15 08:00:00'],['out','2026-10-15 12:30:00'],['in','2026-10-15 14:00:00'],['out','2026-10-15 18:00:00']],$split)[0];
ensure($g['total_hours']==='08:30', 'Segment overtime within six hours is counted');

$g=reportGroup([['in','2026-10-15 20:00:00'],['in','2026-10-15 21:00:00'],['out','2026-10-16 05:00:00']],$night)[0];
ensure($g['pairs'][0]['out']==='-' && str_contains($g['pairs'][1]['out'],'05:00'), 'Late exit uses latest entry, not abandoned duplicate entry');
// Fixed shifts do not have an 18-hour ceiling.
$long=shift('08:00:00','22:00:00');
foreach (['02:00:00'=>'18:00','02:00:01'=>'18:00','03:00:00'=>'19:00'] as $exit=>$hours) {
 $g=reportGroup([['in','2026-10-15 08:00:00'],['out','2026-10-16 '.$exit]],$long)[0];
 ensure($g['total_hours']===$hours && str_contains($g['pairs'][0]['out'],substr($exit,0,5)), 'Fixed shift uses scheduled exit, not flexible ceiling');
}
// The cutoff uses scheduled end, not elapsed duration or late clock-in.
$g=reportGroup([['in','2026-10-15 23:00:00'],['out','2026-10-16 10:00:01']],$night)[0];
ensure($g['total_hours']==='00:00', 'Six-hour late exit zeros even an eleven-hour duration');
$override=$night;$override[1]['overrides']['2026-10-15']=['shift_id'=>2,'shift_name'=>'Override','start_time'=>'20:00:00','end_time'=>'06:00:00'];
$g=reportGroup([['in','2026-10-15 20:00:00'],['out','2026-10-16 12:00:00']],$override)[0];
ensure($g['total_hours']==='16:00', 'Assigned date override controls six-hour cutoff');
foreach (['01:59:59'=>'17:59','02:00:00'=>'18:00','02:00:01'=>'00:00'] as $exit=>$hours) {
 $g=reportGroup([['in','2026-10-15 08:00:00'],['out','2026-10-16 '.$exit]],$flex)[0];
 ensure($g['total_hours']===$hours && str_contains($g['pairs'][0]['out'],'2026-10-16'), 'Flexible exact 18h boundary');
}
// A correction is re-evaluated, never a permanent exemption from the threshold.
foreach (['01:00:00'=>'17:00','03:00:00'=>'00:00'] as $exit=>$hours) {
 $g=reportGroup([['in','2026-10-15 08:00:00'],['out','2026-10-16 '.$exit,'request']],$flex)[0];
 ensure($g['total_hours']===$hours, 'Approved flexible time recalculates with 18h rule');
}
$g=reportGroup([['in','2026-10-15 00:00:00'],['out','2026-10-15 10:00:00'],['in','2026-10-15 12:00:00'],['out','2026-10-15 22:00:00']],$flex)[0];
ensure($g['total_hours']==='20:00', 'Flexible ceiling applies from each clock-in, not unrelated sessions combined');
echo "PASS: report six-hour and 18-hour boundaries, exact boundary, late times, approved precedence, missing punches, flexible parity, split shifts and cross-shift isolation.\n";
