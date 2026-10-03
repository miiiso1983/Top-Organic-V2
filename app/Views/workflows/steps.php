<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2>خطوات الموافقة: <?= Helpers::e($template['name']) ?></h2>
  <a href="/workflows/templates" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
  <h3>إضافة خطوة</h3>
  <form method="post" action="/workflows/templates/<?= $template['id'] ?>/steps" class="form-inline">
    <div class="form-row"><label>عنوان الخطوة</label><input name="title" placeholder="مثال: موافقة المدير المباشر" required></div>
    <div class="form-row"><label>نوع المعتمد</label>
      <select name="approver_type">
        <option value="position">مسمى وظيفي (الهيكل)</option>
        <option value="role">دور</option>
        <option value="direct_manager">المدير المباشر</option>
        <option value="department_manager">مدير القسم</option>
      </select>
    </div>
    <div class="form-row approver-position"><label>المسمى الوظيفي (من الهيكل)</label>
      <select name="job_title_id"><option value="">—</option>
        <?php foreach ($positions as $p): ?><option value="<?= $p['id'] ?>"><?= Helpers::e($p['title']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الدور المسؤول (احتياطي)</label>
      <select name="role_id"><option value="">—</option>
        <?php foreach ($roles as $role): ?><option value="<?= $role['id'] ?>"><?= Helpers::e($role['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الحد الأدنى للمبلغ</label><input type="number" name="min_amount" value="0"></div>
    <div class="form-row"><label>نوع الخطوة</label>
      <select name="step_type">
        <option value="sequential">تسلسلية</option>
        <option value="parallel">متعددة المستويات / متوازية</option>
      </select>
    </div>
    <div class="form-row"><label>عدد الموافقات المطلوبة (0 = جميع المعتمدين)</label><input type="number" name="required_count" value="1"></div>
    <div class="form-row" style="min-width:100%"><label>شروط (JSON)</label><textarea name="condition" rows="2" placeholder='{"department":"المطبخ"}'></textarea></div>
    <div class="form-row" style="min-width:100%">
      <label style="display:inline; margin-left:12px"><input type="checkbox" name="can_return_for_edit" value="1" checked> سماح بإعادة الطلب للتعديل</label>
      <label style="display:inline; margin-left:12px"><input type="checkbox" name="is_final" value="1"> الخطوة النهائية</label>
    </div>
    <div class="form-row" style="min-width:100%">
      <label style="display:inline; margin-left:12px"><input type="checkbox" name="notify_push" value="1" checked> Push</label>
      <label style="display:inline; margin-left:12px"><input type="checkbox" name="notify_email" value="1"> Email</label>
      <label style="display:inline; margin-left:12px"><input type="checkbox" name="notify_whatsapp" value="1"> WhatsApp</label>
    </div>
    <button class="btn btn-success">إضافة الخطوة</button>
  </form>
</div>

<div class="card">
  <h3>الخطوات المحددة</h3>
  <table>
    <?php
    $approverTypeLabels = [
        'position' => 'مسمى وظيفي',
        'role' => 'دور',
        'direct_manager' => 'المدير المباشر',
        'department_manager' => 'مدير القسم',
    ];
    ?>
    <tr><th>الترتيب</th><th>العنوان</th><th>نوع المعتمد</th><th>المسمى/الدور</th><th>الحد</th><th>النوع</th><th>الموافقات</th><th>الإشعارات</th><th>شروط</th><th></th></tr>
    <?php foreach ($steps as $s): ?>
    <?php $ch = json_decode($s['notify_channels'] ?? '{}', true); ?>
    <?php $approverType = $s['approver_type'] ?? 'position'; ?>
    <tr>
      <td><?= $s['step_order'] ?></td>
      <td><?= Helpers::e($s['title']) ?></td>
      <td><?= Helpers::e($approverTypeLabels[$approverType] ?? $approverType) ?></td>
      <td><?= Helpers::e($s['job_title'] ?? $s['role_name'] ?? '-') ?></td>
      <td><?= number_format((float)$s['min_amount'], 0) ?></td>
      <td><?= $s['step_type'] === 'parallel' ? 'متوازية' : 'تسلسلية' ?></td>
      <td><?= $s['step_type'] === 'parallel' ? ($s['required_count'] ?: 'الكل') : '1' ?></td>
      <td><?= ($ch['push'] ?? 0 ? 'Push ' : '') . ($ch['email'] ?? 0 ? 'Email ' : '') . ($ch['whatsapp'] ?? 0 ? 'WhatsApp' : '') ?></td>
      <td><code><?= Helpers::e($s['condition_json'] ?? '-') ?></code></td>
      <td>
        <form method="post" action="/workflows/templates/<?= $template['id'] ?>/steps/<?= $s['id'] ?>/delete" style="display:inline" onsubmit="return confirm('حذف الخطوة؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
