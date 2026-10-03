<?php
/**
 * إعدادات النظام العامة
 */
return [
    'app_name'  => 'Top Organic',
    'base_url'  => '',              // يُترك فارغاً عند استخدام خادم PHP المدمج
    'timezone'  => 'Asia/Baghdad',
    'locale'    => 'ar',
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'restaurant_db',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],
    'session_name' => 'RESTAURANT_SESS',
    'currency'     => 'IQD',
    // مفتاح توقيع رموز API الخاصة بتطبيق الموبايل
    'api_secret'   => 'CHANGE_ME_TO_RANDOM_64_CHARS',
    // إعدادات المزامنة الهجينة مع الخادم المركزي (اختياري)
    'sync' => [
        'enabled'    => false,                  // فعّل بعد ضبط رابط الخادم المركزي
        'remote_url' => '',                     // مثال: https://cloud.example.com
        'remote_key' => 'CHANGE_ME_SYNC_KEY',    // مفتاح مصادقة يُرسل مع كل طلب مزامنة
        'timeout'    => 15,                      // مهلة الاتصال بالثواني
    ],
];
