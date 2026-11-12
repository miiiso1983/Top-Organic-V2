<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/vendor/autoload.php';
spl_autoload_register(static function($class){if(str_starts_with($class,'App\\')){ $file=BASE_PATH.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require_once $file; }});
\App\Core\App::boot();
$lock=fopen(sys_get_temp_dir().'/top-organic-mobile-release.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB))exit;
try { echo gmdate('c').' '.\App\Core\GooglePlayReleaseService::activatePendingAndroid().PHP_EOL; }
catch(Throwable $e){fwrite(STDERR,gmdate('c').' Release verification failed; policy unchanged: '.$e->getMessage().PHP_EOL);exit(1);}
