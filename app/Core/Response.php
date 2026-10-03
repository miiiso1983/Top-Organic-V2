<?php
namespace App\Core;

/**
 * أدوات الاستجابة (JSON / تحويل / أخطاء)
 */
class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    public static function abort(int $status, string $message): void
    {
        http_response_code($status);
        echo '<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8"><title>خطأ</title></head>'
           . '<body style="font-family:Cairo,Tahoma,Arial,sans-serif;text-align:center;padding-top:80px">'
           . '<h1>' . $status . '</h1><p>' . htmlspecialchars($message) . '</p>'
           . '<a href="/dashboard">العودة للرئيسية</a></body></html>';
        exit;
    }
}
