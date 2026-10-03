<?php
// معالج SLA والتصعيد التلقائي — يُشغَّل من cron كل 5-15 دقيقة.
// مثال جدولة لينكس (خمس حقول: دقيقة ساعة يوم شهر أسبوع):
//   كل 5 دقائق →  php /path/to/project/scripts/workflow_cron.php >> /dev/null 2>&1
// بديل للاستضافات بدون CLI: استدعِ URL (يتطلب توكن من الإعدادات):
//   https://example.com/cron/workflow-escalation?token=XXXX
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) require $file;
    }
});

use App\Core\App;
use App\Core\Workflow\EscalationService;
use App\Core\Ptt\{PttService, PttAiService};
use App\Core\HrService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

@session_start();
App::boot();

$start = microtime(true);
$result = EscalationService::run();
$elapsed = round(microtime(true) - $start, 2);

echo '[' . date('Y-m-d H:i:s') . "] workflow escalation: "
    . "auto_actions={$result['auto_actions']} reminders={$result['reminders']} pending_reminders={$result['pending_reminders']} overtime_expired={$result['overtime_expired']} ({$elapsed}s)\n";

// PTT: حذف الرسائل الأقدم من مدة الاحتفاظ + تفريغ AI تلقائي للعاجل/الطارئ
try {
    $purged = PttService::purgeOld();
    $transcribed = PttAiService::cronAutoTranscribe(5);
    echo '[' . date('Y-m-d H:i:s') . "] ptt maintenance: purged=$purged transcribed=$transcribed\n";
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] ptt maintenance failed: ' . $e->getMessage() . "\n";
}

// إرسال إشعارات Push المعلقة (FCM + Web Push)
try {
    $push = \App\Core\Push\PushSender::processQueue(50);
    echo '[' . date('Y-m-d H:i:s') . "] push queue: sent={$push['sent']} failed={$push['failed']} skipped={$push['skipped']}\n";
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] push queue failed: ' . $e->getMessage() . "\n";
}
// إرسال رسائل WhatsApp/SMS/Email المعلّقة
try {
    $notifications = \App\Core\NotificationService::processQueue(50);
    echo '[' . date('Y-m-d H:i:s') . "] notifications queue: sent={$notifications['sent']} failed={$notifications['failed']} skipped={$notifications['skipped']}\n";
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] notifications queue failed: ' . $e->getMessage() . "\n";
}

// تنبيهات نسيان بصمة الخروج (بعد انتهاء الوردية + فترة سماح)
try {
    $missingClockOuts = HrService::checkMissingClockOuts();
    echo '[' . date('Y-m-d H:i:s') . '] missing clockouts: ' . count($missingClockOuts) . " alerts\n";
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] missing clockouts check failed: ' . $e->getMessage() . "\n";
}

// تسوية بصمات الخروج المنسية تلقائياً (افتراضياً لليوم السابق)
try {
    $autoClose = HrService::autoCloseMissingClockOuts();
    echo '[' . date('Y-m-d H:i:s') . '] auto clockouts: closed=' . $autoClose['closed'] . ' alerts=' . $autoClose['alerts'] . "\n";
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] auto clockouts failed: ' . $e->getMessage() . "\n";
}

// إرسال تذكيرات التعميمات المستحقة (لمرة واحدة أو روتينية)
try {
    $announcementReminders = \App\Core\AnnouncementReminderService::processDue();
    echo '[' . date('Y-m-d H:i:s') . "] announcement reminders: sent=$announcementReminders\n";
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] announcement reminders failed: ' . $e->getMessage() . "\n";
}

// تذكير المدراء بتقييم أداء الفريق (من 1 إلى 4 من كل شهر، مرة واحدة يومياً لكل مدير)
try {
    $kpiReminders = HrService::sendMonthlyKpiEvaluationReminders();
    echo '[' . date('Y-m-d H:i:s') . "] kpi evaluation reminders: sent=$kpiReminders\n";
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] kpi evaluation reminders failed: ' . $e->getMessage() . "\n";
}

