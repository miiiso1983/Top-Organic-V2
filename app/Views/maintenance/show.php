<?php use App\Core\Helpers; ?>
<?php
$statusBadge = [
    'pending' => 'warning', 'assigned' => 'info', 'in_progress' => 'primary',
    'completed' => 'success', 'confirmed' => 'success', 'cancelled' => 'danger',
][$row['status']] ?? 'secondary';
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">طلب الصيانة <?= Helpers::e($row['request_number']) ?></h3>
    <a href="/maintenance" class="btn btn-sm btn-secondary">← العودة</a>
  </div>
</div>
<div class="card" style="margin-top:16px">
  <div class="info-grid">
    <div class="info-item"><span class="info-label">العطل</span><span class="info-value"><?= Helpers::e($row['title']) ?></span></div>
    <div class="info-item"><span class="info-label">الجهاز</span><span class="info-value"><?= Helpers::e($row['device_name']) ?></span></div>
    <div class="info-item"><span class="info-label">الحالة</span><span class="info-value"><span class="badge badge-<?= $statusBadge ?>"><?= $statuses[$row['status']] ?? $row['status'] ?></span></span></div>
    <div class="info-item"><span class="info-label">الأهمية</span><span class="info-value"><?= Helpers::e($priorities[$row['priority']] ?? $row['priority'] ?? '-') ?></span></div>
    <div class="info-item"><span class="info-label">صاحب الطلب</span><span class="info-value"><?= Helpers::e($row['requester_name'] ?? '-') ?></span></div>
    <div class="info-item"><span class="info-label">المعين</span><span class="info-value"><?= Helpers::e($row['assigned_name'] ?? '-') ?></span></div>
    <?php if (!empty($row['work_status'])): ?>
    <div class="info-item"><span class="info-label">حالة الصيانة</span><span class="info-value"><?= Helpers::e($workStatuses[$row['work_status']] ?? $row['work_status']) ?></span></div>
    <?php endif; ?>
    <?php if ($row['cost'] !== null && $row['cost'] !== ''): ?>
    <div class="info-item"><span class="info-label">تكلفة الصيانة</span><span class="info-value"><?= number_format((float)$row['cost'], 2) ?></span></div>
    <?php endif; ?>
    <div class="info-item"><span class="info-label">تاريخ الإنشاء</span><span class="info-value"><?= Helpers::e($row['created_at']) ?></span></div>
  </div>
  <?php if ($row['description']): ?>
  <div style="margin-top:12px"><strong>ملاحظات:</strong><br><?= nl2br(Helpers::e($row['description'])) ?></div>
  <?php endif; ?>
  <?php if (!empty($row['resolution_notes'])): ?>
  <div style="margin-top:12px"><strong>الملاحظات والأدوات المستخدمة:</strong><br><?= nl2br(Helpers::e($row['resolution_notes'])) ?></div>
  <?php endif; ?>
  <?php if ($row['image_path']): ?>
  <div style="margin-top:12px"><strong>صورة العطل:</strong><br>
    <a href="<?= Helpers::e($row['image_path']) ?>" target="_blank"><img src="<?= Helpers::e($row['image_path']) ?>" style="max-width:300px;border-radius:8px;border:1px solid var(--border)"></a>
  </div>
  <?php endif; ?>
</div>

<?php if ($isMaintManager && $row['status'] === 'pending'): ?>
<div class="card" style="margin-top:16px">
  <h4>تعيين المهمة لموظف صيانة</h4>
  <form method="post" action="/maintenance/<?= $row['id'] ?>/assign" class="form-inline">
    <div class="form-row" style="min-width:100%">
      <label>اختر الموظف</label>
      <select name="assigned_to" class="select2-shift" required data-placeholder="اختر موظف الصيانة" style="width:100%">
        <option value="">— اختر موظف —</option>
        <?php foreach ($employees as $e): ?>
        <option value="<?= $e['id'] ?>"><?= Helpers::e($e['full_name']) ?> (<?= Helpers::e($e['emp_code']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary">تعيين</button>
  </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <?php if ($row['status'] === 'assigned' && $isAssignedToMe): ?>
  <form method="post" action="/maintenance/<?= $row['id'] ?>/start" style="display:inline">
    <button class="btn btn-primary"><i class="fa-solid fa-play"></i> الموافقة وبدء المهمة</button>
  </form>
  <?php endif; ?>
  <?php if ($row['status'] === 'in_progress' && $isAssignedToMe): ?>
  <form method="post" action="/maintenance/<?= $row['id'] ?>/finish" class="form-inline" onsubmit="return confirm('تأكيد حفظ تحديث حالة الصيانة؟')">
    <div class="form-row" style="min-width:100%">
      <label>حالة الصيانة <small class="text-danger">*</small></label>
      <select name="work_status" required>
        <?php foreach ($workStatuses as $key => $label): ?>
        <option value="<?= Helpers::e($key) ?>"><?= Helpers::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row" style="min-width:100%">
      <label>الملاحظات والأدوات المستخدمة</label>
      <textarea name="resolution_notes" rows="3" placeholder="اكتب الملاحظات والأدوات المستخدمة"></textarea>
    </div>
    <div class="form-row" style="min-width:100%">
      <label>تكلفة الصيانة (اختياري)</label>
      <input type="number" step="0.01" name="cost" placeholder="0.00">
    </div>
    <button class="btn btn-success"><i class="fa-solid fa-check"></i> حفظ تحديث الحالة</button>
  </form>
  <?php endif; ?>
  <?php if ($row['status'] === 'completed' && $isRequester): ?>
  <form method="post" action="/maintenance/<?= $row['id'] ?>/confirm" style="display:inline" onsubmit="return confirm('تأكيد إنجاز العمل؟')">
    <button class="btn btn-success"><i class="fa-solid fa-thumbs-up"></i> تأكيد إنجاز العمل</button>
  </form>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
  <h4>سجل الحالة</h4>
  <table class="table">
    <thead><tr><th>التاريخ</th><th>الحالة</th><th>بواسطة</th><th>ملاحظة</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= Helpers::e($l['created_at']) ?></td>
        <td><?= Helpers::e($l['old_status'] ?? '-') ?> → <?= Helpers::e($l['new_status']) ?></td>
        <td><?= Helpers::e($l['changer_name'] ?? '-') ?></td>
        <td><?= Helpers::e($l['notes'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="4" class="text-center">لا يوجد سجل</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

