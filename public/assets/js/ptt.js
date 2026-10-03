/* PTT — تسجيل ضغط-وتحدث + تشغيل الرسائل + تأكيد */
(function () {
  'use strict';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  // ===== مشغل الرسائل (مشترك في كل الصفحات) =====
  let currentAudio = null;
  document.querySelectorAll('.ptt-play').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (currentAudio) { currentAudio.pause(); currentAudio = null; }
      const src = btn.dataset.src;
      if (!src) { alert('لا يوجد ملف صوتي'); return; }
      const a = new Audio(src);
      a.preload = 'auto';
      currentAudio = a;
      try {
        await a.play();
        btn.classList.add('playing');
      } catch (e) {
        alert('تعذر تشغيل الصوت: ' + (e.message || 'تحقق من الإذن والاتصال'));
      }
      a.addEventListener('ended', () => { currentAudio = null; btn.classList.remove('playing'); });
      a.addEventListener('error', () => { currentAudio = null; btn.classList.remove('playing'); });
      if (btn.dataset.id) {
        fetch('/ptt/messages/' + btn.dataset.id + '/played', { method: 'POST' }).catch(() => {});
        btn.closest('.ptt-msg')?.classList.remove('p-emergency');
      }
    });
  });
  document.querySelectorAll('.ptt-ack-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      fetch('/ptt/messages/' + btn.dataset.id + '/ack', { method: 'POST' })
        .then(() => { btn.textContent = '✔ تم التأكيد'; btn.disabled = true; })
        .catch(() => {});
    });
  });

  // ===== تسجيل ضغط-وتحدث =====
  const holdBtn = document.getElementById('ptt-hold');
  if (!holdBtn) return;
  const statusEl = document.getElementById('ptt-status');
  const maxSec = parseInt(holdBtn.dataset.max || '60', 10);
  let recorder = null, stream = null, chunks = [], startTs = 0, maxTimer = null, cancelled = false;

  const setStatus = t => { if (statusEl) statusEl.textContent = t; };
  const pickMime = () => {
    const c = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
    if (!window.MediaRecorder) return '';
    return c.find(m => MediaRecorder.isTypeSupported(m)) || '';
  };

  async function startRec(e) {
    e.preventDefault();
    if (recorder) return;
    cancelled = false;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
    } catch (err) {
      setStatus('⚠️ لا يمكن الوصول للميكروفون — تحقق من الإذن');
      return;
    }
    chunks = [];
    recorder = new MediaRecorder(stream, pickMime() ? { mimeType: pickMime() } : undefined);
    recorder.ondataavailable = ev => { if (ev.data.size > 0) chunks.push(ev.data); };
    recorder.onstop = onStop;
    recorder.start(250);
    startTs = Date.now();
    holdBtn.classList.add('recording');
    holdBtn.textContent = '🔴 جارٍ التسجيل… أفلت للإرسال';
    setStatus('جارٍ التسجيل — الحد الأقصى ' + maxSec + ' ثانية');
    maxTimer = setTimeout(() => stopRec(), maxSec * 1000);
  }

  function stopRec(cancel = false) {
    if (!recorder) return;
    cancelled = cancel;
    clearTimeout(maxTimer);
    try { recorder.stop(); } catch (_) {}
  }

  async function onStop() {
    holdBtn.classList.remove('recording');
    holdBtn.textContent = '🎙️ اضغط مطولاً للتحدث';
    stream?.getTracks().forEach(t => t.stop());
    const dur = (Date.now() - startTs) / 1000;
    const mime = recorder.mimeType || 'audio/webm';
    recorder = null; stream = null;
    if (cancelled || dur < 0.4) { setStatus('أُلغي التسجيل'); return; }
    const ext = mime.includes('mp4') ? 'm4a' : (mime.includes('ogg') ? 'ogg' : 'webm');
    const blob = new Blob(chunks, { type: mime });
    setStatus('⏫ جارٍ الإرسال… (' + dur.toFixed(1) + ' ث)');
    await upload(blob, ext, dur);
  }

  function buildFields(dur, ext) {
    return {
      channel_id: holdBtn.dataset.channel || '',
      recipient_type: holdBtn.dataset.recipientType || 'channel',
      recipient_data: holdBtn.dataset.recipientData || '{}',
      reply_to: holdBtn.dataset.replyTo || '',
      duration_sec: dur.toFixed(2),
      priority: document.getElementById('ptt-priority')?.value || 'normal',
      require_ack: document.getElementById('ptt-req-ack')?.checked ? '1' : '',
      require_listen: document.getElementById('ptt-req-listen')?.checked ? '1' : '',
      _ext: ext,
    };
  }

  async function sendBlob(blob, fields) {
    const fd = new FormData();
    fd.append('audio', blob, 'ptt.' + (fields._ext || 'webm'));
    Object.entries(fields).forEach(([k, v]) => { if (k !== '_ext' && v !== '' && v != null) fd.append(k, v); });
    const res = await fetch('/ptt/send', { method: 'POST', body: fd });
    let msg = 'فشل الإرسال';
    try {
      const json = await res.json();
      msg = json.error || 'فشل الإرسال';
      if (json.ok) return json;
    } catch (_) {
      msg = 'خطأ في الخادم: ' + res.status;
    }
    throw new Error(msg);
  }

  async function upload(blob, ext, dur, attempt = 1) {
    const fields = buildFields(dur, ext);
    try {
      await sendBlob(blob, fields);
      setStatus('✔ أُرسلت بنجاح');
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      if (attempt < 3 && navigator.onLine !== false) {
        setStatus('انقطع الاتصال — إعادة محاولة ' + (attempt + 1) + '/3…');
        await new Promise(r => setTimeout(r, 1500 * attempt));
        return upload(blob, ext, dur, attempt + 1);
      }
      // حفظ محلي لإرسالها لاحقاً (Offline Queue)
      if (window.PttOffline?.supported()) {
        await window.PttOffline.save({ blob, fields }).catch(() => {});
        window.PttOffline.refreshBadge?.();
        setStatus('📴 حُفظت محلياً — ستُرسل تلقائياً عند عودة الاتصال');
      } else {
        setStatus('❌ ' + (err.message || 'فشل الإرسال — تحقق من الاتصال'));
      }
    }
  }

  // إعادة إرسال المعلّق عند عودة الاتصال أو عند فتح الصفحة
  async function flushQueue() {
    if (!window.PttOffline?.supported()) return;
    const sent = await window.PttOffline.flush((blob, fields) => sendBlob(blob, fields)).catch(() => 0);
    if (sent > 0) { setStatus('✔ أُرسلت ' + sent + ' رسالة معلّقة'); setTimeout(() => location.reload(), 700); }
    window.PttOffline.refreshBadge?.();
  }
  window.addEventListener('online', flushQueue);
  document.addEventListener('ptt-flush', flushQueue);
  if (holdBtn) flushQueue();

  // ===== وضع الرد الصوتي =====
  document.querySelectorAll('.ptt-reply').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!holdBtn) { alert('لا يمكنك الإرسال في هذه القناة'); return; }
      holdBtn.dataset.recipientType = 'user';
      holdBtn.dataset.recipientData = JSON.stringify({ user_id: btn.dataset.sender });
      holdBtn.dataset.replyTo = btn.dataset.id;
      holdBtn.dataset.channel = '';
      setStatus('↩ تسجيل رد على ' + btn.dataset.name + ' — اضغط مطولاً للتحدث');
      holdBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
      holdBtn.focus();
    });
  });

  // ===== تحليل AI (تفريغ/تلخيص/مهام) =====
  document.querySelectorAll('.ptt-ai').forEach(btn => {
    btn.addEventListener('click', async () => {
      const box = btn.closest('.ptt-msg, .pttc-msg')?.querySelector('.ptt-ai-out');
      btn.disabled = true; btn.textContent = '⏳ جارٍ التحليل…';
      try {
        const res = await fetch('/ptt/messages/' + btn.dataset.id + '/transcribe', { method: 'POST' });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || 'فشل');
        const d = json.data;
        let h = '';
        if (d.transcript) h += '<div><strong>النص:</strong> ' + esc(d.transcript) + '</div>';
        if (d.summary) h += '<div><strong>الملخص:</strong> ' + esc(d.summary) + '</div>';
        if (d.tasks && d.tasks.length) {
          h += '<div><strong>المهام:</strong><ul style="margin:4px 0;padding-right:16px">' +
            d.tasks.map(t => '<li>' + esc(t.title) + (t.assignee ? ' — ' + esc(t.assignee) : '') + (t.due ? ' (' + esc(t.due) + ')' : '') + '</li>').join('') + '</ul></div>';
        }
        if (box) box.innerHTML = h || 'لا نتائج';
        btn.textContent = '🤖 تحليل';
      } catch (e) {
        if (box) box.innerHTML = '<span style="color:#dc2626">' + esc(e.message) + '</span>';
        btn.textContent = '🤖 تحليل';
      } finally {
        btn.disabled = false;
      }
    });
  });

  // أحداث Pointer للمتصفحات الحديثة + أحداث Touch/Mouse احتياطية للأجهزة القديمة
  const startHandler = e => { e.preventDefault(); startRec(e); };
  const stopHandler = () => stopRec();
  const leaveHandler = () => { if (recorder) stopRec(); };

  holdBtn.addEventListener('pointerdown', startHandler);
  holdBtn.addEventListener('pointerup', stopHandler);
  holdBtn.addEventListener('pointerleave', leaveHandler);
  holdBtn.addEventListener('touchstart', startHandler, { passive: false });
  holdBtn.addEventListener('touchend', stopHandler);
  holdBtn.addEventListener('mousedown', startHandler);
  holdBtn.addEventListener('mouseup', stopHandler);
  holdBtn.addEventListener('mouseleave', leaveHandler);
  holdBtn.addEventListener('contextmenu', e => e.preventDefault());

  // ===== حذف رسالة =====
  document.querySelectorAll('.ptt-delete').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('هل تريد حذف هذه الرسالة؟')) return;
      const id = btn.dataset.id;
      const row = btn.closest('.ptt-msg, .pttc-msg');
      try {
        const res = await fetch('/ptt/messages/' + id + '/delete', { method: 'POST' });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || 'فشل الحذف');
        row?.remove();
      } catch (e) {
        alert('فشل الحذف: ' + e.message);
      }
    });
  });
})();
