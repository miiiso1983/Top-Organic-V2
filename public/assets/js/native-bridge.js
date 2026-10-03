/* جسر Capacitor — يعمل فقط داخل التطبيق الأصلي (Android/iOS):
   1) طلب إذن الميكروفون لـ PTT
   2) تسجيل FCM token وربطه بحساب المستخدم عبر /push/subscribe */
(function () {
  'use strict';
  const Cap = window.Capacitor;
  if (!Cap || typeof Cap.isNativePlatform !== 'function' || !Cap.isNativePlatform()) return;

  document.addEventListener('DOMContentLoaded', async () => {
    const platform = Cap.getPlatform(); // android | ios

    // 1) إذن الميكروفون (قناة PTT تستخدم getUserMedia داخل WebView)
    try {
      const Mic = Cap.Plugins.Microphone;
      if (Mic && Mic.checkPermissions) {
        const p = await Mic.checkPermissions();
        if (p.microphone !== 'granted') await Mic.requestPermissions();
      }
    } catch (e) { /* plugin غير متاح — WebView قد يطلب الإذن بنفسه */ }

    // 2) إشعارات FCM
    try {
      const PN = Cap.Plugins.PushNotifications;
      if (!PN) return;
      let perm = await PN.checkPermissions();
      if (perm.receive === 'prompt') perm = await PN.requestPermissions();
      if (perm.receive !== 'granted') return;

      PN.addListener('registration', async (token) => {
        try {
          await fetch('/push/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ endpoint: token.value, platform, keys: {} })
          });
        } catch (e) {}
      });

      // إشعار وصل والتطبيق مفتوح — حدّث عدّاد الجرس فوراً
      PN.addListener('pushNotificationReceived', () => {
        document.dispatchEvent(new Event('ntf-refresh'));
      });

      // المستخدم ضغط الإشعار — افتح الرابط داخل التطبيق (افتراضياً PTT للرسائل الصوتية)
      PN.addListener('pushNotificationActionPerformed', (action) => {
        let link = action.notification && action.notification.data && action.notification.data.link;
        if (!link && action.notification && action.notification.body && /رسالة صوتية/.test(action.notification.body)) {
          link = '/ptt';
        }
        if (link) window.location.href = link;
      });

      await PN.register();
    } catch (e) { /* FCM غير مضبوط بعد (google-services.json) — تجاهل */ }
  });
})();
