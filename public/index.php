<?php
/**
 * نقطة الدخول الأمامية للنظام
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Composer autoloader (PhpSpreadsheet + mPDF + App PSR-4)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

// محمّل الأصناف التلقائي (PSR-4 مبسّط: App\ => app/)
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

use App\Core\App;

App::boot();

$router = require BASE_PATH . '/config/routes.php';
$router->dispatch();
