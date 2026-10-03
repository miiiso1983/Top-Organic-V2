<?php
/**
 * Diagnostic script for maintenance module
 */
header('Content-Type: text/html; charset=utf-8');

echo '<pre dir="rtl" style="font-family:monospace;direction:rtl;text-align:right">';

echo "PHP version: " . PHP_VERSION . "\n\n";

try {
    define('BASE_PATH', dirname(__DIR__));

    if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
        require BASE_PATH . '/vendor/autoload.php';
    }

    spl_autoload_register(function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }
    });

    App\Core\App::boot();

    echo "App boot: OK\n\n";

    $pdo = App\Core\Database::connect();
    $tables = $pdo->query("SHOW TABLES LIKE 'maintenance_requests'")->fetchAll(PDO::FETCH_COLUMN);
    echo "maintenance_requests table: " . (count($tables) ? 'EXISTS' : 'MISSING') . "\n";

    $tables2 = $pdo->query("SHOW TABLES LIKE 'maintenance_status_logs'")->fetchAll(PDO::FETCH_COLUMN);
    echo "maintenance_status_logs table: " . (count($tables2) ? 'EXISTS' : 'MISSING') . "\n\n";

    $controller = new App\Controllers\MaintenanceController();
    echo "MaintenanceController load: OK\n\n";

    echo "Calling index()...\n";
    try {
        ob_start();
        $controller->index();
        ob_end_clean();
        echo "index() executed: OK\n";
    } catch (Throwable $e2) {
        ob_end_clean();
        echo "index() ERROR: " . $e2->getMessage() . "\n";
        echo "File: " . $e2->getFile() . ":" . $e2->getLine() . "\n";
        echo "Trace:\n" . $e2->getTraceAsString() . "\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo '</pre>';
