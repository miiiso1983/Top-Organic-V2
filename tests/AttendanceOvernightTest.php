<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/HrService.php';
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
function shift(?string $start, string $mode = 'shift', array $overrides = []): array {
    return [1 => ['default' => ['attendance_type' => $mode, 'start_time' => $start], 'overrides' => $overrides]];
}
function onePair(array $events, array $shift, int $seconds, string $day, string $label): array {
    // The result must not depend on the database's row order.
    $groups = HrService::pairAndGroupAttendance(array_reverse(punches($events)), $shift);
    ensure(count($groups) === 1, "$label: clock-in and clock-out must stay together");
    $g = $groups[0];
    ensure($g['work_date'] === $day && $g['total_seconds'] === $seconds, "$label: wrong workday or elapsed time");
    ensure(count($g['pairs']) === 1 && $g['pairs'][0]['in'] !== '-' && $g['pairs'][0]['out'] !== '-', "$label: lost a punch");
    return $g;
}
$night = [['in','2026-10-15 22:10:15'], ['out','2026-10-16 06:45:45']];
foreach ([null, '00:00:00', '01:00:00', '08:00:00', '21:00:00'] as $start) {
    $g = onePair($night, shift($start), 30930, '2026-10-15', 'fixed ' . ($start ?? 'no shift'));
    ensure($g['total_hours'] === '08:35', 'Round only after summing actual seconds');
    ensure($g['pairs'][0]['out'] === '06:45 (2026-10-16)', 'Show the following-day date');
}
onePair($night, shift(null, 'flexible'), 30930, '2026-10-15', 'flexible');
onePair($night, [], 30930, '2026-10-15', 'no shift mapping');
onePair([['in','2026-10-15 22:00:00'],['out','2026-10-16 05:00:00','request']], shift('21:00:00'), 25200, '2026-10-15', 'requested clock-out');
onePair([['in','2026-10-15 22:00:00','request'],['out','2026-10-16 05:00:00']], shift('21:00:00'), 25200, '2026-10-15', 'requested clock-in');
onePair($night, shift('21:00:00','shift',['2026-10-16'=>['start_time'=>'01:00:00']]), 30930, '2026-10-15', 'next-day shift override');
$long = onePair([['in','2026-10-15 10:00:00'],['out','2026-10-16 12:00:00']], shift('08:00:00'), 93600, '2026-10-15', 'over 24 hours');
ensure($long['total_hours'] === '26:00', 'Durations must not wrap at 24 hours');
$boundary = punches([['in','2026-12-31 22:00:00'],['out','2027-01-01 06:00:00']]);
$rows = HrService::pairAndGroupAttendance($boundary, shift('08:00:00'), '2026-12-31', '2026-12-01', '2026-12-31');
ensure(count($rows) === 1 && $rows[0]['total_hours'] === '08:00', 'Month/year boundary and day filter must retain the whole shift');
ensure(HrService::pairAndGroupAttendance($boundary, shift('08:00:00'), '2027-01-01') === [], 'Do not count the same overnight shift twice');
$split = punches([['in','2026-10-15 22:00:00'],['out','2026-10-15 23:00:00'],['in','2026-10-15 23:30:00'],['out','2026-10-16 01:00:00']]);
$rows = HrService::pairAndGroupAttendance($split, shift('08:00:00'));
ensure(count($rows) === 1 && count($rows[0]['pairs']) === 2 && $rows[0]['total_hours'] === '02:30', 'Sum work intervals without counting the break');
$duplicate = punches([['in','2026-10-15 20:00:00'],['in','2026-10-15 21:00:00'],['out','2026-10-16 02:00:00']]);
$rows = HrService::pairAndGroupAttendance($duplicate, shift('08:00:00'));
ensure(count($rows[0]['pairs']) === 2 && $rows[0]['pairs'][0]['out'] === '-' && $rows[0]['total_hours'] === '05:00', 'Preserve an unmatched repeated clock-in without double-counting');
foreach ([
    [['out','2026-10-15 05:00:00'],['in','2026-10-15 20:00:00']],
    [['in','2026-10-15 20:00:00'],['out','2026-10-18 05:00:00']],
] as $incomplete) {
    $rows = HrService::pairAndGroupAttendance(punches($incomplete), shift('08:00:00'));
    ensure(array_sum(array_column($rows,'total_seconds')) === 0, 'Do not invent hours for reversed or several-days-apart fixed-shift punches');
    ensure(array_sum(array_map(fn($r) => count($r['pairs']),$rows)) === 2, 'Preserve both unmatched punches');
}
echo "PASS: overnight pairing, shift overrides, requests, full dates, seconds, breaks, duplicate and missing punches, 26-hour durations, month/year boundaries and day filters.\n";
