<?php
/** Self-contained regression checks. No real database connection or employee data. */
namespace App\Core {
    class Database {
        public static $handler;
        public static array $queries = [];
        public static function connect(): object {
            return new class {
                public function inTransaction(): bool { return false; }
                public function beginTransaction(): void {}
                public function commit(): void {}
                public function rollBack(): void {}
            };
        }
        public static function query(string $sql, array $params = []): object {
            self::$queries[] = $sql;
            $value = (self::$handler)($sql, $params);
            return new class($value) {
                public function __construct(private mixed $value) {}
                public function fetch(): mixed { return $this->value; }
                public function fetchColumn(): mixed { return $this->value; }
                public function fetchAll(): array { return $this->value ?: []; }
            };
        }
    }
    class WorkflowTemplateService {
        public static string $code = 'hr_missing_attendance';
        public static function getTemplate($id): array { return ['code'=>self::$code]; }
    }
    class HrService {
        public static function loadShiftMap(...$args): array { return [1 => ['flexible' => true]]; }
    }
    class AttendanceShiftPolicy {
        public static function isFlexible($shift): bool { return true; }
    }
}
namespace {
    require __DIR__ . '/../app/Core/AttendanceCorrectionService.php';
    require __DIR__ . '/../app/Core/WorkflowEngine.php';
    use App\Core\{AttendanceCorrectionService as Correction, Database};
    function ensure(bool $ok, string $message): void {
        if (!$ok) throw new \LogicException($message);
    }
    function futureRejected(callable $action, string $message): void {
        try { $action(); } catch (\RuntimeException $e) {
            ensure(str_contains($e->getMessage(), 'لم يحن بعد'), $message . ': wrong validation error');
            return;
        }
        throw new \LogicException($message);
    }
    $now = new DateTimeImmutable('2026-11-07T21:00:30Z'); // Baghdad: Nov 8, 00:00:30.
    foreach (['UTC', 'America/Los_Angeles', 'Asia/Tokyo'] as $zone) {
        date_default_timezone_set($zone);
        ensure(Correction::occurredTimestamp('2026-11-08 00:00:30', $now) === '2026-11-08 00:00:30', 'Exact server second must be allowed');
        ensure(Correction::occurredTimestamp('2026-11-08T00:00', $now) === '2026-11-08 00:00:00', 'Current minute must be allowed');
        ensure(Correction::occurredTimestamp('2026-11-07 23:59:59', $now) === '2026-11-07 23:59:59', 'Past time across midnight must be allowed');
        futureRejected(fn() => Correction::occurredTimestamp('2026-11-08 00:00:31', $now), 'Future second accepted');
        futureRejected(fn() => Correction::occurredTimestamp('2026-11-08T00:01', $now), 'Future same-day minute accepted');
        futureRejected(fn() => Correction::occurredTimestamp('2026-11-09T00:00', $now), 'Future day accepted');
    }
    // A completed overnight shift can use next-day exit; an upcoming exit cannot.
    $afterExit = new DateTimeImmutable('2026-11-08T01:00:00Z');
    ensure(Correction::occurredTimestamp('2026-11-08 04:00:00', $afterExit) === '2026-11-08 04:00:00', 'Completed overnight exit rejected');
    futureRejected(fn() => Correction::occurredTimestamp('2026-11-08 04:00:00', $now), 'Upcoming overnight exit accepted');
    try { Correction::occurredTimestamp('2026-02-30T12:00', $now); throw new LogicException('Invalid date accepted'); }
    catch (RuntimeException $e) { ensure(str_contains($e->getMessage(), 'غير صالح'), 'Invalid date message'); }

    $future = (new DateTimeImmutable('now', new DateTimeZone('Asia/Baghdad')))->modify('+1 day')->format('Y-m-d H:i:s');
    $validateDraft = new ReflectionMethod(App\Core\WorkflowEngine::class, 'validateMissingAttendanceRequest');
    foreach (['clock_in_at', 'clock_out_at'] as $field) {
        futureRejected(fn() => $validateDraft->invoke(null, 1, ['reason'=>'test', $field=>$future]), 'Future workflow draft accepted');
    }
    App\Core\WorkflowTemplateService::$code = 'hr_leave';
    $validateDraft->invoke(null, 1, ['reason'=>'test', 'clock_in_at'=>$future]);
    App\Core\WorkflowTemplateService::$code = 'hr_missing_attendance';
    Database::$handler = function($sql) { throw new LogicException('Future payload reached database: ' . $sql); };
    foreach (['add', 'correct', 'auto'] as $mode) {
        foreach (['in', 'out', 'both'] as $type) {
            futureRejected(fn() => Correction::prepare(1, [
                'correction_mode' => $mode, 'request_type' => $type,
                'clock_in_at' => $future, 'clock_out_at' => $future,
                'original_in_at' => '2026-10-01 08:00:00', 'original_out_at' => '2026-10-01 16:00:00',
                'target_in_id' => 123, 'target_out_id' => 456,
            ]), 'Tampered future request accepted: ' . $mode . '/' . $type);
        }
    }
    ensure(Database::$queries === [], 'Future input must fail before any lookup');
    Database::$handler = function($sql) {
        if (str_starts_with($sql, 'SELECT id FROM attendance')) return false;
        throw new LogicException('Unexpected query: ' . $sql);
    };
    $plan = Correction::prepare(1, ['correction_mode'=>'add', 'request_type'=>'both', 'clock_in_at'=>'2026-10-01 20:00:00', 'clock_out_at'=>'2026-10-02 04:00:00']);
    ensure(count($plan) === 2, 'Past overnight request should remain valid');
    futureRejected(fn() => Correction::prepare(1, ['correction_mode'=>'add', 'request_type'=>'both', 'clock_in_at'=>'2026-10-01 20:00:00', 'clock_out_at'=>$future]), 'Past entry with future exit accepted');
    Database::$handler = function($sql) use ($future) {
        if (str_starts_with($sql, 'SELECT id,checked_at,source FROM attendance')) return [['id'=>123, 'checked_at'=>$future, 'source'=>'mobile']];
        if (str_starts_with($sql, 'SELECT COALESCE')) return 0;
        throw new LogicException('Unexpected original-reference query: ' . $sql);
    };
    $plan = Correction::prepare(1, ['correction_mode'=>'correct', 'request_type'=>'in', 'original_in_at'=>$future, 'clock_in_at'=>'2026-10-01 20:00:00']);
    ensure($plan['in']['original_at'] === $future && $plan['in']['corrected_at'] === '2026-10-01 20:00:00', 'Erroneous future original must remain repairable');

    // Unchanged drafts/resubmissions must not bypass validation through their saved plan.
    Database::$handler = function($sql) use ($future) {
        if (str_starts_with($sql, 'SELECT * FROM missing_attendance_requests')) return ['id'=>7, 'employee_id'=>1, 'status'=>'pending', 'request_type'=>'in', 'clock_in_at'=>$future, 'clock_out_at'=>null];
        if (str_starts_with($sql, 'SELECT user_id FROM employees')) return 11;
        throw new LogicException('Future edit reached persistence: ' . $sql);
    };
    futureRejected(fn() => Correction::syncWorkflow(['id'=>8, 'requester_id'=>11, 'requester_employee_id'=>1, 'status'=>'draft'], ['employee_id'=>1, 'request_type'=>'in', 'clock_in_at'=>$future]), 'Saved future edit accepted');

    // Legacy pending plans are rechecked at final approval, before attendance writes.
    Database::$queries = [];
    Database::$handler = function($sql) use ($future) {
        if (str_starts_with($sql, 'SELECT * FROM missing_attendance_requests')) return ['id'=>7, 'employee_id'=>1, 'workflow_request_id'=>8];
        if (str_starts_with($sql, 'SELECT id FROM requests')) return ['id'=>8];
        if (str_starts_with($sql, 'SELECT * FROM attendance_correction_plans')) return ['plan_json'=>json_encode(['in'=>['corrected_at'=>$future]])];
        if (str_starts_with($sql, 'SELECT id FROM employees')) return ['id'=>1];
        throw new LogicException('Future approval reached write: ' . $sql);
    };
    futureRejected(fn() => Correction::apply(7, 11), 'Legacy future plan approved');
    ensure(!array_filter(Database::$queries, fn($sql) => preg_match('/^(INSERT|UPDATE|DELETE)/', $sql)), 'Rejected plan wrote data');
    echo "PASS: server-authoritative Baghdad time, exact second, current minute, future second/day, midnight and overnight, tampered add/correct/legacy input, saved edits and final approval. No real database used.\n";
}
