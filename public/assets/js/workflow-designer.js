/* مصمم Workflow المرئي — Drag & Drop بدون مكتبات خارجية */
(function () {
  'use strict';
  const TYPES = {
    start:     { label: 'بداية',        color: '#16a34a', icon: '▶' },
    approval:  { label: 'موافقة',       color: '#2563eb', icon: '✔' },
    condition: { label: 'شرط',          color: '#d97706', icon: '؟' },
    action:    { label: 'إجراء تلقائي', color: '#7c3aed', icon: '⚡' },
    notify:    { label: 'إشعار',        color: '#0891b2', icon: '🔔' },
    wait:      { label: 'انتظار',       color: '#64748b', icon: '⏳' },
    return:    { label: 'إعادة للتعديل', color: '#ea580c', icon: '↩' },
    reject:    { label: 'رفض نهائي',    color: '#dc2626', icon: '✖' },
    end:       { label: 'نهاية (اعتماد)', color: '#111827', icon: '🏁' },
  };
  const OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'IN', 'NOT IN', 'Contains', 'Starts With', 'Ends With', 'Between', 'is_empty', 'is_not_empty'];
  const canvas = document.getElementById('wfd-canvas');
  const svg = document.getElementById('wfd-svg');
  const propsBody = document.getElementById('wfd-props-body');
  let seq = 0;
  const state = { nodes: [], edges: [], meta: null, selected: null, connect: null };

  const uid = () => 'c' + (++seq) + '_' + Date.now() % 10000;
  const nodeBy = cid => state.nodes.find(n => n.cid === cid);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const toast = msg => { const t = document.getElementById('wfd-toast'); t.textContent = msg; t.style.display = 'block'; setTimeout(() => t.style.display = 'none', 2500); };

  // ===== اللوحة الجانبية =====
  function buildPalette() {
    const pal = document.getElementById('wfd-palette');
    pal.innerHTML = '';
    Object.entries(TYPES).forEach(([type, t]) => {
      const el = document.createElement('div');
      el.className = 'wfd-pal-item';
      el.style.background = t.color;
      el.textContent = t.icon + ' ' + t.label;
      el.draggable = true;
      el.addEventListener('dragstart', e => e.dataTransfer.setData('wfd-type', type));
      pal.appendChild(el);
    });
  }

  // ===== إنشاء وحذف العقد =====
  function addNode(type, x, y, data = {}) {
    const n = { cid: data.cid || uid(), key: data.key || '', type, title: data.title || TYPES[type].label, config: data.config || {}, x: Math.round(x), y: Math.round(y) };
    state.nodes.push(n);
    renderNode(n);
    return n;
  }
  function deleteNode(cid) {
    state.nodes = state.nodes.filter(n => n.cid !== cid);
    state.edges = state.edges.filter(e => e.from !== cid && e.to !== cid);
    if (state.selected === cid) { state.selected = null; renderProps(); }
    renderAll();
  }

  function renderNode(n) {
    let el = document.getElementById('nd-' + n.cid);
    if (!el) {
      el = document.createElement('div');
      el.id = 'nd-' + n.cid;
      el.className = 'wfd-node';
      canvas.appendChild(el);
      el.addEventListener('mousedown', e => startDrag(e, n));
      el.addEventListener('click', e => { e.stopPropagation(); select(n.cid); });
    }
    const t = TYPES[n.type];
    el.style.left = n.x + 'px'; el.style.top = n.y + 'px';
    el.style.borderColor = state.selected === n.cid ? '#2563eb' : t.color;
    el.classList.toggle('selected', state.selected === n.cid);
    let ports = '';
    if (n.type !== 'start') ports += '<div class="wfd-port in" data-cid="' + n.cid + '" data-handle="in" title="مدخل"></div>';
    if (n.type === 'condition') {
      ports += '<div class="wfd-port yes" data-cid="' + n.cid + '" data-handle="yes" title="نعم"></div>';
      ports += '<div class="wfd-port no" data-cid="' + n.cid + '" data-handle="no" title="لا"></div>';
    } else if (!['end', 'reject', 'return'].includes(n.type)) {
      ports += '<div class="wfd-port out" data-cid="' + n.cid + '" data-handle="default" title="مخرج"></div>';
    }
    el.innerHTML = '<div class="wfd-head" style="background:' + t.color + '">' + t.icon + ' ' + esc(t.label) + '</div>' +
      '<div class="wfd-body">' + esc(n.title) + '</div>' + ports;
    el.querySelectorAll('.wfd-port').forEach(p => p.addEventListener('click', e => { e.stopPropagation(); portClick(p.dataset.cid, p.dataset.handle); }));
  }

  // ===== سحب العقد =====
  function startDrag(e, n) {
    if (e.target.classList.contains('wfd-port')) return;
    e.preventDefault();
    const wrap = document.getElementById('wfd-canvas-wrap');
    const rect = canvas.getBoundingClientRect();
    const offX = e.clientX - rect.left + wrap.scrollLeft - n.x;
    const offY = e.clientY - rect.top + wrap.scrollTop - n.y;
    function move(ev) {
      n.x = Math.max(0, Math.round(ev.clientX - rect.left + wrap.scrollLeft - offX));
      n.y = Math.max(0, Math.round(ev.clientY - rect.top + wrap.scrollTop - offY));
      renderNode(n); drawEdges();
    }
    function up() { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); }
    document.addEventListener('mousemove', move);
    document.addEventListener('mouseup', up);
  }

  // ===== إسقاط من اللوحة =====
  const wrapEl = document.getElementById('wfd-canvas-wrap');
  wrapEl.addEventListener('dragover', e => e.preventDefault());
  wrapEl.addEventListener('drop', e => {
    e.preventDefault();
    const type = e.dataTransfer.getData('wfd-type');
    if (!type) return;
    const rect = canvas.getBoundingClientRect();
    const n = addNode(type, e.clientX - rect.left + wrapEl.scrollLeft - 85, e.clientY - rect.top + wrapEl.scrollTop - 25);
    select(n.cid);
  });
  canvas.addEventListener('click', () => select(null));

  // ===== الربط بين العقد =====
  function portClick(cid, handle) {
    if (handle === 'in') {
      if (!state.connect) return;
      const from = state.connect;
      if (from.cid === cid) { state.connect = null; drawEdges(); return; }
      state.edges = state.edges.filter(e => !(e.from === from.cid && e.handle === from.handle));
      state.edges.push({ from: from.cid, to: cid, handle: from.handle, label: from.handle === 'yes' ? 'نعم' : from.handle === 'no' ? 'لا' : '' });
      state.connect = null;
      renderAll();
      return;
    }
    state.connect = { cid, handle };
    document.querySelectorAll('.wfd-port').forEach(p => p.classList.toggle('pending', p.dataset.cid === cid && p.dataset.handle === handle));
    toast(handle === 'yes' ? 'اختر عقدة لمسار «نعم»' : handle === 'no' ? 'اختر عقدة لمسار «لا»' : 'اختر العقدة التالية');
  }

  function edgeMid(a, b) { return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }; }
  function nodePortPos(n, handle) {
    const w = 170, cx = n.x + w / 2;
    if (handle === 'yes') return { x: n.x + w * 0.25, y: n.y + 62 };
    if (handle === 'no') return { x: n.x + w * 0.75, y: n.y + 62 };
    if (handle === 'in') return { x: cx, y: n.y };
    return { x: cx, y: n.y + 62 };
  }

  function drawEdges() {
    svg.innerHTML = '';
    document.querySelectorAll('.wfd-edge-label').forEach(l => l.remove());
    state.edges.forEach((e, i) => {
      const a = nodeBy(e.from), b = nodeBy(e.to);
      if (!a || !b) return;
      const p1 = nodePortPos(a, e.handle), p2 = nodePortPos(b, 'in');
      const midY = (p1.y + p2.y) / 2;
      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', `M ${p1.x} ${p1.y} C ${p1.x} ${midY}, ${p2.x} ${midY}, ${p2.x} ${p2.y}`);
      path.setAttribute('fill', 'none');
      path.setAttribute('stroke', e.handle === 'yes' ? '#16a34a' : e.handle === 'no' ? '#dc2626' : '#64748b');
      path.setAttribute('stroke-width', '2.5');
      path.setAttribute('marker-end', 'url(#wfd-arrow)');
      path.style.pointerEvents = 'stroke';
      path.style.cursor = 'pointer';
      path.addEventListener('click', ev => { ev.stopPropagation(); if (confirm('حذف هذا السهم؟')) { state.edges.splice(i, 1); renderAll(); } });
      svg.appendChild(path);
      if (e.label) {
        const m = edgeMid(p1, p2);
        const lb = document.createElement('div');
        lb.className = 'wfd-edge-label';
        lb.textContent = e.label;
        lb.style.left = m.x + 'px'; lb.style.top = (m.y - 10) + 'px';
        canvas.appendChild(lb);
      }
    });
    const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    defs.innerHTML = '<marker id="wfd-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#475569"/></marker>';
    svg.appendChild(defs);
  }

  function renderAll() {
    document.querySelectorAll('.wfd-node').forEach(el => el.remove());
    state.nodes.forEach(renderNode);
    drawEdges();
    renderProps();
  }

  function select(cid) {
    state.selected = cid;
    state.connect = null;
    state.nodes.forEach(renderNode);
    renderProps();
  }

  // ===== لوحة الخصائص =====
  const APPROVER_TYPE_LABELS = {
    user: 'مستخدم محدد', role: 'دور وظيفي', position: 'منصب تنظيمي',
    direct_manager: 'المدير المباشر', manager_of_manager: 'مدير المدير',
    department_manager: 'مدير القسم', branch_manager: 'مدير الفرع',
    permission: 'من يملك صلاحية', group: 'مجموعة مستخدمين', committee: 'لجنة'
  };

  function opts(list, val, labelFn) {
    return list.map(o => {
      const v = o.id ?? o.code ?? o;
      const l = labelFn ? labelFn(o) : (o.full_name || o.title || o.name || o.code || o);
      return '<option value="' + esc(v) + '"' + (String(v) === String(val) ? ' selected' : '') + '>' + esc(l) + '</option>';
    }).join('');
  }

  function approverPickerHtml(cfg, key) {
    cfg = cfg || { type: 'role' };
    let extra = '';
    const m = state.meta;
    if (cfg.type === 'user') extra = '<label>المستخدم</label><select data-ak="user_id">' + opts(m.users, cfg.user_id) + '</select>';
    else if (cfg.type === 'role') extra = '<label>الدور</label><select data-ak="role_id">' + opts(m.roles, cfg.role_id) + '</select>';
    else if (cfg.type === 'position') extra = '<label>المنصب</label><select data-ak="position_id">' + opts(m.positions, cfg.position_id) + '</select>';
    else if (cfg.type === 'permission') extra = '<label>الصلاحية</label><select data-ak="permission">' + opts(m.permissions, cfg.permission, p => p.code + ' — ' + p.name) + '</select>';
    else if (cfg.type === 'group' || cfg.type === 'committee') {
      const sel = (cfg.user_ids || []).map(String);
      extra = '<label>الأعضاء (Ctrl للتحديد المتعدد)</label><select multiple size="5" data-ak="user_ids">' +
        m.users.map(u => '<option value="' + u.id + '"' + (sel.includes(String(u.id)) ? ' selected' : '') + '>' + esc(u.full_name) + '</option>').join('') + '</select>';
    }
    return '<div class="form-row"><label>نوع المعتمد</label>' +
      '<select data-ak="type" data-akey="' + key + '">' +
      Object.entries(APPROVER_TYPE_LABELS).map(([v, l]) => '<option value="' + v + '"' + (cfg.type === v ? ' selected' : '') + '>' + l + '</option>').join('') +
      '</select></div><div class="form-row" data-aparams="' + key + '">' + extra + '</div>';
  }

  function renderProps() {
    const n = nodeBy(state.selected);
    if (!n || !state.meta) { propsBody.innerHTML = '<span style="color:#9ca3af;font-size:13px">اختر عقدة من المخطط</span>'; return; }
    const c = n.config;
    let h = '<div class="form-row"><label>العنوان</label><input id="p-title" value="' + esc(n.title) + '"></div>';

    if (n.type === 'approval') {
      c.approver = c.approver || { type: 'role', role_id: state.meta.roles[0]?.id };
      h += approverPickerHtml(c.approver, 'approver');
      h += '<div class="form-row"><label>نمط الموافقة</label><select id="p-mode">' +
        opts([{id:'all',name:'الجميع (تسلسلي متوازي)'},{id:'any',name:'أي واحد يكفي'},{id:'count',name:'عدد محدد'}], c.approval_mode || 'all') + '</select></div>';
      h += '<div class="form-row" id="p-count-wrap" style="display:' + (c.approval_mode === 'count' ? 'block' : 'none') + '"><label>العدد المطلوب</label><input type="number" id="p-count" value="' + (c.required_count || 1) + '" min="1"></div>';
      h += '<div class="form-row"><label>عند الرفض</label><select id="p-onreject">' +
        opts([{id:'final',name:'رفض نهائي'},{id:'return_requester',name:'إعادة لمقدم الطلب'},{id:'return_previous',name:'إعادة لمرحلة سابقة'}], c.on_reject || 'final') + '</select></div>';
      // إعدادات SLA والتصعيد
      c.sla = c.sla || {};
      h += '<hr><h4 style="font-size:13px;margin:6px 0">SLA والتصعيد</h4>';
      h += '<div class="form-row"><label>المدة القصوى (ساعة، 0 = بلا حد)</label><input type="number" id="p-sla-max" value="' + (c.sla.max_hours || 0) + '" min="0"></div>';
      h += '<div class="form-row"><label>تذكير بعد (ساعة، 0 = بدون)</label><input type="number" id="p-sla-remind" value="' + (c.sla.remind_after_hours || 0) + '" min="0"></div>';
      h += '<div class="form-row"><label>عند تجاوز المدة</label><select id="p-sla-action">' +
        opts([{id:'escalate',name:'تصعيد للمستوى الأعلى'},{id:'auto_approve',name:'موافقة تلقائية'},{id:'auto_reject',name:'رفض تلقائي'}], c.sla.auto_action || 'escalate') + '</select></div>';
      h += '<div id="p-sla-target-wrap" style="display:' + ((c.sla.auto_action || 'escalate') === 'escalate' ? 'block' : 'none') + '">' +
        '<div class="form-row"><label>يُصعَّد إلى (اختياري)</label>' + approverPickerHtml(c.sla.escalate_to || { type: 'direct_manager' }, 'sla_target') + '</div></div>';
    } else if (n.type === 'condition') {
      c.rule = c.rule && c.rule.conditions ? c.rule : { logic: 'AND', not: false, conditions: [] };
      h += '<div id="rule-root"></div>';
    } else if (n.type === 'action') {
      h += '<div class="form-row"><label>الإجراء</label><select id="p-action"><option value="">— اختر —</option>' + opts(state.meta.actions, c.action) + '</select></div>';
      h += '<div class="form-row"><label>معاملات (JSON)</label><textarea id="p-params" rows="3">' + esc(JSON.stringify(c.params || {})) + '</textarea></div>';
    } else if (n.type === 'notify') {
      c.channels = c.channels || { in_app: 1 };
      c.targets = c.targets || { type: 'role' };
      h += '<div class="form-row"><label>القنوات</label>' +
        ['in_app:داخل التطبيق', 'email:بريد', 'whatsapp:واتساب', 'sms:SMS'].map(pair => {
          const [k, l] = pair.split(':');
          return '<label style="display:block"><input type="checkbox" data-ch="' + k + '"' + (c.channels[k] ? ' checked' : '') + '> ' + l + '</label>';
        }).join('') + '</div>';
      h += approverPickerHtml(c.targets, 'targets');
      h += '<div class="form-row"><label>عنوان الرسالة</label><input id="p-ntitle" value="' + esc(c.title_msg || '') + '"></div>';
      h += '<div class="form-row"><label>نص الرسالة</label><textarea id="p-nbody" rows="2">' + esc(c.body || '') + '</textarea></div>';
    } else if (n.type === 'wait') {
      h += '<div class="form-row"><label>ساعات الانتظار</label><input type="number" id="p-hours" value="' + (c.hours || 24) + '" min="1"></div>';
    }
    h += '<button class="btn btn-sm btn-danger" id="p-del" style="margin-top:10px">حذف العقدة</button>';
    propsBody.innerHTML = h;
    bindProps(n);
    if (n.type === 'condition') renderRuleBuilder(n);
  }

  function bindProps(n) {
    const c = n.config;
    const q = s => propsBody.querySelector(s);
    const on = (sel, ev, fn) => { const el = q(sel); if (el) el.addEventListener(ev, fn); };
    on('#p-title', 'input', e => { n.title = e.target.value; renderNode(n); drawEdges(); });
    on('#p-mode', 'change', e => { c.approval_mode = e.target.value; q('#p-count-wrap').style.display = c.approval_mode === 'count' ? 'block' : 'none'; });
    on('#p-count', 'input', e => c.required_count = +e.target.value || 1);
    on('#p-onreject', 'change', e => c.on_reject = e.target.value);
    on('#p-action', 'change', e => c.action = e.target.value);
    on('#p-params', 'input', e => { try { c.params = JSON.parse(e.target.value || '{}'); } catch (_) {} });
    on('#p-ntitle', 'input', e => c.title_msg = e.target.value);
    on('#p-nbody', 'input', e => c.body = e.target.value);
    on('#p-hours', 'input', e => c.hours = +e.target.value || 24);
    on('#p-sla-max', 'input', e => { c.sla = c.sla || {}; c.sla.max_hours = +e.target.value || 0; });
    on('#p-sla-remind', 'input', e => { c.sla = c.sla || {}; c.sla.remind_after_hours = +e.target.value || 0; });
    on('#p-sla-action', 'change', e => {
      c.sla = c.sla || {}; c.sla.auto_action = e.target.value;
      const w = q('#p-sla-target-wrap'); if (w) w.style.display = e.target.value === 'escalate' ? 'block' : 'none';
    });
    on('#p-del', 'click', () => { if (confirm('حذف العقدة وأسهمها؟')) deleteNode(n.cid); });
    propsBody.querySelectorAll('[data-ch]').forEach(cb => cb.addEventListener('change', () => {
      c.channels = c.channels || {};
      c.channels[cb.dataset.ch] = cb.checked ? 1 : 0;
    }));
    // منتقي المعتمد (موافقة / إشعار / هدف التصعيد)
    propsBody.querySelectorAll('[data-ak="type"]').forEach(sel => sel.addEventListener('change', () => {
      const key = sel.dataset.akey;
      if (key === 'sla_target') { c.sla = c.sla || {}; c.sla.escalate_to = { type: sel.value }; }
      else c[key] = { type: sel.value };
      renderPropsKeep(n);
    }));
    propsBody.querySelectorAll('[data-aparams] select').forEach(sel => sel.addEventListener('change', () => {
      const key = sel.closest('[data-aparams]').dataset.aparams;
      const prop = sel.dataset.ak;
      let target;
      if (key === 'sla_target') { c.sla = c.sla || {}; target = c.sla.escalate_to = c.sla.escalate_to || {}; }
      else target = c[key];
      if (prop === 'user_ids') target[prop] = Array.from(sel.selectedOptions).map(o => +o.value);
      else target[prop] = isNaN(+sel.value) || prop === 'permission' ? sel.value : +sel.value;
    }));
  }
  function renderPropsKeep(n) { const t = n.title; renderProps(); const ti = propsBody.querySelector('#p-title'); if (ti) ti.value = t; }

  // ===== بنّاء الشروط (متداخل) =====
  function groupAt(rule, path) {
    let g = rule;
    if (path) path.split('/').forEach(i => g = g.conditions[+i]);
    return g;
  }
  function renderRuleBuilder(n) {
    const root = propsBody.querySelector('#rule-root');
    const draw = () => {
      root.innerHTML = groupHtml(n.config.rule, '') +
        '<div style="font-size:11px;color:#6b7280">IN/Between: افصل القيم بفاصلة ، مثل 500,5000</div>';
      root.querySelectorAll('[data-rpath]').forEach(el => el.addEventListener('change', onRuleChange));
      root.querySelectorAll('[data-ract]').forEach(el => el.addEventListener('click', onRuleAction));
    };
    const onRuleChange = e => {
      const el = e.target, g = groupAt(n.config.rule, el.dataset.rpath);
      const idx = el.dataset.ridx;
      if (el.dataset.rwhat === 'logic') g.logic = el.value;
      else if (el.dataset.rwhat === 'not') g.not = el.checked;
      else {
        const cond = g.conditions[+idx];
        cond[el.dataset.rwhat] = el.dataset.rwhat === 'value' && !isNaN(+el.value) && el.value.trim() !== '' && !el.value.includes(',') ? +el.value : el.value;
      }
    };
    const onRuleAction = e => {
      e.preventDefault();
      const el = e.target, g = groupAt(n.config.rule, el.dataset.rpath);
      const act = el.dataset.ract, idx = el.dataset.ridx;
      if (act === 'add-cond') g.conditions.push({ field: state.meta.fields[0]?.name || 'amount', op: '=', value: '' });
      else if (act === 'add-group') g.conditions.push({ logic: 'AND', not: false, conditions: [] });
      else if (act === 'del') g.conditions.splice(+idx, 1);
      draw();
    };
    draw();
  }
  function groupHtml(g, path) {
    const kids = g.conditions.map((cond, i) => {
      const p = path ? path + '/' + i : String(i);
      if (cond.conditions) return groupHtml(cond, p) + '<button class="btn wfd-mini btn-danger" data-ract="del" data-rpath="' + path + '" data-ridx="' + i + '">حذف المجموعة</button>';
      return '<div class="wfd-rule-row">' +
        '<select data-rpath="' + path + '" data-ridx="' + i + '" data-rwhat="field">' +
          state.meta.fields.map(f => '<option value="' + esc(f.name) + '"' + (cond.field === f.name ? ' selected' : '') + '>' + esc(f.label) + '</option>').join('') + '</select>' +
        '<select data-rpath="' + path + '" data-ridx="' + i + '" data-rwhat="op" style="width:90px">' +
          OPERATORS.map(o => '<option' + (cond.op === o ? ' selected' : '') + '>' + o + '</option>').join('') + '</select>' +
        '<input data-rpath="' + path + '" data-ridx="' + i + '" data-rwhat="value" value="' + esc(cond.value ?? '') + '" placeholder="القيمة" style="width:100px">' +
        '<button class="btn wfd-mini btn-danger" data-ract="del" data-rpath="' + path + '" data-ridx="' + i + '">×</button></div>';
    }).join('');
    return '<div class="wfd-rule-group">' +
      '<select data-rpath="' + path + '" data-rwhat="logic" style="width:70px">' +
        '<option' + (g.logic === 'AND' ? ' selected' : '') + '>AND</option><option' + (g.logic === 'OR' ? ' selected' : '') + '>OR</option></select>' +
      ' <label style="font-size:11px"><input type="checkbox" data-rpath="' + path + '" data-rwhat="not"' + (g.not ? ' checked' : '') + '> NOT</label>' +
      kids +
      '<div style="margin-top:4px"><button class="btn wfd-mini btn-secondary" data-ract="add-cond" data-rpath="' + path + '">+ شرط</button> ' +
      '<button class="btn wfd-mini btn-secondary" data-ract="add-group" data-rpath="' + path + '">+ مجموعة</button></div></div>';
  }

  // ===== حفظ / تحميل / نشر =====
  function save(cb) {
    const payload = {
      nodes: state.nodes.map(n => ({ client_id: n.cid, node_key: n.key || n.cid, node_type: n.type, title: n.title, config: n.config, pos_x: n.x, pos_y: n.y })),
      edges: state.edges.map(e => ({ from: e.from, to: e.to, handle: e.handle, label: e.label }))
    };
    fetch(WFD.urls.graph, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
      .then(r => r.json()).then(res => {
        if (!res.ok) { toast(res.error || 'فشل الحفظ'); return; }
        toast('تم الحفظ ✔'); if (cb) cb();
      }).catch(() => toast('خطأ اتصال'));
  }
  document.getElementById('btnSave').addEventListener('click', () => save());
  document.getElementById('btnPublish').addEventListener('click', () => save(() => {
    if (!confirm('نشر هذه النسخة؟ ستصبح فعالة فوراً للطلبات الجديدة')) return;
    fetch(WFD.urls.publish, { method: 'POST' }).then(r => r.json()).then(res => {
      if (res.ok) { toast('تم النشر 🎉'); setTimeout(() => location.reload(), 800); }
      else toast(res.error || 'فشل النشر');
    });
  }));

  function boot() {
    buildPalette();
    Promise.all([
      fetch(WFD.urls.graph).then(r => r.json()),
      fetch(WFD.urls.meta).then(r => r.json())
    ]).then(([g, m]) => {
      state.meta = m.data;
      const idMap = {};
      (g.data.nodes || []).forEach(n => {
        const cid = uid(); idMap[n.id] = cid;
        let cfg = {}; try { cfg = JSON.parse(n.config_json || '{}'); } catch (_) {}
        addNode(n.node_type, +n.pos_x, +n.pos_y, { cid, key: n.node_key, title: n.title, config: cfg });
      });
      (g.data.edges || []).forEach(e => {
        if (idMap[e.from_node_id] && idMap[e.to_node_id])
          state.edges.push({ from: idMap[e.from_node_id], to: idMap[e.to_node_id], handle: e.handle, label: e.label || '' });
      });
      if (!state.nodes.length) addNode('start', 400, 40);
      renderAll();
    }).catch(() => toast('تعذر تحميل المخطط'));
  }
  boot();
})();
