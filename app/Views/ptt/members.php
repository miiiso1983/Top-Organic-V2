<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2>أعضاء: <?= Helpers::e($channel['name']) ?></h2>
  <a href="/ptt/admin" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
  <h3>إضافة / تعديل عضو</h3>
  <form method="post" action="/ptt/admin/channels/<?= $channel['id'] ?>/members" class="form-inline">
    <div class="form-row"><label>المستخدم</label>
      <select name="user_id" required><option value="">— اختر —</option>
        <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= Helpers::e($u['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الدور</label>
      <select name="role"><option value="member">عضو</option><option value="supervisor">مشرف</option></select>
    </div>
    <div class="form-row"><label>الصلاحيات</label>
      <label><input type="checkbox" name="can_send" checked> إرسال</label>
      <label style="margin-right:8px"><input type="checkbox" name="can_broadcast_live"> بث مباشر</label>
      <label style="margin-right:8px"><input type="checkbox" name="muted"> مكتوم</label>
    </div>
    <button class="btn btn-success">حفظ العضو</button>
  </form>
  <small style="color:#6b7280">اختيار مستخدم موجود مسبقاً يحدّث صلاحياته.</small>
</div>

<div class="card">
  <h3>الأعضاء (<?= count($members) ?>)</h3>
  <table>
    <tr><th>الاسم</th><th>الدور الوظيفي</th><th>دور القناة</th><th>إرسال</th><th>بث مباشر</th><th>مكتوم</th><th></th></tr>
    <?php foreach ($members as $m): ?>
    <tr>
      <td><?= Helpers::e($m['full_name']) ?> <small style="color:#6b7280">(<?= Helpers::e($m['username']) ?>)</small></td>
      <td><?= Helpers::e($m['role_name'] ?? '—') ?></td>
      <td><?= $m['role'] === 'supervisor' ? '⭐ مشرف' : 'عضو' ?></td>
      <td><?= $m['can_send'] ? '<i class="fa-solid fa-check" style="color:#16a34a"></i>' : '🚫' ?></td>
      <td><?= $m['can_broadcast_live'] ? '<i class="fa-solid fa-check" style="color:#16a34a"></i>' : '—' ?></td>
      <td><?= $m['muted'] ? '🔇' : '—' ?></td>
      <td>
        <form method="post" action="/ptt/admin/channels/<?= $channel['id'] ?>/members/<?= $m['user_id'] ?>/delete" style="display:inline" onsubmit="return confirm('إزالة العضو؟')">
          <button class="btn btn-sm btn-danger">إزالة</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$members): ?><tr><td colspan="7">لا أعضاء بعد</td></tr><?php endif; ?>
  </table>
</div>
