/* معالج إنشاء سريع لمسار الموافقات — يبني العقد/الأسهم تلقائياً من إجابات بسيطة
   ويحفظها مباشرة عبر نفس نقطة حفظ المصمم المرئي (WFD.urls.graph)، دون المساس
   بمنطق السحب والإفلات الموجود في workflow-designer.js */
(function () {
  'use strict';
  if (typeof WFD === 'undefined') return;

  const APPROVER_TYPE_LABELS = {
    direct_manager: 'المدير المباشر', manager_of_manager: 'مدير المدير',
    department_manager: 'مدير القسم', branch_manager: 'مدير الفرع',
    role: 'دور وظيفي محدد', user: 'شخص محدد', position: 'منصب تنظيمي محدد',
    permission: 'كل من يملك صلاحية معينة', group: 'مجموعة أشخاص (أي واحد يكفي بينهم)',
  };

  let meta = null; // تُحمَّل عند أول فتح للمعالج
  let seq = 0;
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  function loadMeta() {
    if (meta) return Promise.resolve(meta);
    return fetch(WFD.urls.meta).then(r => r.json()).then(res => { meta = res.data; return meta; });
  }

  // ===== بناء صف "خطوة اعتماد" واحدة =====
  function stepRowHtml(cid) {
    const typeOpts = Object.entries(APPROVER_TYPE_LABELS).map(([v, l]) => '<option value="' + v + '">' + l + '</option>').join('');
    return '<div class="wfw-step" data-cid="' + cid + '">' +
      '<select class="wfw-step-type">' + typeOpts + '</select>' +
      '<span class="wfw-step-value"></span>' +
      '<select class="wfw-step-mode" title="نمط القرار إذا تعدد المعتمدون">' +
        '<option value="all">يلزم موافقة الجميع</option><option value="any">يكفي أي واحد</option>' +
      '</select>' +
      '<button type="button" class="btn btn-sm btn-danger wfw-step-del">×</button>' +
      '</div>';
  }

  function renderStepValue(row) {
    const type = row.querySelector('.wfw-step-type').value;
    const holder = row.querySelector('.wfw-step-value');
    if (type === 'role') {
      holder.innerHTML = '<select class="wfw-val-single">' + meta.roles.map(r => '<option value="' + r.id + '">' + esc(r.name) + '</option>').join('') + '</select>';
    } else if (type === 'user') {
      holder.innerHTML = '<select class="wfw-val-single">' + meta.users.map(u => '<option value="' + u.id + '">' + esc(u.full_name) + '</option>').join('') + '</select>';
    } else if (type === 'position') {
      holder.innerHTML = '<select class="wfw-val-single">' + meta.positions.map(p => '<option value="' + p.id + '">' + esc(p.title) + '</option>').join('') + '</select>';
    } else if (type === 'permission') {
      holder.innerHTML = '<select class="wfw-val-single">' + meta.permissions.map(p => '<option value="' + esc(p.code) + '">' + esc(p.code + ' — ' + p.name) + '</option>').join('') + '</select>';
    } else if (type === 'group') {
      holder.innerHTML = '<select class="wfw-val-multi" multiple size="4" style="min-width:180px">' +
        meta.users.map(u => '<option value="' + u.id + '">' + esc(u.full_name) + '</option>').join('') + '</select>';
    } else {
      holder.innerHTML = ''; // أنواع المستوى الإداري لا تحتاج قيمة إضافية
    }
  }

  function bindStepRow(row) {
    row.querySelector('.wfw-step-type').addEventListener('change', () => renderStepValue(row));
    row.querySelector('.wfw-step-del').addEventListener('click', () => row.remove());
    renderStepValue(row);
  }

  function addStepRow(container) {
    const div = document.createElement('div');
    div.innerHTML = stepRowHtml('s' + (++seq));
    const row = div.firstElementChild;
    container.appendChild(row);
    bindStepRow(row);
    return row;
  }

  /** يقرأ كل خطوات سلسلة اعتماد من حاوية ويعيد [{approver:{...}, approval_mode}] أو null عند خطأ تحقق */
  function readChain(container) {
    const steps = [];
    for (const row of container.querySelectorAll('.wfw-step')) {
      const type = row.querySelector('.wfw-step-type').value;
      const mode = row.querySelector('.wfw-step-mode').value;
      const approver = { type };
      if (type === 'role') approver.role_id = +row.querySelector('.wfw-val-single').value;
      else if (type === 'user') approver.user_id = +row.querySelector('.wfw-val-single').value;
      else if (type === 'position') approver.position_id = +row.querySelector('.wfw-val-single').value;
      else if (type === 'permission') approver.permission = row.querySelector('.wfw-val-single').value;
      else if (type === 'group') {
        approver.type = 'group';
        approver.user_ids = Array.from(row.querySelector('.wfw-val-multi').selectedOptions).map(o => +o.value);
        if (!approver.user_ids.length) { alert('اختر عضواً واحداً على الأقل في خطوة "مجموعة أشخاص"'); return null; }
      }
      steps.push({ approver, approval_mode: mode });
    }
    if (!steps.length) { alert('أضف خطوة اعتماد واحدة على الأقل'); return null; }
    return steps;
  }

  // ===== بناء عقد/أسهم مسار كامل من سلسلة خطوات، تبدأ من عقدة معيّنة وتنتهي بعقدة End =====
  function buildChainGraph(steps, startX, startY) {
    const nodes = [];
    const edges = [];
    let prevCid = null;
    let y = startY;
    steps.forEach((step, i) => {
      const cid = 'n' + (++seq);
      nodes.push({
        client_id: cid, node_key: cid, node_type: 'approval',
        title: 'اعتماد ' + (i + 1) + ' — ' + (APPROVER_TYPE_LABELS[step.approver.type] || step.approver.type),
        config: { approver: step.approver, approval_mode: step.approval_mode, on_reject: 'return_requester' },
        pos_x: startX, pos_y: y,
      });
      if (prevCid) edges.push({ from: prevCid, to: cid, handle: 'default', label: '' });
      prevCid = cid;
      y += 150;
    });
    const endCid = 'n' + (++seq);
    nodes.push({ client_id: endCid, node_key: endCid, node_type: 'end', title: 'نهاية (اعتماد)', config: {}, pos_x: startX, pos_y: y });
    edges.push({ from: prevCid, to: endCid, handle: 'default', label: '' });
    return { nodes, edges, firstCid: nodes[0].client_id, lastY: y };
  }

  // ===== نمط 1: مسار واحد لكل الطلبات =====
  function generateSingleChain() {
    const steps = readChain(document.getElementById('wfwChainSteps'));
    if (!steps) return null;
    const startCid = 'n' + (++seq);
    const nodes = [{ client_id: startCid, node_key: startCid, node_type: 'start', title: 'بداية', config: {}, pos_x: 400, pos_y: 40 }];
    const edges = [];
    const chain = buildChainGraph(steps, 400, 190);
    nodes.push(...chain.nodes);
    edges.push(...chain.edges, { from: startCid, to: chain.firstCid, handle: 'default', label: '' });
    return { nodes, edges };
  }

  // ===== نمط 2: مسارات متعددة حسب نطاق المبلغ =====
  function generateAmountBased() {
    const field = document.getElementById('wfwAmountField').value;
    if (!field) { alert('اختر حقل المبلغ'); return null; }
    const bandEls = Array.from(document.querySelectorAll('.wfw-band'));
    if (bandEls.length < 1) { alert('أضف نطاق مبلغ واحداً على الأقل'); return null; }
    const bands = [];
    for (const el of bandEls) {
      const thresholdInput = el.querySelector('.wfw-band-threshold');
      const isLast = el === bandEls[bandEls.length - 1];
      const threshold = isLast ? null : parseFloat(thresholdInput.value);
      if (!isLast && (isNaN(threshold) || threshold <= 0)) { alert('أدخل حد أعلى صحيح لكل نطاق (ما عدا النطاق الأخير المفتوح)'); return null; }
      const steps = readChain(el.querySelector('.wfw-band-steps'));
      if (!steps) return null;
      bands.push({ threshold, steps });
    }
    // يجب أن تكون الحدود تصاعدية
    for (let i = 1; i < bands.length - 1; i++) {
      if (bands[i].threshold !== null && bands[i].threshold <= bands[i - 1].threshold) {
        alert('يجب أن تكون نطاقات المبلغ تصاعدية (كل نطاق أكبر من الذي قبله)');
        return null;
      }
    }

    const startCid = 'n' + (++seq);
    const nodes = [{ client_id: startCid, node_key: startCid, node_type: 'start', title: 'بداية', config: {}, pos_x: 400, pos_y: 40 }];
    const edges = [];
    let condY = 160;
    let prevConditionCid = null;
    bands.forEach((band, i) => {
      const chainX = 650 + i * 230;
      const chain = buildChainGraph(band.steps, chainX, 160);
      nodes.push(...chain.nodes);
      edges.push(...chain.edges);

      if (band.threshold === null) {
        // النطاق الأخير المفتوح: يُصل إليه مباشرة عبر "لا" من آخر شرط، أو من البداية إن كان النطاق الوحيد
        if (prevConditionCid) edges.push({ from: prevConditionCid, to: chain.firstCid, handle: 'no', label: 'لا' });
        else edges.push({ from: startCid, to: chain.firstCid, handle: 'default', label: '' });
      } else {
        const condCid = 'n' + (++seq);
        nodes.push({
          client_id: condCid, node_key: condCid, node_type: 'condition',
          title: 'المبلغ ≤ ' + band.threshold,
          config: { rule: { logic: 'AND', not: false, conditions: [{ field, op: '<=', value: band.threshold }] } },
          pos_x: 400, pos_y: condY,
        });
        if (prevConditionCid) edges.push({ from: prevConditionCid, to: condCid, handle: 'no', label: 'لا' });
        else edges.push({ from: startCid, to: condCid, handle: 'default', label: '' });
        edges.push({ from: condCid, to: chain.firstCid, handle: 'yes', label: 'نعم' });
        prevConditionCid = condCid;
        condY += 150;
      }
    });
    return { nodes, edges };
  }

  // ===== عناصر النطاقات (نمط المبلغ) =====
  function bandHtml(cid) {
    return '<div class="wfw-band" data-cid="' + cid + '">' +
      '<div class="wfw-band-head"><label style="font-size:13px">حتى مبلغ</label>' +
      '<input type="number" class="wfw-band-threshold" placeholder="مثال: 500000" style="width:140px">' +
      '<span class="wfw-hint wfw-band-last-note" style="display:none">(النطاق الأخير مفتوح بدون حد أعلى)</span>' +
      '<button type="button" class="btn btn-sm btn-danger wfw-band-del" style="margin-right:auto">حذف النطاق</button></div>' +
      '<div class="wfw-band-steps"></div>' +
      '<button type="button" class="btn btn-sm btn-secondary wfw-band-addstep">+ إضافة خطوة اعتماد لهذا النطاق</button>' +
      '</div>';
  }

  function refreshBandLastNote() {
    const bands = document.querySelectorAll('.wfw-band');
    bands.forEach((b, i) => {
      const isLast = i === bands.length - 1;
      b.querySelector('.wfw-band-threshold').style.display = isLast ? 'none' : 'inline-block';
      b.querySelector('.wfw-band-last-note').style.display = isLast ? 'inline' : 'none';
    });
  }

  function addBand() {
    const container = document.getElementById('wfwBands');
    const div = document.createElement('div');
    div.innerHTML = bandHtml('b' + (++seq));
    const band = div.firstElementChild;
    container.appendChild(band);
    const stepsHolder = band.querySelector('.wfw-band-steps');
    addStepRow(stepsHolder);
    band.querySelector('.wfw-band-addstep').addEventListener('click', () => addStepRow(stepsHolder));
    band.querySelector('.wfw-band-del').addEventListener('click', () => { band.remove(); refreshBandLastNote(); });
    refreshBandLastNote();
  }

  function populateAmountFieldSelect() {
    const sel = document.getElementById('wfwAmountField');
    sel.innerHTML = meta.fields.map(f => '<option value="' + esc(f.name) + '">' + esc(f.label) + '</option>').join('');
    const amountField = meta.fields.find(f => f.name === 'amount');
    if (amountField) sel.value = 'amount';
  }

  // ===== فتح/إغلاق المعالج =====
  function openWizard() {
    loadMeta().then(() => {
      document.getElementById('wfwChainSteps').innerHTML = '';
      addStepRow(document.getElementById('wfwChainSteps'));
      document.getElementById('wfwBands').innerHTML = '';
      addBand();
      populateAmountFieldSelect();
      document.querySelector('input[name="wfwMode"][value="chain"]').checked = true;
      document.getElementById('wfwChainOnly').style.display = '';
      document.getElementById('wfwAmountOnly').style.display = 'none';
      document.getElementById('wfwOverlay').classList.add('active');
    }).catch(() => alert('تعذر تحميل بيانات المعالج'));
  }
  function closeWizard() { document.getElementById('wfwOverlay').classList.remove('active'); }

  function onGenerate() {
    const mode = document.querySelector('input[name="wfwMode"]:checked').value;
    const graph = mode === 'amount' ? generateAmountBased() : generateSingleChain();
    if (!graph) return;
    if (!confirm('سيتم استبدال المخطط الحالي بالكامل بهذا المخطط الجديد. هل تريد المتابعة؟')) return;
    fetch(WFD.urls.graph, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(graph) })
      .then(r => r.json()).then(res => {
        if (!res.ok) { alert(res.error || 'فشل الحفظ'); return; }
        closeWizard();
        location.reload();
      }).catch(() => alert('خطأ اتصال أثناء الحفظ'));
  }

  document.getElementById('btnWizard').addEventListener('click', openWizard);
  document.getElementById('wfwClose').addEventListener('click', closeWizard);
  document.getElementById('wfwCancel').addEventListener('click', closeWizard);
  document.getElementById('wfwAddStep').addEventListener('click', () => addStepRow(document.getElementById('wfwChainSteps')));
  document.getElementById('wfwAddBand').addEventListener('click', addBand);
  document.getElementById('wfwGenerate').addEventListener('click', onGenerate);
  document.querySelectorAll('input[name="wfwMode"]').forEach(r => r.addEventListener('change', () => {
    const isAmount = document.querySelector('input[name="wfwMode"]:checked').value === 'amount';
    document.getElementById('wfwChainOnly').style.display = isAmount ? 'none' : '';
    document.getElementById('wfwAmountOnly').style.display = isAmount ? '' : 'none';
  }));
})();
