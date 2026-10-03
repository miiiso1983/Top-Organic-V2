<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-satellite-dish"></i> إعدادات الإشعارات والبث الفوري</h2>
</div>

<form method="post" action="/admin/push-settings">
<div class="card">
  <h3>Firebase Cloud Messaging (FCM) — إشعارات الموبايل</h3>
  <p style="color:#6b7280;font-size:13px">
    الحالة: <?= $fcmConfigured ? '<span class="badge badge-success">مضبوط ✔</span>' : '<span class="badge badge-warning">غير مضبوط</span>' ?>
    — أنشئ مشروعاً في <a href="https://console.firebase.google.com" target="_blank">Firebase Console</a> ثم ألصق إحدى الطريقتين:
  </p>
  <div class="form-row" style="min-width:100%">
    <label>Service Account JSON (الموصى به — HTTP v1)</label>
    <textarea name="fcm_service_account_json" rows="6" placeholder='{"type":"service_account","project_id":"...","private_key":"...","client_email":"..."}' style="direction:ltr;font-family:monospace;font-size:11px"><?= Helpers::e($s['fcm_service_account_json']) ?></textarea>
    <small style="color:#6b7280">من: Project Settings ← Service Accounts ← Generate new private key</small>
  </div>
  <div class="form-row" style="min-width:100%">
    <label>أو Server Key (القديم — Legacy)</label>
    <input name="fcm_server_key" value="<?= Helpers::e($s['fcm_server_key']) ?>" placeholder="AAAA..." style="direction:ltr">
  </div>
</div>

<div class="card">
  <h3>LiveKit — البث الصوتي المباشر (PTT Walkie-Talkie)</h3>
  <p style="color:#6b7280;font-size:13px">
    أنشئ حساباً في <a href="https://cloud.livekit.io" target="_blank">LiveKit Cloud</a> (طبقة مجانية كافية للمطاعم) أو استضفه ذاتياً، ثم ألصق بيانات المشروع:
  </p>
  <div class="form-row"><label>WebSocket URL</label>
    <input name="livekit_url" value="<?= Helpers::e($s['livekit_url']) ?>" placeholder="wss://your-project.livekit.cloud" style="direction:ltr">
  </div>
  <div class="form-row"><label>API Key</label>
    <input name="livekit_api_key" value="<?= Helpers::e($s['livekit_api_key']) ?>" style="direction:ltr">
  </div>
  <div class="form-row"><label>API Secret</label>
    <input name="livekit_api_secret" value="<?= Helpers::e($s['livekit_api_secret']) ?>" style="direction:ltr">
  </div>
</div>

<div class="card">
  <h3>Web Push — إشعارات المتصفح (VAPID)</h3>
  <p style="color:#6b7280;font-size:13px">المفتاح العام (يُولّد تلقائياً عند تفعيل Web Push):</p>
  <code style="font-size:11px;word-break:break-all;display:block;direction:ltr"><?= Helpers::e($s['vapid_public'] ?: '— لم يُولّد بعد —') ?></code>
</div>

<button class="btn btn-success btn-lg">حفظ كل الإعدادات</button>
</form>

<div class="card" style="margin-top:14px">
  <h3>جدولة التصعيد وإرسال الإشعارات (Cron)</h3>
  <p style="color:#6b7280;font-size:13px">لإرسال الإشعارات المعلّقة في الطابور، شغّل هذا المسار كل 5 دقائق:</p>

  <p style="color:#6b7280;font-size:13px;margin-top:8px"><strong>1) طريقة cURL (URL كامل):</strong></p>
  <code style="font-size:11px;word-break:break-all;display:block;direction:ltr">*/5 * * * * curl -s "https://<?= $_SERVER['HTTP_HOST'] ?? 'example.com' ?><?= Helpers::e($cronUrl) ?>" >> /dev/null 2>&1</code>
  <p style="color:#6b7280;font-size:13px;margin-top:8px">في Cloudways اختر Type = <strong>cURL</strong> والصق الرابط فقط.</p>

  <p style="color:#6b7280;font-size:13px;margin-top:8px"><strong>2) طريقة PHP (بدون توكن):</strong></p>
  <code style="font-size:11px;word-break:break-all;display:block;direction:ltr">*/5 * * * * php <?= Helpers::e(BASE_PATH) ?>/scripts/workflow_cron.php >> /dev/null 2>&1</code>
  <p style="color:#6b7280;font-size:13px;margin-top:8px">في Cloudways اختر Type = <strong>PHP</strong> والصق المسار النسبي: <code style="font-size:11px">scripts/workflow_cron.php</code> (إذا كان المشروع في مجلد فرعي أضفه: <code style="font-size:11px">restaurant-system/scripts/workflow_cron.php</code>).</p>
</div>

<div class="card" style="margin-top:14px">
  <h3>الاختبار</h3>
  <p style="color:#6b7280;font-size:13px">احفظ أولاً، وبعد تسجيل جهازك من التطبيق أرسل إشعاراً تجريبياً:</p>
  <form method="post" action="/admin/push-settings/test">
    <button class="btn btn-secondary">إرسال إشعار تجريبي لحسابي</button>
  </form>
</div>
