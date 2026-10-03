<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>تعديل المستخدم: <?= Helpers::e($user['full_name']) ?></h3>
  <form method="post" action="/admin/users/<?= (int)$user['id'] ?>/edit" class="form-inline">
    <div class="form-row"><label>اسم المستخدم</label><input name="username" value="<?= Helpers::e($user['username']) ?>" required></div>
    <div class="form-row"><label>كلمة المرور الجديدة</label><input type="password" name="password" placeholder="اتركها فارغة إذا لم ترد التغيير"></div>
    <div class="form-row"><label>الاسم الكامل</label><input name="full_name" value="<?= Helpers::e($user['full_name']) ?>" required></div>
    <div class="form-row"><label>الدور</label>
      <select name="role_id" required>
        <?php foreach ($roles as $r): ?>
          <option value="<?= $r['id'] ?>" <?= $r['id'] == $user['role_id'] ? 'selected' : '' ?>><?= Helpers::e($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الفرع</label>
      <select name="branch_id">
        <option value="">— كل الفروع —</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $b['id'] == $user['branch_id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>نشط</label><input type="checkbox" name="is_active" value="1" <?= $user['is_active'] ? 'checked' : '' ?>></div>
    <div class="form-row">
      <button class="btn btn-success">حفظ</button>
      <a href="/admin/users" class="btn btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<div class="card">
  <h3>الصلاحيات الممنوحة للدور: <?= Helpers::e($user['role_name']) ?></h3>
  <p class="text-muted" style="font-size:13px;margin-bottom:10px">
    هذه الصلاحيات مرتبطة بالدور. لتعديلها، عدّل الدور من صفحة <a href="/admin/users">المستخدمون والصلاحيات</a>.
  </p>
  <?php
    $byModule = [];
    foreach ($permissions as $p) { $byModule[$p['module']][] = $p; }
  ?>
  <?php foreach ($byModule as $module => $perms): ?>
  <fieldset style="border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px;padding:8px">
    <legend style="font-weight:bold"><?= Helpers::e($module) ?></legend>
    <?php foreach ($perms as $p): ?>
    <label style="display:inline-block;margin-inline-end:14px;opacity:<?= in_array($p['id'], $rolePermIds) ? '1' : '0.5' ?>">
      <input type="checkbox" disabled <?= in_array($p['id'], $rolePermIds) ? 'checked' : '' ?>>
      <?= Helpers::e($p['name']) ?>
    </label>
    <?php endforeach; ?>
  </fieldset>
  <?php endforeach; ?>
</div>

<div class="card">
  <h3>قوالب سير العمل المتاحة</h3>
  <p class="text-muted" style="font-size:13px;margin-bottom:10px">
    أنواع الطلبات التي يستطيع هذا المستخدم إنشاؤها أو عرض طلباتها من التطبيق/الويب. تُدار عبر صلاحيات الدور.
  </p>
  <?php if (empty($workflowTemplates)): ?>
  <div class="alert alert-info">لا توجد قوالب سير عمل نشطة.</div>
  <?php else: ?>
  <table>
    <tr><th>القالب</th><th>الكود</th><th>إنشاء</th><th>عرض الكل</th></tr>
    <?php foreach ($workflowTemplates as $t): ?>
    <?php $tr = $templateRolePerms[$t['id']] ?? null; ?>
    <tr>
      <td><?= Helpers::e($t['name']) ?></td>
      <td><code><?= Helpers::e($t['code']) ?></code></td>
      <td><?= !empty($tr['can_create']) ? '<i class="fa-solid fa-check" style="color:green"></i>' : '—' ?></td>
      <td><?= !empty($tr['can_view_all']) ? '<i class="fa-solid fa-check" style="color:green"></i>' : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
