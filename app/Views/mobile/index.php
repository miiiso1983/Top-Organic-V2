<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" id="html-root">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title><?= Helpers::e($title) ?></title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif; margin: 0; background: #f3f4f6; color: #111; }
    .app { max-width: 480px; margin: 0 auto; min-height: 100vh; background: #fff; }
    header { background: #1B5E20; color: #fff; padding: 16px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 10; }
    header .app-title { flex: 1; text-align: center; font-weight: 700; }
    #lang-switch { background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.4); border-radius: 8px; padding: 6px 10px; font-size: 13px; width: auto; }
    .tabs { display: flex; border-bottom: 1px solid #e5e7eb; background: #fff; overflow-x: auto; }
    .tab { flex: 1 0 auto; min-width: 70px; padding: 12px 6px; text-align: center; font-size: 12px; background: none; border: none; color: #6b7280; white-space: nowrap; }
    .tab.active { color: #1B5E20; border-bottom: 2px solid #1B5E20; font-weight: 700; }
    .panel { display: none; padding: 16px; }
    .panel.active { display: block; }
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .btn { display: block; width: 100%; padding: 14px; border: none; border-radius: 10px; background: #1B5E20; color: #fff; font-size: 16px; margin: 8px 0; }
    .btn.secondary { background: #374151; }
    .btn.danger { background: #dc2626; }
    .btn.small { padding: 8px; font-size: 13px; margin: 4px 0; }
    .btn:disabled { background: #9ca3af; }
    .form-group { margin-bottom: 12px; }
    label { display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px; }
    input, select, textarea { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; }
    .status-badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 12px; background: #e5e7eb; }
    .loading { text-align: center; padding: 20px; color: #6b7280; }
    .error { color: #dc2626; font-size: 14px; margin-top: 8px; }
    .success { color: #16a34a; font-size: 14px; margin-top: 8px; }
    .kpi-row { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
    .list-link { display: block; padding: 10px 0; border-bottom: 1px solid #f3f4f6; color: #1B5E20; }
    .section-title { font-weight: 700; margin: 10px 0 6px; }
  </style>
</head>
<body>
<div class="app">
  <header>
    <button id="lang-switch" onclick="toggleLang()">EN</button>
    <div class="app-title">Top Organic Mobile</div>
    <span style="width:40px"></span>
  </header>
  <div class="tabs" id="tabs-bar">
    <button class="tab active" data-tab="attendance" onclick="showTab('attendance')" data-i18n="tab_attendance"></button>
    <button class="tab" data-tab="tasks" onclick="showTab('tasks')" data-i18n="tab_tasks"></button>
    <button class="tab" data-tab="approvals" onclick="showTab('approvals')" data-i18n="tab_approvals"></button>
    <button class="tab" data-tab="requests" onclick="showTab('requests')" data-i18n="tab_requests"></button>
    <?php if (!empty($is_manager)): ?>
    <button class="tab" data-tab="team" onclick="showTab('team')" data-i18n="tab_team"></button>
    <?php endif; ?>
    <?php if (!empty($is_housing_guard)): ?>
    <button class="tab" data-tab="housing" onclick="showTab('housing')" data-i18n="tab_housing"></button>
    <?php endif; ?>
    <button class="tab" data-tab="profile" onclick="showTab('profile')" data-i18n="tab_profile"></button>
  </div>

  <div id="attendance" class="panel active"></div>
  <div id="tasks" class="panel"></div>
  <div id="approvals" class="panel"></div>
  <div id="requests" class="panel"></div>
  <?php if (!empty($is_manager)): ?>
  <div id="team" class="panel"></div>
  <?php endif; ?>
  <?php if (!empty($is_housing_guard)): ?>
  <div id="housing" class="panel"></div>
  <?php endif; ?>
  <div id="profile" class="panel"></div>
</div>

<script>
const TOKEN = <?= json_encode($api_token) ?>;
const IS_MANAGER = <?= json_encode(!empty($is_manager)) ?>;
const IS_HOUSING_GUARD = <?= json_encode(!empty($is_housing_guard)) ?>;

/* ===================== Localization (AR/EN) ===================== */
const I18N = {
  ar: {
    tab_attendance: 'الحضور', tab_tasks: 'المهام', tab_approvals: 'الموافقات', tab_requests: 'الطلبات',
    tab_team: 'فريقي', tab_housing: 'السكن', tab_profile: 'حسابي',
    loading: 'جاري التحميل...', no_data: 'لا توجد بيانات', error_generic: 'خطأ',
    att_title: 'حالة الحضور اليوم', att_in: 'دخول', att_out: 'خروج', att_branch: 'الفرع',
    att_radius: 'نطاق', att_general_zone: 'النطاق العام', att_no_geofence: 'لم يُضبط النطاق الجغرافي للفرع/النظام. راجع إدارة الموارد البشرية.',
    btn_clock_in: 'تسجيل حضور', btn_clock_out: 'تسجيل انصراف', getting_location: 'جاري تحديد الموقع...',
    gps_unsupported: 'GPS غير مدعوم', weak_accuracy: 'دقة الموقع ضعيفة', retry_open_area: 'أعد المحاولة في مكان مفتوح',
    location_failed: 'تعذر الحصول على الموقع',
    btn_new_task: '+ مهمة جديدة', no_tasks: 'لا توجد مهام', task_for: 'لـ', task_priority: 'الأولوية', task_date: 'تاريخ',
    task_title_label: 'العنوان', task_desc_label: 'الوصف', task_employee_label: 'موظف', task_priority_label: 'الأولوية',
    task_due_label: 'تاريخ الاستحقاق', btn_save: 'حفظ', prio_low: 'منخفض', prio_normal: 'عادي', prio_high: 'مرتفع', prio_urgent: 'عاجل',
    no_pending_approvals: 'لا توجد طلبات معلقة', from_label: 'من', notes_placeholder: 'ملاحظات', btn_approve: 'موافقة', btn_reject: 'رفض',
    material_request_title: 'طلب مواد', purchase_request_title: 'طلب شراء', missing_att_title: 'طلب نسيان بصمة',
    item_label: 'المادة', qty_label: 'الكمية', reason_label: 'السبب', notes_label: 'ملاحظات',
    btn_send_material_request: 'إرسال طلب مواد', btn_send_purchase_request: 'إرسال طلب شراء',
    forget_type_label: 'نسيان', opt_clock_in: 'بصمة دخول', opt_clock_out: 'بصمة خروج', datetime_label: 'التاريخ والوقت',
    reason_notes_label: 'السبب / الملاحظات *', btn_send_missing_att: 'إرسال طلب نسيان بصمة', my_requests_title: 'طلباتي',
    sent_material_ok: 'تم إرسال طلب المواد للموافقة', sent_purchase_ok: 'تم إرسال طلب الشراء للموافقة',
    sent_missing_att_ok: 'تم إرسال طلب نسيان البصمة', no_requests: 'لا توجد طلبات', reason_required_msg: 'السبب / الملاحظات حقل مطلوب',
    opt_in_label: 'دخول', opt_out_label: 'خروج',
    team_title: 'فريقي', team_no_subordinates: 'لا يوجد لديك مرؤوسون حالياً', period_label: 'الفترة',
    evaluated: 'مُقيَّم', not_evaluated: 'غير مُقيَّم', btn_evaluate: 'تقييم', btn_view: 'عرض', btn_request_on_behalf: 'تقديم طلب نيابة',
    eval_form_title: 'تقييم أداء الموظف', score_label: 'الدرجة', total_score_label: 'الدرجة الإجمالية',
    already_evaluated: 'تم تقييم هذا الموظف مسبقاً لهذه الفترة', btn_submit_eval: 'حفظ التقييم', eval_saved_ok: 'تم حفظ التقييم بنجاح',
    eval_details_title: 'تفاصيل التقييم', kpi_label: 'المؤشر', weight_label: 'الوزن', notes_col: 'ملاحظات',
    on_behalf_title: 'تقديم طلب نيابة عن الموظف', request_type_label: 'نوع الطلب', opt_missing_att: 'نسيان بصمة', opt_leave: 'إجازة',
    date_from_label: 'من تاريخ', date_to_label: 'إلى تاريخ', leave_type_label: 'نوع الإجازة',
    opt_leave_unpaid: 'بدون راتب', opt_leave_hourly: 'ساعية', opt_leave_emergency: 'طارئة', opt_leave_sick: 'مرضية', opt_leave_annual: 'سنوية',
    sent_on_behalf_ok: 'تم إرسال الطلب نيابة عن الموظف بنجاح', btn_cancel: 'إلغاء',
    housing_title: 'المقيمون في السكن', housing_site_label: 'الموقع', housing_apartment_label: 'رقم الشقة',
    housing_room_label: 'رقم الغرفة', btn_save_housing: 'حفظ بيانات السكن', btn_edit_housing: 'تعديل السكن', housing_saved_ok: 'تم تحديث بيانات السكن بنجاح',
    housing_no_employees: 'لا يوجد موظفون مطابقون', housing_current: 'السكن الحالي', none_label: '—',
    profile_title: 'حسابي', change_password_title: 'تغيير كلمة المرور', current_password_label: 'كلمة المرور الحالية',
    new_password_label: 'كلمة المرور الجديدة', confirm_password_label: 'تأكيد كلمة المرور الجديدة',
    btn_change_password: 'تغيير كلمة المرور', password_changed_ok: 'تم تغيير كلمة المرور بنجاح',
    language_label: 'اللغة', lang_ar: 'العربية', lang_en: 'English',
  },
  en: {
    tab_attendance: 'Attendance', tab_tasks: 'Tasks', tab_approvals: 'Approvals', tab_requests: 'Requests',
    tab_team: 'My Team', tab_housing: 'Housing', tab_profile: 'Profile',
    loading: 'Loading...', no_data: 'No data', error_generic: 'Error',
    att_title: "Today's Attendance Status", att_in: 'Check-in', att_out: 'Check-out', att_branch: 'Branch',
    att_radius: 'radius', att_general_zone: 'General zone', att_no_geofence: 'Geofence not configured for this branch/system. Contact HR.',
    btn_clock_in: 'Clock In', btn_clock_out: 'Clock Out', getting_location: 'Getting location...',
    gps_unsupported: 'GPS not supported', weak_accuracy: 'Weak location accuracy', retry_open_area: 'Retry in an open area',
    location_failed: 'Could not get location',
    btn_new_task: '+ New Task', no_tasks: 'No tasks', task_for: 'For', task_priority: 'Priority', task_date: 'Date',
    task_title_label: 'Title', task_desc_label: 'Description', task_employee_label: 'Employee', task_priority_label: 'Priority',
    task_due_label: 'Due date', btn_save: 'Save', prio_low: 'Low', prio_normal: 'Normal', prio_high: 'High', prio_urgent: 'Urgent',
    no_pending_approvals: 'No pending requests', from_label: 'From', notes_placeholder: 'Notes', btn_approve: 'Approve', btn_reject: 'Reject',
    material_request_title: 'Material Request', purchase_request_title: 'Purchase Request', missing_att_title: 'Missing Fingerprint Request',
    item_label: 'Item', qty_label: 'Quantity', reason_label: 'Reason', notes_label: 'Notes',
    btn_send_material_request: 'Send Material Request', btn_send_purchase_request: 'Send Purchase Request',
    forget_type_label: 'Missing', opt_clock_in: 'Check-in', opt_clock_out: 'Check-out', datetime_label: 'Date & time',
    reason_notes_label: 'Reason / notes *', btn_send_missing_att: 'Send Missing Fingerprint Request', my_requests_title: 'My Requests',
    sent_material_ok: 'Material request sent for approval', sent_purchase_ok: 'Purchase request sent for approval',
    sent_missing_att_ok: 'Missing fingerprint request sent', no_requests: 'No requests', reason_required_msg: 'Reason / notes is required',
    opt_in_label: 'In', opt_out_label: 'Out',
    team_title: 'My Team', team_no_subordinates: 'You have no direct reports currently', period_label: 'Period',
    evaluated: 'Evaluated', not_evaluated: 'Not evaluated', btn_evaluate: 'Evaluate', btn_view: 'View', btn_request_on_behalf: 'Submit Request On Behalf',
    eval_form_title: 'Employee Performance Evaluation', score_label: 'Score', total_score_label: 'Total score',
    already_evaluated: 'This employee has already been evaluated for this period', btn_submit_eval: 'Save Evaluation', eval_saved_ok: 'Evaluation saved successfully',
    eval_details_title: 'Evaluation Details', kpi_label: 'KPI', weight_label: 'Weight', notes_col: 'Notes',
    on_behalf_title: 'Submit a Request On Behalf of Employee', request_type_label: 'Request type', opt_missing_att: 'Missing fingerprint', opt_leave: 'Leave',
    date_from_label: 'From date', date_to_label: 'To date', leave_type_label: 'Leave type',
    opt_leave_unpaid: 'Unpaid', opt_leave_hourly: 'Hourly', opt_leave_emergency: 'Emergency', opt_leave_sick: 'Sick', opt_leave_annual: 'Annual',
    sent_on_behalf_ok: 'Request submitted on behalf of the employee', btn_cancel: 'Cancel',
    housing_title: 'Housing residents', housing_site_label: 'Site', housing_apartment_label: 'Apartment No.',
    housing_room_label: 'Room No.', btn_save_housing: 'Save Housing Info', btn_edit_housing: 'Edit Housing', housing_saved_ok: 'Housing info updated successfully',
    housing_no_employees: 'No matching employees', housing_current: 'Current housing', none_label: '—',
    profile_title: 'My Profile', change_password_title: 'Change Password', current_password_label: 'Current password',
    new_password_label: 'New password', confirm_password_label: 'Confirm new password',
    btn_change_password: 'Change Password', password_changed_ok: 'Password changed successfully',
    language_label: 'Language', lang_ar: 'العربية', lang_en: 'English',
  }
};
let LANG = localStorage.getItem('pj_mobile_lang') || 'ar';
function t(key) { return (I18N[LANG] && I18N[LANG][key]) || key; }
function applyStaticI18n() {
  document.querySelectorAll('[data-i18n]').forEach(el => { el.textContent = t(el.getAttribute('data-i18n')); });
  const root = document.getElementById('html-root');
  root.setAttribute('lang', LANG === 'ar' ? 'ar' : 'en');
  root.setAttribute('dir', LANG === 'ar' ? 'rtl' : 'ltr');
  document.getElementById('lang-switch').textContent = LANG === 'ar' ? 'EN' : 'AR';
}
function toggleLang() {
  LANG = LANG === 'ar' ? 'en' : 'ar';
  localStorage.setItem('pj_mobile_lang', LANG);
  applyStaticI18n();
  const activeTab = document.querySelector('.tab.active');
  if (activeTab) reloadTab(activeTab.getAttribute('data-tab'));
}
function reloadTab(id) {
  if (id === 'attendance') loadAttendance();
  if (id === 'tasks') loadTasks();
  if (id === 'approvals') loadApprovals();
  if (id === 'requests') loadRequests();
  if (id === 'team') loadTeam();
  if (id === 'housing') loadHousing();
  if (id === 'profile') loadProfile();
}
applyStaticI18n();

/* ===================== Core helpers ===================== */
const API = (path, opts={}) => {
  return fetch(path, Object.assign({headers: {'Authorization': 'Bearer '+TOKEN, 'Content-Type': 'application/json'}}, opts))
    .then(r => r.json()).catch(e => ({ok:false, error:e.message}));
};
function showTab(id) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  event.target.classList.add('active');
  document.getElementById(id).classList.add('active');
  reloadTab(id);
}
function render(id, html) { document.getElementById(id).innerHTML = html; }
function setLoading(id) { render(id, '<div class="loading">' + t('loading') + '</div>'); }
loadAttendance();

/* ===================== Attendance ===================== */
async function loadAttendance() {
  setLoading('attendance');
  const res = await API('/api/attendance/status');
  if (!res.ok) { render('attendance', '<div class="error">' + (res.error || t('error_generic')) + '</div>'); return; }
  const d = res.data;
  let html = '<div class="card">';
  html += '<h3>' + t('att_title') + '</h3>';
  html += '<p><strong>' + t('att_in') + ':</strong> ' + (d.clock_in || t('none_label')) + '</p>';
  html += '<p><strong>' + t('att_out') + ':</strong> ' + (d.clock_out || t('none_label')) + '</p>';
  if (d.branch) {
    html += '<p><strong>' + t('att_branch') + ':</strong> ' + d.branch.name + '</p>';
    html += '<p><small>' + d.branch.latitude + ', ' + d.branch.longitude + ' (' + t('att_radius') + ' ' + d.branch.allowed_radius_meters + 'م)</small></p>';
  } else if (d.fallback_geofence) {
    html += '<p><small>' + t('att_general_zone') + ': ' + d.fallback_geofence.lat + ', ' + d.fallback_geofence.lng + ' (' + t('att_radius') + ' ' + d.fallback_geofence.radius_m + 'م)</small></p>';
  }
  if (!d.geofence_configured && d.strict) {
    html += '<div class="error" style="margin-top:10px">' + t('att_no_geofence') + '</div>';
  }
  html += '</div>';
  const disabled = !d.geofence_configured && d.strict ? 'disabled' : '';
  html += '<button class="btn" ' + disabled + ' onclick="clock(\'in\')">' + t('btn_clock_in') + '</button>';
  html += '<button class="btn secondary" ' + disabled + ' onclick="clock(\'out\')">' + t('btn_clock_out') + '</button>';
  html += '<div id="att-msg"></div>';
  render('attendance', html);
}
function clock(type) {
  const btn = event.target;
  if (btn.disabled) return;
  btn.disabled = true;
  document.getElementById('att-msg').innerHTML = '<div class="loading">' + t('getting_location') + '</div>';
  if (!navigator.geolocation) {
    document.getElementById('att-msg').innerHTML = '<div class="error">' + t('gps_unsupported') + '</div>';
    btn.disabled = false; return;
  }
  navigator.geolocation.getCurrentPosition(async pos => {
    const acc = pos.coords.accuracy || 0;
    if (acc > 250) {
      document.getElementById('att-msg').innerHTML = '<div class="error">' + t('weak_accuracy') + ' (' + Math.round(acc) + ' م). ' + t('retry_open_area') + '.</div>';
      btn.disabled = false; return;
    }
    const res = await API('/api/attendance/check', {
      method: 'POST',
      body: JSON.stringify({check_type: type, latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: acc})
    });
    if (res.ok) {
      document.getElementById('att-msg').innerHTML = '<div class="success">' + res.message + '</div>';
      loadAttendance();
    } else {
      document.getElementById('att-msg').innerHTML = '<div class="error">' + (res.error || t('error_generic')) + '</div>';
    }
    btn.disabled = false;
  }, err => {
    document.getElementById('att-msg').innerHTML = '<div class="error">' + t('location_failed') + ': ' + err.message + '</div>';
    btn.disabled = false;
  }, {enableHighAccuracy: true, timeout: 15000, maximumAge: 0});
}

/* ===================== Tasks ===================== */
async function loadTasks() {
  setLoading('tasks');
  const res = await API('/api/tasks');
  if (!res.ok) { render('tasks', '<div class="error">' + (res.error || t('error_generic')) + '</div>'); return; }
  let html = '<button class="btn" onclick="showTaskForm()">' + t('btn_new_task') + '</button>';
  html += '<div id="task-form"></div>';
  if (!res.data.length) { html += '<div class="loading">' + t('no_tasks') + '</div>'; }
  res.data.forEach(tk => {
    html += '<div class="card">';
    html += '<h4>' + tk.title + ' <span class="status-badge">' + tk.status + '</span></h4>';
    html += '<p>' + (tk.description || '') + '</p>';
    html += '<p><small>' + t('task_for') + ': ' + (tk.assignee || '') + ' | ' + t('task_priority') + ': ' + (tk.priority || 'normal') + ' | ' + t('task_date') + ': ' + (tk.due_date || t('none_label')) + '</small></p>';
    html += '<select onchange="updateTask(' + tk.id + ', this.value)"><option ' + (tk.status==='open'?'selected':'') + '>open</option><option ' + (tk.status==='in_progress'?'selected':'') + '>in_progress</option><option ' + (tk.status==='done'?'selected':'') + '>done</option><option ' + (tk.status==='cancelled'?'selected':'') + '>cancelled</option></select>';
    html += '</div>';
  });
  render('tasks', html);
}
async function updateTask(id, status) {
  const res = await API('/api/tasks/' + id + '/status', {method: 'POST', body: JSON.stringify({status})});
  if (res.ok) loadTasks();
  else alert(res.error || t('error_generic'));
}
async function showTaskForm() {
  const emp = await API('/api/employees');
  let html = '<div class="card">';
  html += '<div class="form-group"><label>' + t('task_title_label') + '</label><input id="t-title"></div>';
  html += '<div class="form-group"><label>' + t('task_desc_label') + '</label><textarea id="t-desc"></textarea></div>';
  html += '<div class="form-group"><label>' + t('task_employee_label') + '</label><select id="t-assign">' + (emp.data || []).map(e => '<option value="' + e.id + '">' + e.full_name + '</option>').join('') + '</select></div>';
  html += '<div class="form-group"><label>' + t('task_priority_label') + '</label><select id="t-priority"><option value="low">' + t('prio_low') + '</option><option value="normal" selected>' + t('prio_normal') + '</option><option value="high">' + t('prio_high') + '</option><option value="urgent">' + t('prio_urgent') + '</option></select></div>';
  html += '<div class="form-group"><label>' + t('task_due_label') + '</label><input type="date" id="t-due"></div>';
  html += '<button class="btn" onclick="saveTask()">' + t('btn_save') + '</button><div id="task-msg"></div>';
  html += '</div>';
  document.getElementById('task-form').innerHTML = html;
}
async function saveTask() {
  const res = await API('/api/tasks', {method: 'POST', body: JSON.stringify({
    title: document.getElementById('t-title').value,
    description: document.getElementById('t-desc').value,
    assigned_to: document.getElementById('t-assign').value,
    priority: document.getElementById('t-priority').value,
    due_date: document.getElementById('t-due').value
  })});
  if (res.ok) { document.getElementById('task-form').innerHTML = ''; loadTasks(); }
  else document.getElementById('task-msg').innerHTML = '<div class="error">' + (res.error || t('error_generic')) + '</div>';
}

/* ===================== Approvals ===================== */
async function loadApprovals() {
  setLoading('approvals');
  const [legacy, wf] = await Promise.all([API('/api/approvals'), API('/api/workflows/pending')]);
  let html = '';
  const legacyRows = legacy.data || [];
  const wfRows = wf.data || [];
  if (!legacyRows.length && !wfRows.length) { render('approvals', '<div class="loading">' + t('no_pending_approvals') + '</div>'); return; }
  legacyRows.forEach(r => {
    html += '<div class="card"><h4>' + (r.workflow_name || r.entity_type) + '</h4>';
    html += '<p><small>' + t('from_label') + ': ' + (r.requester_name || '') + '</small></p>';
    html += '<textarea id="notes-' + r.id + '" placeholder="' + t('notes_placeholder') + '"></textarea>';
    html += '<button class="btn" onclick="decideApproval(' + r.id + ', \'approved\', \'legacy\')">' + t('btn_approve') + '</button>';
    html += '<button class="btn secondary" onclick="decideApproval(' + r.id + ', \'rejected\', \'legacy\')">' + t('btn_reject') + '</button></div>';
  });
  wfRows.forEach(r => {
    html += '<div class="card"><h4>' + r.template_name + '</h4>';
    html += '<p>' + r.title + '</p>';
    html += '<textarea id="notes-wf-' + r.id + '" placeholder="' + t('notes_placeholder') + '"></textarea>';
    html += '<button class="btn" onclick="decideApproval(' + r.id + ', \'approved\', \'workflow\')">' + t('btn_approve') + '</button>';
    html += '<button class="btn secondary" onclick="decideApproval(' + r.id + ', \'rejected\', \'workflow\')">' + t('btn_reject') + '</button></div>';
  });
  render('approvals', html);
}
async function decideApproval(id, decision, kind) {
  const notes = document.getElementById('notes-' + (kind === 'workflow' ? 'wf-' : '') + id).value;
  const path = kind === 'workflow' ? '/api/workflows/requests/' + id + '/decide' : '/api/approvals/' + id + '/decide';
  const res = await API(path, {method: 'POST', body: JSON.stringify({decision, notes})});
  if (res.ok) loadApprovals();
  else alert(res.error || t('error_generic'));
}

/* ===================== Requests (self-service) ===================== */
async function loadRequests() {
  setLoading('requests');
  const items = await API('/api/mobile/items');
  const opts = (items.data || []).map(i => '<option value="' + i.id + '">' + i.name + '</option>').join('');
  let html = '<div class="card"><h3>' + t('material_request_title') + '</h3>';
  html += '<div class="form-group"><label>' + t('item_label') + '</label><select id="mr-item">' + opts + '</select></div>';
  html += '<div class="form-group"><label>' + t('qty_label') + '</label><input type="number" id="mr-qty"></div>';
  html += '<div class="form-group"><label>' + t('reason_label') + '</label><input id="mr-reason"></div>';
  html += '<button class="btn" onclick="saveMaterialRequest()">' + t('btn_send_material_request') + '</button>';
  html += '<div id="mr-msg"></div></div>';
  html += '<div class="card"><h3>' + t('purchase_request_title') + '</h3>';
  html += '<div class="form-group"><label>' + t('item_label') + '</label><select id="pr-item">' + opts + '</select></div>';
  html += '<div class="form-group"><label>' + t('qty_label') + '</label><input type="number" id="pr-qty"></div>';
  html += '<div class="form-group"><label>' + t('notes_label') + '</label><input id="pr-notes"></div>';
  html += '<button class="btn" onclick="savePurchaseRequest()">' + t('btn_send_purchase_request') + '</button>';
  html += '<div id="pr-msg"></div></div>';

  html += '<div class="card"><h3>' + t('missing_att_title') + '</h3>';
  html += '<div class="form-group"><label>' + t('forget_type_label') + '</label>';
  html += '<select id="ma-type"><option value="in">' + t('opt_clock_in') + '</option><option value="out">' + t('opt_clock_out') + '</option></select></div>';
  html += '<div class="form-group"><label>' + t('datetime_label') + '</label><input type="datetime-local" id="ma-datetime"></div>';
  html += '<div class="form-group"><label>' + t('reason_notes_label') + '</label><textarea id="ma-reason" required></textarea></div>';
  html += '<button class="btn" onclick="saveMissingAttendanceRequest()">' + t('btn_send_missing_att') + '</button>';
  html += '<div id="ma-msg"></div></div>';

  html += '<div class="card"><h3>' + t('my_requests_title') + '</h3><div id="my-req"></div></div>';
  render('requests', html);
  loadMyRequests();
}
async function saveMaterialRequest() {
  const res = await API('/api/material-requests', {method: 'POST', body: JSON.stringify({
    items: [{item_id: document.getElementById('mr-item').value, requested_qty: document.getElementById('mr-qty').value, purpose: document.getElementById('mr-reason').value}],
    reason: document.getElementById('mr-reason').value
  })});
  if (res.ok) {
    await API('/api/material-requests/' + res.id + '/submit', {method: 'POST'});
    document.getElementById('mr-msg').innerHTML = '<div class="success">' + t('sent_material_ok') + '</div>';
    loadMyRequests();
  } else {
    document.getElementById('mr-msg').innerHTML = '<div class="error">' + (res.error || t('error_generic')) + '</div>';
  }
}
async function savePurchaseRequest() {
  const res = await API('/api/purchasing/requests', {method: 'POST', body: JSON.stringify({
    item_id: document.getElementById('pr-item').value,
    qty: document.getElementById('pr-qty').value,
    item_notes: document.getElementById('pr-notes').value,
    notes: document.getElementById('pr-notes').value
  })});
  if (res.ok) {
    document.getElementById('pr-msg').innerHTML = '<div class="success">' + t('sent_purchase_ok') + '</div>';
    loadMyRequests();
  } else {
    document.getElementById('pr-msg').innerHTML = '<div class="error">' + (res.error || t('error_generic')) + '</div>';
  }
}
async function loadMyRequests() {
  const [mr, pr, ma] = await Promise.all([API('/api/material-requests'), API('/api/purchasing/requests'), API('/api/missing-attendance')]);
  let html = '';
  (mr.data || []).forEach(r => {
    html += '<p><strong>[MR] ' + r.request_no + '</strong> — ' + r.status + ' — ' + (r.reason || '') + '</p>';
  });
  (pr.data || []).forEach(r => {
    html += '<p><strong>[PR] ' + r.request_no + '</strong> — ' + r.status + '</p>';
  });
  (ma.data || []).forEach(r => {
    const dt = r.clock_in_at || r.clock_out_at;
    html += '<p><strong>[' + t('missing_att_title') + '] ' + (r.request_type === 'in' ? t('opt_in_label') : t('opt_out_label')) + '</strong> — ' + r.status;
    if (dt) html += ' | ' + new Date(dt).toLocaleString(LANG === 'ar' ? 'ar-AE' : 'en-GB', {hour12: false});
    html += '</p>';
  });
  document.getElementById('my-req').innerHTML = html || '<p>' + t('no_requests') + '</p>';
}
function formatMissingAttDateTime(value) {
  if (!value) return null;
  return value.replace('T', ' ') + ':00';
}
async function saveMissingAttendanceRequest() {
  const type = document.getElementById('ma-type').value;
  const reasonValue = document.getElementById('ma-reason').value.trim();
  if (!reasonValue) {
    document.getElementById('ma-msg').innerHTML = '<div class="error">' + t('reason_required_msg') + '</div>';
    return;
  }
  const payload = { request_type: type, reason: reasonValue };
  const dateTime = formatMissingAttDateTime(document.getElementById('ma-datetime').value);
  if (type === 'in') { payload.clock_in_at = dateTime; } else if (type === 'out') { payload.clock_out_at = dateTime; }
  const btn = event.target;
  btn.disabled = true;
  const res = await API('/api/missing-attendance', {method: 'POST', body: JSON.stringify(payload)});
  btn.disabled = false;
  if (res.ok) {
    document.getElementById('ma-msg').innerHTML = '<div class="success">' + t('sent_missing_att_ok') + '</div>';
    document.getElementById('ma-reason').value = '';
    document.getElementById('ma-datetime').value = '';
    loadMyRequests();
  } else {
    document.getElementById('ma-msg').innerHTML = '<div class="error">' + (res.error || t('error_generic')) + '</div>';
  }
}

/* ===================== Team (direct managers only) ===================== */
async function loadTeam() {
  setLoading('team');
  const period = new Date().toISOString().slice(0, 7);
  const res = await API('/api/team/evaluations?period=' + period);
  if (!res.ok) { render('team', '<div class="error">' + (res.error || t('error_generic')) + '</div>'); return; }
  if (!res.data.length) { render('team', '<div class="loading">' + t('team_no_subordinates') + '</div>'); return; }
  let html = '<p class="section-title">' + t('period_label') + ': ' + period + '</p>';
  res.data.forEach(e => {
    const isEvaluated = !!e.evaluation_id;
    html += '<div class="card">';
    html += '<h4>' + e.full_name + ' <span class="status-badge">' + (e.job_title || '') + '</span></h4>';
    html += '<p><small>' + (e.department || '') + '</small></p>';
    html += '<p>' + (isEvaluated ? ('<span class="status-badge" style="background:#dcfce7;color:#166534">' + t('evaluated') + ' (' + Math.round(e.total_score || 0) + '%)</span>') : ('<span class="status-badge">' + t('not_evaluated') + '</span>')) + '</p>';
    html += '<div style="display:flex;gap:8px">';
    if (isEvaluated) {
      html += '<button class="btn small secondary" style="flex:1" onclick="viewEvaluation(' + e.evaluation_id + ')">' + t('btn_view') + '</button>';
    } else {
      html += '<button class="btn small" style="flex:1" onclick="showEvalForm(' + e.id + ', \'' + period + '\')">' + t('btn_evaluate') + '</button>';
    }
    html += '<button class="btn small secondary" style="flex:1" onclick="showOnBehalfForm(' + e.id + ', \'' + (e.full_name||'').replace(/'/g, '') + '\')">' + t('btn_request_on_behalf') + '</button>';
    html += '</div>';
    html += '<div id="team-extra-' + e.id + '"></div>';
    html += '</div>';
  });
  render('team', html);
}
async function showEvalForm(employeeId, period) {
  const box = document.getElementById('team-extra-' + employeeId);
  box.innerHTML = '<div class="loading">' + t('loading') + '</div>';
  const res = await API('/api/team/employees/' + employeeId + '/kpis?period=' + period);
  if (!res.ok) { box.innerHTML = '<div class="error">' + (res.error || t('error_generic')) + '</div>'; return; }
  if (res.already_evaluated) { box.innerHTML = '<div class="error">' + t('already_evaluated') + '</div>'; return; }
  let html = '<div class="card" style="background:#f9fafb"><h4>' + t('eval_form_title') + '</h4>';
  (res.data || []).forEach(k => {
    html += '<div class="kpi-row"><span>' + k.name + ' (' + k.weight + '%)</span>';
    html += '<input type="number" min="0" max="100" style="width:70px;text-align:center" id="kpi-' + employeeId + '-' + k.id + '" value="0"></div>';
  });
  html += '<div class="form-group" style="margin-top:8px"><label>' + t('notes_label') + '</label><textarea id="eval-notes-' + employeeId + '"></textarea></div>';
  html += '<div style="display:flex;gap:8px">';
  html += '<button class="btn small" style="flex:1" onclick="submitEvaluation(' + employeeId + ', \'' + period + '\')">' + t('btn_submit_eval') + '</button>';
  html += '<button class="btn small secondary" style="flex:1" onclick="document.getElementById(\'team-extra-' + employeeId + '\').innerHTML=\'\'">' + t('btn_cancel') + '</button>';
  html += '</div><div id="eval-msg-' + employeeId + '"></div></div>';
  box.innerHTML = html;
  box.dataset.kpiIds = (res.data || []).map(k => k.id).join(',');
}
async function submitEvaluation(employeeId, period) {
  const box = document.getElementById('team-extra-' + employeeId);
  const kpiIds = (box.dataset.kpiIds || '').split(',').filter(Boolean);
  const kpiScores = kpiIds.map(id => ({ kpi_id: id, score: document.getElementById('kpi-' + employeeId + '-' + id).value }));
  const res = await API('/api/team/evaluations', {method: 'POST', body: JSON.stringify({
    employee_id: employeeId, period: period, kpi_scores: kpiScores, notes: document.getElementById('eval-notes-' + employeeId).value
  })});
  const msg = document.getElementById('eval-msg-' + employeeId);
  if (res.ok) { msg.innerHTML = '<div class="success">' + t('eval_saved_ok') + '</div>'; setTimeout(loadTeam, 1000); }
  else msg.innerHTML = '<div class="error">' + (res.error || t('error_generic')) + '</div>';
}
async function viewEvaluation(evaluationId) {
  const res = await API('/api/team/evaluations/' + evaluationId);
  if (!res.ok) { alert(res.error || t('error_generic')); return; }
  let html = '<h4>' + t('eval_details_title') + '</h4>';
  html += '<p><strong>' + t('total_score_label') + ':</strong> ' + Math.round(res.data.total_score || 0) + '%</p>';
  (res.items || []).forEach(i => {
    html += '<div class="kpi-row"><span>' + i.kpi_name + '</span><span>' + i.score + '</span></div>';
  });
  const modalHost = document.createElement('div');
  modalHost.className = 'card';
  modalHost.style.cssText = 'position:fixed;top:10%;left:5%;right:5%;z-index:999;max-height:80vh;overflow:auto;box-shadow:0 10px 30px rgba(0,0,0,0.3)';
  modalHost.innerHTML = html + '<button class="btn secondary" onclick="this.parentElement.remove()">' + t('btn_cancel') + '</button>';
  document.body.appendChild(modalHost);
}
function showOnBehalfForm(employeeId, employeeName) {
  const box = document.getElementById('team-extra-' + employeeId);
  let html = '<div class="card" style="background:#f9fafb"><h4>' + t('on_behalf_title') + ' — ' + employeeName + '</h4>';
  html += '<div class="form-group"><label>' + t('request_type_label') + '</label>';
  html += '<select id="ob-type-' + employeeId + '" onchange="toggleOnBehalfFields(' + employeeId + ')"><option value="missing_attendance">' + t('opt_missing_att') + '</option><option value="leave">' + t('opt_leave') + '</option></select></div>';
  html += '<div id="ob-fields-' + employeeId + '"></div>';
  html += '<div class="form-group"><label>' + t('reason_label') + '</label><textarea id="ob-reason-' + employeeId + '"></textarea></div>';
  html += '<div style="display:flex;gap:8px">';
  html += '<button class="btn small" style="flex:1" onclick="submitOnBehalf(' + employeeId + ')">' + t('btn_save') + '</button>';
  html += '<button class="btn small secondary" style="flex:1" onclick="document.getElementById(\'team-extra-' + employeeId + '\').innerHTML=\'\'">' + t('btn_cancel') + '</button>';
  html += '</div><div id="ob-msg-' + employeeId + '"></div></div>';
  box.innerHTML = html;
  toggleOnBehalfFields(employeeId);
}
function toggleOnBehalfFields(employeeId) {
  const type = document.getElementById('ob-type-' + employeeId).value;
  const host = document.getElementById('ob-fields-' + employeeId);
  if (type === 'missing_attendance') {
    host.innerHTML = '<div class="form-group"><label>' + t('forget_type_label') + '</label>'
      + '<select id="ob-ma-type-' + employeeId + '"><option value="in">' + t('opt_clock_in') + '</option><option value="out">' + t('opt_clock_out') + '</option></select></div>'
      + '<div class="form-group"><label>' + t('datetime_label') + '</label><input type="datetime-local" id="ob-ma-datetime-' + employeeId + '"></div>';
  } else {
    host.innerHTML = '<div class="form-group"><label>' + t('leave_type_label') + '</label>'
      + '<select id="ob-leave-type-' + employeeId + '"><option value="unpaid">' + t('opt_leave_unpaid') + '</option><option value="hourly">' + t('opt_leave_hourly') + '</option><option value="emergency">' + t('opt_leave_emergency') + '</option><option value="sick">' + t('opt_leave_sick') + '</option><option value="annual">' + t('opt_leave_annual') + '</option></select></div>'
      + '<div class="form-group"><label>' + t('date_from_label') + '</label><input type="date" id="ob-date-from-' + employeeId + '"></div>'
      + '<div class="form-group"><label>' + t('date_to_label') + '</label><input type="date" id="ob-date-to-' + employeeId + '"></div>';
  }
}
async function submitOnBehalf(employeeId) {
  const type = document.getElementById('ob-type-' + employeeId).value;
  const reason = document.getElementById('ob-reason-' + employeeId).value.trim();
  const msg = document.getElementById('ob-msg-' + employeeId);
  if (type === 'missing_attendance' && !reason) {
    msg.innerHTML = '<div class="error">' + t('reason_required_msg') + '</div>';
    return;
  }
  let res;
  if (type === 'missing_attendance') {
    const maType = document.getElementById('ob-ma-type-' + employeeId).value;
    const dt = formatMissingAttDateTime(document.getElementById('ob-ma-datetime-' + employeeId).value);
    const payload = { request_type: maType, reason };
    if (maType === 'in') payload.clock_in_at = dt; else payload.clock_out_at = dt;
    res = await API('/api/team/employees/' + employeeId + '/missing-attendance', {method: 'POST', body: JSON.stringify(payload)});
  } else {
    const payload = {
      leave_type: document.getElementById('ob-leave-type-' + employeeId).value,
      date_from: document.getElementById('ob-date-from-' + employeeId).value,
      date_to: document.getElementById('ob-date-to-' + employeeId).value,
      reason
    };
    res = await API('/api/team/employees/' + employeeId + '/leave', {method: 'POST', body: JSON.stringify(payload)});
  }
  if (res.ok) { msg.innerHTML = '<div class="success">' + t('sent_on_behalf_ok') + '</div>'; }
  else msg.innerHTML = '<div class="error">' + (res.error || t('error_generic')) + '</div>';
}

/* ===================== Housing (authorized viewers) ===================== */
let HOUSING_SITES = [];
async function loadHousing() {
  setLoading('housing');
  const [sitesRes, empRes] = await Promise.all([API('/api/housing/sites'), API('/api/housing/employees?residents_only=1')]);
  if (!sitesRes.ok || !empRes.ok) { render('housing', '<div class="error">' + ((sitesRes.error || empRes.error) || t('error_generic')) + '</div>'); return; }
  HOUSING_SITES = sitesRes.data || [];
  if (!empRes.data.length) { render('housing', '<div class="loading">' + t('housing_no_employees') + '</div>'); return; }
  let html = '<h3>' + t('housing_title') + '</h3>';
  empRes.data.forEach(e => {
    html += '<div class="card">';
    html += '<h4>' + e.full_name + ' <small>(' + e.emp_code + ')</small></h4>';
    html += '<p><small>' + t('housing_current') + ': ' + (e.site_name || t('none_label')) + (e.apartment_number ? (' / ' + e.apartment_number) : '') + (e.room_number ? (' / ' + e.room_number) : '') + '</small></p>';
    if(e.can_edit) html += '<button class="btn small secondary" onclick="showHousingForm(' + e.id + ')">' + t('btn_edit_housing') + '</button>';
    html += '<div id="housing-extra-' + e.id + '"></div>';
    html += '</div>';
  });
  render('housing', html);
}
async function showHousingForm(employeeId) {
  const box = document.getElementById('housing-extra-' + employeeId);
  const siteOpts = HOUSING_SITES.map(s => '<option value="' + s.id + '">' + s.name + '</option>').join('');
  let html = '<div class="card" style="background:#f9fafb">';
  html += '<div class="form-group"><label>' + t('housing_site_label') + '</label><select id="h-site-' + employeeId + '" onchange="loadHousingApartments(' + employeeId + ')">' + siteOpts + '</select></div>';
  html += '<div class="form-group"><label>' + t('housing_apartment_label') + '</label><select id="h-apt-' + employeeId + '" onchange="loadHousingRooms(' + employeeId + ')"></select></div>';
  html += '<div class="form-group"><label>' + t('housing_room_label') + '</label><select id="h-room-' + employeeId + '"></select></div>';
  html += '<button class="btn small" onclick="saveHousing(' + employeeId + ')">' + t('btn_save_housing') + '</button>';
  html += '<div id="h-msg-' + employeeId + '"></div></div>';
  box.innerHTML = html;
  await loadHousingApartments(employeeId);
}
async function loadHousingApartments(employeeId) {
  const siteId = document.getElementById('h-site-' + employeeId).value;
  const res = await API('/api/housing/apartments?site_id=' + siteId);
  const sel = document.getElementById('h-apt-' + employeeId);
  sel.innerHTML = '<option value="">' + t('none_label') + '</option>' + (res.data || []).map(a => '<option value="' + a.id + '">' + a.apartment_number + '</option>').join('');
  await loadHousingRooms(employeeId);
}
async function loadHousingRooms(employeeId) {
  const aptId = document.getElementById('h-apt-' + employeeId).value;
  const sel = document.getElementById('h-room-' + employeeId);
  if (!aptId) { sel.innerHTML = '<option value="">' + t('none_label') + '</option>'; return; }
  const res = await API('/api/housing/rooms?apartment_id=' + aptId);
  sel.innerHTML = '<option value="">' + t('none_label') + '</option>' + (res.data || []).map(r => '<option value="' + r.id + '">' + r.room_number + ' (' + r.available_beds + '/' + r.bed_capacity + ')</option>').join('');
}
async function saveHousing(employeeId) {
  const payload = {
    site_id: document.getElementById('h-site-' + employeeId).value,
    apartment_id: document.getElementById('h-apt-' + employeeId).value || null,
    room_id: document.getElementById('h-room-' + employeeId).value || null
  };
  const res = await API('/api/housing/employees/' + employeeId, {method: 'POST', body: JSON.stringify(payload)});
  const msg = document.getElementById('h-msg-' + employeeId);
  if (res.ok) { msg.innerHTML = '<div class="success">' + t('housing_saved_ok') + '</div>'; setTimeout(loadHousing, 1200); }
  else msg.innerHTML = '<div class="error">' + (res.error || t('error_generic')) + '</div>';
}

/* ===================== Profile (change password) ===================== */
function loadProfile() {
  let html = '<h3>' + t('profile_title') + '</h3>';
  html += '<div class="card">';
  html += '<div class="form-group"><label>' + t('language_label') + '</label>';
  html += '<select id="profile-lang" onchange="setLangFromProfile()"><option value="ar"' + (LANG==='ar'?' selected':'') + '>' + t('lang_ar') + '</option><option value="en"' + (LANG==='en'?' selected':'') + '>' + t('lang_en') + '</option></select></div>';
  html += '</div>';
  html += '<div class="card">';
  html += '<h4>' + t('change_password_title') + '</h4>';
  html += '<div class="form-group"><label>' + t('current_password_label') + '</label><input type="password" id="pw-current"></div>';
  html += '<div class="form-group"><label>' + t('new_password_label') + '</label><input type="password" id="pw-new"></div>';
  html += '<div class="form-group"><label>' + t('confirm_password_label') + '</label><input type="password" id="pw-confirm"></div>';
  html += '<button class="btn" onclick="changePassword()">' + t('btn_change_password') + '</button>';
  html += '<div id="pw-msg"></div>';
  html += '</div>';
  render('profile', html);
}
function setLangFromProfile() {
  const val = document.getElementById('profile-lang').value;
  if (val !== LANG) toggleLang();
}
async function changePassword() {
  const current = document.getElementById('pw-current').value;
  const newPass = document.getElementById('pw-new').value;
  const confirm = document.getElementById('pw-confirm').value;
  const msg = document.getElementById('pw-msg');
  const res = await API('/api/change-password', {method: 'POST', body: JSON.stringify({
    current_password: current, new_password: newPass, new_password_confirmation: confirm
  })});
  if (res.ok) {
    msg.innerHTML = '<div class="success">' + t('password_changed_ok') + '</div>';
    document.getElementById('pw-current').value = '';
    document.getElementById('pw-new').value = '';
    document.getElementById('pw-confirm').value = '';
  } else {
    msg.innerHTML = '<div class="error">' + (res.error || t('error_generic')) + '</div>';
  }
}
</script>
</body>
</html>
