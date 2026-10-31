<?php
namespace App\Core;

/** Pure scheduling rules shared by attendance reports and approved corrections. */
final class AttendanceShiftPolicy
{
    public const EXIT_GRACE_SECONDS = 30 * 60;

    public static function isFlexible(?array $shift): bool
    {
        return ($shift['default']['attendance_type'] ?? '') === 'flexible';
    }

    /** Each segment is its own matching window, anchored to the shift's starting date. */
    public static function windows(?array $shift, string $date): array
    {
        if (!$shift || self::isFlexible($shift)) return [];
        $s = $shift['overrides'][$date] ?? $shift['default'] ?? [];
        $segments = $s['segments'] ?? [];
        if (!$segments && !empty($s['start_time']) && !empty($s['end_time'])) $segments = [$s];
        $windows = []; $previousStart = null;
        foreach ($segments as $index => $segment) {
            $start = strtotime($date . ' ' . ($segment['start_time'] ?? ''));
            $end = strtotime($date . ' ' . ($segment['end_time'] ?? ''));
            // Equal start/end is ambiguous, not an implicit 24-hour shift.
            if (!$start || !$end || $start === $end) continue;
            if ($previousStart !== null && $start < $previousStart) { $start += 86400; $end += 86400; }
            if ($end < $start) $end += 86400;
            $previousStart = $start;
            $windows[] = ['key'=>$date.'|'.($s['shift_id'] ?? 0).'|'.$index,
                'work_date'=>$date,'shift_id'=>(int)($s['shift_id'] ?? 0),'segment'=>$index,
                'name'=>$s['shift_name'] ?? '', 'start'=>$start,'end'=>$end,
                'cutoff'=>$end+self::EXIT_GRACE_SECONDS];
        }
        return $windows;
    }

    public static function locate(array $record, ?array $shift, ?string $workDate = null): ?array
    {
        if (!$shift || self::isFlexible($shift)) return null;
        $ts = strtotime($record['checked_at']); $date = date('Y-m-d',$ts);
        $candidates = [];
        foreach ($workDate ? [$workDate] : [date('Y-m-d',$ts-86400),$date,date('Y-m-d',$ts+86400)] as $d) {
            foreach (self::windows($shift,$d) as $w) {
                $isIn = $record['check_type']==='in';
                $inside = $isIn ? ($ts >= $w['start'] && $ts < $w['end']) : ($ts >= $w['start'] && $ts <= $w['cutoff']);
                $earlyIn = $isIn && $ts >= $w['start']-14400 && $ts < $w['start'];
                $w['eligible'] = $inside || $earlyIn;
                // An out-of-window punch must not disappear into a future report period.
                if (!$workDate && $d > $date && !$w['eligible']) continue;
                $w['rank'] = $inside ? 0 : ($earlyIn ? 1 : 2);
                $w['distance'] = abs($ts-($isIn ? $w['start'] : $w['end']));
                $candidates[] = $w;
            }
        }
        usort($candidates,fn($a,$b)=>[$a['rank'],$a['distance'],$a['start']] <=> [$b['rank'],$b['distance'],$b['start']]);
        return $candidates[0] ?? null;
    }

    /** Approved exceptions may pass the grace period, but cannot run into the next shift. */
    public static function approvedTimeFits(array $record, array $window, ?array $shift): bool
    {
        $ts=strtotime($record['checked_at']);
        if ($ts < $window['start']-14400 || $ts >= $window['start']+86400) return false;
        if ($record['check_type']==='in') return $ts < $window['end'];
        if ($ts < $window['start']) return false;
        foreach ([$window['work_date'],date('Y-m-d',strtotime($window['work_date'].' +1 day'))] as $date)
            foreach (self::windows($shift,$date) as $next)
                if ($next['start']>$window['start'] && $ts >= $next['start']) return false;
        return true;
    }
}
