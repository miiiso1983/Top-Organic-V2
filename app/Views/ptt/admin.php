<?php use App\Core\{Helpers, Auth, ImportHelper}; ?>
<?php $importErrors = ImportHelper::getErrors(); ?>
<?php if ($importErrors): ?>
<div class="alert alert-danger">
  <strong>أخطاء الاستيراد:</strong>
  <ul><?php foreach ($importErrors as $err): ?><li><?= Helpers::e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>
<div class="page-head">
  <h2>إدارة قنوات PTT</h2>
  <a href="/ptt" class="btn btn-secondary">رجوع</a>
</div>

<?php if (Auth::can('ptt.service_control')): ?>
<?php $svcActive = ($serviceStatusMeta['status'] ?? 'active') === 'active'; ?>
<div class="card" style="border-right:4px solid <?= $svcActive ? '#16a34a' : '#d97706' ?>">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <h3 style="margin:0 0 4px"><i class="fa-solid fa-server"></i> حالة خدمة PTT</h3>
      <span class="badge badge-<?= $svcActive ? 'success' : 'warning' ?>"><?= $svcActive ? 'تعمل بشكل كامل' : 'متوقفة جزئياً' ?></span>
      <?php if (!$svcActive && !empty($serviceStatusMeta['updated_at'])): ?>
      <span class="text-muted" style="margin-right:8px">منذ <?= Helpers::e($serviceStatusMeta['updated_at']) ?></span>
      <?php endif; ?>
    </div>
    <div>
      <form method="post" action="/ptt/admin/service/stop" style="display:inline" onsubmit="return confirm('سيتم إيقاف خدمة PTT جزئياً؛ لن يتمكن المستخدمون من إرسال رسائل صوتية جديدة أو بدء بث مباشر جديد حتى إعادة التشغيل. متابعة؟');">
        <button class="btn btn-warning" <?= $svcActive ? '' : 'disabled' ?>><i class="fa-solid fa-pause"></i> إيقاف جزئي لخدمة PTT</button>
      </form>
      <form method="post" action="/ptt/admin/service/restart" style="display:inline" onsubmit="return confirm('سيتم إعادة تشغيل خدمة PTT بشكل كامل. متابعة؟');">
        <button class="btn btn-success" <?= $svcActive ? 'disabled' : '' ?>><i class="fa-solid fa-play"></i> إعادة تشغيل الخدمة</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3><i class="fa-solid fa-file-excel"></i> استيراد قنوات من Excel</h3>
  <p>الأعمدة المطلوبة: <code>code</code>، <code>name</code>. الأعمدة الاختيارية: <code>description</code>، <code>department</code>، <code>branch</code>، <code>color</code>، <code>icon</code>، <code>auto_sync</code>، <code>is_active</code>، <code>is_readonly</code>، <code>max_duration_sec</code>.</p>
  <form method="post" action="/ptt/admin/channels/import" enctype="multipart/form-data" class="form-inline">
    <div class="form-row"><input type="file" name="excel_file" accept=".xlsx,.xls" required></div>
    <button class="btn btn-success">استيراد</button>
    <a href="/ptt/admin/channels/template" class="btn btn-secondary">تحميل نموذج</a>
  </form>
</div>

<div class="card">
  <h3>قناة جديدة</h3>
  <form method="post" action="/ptt/admin/channels" class="form-inline">
    <div class="form-row"><label>الكود</label><input name="code" placeholder="drivers_north" required></div>
    <div class="form-row"><label>الاسم</label><input name="name" placeholder="سائقو المنطقة الشمالية" required></div>
    <div class="form-row" style="min-width:100%"><label>الوصف</label><textarea name="description" rows="1"></textarea></div>
    <div class="form-row"><label>الفرع</label>
      <select name="branch_id"><option value="">عامة (كل الفروع)</option>
        <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>القسم</label>
      <select name="department">
        <option value="">— كل الأقسام —</option>
        <?php foreach ($departments as $d): ?><option value="<?= Helpers::e($d) ?>"><?= Helpers::e($d) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>اللون</label><input type="color" name="color" value="#2563eb" style="width:50px;height:32px;padding:2px"></div>
    <div class="form-row"><label>الأيقونة</label><input name="icon" value="fa-solid fa-tower-broadcast"></div>
    <div class="form-row"><label>المشرف</label>
      <select name="owner_id"><option value="">—</option>
        <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>حد المدة (ثانية)</label><input type="number" name="max_duration_sec" placeholder="الافتراضي" min="5" max="600"></div>
    <div class="form-row"><label>خيارات</label>
      <label><input type="checkbox" name="is_active" checked> فعّالة</label>
      <label style="margin-right:10px"><input type="checkbox" name="is_readonly"> للقراءة فقط</label>
      <label style="margin-right:10px"><input type="checkbox" name="auto_sync"> مزامنة تلقائية مع القسم</label>
    </div>
    <button class="btn btn-success">إنشاء القناة</button>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h3>القنوات (<?= count($channels) ?>)</h3>
    <?php if (Auth::can('ptt.sync_departments')): ?>
      <button class="btn btn-primary" onclick="syncAllChannels(this)">مزامنة كل القنوات</button>
    <?php endif; ?>
  </div>
  <table>
    <tr><th>القناة</th><th>الفرع / القسم</th><th>الأعضاء</th><th>المشرف</th><th>الحالة</th><th></th></tr>
    <?php foreach ($channels as $c): ?>
    <tr>
      <td>
        <span style="display:inline-block;width:14px;height:14px;border-radius:4px;background:<?= Helpers::e($c['color']) ?>"></span>
        <strong><?= Helpers::e($c['name']) ?></strong>
        <small style="color:#6b7280"><code><?= Helpers::e($c['code']) ?></code></small>
        <?php if ($c['is_readonly']): ?><span class="badge badge-warning">قراءة فقط</span><?php endif; ?>
        <?php if (!empty($c['auto_sync'])): ?><span class="badge badge-info">ديناميكية</span><?php endif; ?>
      </td>
      <td><?= Helpers::e($c['branch_name'] ?? 'عامة') ?><?= $c['department'] ? ' / ' . Helpers::e($c['department']) : ' / كل الأقسام' ?></td>
      <td><?= (int)$c['members_count'] ?></td>
      <td><?= Helpers::e($c['owner_name'] ?? '—') ?></td>
      <td><span class="badge badge-<?= $c['is_active'] ? 'success' : 'secondary' ?>"><?= $c['is_active'] ? 'فعّالة' : 'معطلة' ?></span></td>
      <td style="white-space:nowrap">
        <button type="button" class="btn btn-sm btn-secondary edit-channel-btn"
          data-id="<?= $c['id'] ?>"
          data-code="<?= Helpers::e($c['code']) ?>"
          data-name="<?= Helpers::e($c['name']) ?>"
          data-description="<?= Helpers::e($c['description'] ?? '') ?>"
          data-branch-id="<?= $c['branch_id'] ?? '' ?>"
          data-department="<?= Helpers::e($c['department'] ?? '') ?>"
          data-color="<?= Helpers::e($c['color'] ?? '#2563eb') ?>"
          data-icon="<?= Helpers::e($c['icon'] ?? 'fa-solid fa-tower-broadcast') ?>"
          data-owner-id="<?= $c['owner_id'] ?? '' ?>"
          data-max-duration-sec="<?= (int)($c['max_duration_sec'] ?? 0) ?>"
          data-is-active="<?= $c['is_active'] ? '1' : '' ?>"
          data-is-readonly="<?= $c['is_readonly'] ? '1' : '' ?>"
          data-auto-sync="<?= !empty($c['auto_sync']) ? '1' : '' ?>"
        >تعديل</button>
        <?php if (!empty($c['auto_sync']) && Auth::can('ptt.sync_departments')): ?>
          <button class="btn btn-sm btn-info" onclick="syncChannel(<?= (int)$c['id'] ?>, this)">مزامنة</button>
        <?php endif; ?>
        <a href="/ptt/admin/channels/<?= $c['id'] ?>/members" class="btn btn-sm btn-primary">الأعضاء</a>
        <form method="post" action="/ptt/admin/channels/<?= $c['id'] ?>/delete" style="display:inline" onsubmit="return confirm('حذف القناة وكل رسائلها؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div id="editChannelModal" class="modal" style="display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;overflow:auto;background:rgba(0,0,0,0.4);">
  <div class="modal-content" style="background:#fff;margin:10% auto;padding:20px;max-width:700px;border-radius:8px;">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h3>تعديل القناة</h3>
      <button type="button" class="btn btn-sm btn-secondary" onclick="closeEditModal()">×</button>
    </div>
    <form id="editChannelForm" method="post" action="" class="form-inline">
      <input type="hidden" name="id">
      <div class="form-row"><label>الكود</label><input name="code" required></div>
      <div class="form-row"><label>الاسم</label><input name="name" required></div>
      <div class="form-row" style="min-width:100%"><label>الوصف</label><textarea name="description" rows="1"></textarea></div>
      <div class="form-row"><label>الفرع</label>
        <select name="branch_id"><option value="">عامة (كل الفروع)</option>
          <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= Helpers::e($b['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>القسم</label>
        <select name="department">
          <option value="">— كل الأقسام —</option>
          <?php foreach ($departments as $d): ?><option value="<?= Helpers::e($d) ?>"><?= Helpers::e($d) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>اللون</label><input type="color" name="color" style="width:50px;height:32px;padding:2px"></div>
      <div class="form-row"><label>الأيقونة</label><input name="icon"></div>
      <div class="form-row"><label>المشرف</label>
        <select name="owner_id"><option value="">—</option>
          <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['full_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-row"><label>حد المدة (ثانية)</label><input type="number" name="max_duration_sec" min="5" max="600"></div>
      <div class="form-row"><label>خيارات</label>
        <label><input type="checkbox" name="is_active"> فعّالة</label>
        <label style="margin-right:10px"><input type="checkbox" name="is_readonly"> للقراءة فقط</label>
        <label style="margin-right:10px"><input type="checkbox" name="auto_sync"> مزامنة تلقائية مع القسم</label>
      </div>
      <button class="btn btn-success">حفظ التعديل</button>
    </form>
  </div>
</div>

<script>
function parseSyncResponse(r) {
  const ct = r.headers.get('content-type') || '';
  if (!r.ok || !ct.includes('application/json')) {
    return r.text().then(text => Promise.reject('خطأ ' + r.status + (text ? ': ' + text.substring(0, 200) : '')));
  }
  return r.json();
}
function syncChannel(id, btn) {
  btn.disabled = true;
  fetch('/ptt/admin/channels/' + encodeURIComponent(id) + '/sync', { method: 'POST' })
    .then(parseSyncResponse).then(d => {
      alert(d.ok ? 'تمت مزامنة الأعضاء: ' + (d.added ?? 0) + ' عضو' : 'خطأ: ' + d.error);
      if (d.ok) location.reload();
    }).catch(e => alert('خطأ في الاتصال: ' + e)).finally(() => btn.disabled = false);
}
function syncAllChannels(btn) {
  btn.disabled = true;
  fetch('/ptt/admin/channels/sync-all', { method: 'POST' })
    .then(parseSyncResponse).then(d => {
      alert(d.ok ? 'تمت مزامنة القنوات' : 'خطأ: ' + d.error);
      if (d.ok) location.reload();
    }).catch(e => alert('خطأ في الاتصال: ' + e)).finally(() => btn.disabled = false);
}
document.querySelectorAll('.edit-channel-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const m = document.getElementById('editChannelModal');
    const f = document.getElementById('editChannelForm');
    f.action = '/ptt/admin/channels/' + encodeURIComponent(btn.dataset.id);
    f.querySelector('[name=id]').value = btn.dataset.id;
    f.querySelector('[name=code]').value = btn.dataset.code;
    f.querySelector('[name=name]').value = btn.dataset.name;
    f.querySelector('[name=description]').value = btn.dataset.description;
    f.querySelector('[name=branch_id]').value = btn.dataset.branchId;
    f.querySelector('[name=department]').value = btn.dataset.department;
    f.querySelector('[name=color]').value = btn.dataset.color;
    f.querySelector('[name=icon]').value = btn.dataset.icon;
    f.querySelector('[name=owner_id]').value = btn.dataset.ownerId;
    f.querySelector('[name=max_duration_sec]').value = btn.dataset.maxDurationSec || '';
    f.querySelector('[name=is_active]').checked = !!btn.dataset.isActive;
    f.querySelector('[name=is_readonly]').checked = !!btn.dataset.isReadonly;
    f.querySelector('[name=auto_sync]').checked = !!btn.dataset.autoSync;
    m.style.display = 'block';
  });
});
function closeEditModal() { document.getElementById('editChannelModal').style.display = 'none'; }
window.onclick = function(e) { if (e.target === document.getElementById('editChannelModal')) closeEditModal(); };
</script>
