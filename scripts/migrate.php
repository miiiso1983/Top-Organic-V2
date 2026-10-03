<?php
// مشغّل الترحيلات من سطر الأوامر:
//   php scripts/migrate.php
// ينفّذ كل ملفات database/*.sql غير المطبقة بالترتيب مع سجل يمنع التكرار.
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) require $file;
    }
});

use App\Core\{App, MigrationRunner};

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

@session_start();
App::boot();

$pending = MigrationRunner::pending();
if (!$pending) {
    echo "لا توجد ترحيلات معلقة — قاعدة البيانات محدثة ✔\n";
    exit(0);
}

echo 'معلق: ' . count($pending) . " ملف\n";
$failures = 0;
foreach (MigrationRunner::runPending() as $file => $r) {
    $icon = $r['status'] === 'ok' ? '✔' : ($r['status'] === 'warnings' ? '⚠' : '✖');
    echo sprintf("%s %-45s ok=%d warn=%d\n", $icon, $file, $r['ok'], $r['warned']);
    if ($r['status'] === 'failed') {
        echo '   ' . $r['error'] . "\n";
        $failures++;
    }
}
echo $failures ? "\nفشل $failures ملف — راجع الأخطاء أعلاه\n" : "\nاكتمل الترحيل ✔\n";
exit($failures ? 1 : 0);
