<?php
declare(strict_types=1);
require __DIR__ . '/AttendanceOvernightTest.php';

function reportGroup(array $events, array $map): array {
    return HrService::attendanceReportGroups(punches($events), $map);
}
// Fully qualified because imports in the included test are file-scoped.
class_alias(App\Core\HrService::class, 'HrService');
$night = shift('20:00:00');
foreach (['03:00:00'=>'07:00','04:00:00'=>'08:00','04:00:01'=>'00:00','04:30:00'=>'00:00','04:30:01'=>'00:00','05:00:00'=>'00:00'] as $exit=>$hours) {
    $g = reportGroup([['in','2026-10-15 20:00:00'],['out','2026-10-16 '.$exit]], $night)[0];
    ensure($g['total_hours'] === $hours, 'Strict shift-duration ceiling: '.$exit);
    ensure($g['pairs'][0]['in'] === '20:00' && str_contains($g['pairs'][0]['out'],substr($exit,0,5)) && str_contains($g['pairs'][0]['out'],'2026-10-16'), 'Actual overnight times stay visible');
}
$g = reportGroup([['in','2026-10-15 20:00:00']], $night)[0];
ensure($g['pairs'][0]['out']==='-' && $g['total_hours']==='-', 'Genuinely missing exit is not zero hours');
$g = reportGroup([['in','2026-10-15 20:00:00'],['out','2026-10-16 04:45:00','request'],['out','2026-10-16 05:00:00']], $night)[0];
ensure($g['total_hours']==='00:00' && count($g['pairs'])===1 && str_contains($g['pairs'][0]['out'],'04:45'), 'Approved time takes precedence over extra raw exit');
$g = reportGroup([['in','2026-10-15 08:00:00'],['out','2026-10-16 10:00:00']],shift('08:00:00','16:00:00'));
ensure(count($g)===2 && $g[0]['pairs'][0]['out']==='-' && $g[1]['pairs'][0]['out']==='10:00', 'Following shift punch shown separately, no fabricated 26h');
$events=[['in','2026-10-15 08:00:00'],['out','2026-10-16 10:00:00']];
$flex=shift(null,'','flexible');
ensure(reportGroup($events,$flex)===HrService::pairAndGroupAttendance(punches($events),$flex), 'Flexible output remains exactly unchanged');
$g=reportGroup([['in','2026-10-15 19:30:00'],['out','2026-10-16 04:00:00']],$night)[0];
ensure($g['total_hours']==='00:00', 'Early entry also counts toward elapsed shift duration');
$g=reportGroup([['in','2026-10-15 20:00:00'],['out','2026-10-16 04:00:00','auto']],$night)[0];
ensure($g['pairs'][0]['out']==='-', 'Auto-generated exits cannot masquerade as actual times');
$split=shift('08:00:00','18:00:00');
$split[1]['default']['segments']=[['start_time'=>'08:00:00','end_time'=>'12:00:00'],['start_time'=>'14:00:00','end_time'=>'18:00:00']];
$g=reportGroup([['in','2026-10-15 08:00:00'],['out','2026-10-15 12:00:00'],['in','2026-10-15 14:00:00'],['out','2026-10-15 18:00:00']],$split)[0];
ensure($g['total_hours']==='08:00', 'Split-shift break is excluded');
$g=reportGroup([['in','2026-10-15 08:00:00'],['out','2026-10-15 12:30:00'],['in','2026-10-15 14:00:00'],['out','2026-10-15 18:00:00']],$split)[0];
ensure($g['total_hours']==='00:00', 'Overlong segment is flagged without losing times');

$g=reportGroup([['in','2026-10-15 20:00:00'],['in','2026-10-15 21:00:00'],['out','2026-10-16 05:00:00']],$night)[0];
ensure($g['pairs'][0]['out']==='-' && str_contains($g['pairs'][1]['out'],'05:00'), 'Late exit uses latest entry, not abandoned duplicate entry');
echo "PASS: report ceiling, exact boundary, late times, approved precedence, missing punches, flexible parity, split shifts and cross-shift isolation.\n";
