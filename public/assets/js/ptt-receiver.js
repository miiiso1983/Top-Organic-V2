/* One explicit listening session per browser account; incoming audio stays recipient-scoped. */
(() => {
  'use strict';
  const button = document.getElementById('ptt-receiver-toggle');
  if (!button) return;
  const audio = new Audio();
  let enabled = false, paused = false, cursor = null, queue = [], playing = false, epoch = 0, releaseLock = null;
  const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
  let objectUrl = null, playback = 0;
  const stop = () => {
    enabled = false; epoch++; playback++; queue = []; playing = false; audio.pause(); audio.removeAttribute('src');
    if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = null;
    releaseLock?.(); releaseLock = null;
    button.textContent = 'تشغيل استقبال PTT'; button.setAttribute('aria-pressed', 'false');
  };
  window.PttReceiver = { pause(value) { paused = value; if (value) { playback++; audio.pause(); audio.onended = audio.onerror = null; playing = false; } else playNext(); }, stop };
  async function playNext() {
    if (!enabled || paused || playing || !queue.length) return;
    const message = queue.shift(), version = epoch, playbackId = ++playback;
    playing = true;
    try {
      const response = await fetch(`/ptt/messages/${Number(message.id)}/audio`, { cache: 'no-store' });
      if (!response.ok) throw new Error('الصوت غير متاح');
      const blob = await response.blob();
      if (!enabled || epoch !== version || playbackId !== playback) return;
      if (paused) { playing = false; return; }
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(blob); audio.src = objectUrl;
      button.textContent = `استقبال: ${message.sender_name}`;
      audio.onended = () => {
        if (epoch !== version || playbackId !== playback) return;
        fetch(`/ptt/messages/${Number(message.id)}/played`, { method: 'POST' }).catch(() => {});
        playing = false; button.textContent = 'إيقاف استقبال PTT'; playNext();
      };
      audio.onerror = () => { if (epoch === version && playbackId === playback) { playing = false; playNext(); } };
      await audio.play();
    } catch (error) {
      if (epoch !== version || playbackId !== playback) return;
      playing = false;
      if (error.name === 'NotAllowedError') { stop(); button.textContent = 'اضغط للسماح بتشغيل PTT'; }
      else { button.textContent = 'تعذر تشغيل رسالة — متاحة في الوارد'; setTimeout(playNext, 1000); }
    }
  }
  async function listen() {
    const version = epoch;
    while (enabled && epoch === version) {
      let wait = 3000;
      try {
        const response = await fetch('/ptt/delivery' + (cursor === null ? '' : '?after=' + cursor), { cache: 'no-store' });
        if ([401, 403].includes(response.status) || response.redirected) { stop(); return; }
        const result = await response.json();
        if (!enabled || epoch !== version) return;
        if (!response.ok || !result.ok) throw new Error("Delivery unavailable");
        cursor = result.data.cursor; queue.push(...result.data.messages); playNext();
        if (result.data.has_more) wait = 100;
      } catch (_) { wait = 8000; }
      await delay(wait);
    }
  }
  button.addEventListener('click', async () => {
    if (enabled) { stop(); return; }
    // Unlock audio during the user's gesture, as required by browsers.
    audio.src = 'data:audio/wav;base64,UklGRmQGAABXQVZFZm10IBAAAAABAAEAQB8AAIA+AAACABAAZGF0YUAGAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    try { await audio.play(); } catch (_) {} audio.pause();
    if (!navigator.locks) { button.textContent = 'الاستقبال يتطلب متصفحاً يدعم جلسة صوت حصرية'; return; }
    navigator.locks.request('toporganic-ptt-' + button.dataset.user, { ifAvailable: true }, async lock => {
      if (!lock) { button.textContent = 'الاستقبال يعمل في تبويب آخر'; return; }
      enabled = true; epoch++; cursor = null; button.textContent = 'إيقاف استقبال PTT'; button.setAttribute('aria-pressed', 'true');
      const done = new Promise(resolve => { releaseLock = resolve; }); listen(); await done;
    });
  });
  window.addEventListener('pagehide', stop);
})();
