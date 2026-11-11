<?php
/** Runs the real controller against SQLite memory. No production data or files. */
namespace App\Core {
    class ApiAuth { public static function id(): int { return 7; } }
    class Response {
        public static array $data = [];
        public static int $status = 200;
        public static function json(array $data, int $status = 200): void {
            self::$data = $data;
            self::$status = $status;
        }
    }
    class HrService {
        public static int $calls = 0;
        public static function recordAttendance(...$args): array {
            self::$calls++;
            return ['within_geofence' => true];
        }
    }
}
namespace App\Controllers {
    // A blocked request must never enter the photo storage path.
    function is_dir(string $path): bool { throw new \RuntimeException('Photo storage was reached'); }
    function mkdir(...$args): bool { throw new \RuntimeException('Photo directory write attempted'); }
    function file_put_contents(...$args): int { throw new \RuntimeException('Photo write attempted'); }
}
namespace {
    require __DIR__ . '/../app/Core/Database.php';
    require __DIR__ . '/../app/Core/Controller.php';
    require __DIR__ . '/../app/Core/Helpers.php';
    require __DIR__ . '/../app/Core/AttendanceIntegrityService.php';
    require __DIR__ . '/../app/Controllers/ApiController.php';
    use App\Core\{Database, Response, HrService};

    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->sqliteCreateFunction('NOW', fn() => '2026-11-11 12:00:00');
    (new ReflectionProperty(Database::class, 'instance'))->setValue(null, $db);
    $db->exec('CREATE TABLE employees (id INTEGER, user_id INTEGER)');
    $db->exec('INSERT INTO employees VALUES (30, 7)');
    $db->exec('CREATE TABLE audit_log (id INTEGER PRIMARY KEY,user_id INTEGER,action TEXT,entity TEXT,entity_id INTEGER,details TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $db->exec('CREATE TABLE attendance (id INTEGER, employee_id INTEGER, check_type TEXT, checked_at TEXT)');

    function check(bool $ok, string $message): void {
        if (!$ok) throw new RuntimeException($message);
    }
    function submit(string $type, array $extra): void {
        global $db;
        $db->exec('DELETE FROM attendance');
        if ($type === 'out') {
            $db->exec("INSERT INTO attendance VALUES (1, 30, 'in', '2026-11-11 08:00:00')");
        }
        $_POST = ['check_type' => $type, 'latitude' => 33.3, 'longitude' => 44.4] + $extra;
        (new App\Controllers\ApiController())->attendanceCheck();
    }

    foreach (['in', 'out'] as $type) {
        foreach ([
            [['mock_location' => true], 'attendance_mock_location'],
            [['mock_location' => true, 'vpn_active' => true], 'attendance_mock_location'],
        ] as [$signals, $code]) {
            $before = HrService::$calls;
            submit($type, ['attendance_integrity' => $signals, 'photo' => base64_encode('test-photo')]);
            check(Response::$status === 403 && Response::$data['ok'] === false, 'Detected signal must reject ' . $type);
            check(Response::$data['error_code'] === $code, 'Wrong detection reason');
            check(HrService::$calls === $before, 'Blocked punch reached the writer');
            check(!empty(Response::$data['error']), 'A retry explanation is required');
        }
        foreach ([
            [],
            ['attendance_integrity'=>['vpn_active'=>true]],
            ['attendance_integrity' => ['mock_location' => false, 'vpn_active' => false]],
            ['attendance_integrity' => ['mock_location' => null, 'vpn_active' => null]],
            ['attendance_integrity' => ['mock_location' => 'unknown', 'vpn_active' => 'unsupported']],
            ['attendance_integrity' => ['mock_location' => 'true', 'vpn_active' => 1]],
            ['attendance_integrity' => null],
            ['attendance_integrity' => 'unsupported'],
        ] as $payload) {
            $before = HrService::$calls;
            submit($type, $payload);
            check(Response::$status === 200 && Response::$data['ok'] === true, 'Absent/clean/unknown must preserve compatibility');
            check(HrService::$calls === $before + 1, 'Allowed punch did not reach the writer exactly once');
        }
        // Detection is not sticky: retry with cleared signals succeeds.
        submit($type, ['attendance_integrity' => ['vpn_active' => true]]);
        submit($type, ['attendance_integrity' => ['vpn_active' => false, 'mock_location' => false]]);
        check(Response::$status === 200, 'Cleared detection must permit a fresh retry');
    }
    $_POST=['mock_location'=>true,'check_type'=>'out','employee_id'=>999];
    $before=HrService::$calls; (new App\Controllers\ApiController())->attendanceLocationAlert();
    check(Response::$status===200 && HrService::$calls===$before,'Alert endpoint wrote a punch');
    check((int)$db->query('SELECT COUNT(*) FROM audit_log WHERE entity_id=999')->fetchColumn()===0,'Client employee ID trusted');
    check((int)$db->query("SELECT COUNT(*) FROM audit_log WHERE entity_id=30 AND action='attendance_mock_location'")->fetchColumn()>0,'Suspicion not recorded');
    $count=$db->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
    (new App\Controllers\ApiController())->attendanceLocationAlert();
    check($db->query('SELECT COUNT(*) FROM audit_log')->fetchColumn()===$count,'Identical retry not collapsed');
    $_POST=['mock_location'=>false,'check_type'=>'out'];(new App\Controllers\ApiController())->attendanceLocationAlert();check(Response::$status===422,'Invalid report accepted');
    echo "PASS: explicit mock detections reject in/out before photo storage or punch write; VPN-only and optional signals preserve compatibility; alerts bind authenticated employee and do not write attendance; clear retry succeeds.\n";
}
