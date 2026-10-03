/* PTT Live — بث Walkie-Talkie مباشر عبر LiveKit */
(function () {
  'use strict';
  const cfg = window.PTT_LIVE;
  const LK = (typeof LivekitClient !== 'undefined' ? LivekitClient : (typeof LiveKitClient !== 'undefined' ? LiveKitClient : null));

  const statusEl = document.getElementById('live-status');
  const listenBtn = document.getElementById('live-listen');
  const testBtn = document.getElementById('live-test');
  const broadcastBtn = document.getElementById('live-broadcast');
  const holdBtn = document.getElementById('live-hold');
  const indicator = document.getElementById('live-indicator');
  let room = null, micTrack = null;

  const setStatus = (t, isError = false) => {
    if (!statusEl) return;
    statusEl.textContent = t;
    statusEl.style.color = isError ? '#dc2626' : '#6b7280';
    statusEl.style.fontWeight = isError ? '700' : '400';
  };

  window.__pttLiveLoaded = true;
  console.log('[PTT-Live] v2 loaded. cfg=', cfg, 'LK=', LK ? 'yes' : 'no');

  if (!cfg) return;
  if (!LK) {
    setStatus('❌ لم تحمل مكتبة LiveKit — تأكد من الاتصال بالإنترنت', true);
    console.error('LiveKit client not loaded');
    if (broadcastBtn) broadcastBtn.disabled = true;
    if (listenBtn) listenBtn.disabled = true;
    return;
  }

  async function getToken(publish) {
    const fd = new FormData();
    if (publish) fd.append('publish', '1');
    const res = await fetch('/ptt/channels/' + cfg.channel + '/live/token', { method: 'POST', body: fd });
    const j = await res.json();
    if (!j.ok) throw new Error(j.error || 'فشل الحصول على التوكن');
    return j.data;
  }

  async function join(publish) {
    setStatus('⏳ جارٍ الاتصال…');
    const data = await getToken(publish);
    // تأكد من استخدام WebSocket بدلاً من HTTP إذا أُدخلت الإعدادات بشكل خاطئ
    if (data.url) {
      data.url = data.url.replace(/^https:\/\//i, 'wss://').replace(/^http:\/\//i, 'ws://');
    }
    room = new LK.Room({ audioCaptureDefaults: { echoCancellation: true, noiseSuppression: true } });
    const currentRoom = room; // مرجع ثابت لتفادي null بعد cleanup

    room.on('trackSubscribed', track => {
      if (track.kind === 'audio') {
        const el = track.attach();
        el.autoplay = true;
        el.style.display = 'none';
        document.body.appendChild(el);
      }
    });
    room.on('participantConnected', p => setStatus('متصل — ' + (currentRoom.remoteParticipants.size + 1) + ' مشارك'));
    room.on('participantDisconnected', () => setStatus('متصل — ' + (currentRoom.remoteParticipants.size + 1) + ' مشارك'));
    room.on('disconnected', (reason) => {
      console.log('[PTT-Live] disconnected reason:', reason);
      setStatus('غير متصل' + (reason ? ' — ' + reason : ''));
      if (indicator) indicator.style.display = 'none';
      cleanup();
    });

    try {
      await currentRoom.connect(data.url, data.token);
    } catch (e) {
      console.error('[PTT-Live] connect error', e);
      setStatus('❌ فشل الاتصال: ' + (e.message || 'LiveKit'), true);
      cleanup();
      throw e;
    }
    if (currentRoom.connectionState !== 'connected') {
      cleanup();
      throw new Error('تم فصل الاتصال قبل إعداد البث');
    }
    if (indicator) indicator.style.display = 'inline';
    setStatus('متصل — ' + (currentRoom.remoteParticipants.size + 1) + ' مشارك');

    if (publish && data.can_publish) {
      micTrack = await LK.createLocalAudioTrack();
      await currentRoom.localParticipant.publishTrack(micTrack);
      await micTrack.mute(); // وضع Walkie: مكتوم حتى الضغط
      setStatus('✅ متصل كباثث — اضغط مطولاً على الزر الأحمر للتحدث');
    }
    return data;
  }

  function cleanup() {
    micTrack = null;
    room = null;
    if (holdBtn) holdBtn.style.display = 'none';
    if (broadcastBtn) { broadcastBtn.style.display = ''; broadcastBtn.disabled = false; broadcastBtn.textContent = 'بدء البث المباشر (Walkie-Talkie)'; }
    if (listenBtn) listenBtn.disabled = false;
  }

  function setLoading(btn, loading) {
    if (!btn) return;
    btn.disabled = loading;
    const original = btn.dataset.original || btn.textContent;
    if (!btn.dataset.original) btn.dataset.original = original;
    btn.textContent = loading ? '⏳ جارٍ الاتصال…' : original;
  }

  // زر اختبار الإعدادات والتوكن (بدون فتح غرفة)
  testBtn?.addEventListener('click', async () => {
    console.log('[PTT-Live] test button clicked');
    testBtn.disabled = true;
    setStatus('⏳ جارٍ فحص إعدادات LiveKit…');
    try {
      const res = await fetch('/ptt/channels/' + cfg.channel + '/live/token', { method: 'POST' });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'فشل الفحص');
      setStatus('✅ LiveKit جاهز — URL: ' + json.data.url + ' — canPublish: ' + (json.data.can_publish ? 'yes' : 'no'));
    } catch (e) {
      setStatus('❌ ' + e.message, true);
    }
    testBtn.disabled = false;
  });

  // استماع
  listenBtn?.addEventListener('click', async () => {
    setLoading(listenBtn, true);
    try { await join(false); } catch (e) { setStatus('❌ ' + e.message, true); setLoading(listenBtn, false); }
  });

  // بث مع وضع Walkie (مكتوم حتى الضغط)
  broadcastBtn?.addEventListener('click', async () => {
    setLoading(broadcastBtn, true);
    try {
      await join(true);
      broadcastBtn.style.display = 'none';
      holdBtn.style.display = 'block';
      setLoading(broadcastBtn, false);
    } catch (e) {
      setStatus('❌ ' + e.message, true);
      setLoading(broadcastBtn, false);
    }
  });

  async function talking(on) {
    if (!micTrack) return;
    try {
      if (on) { await micTrack.unmute(); holdBtn.classList.add('recording'); holdBtn.textContent = '🔴 تتحدث الآن…'; }
      else { await micTrack.mute(); holdBtn.classList.remove('recording'); holdBtn.textContent = '🎙️ اضغط مطولاً للبث الحي'; }
    } catch (_) {}
  }
  ['pointerdown', 'touchstart'].forEach(ev => {
    holdBtn?.addEventListener(ev, e => { e.preventDefault(); talking(true); });
  });
  ['pointerup', 'pointerleave', 'touchend'].forEach(ev => {
    holdBtn?.addEventListener(ev, () => talking(false));
  });
  holdBtn?.addEventListener('contextmenu', e => e.preventDefault());

  // إنهاء عند مغادرة الصفحة
  window.addEventListener('beforeunload', () => {
    if (room) {
      room.disconnect();
      navigator.sendBeacon('/ptt/channels/' + cfg.channel + '/live/stop');
    }
  });
})();
