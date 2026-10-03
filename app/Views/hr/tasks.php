<?php use App\Core\Helpers; ?>
<?php $statusLabels = ['open'=>'مفتوحة','in_progress'=>'قيد التنفيذ','done'=>'منجزة','cancelled'=>'ملغاة']; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">إسناد مهمة جديدة</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <a href="/hr/tasks/export?format=excel" class="btn btn-sm btn-primary"><i class="fa-solid fa-file-excel"></i> Excel</a>
      <a href="/hr/tasks/export?format=pdf" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf"></i> PDF</a>
    </div>
  </div>
  <form method="post" action="/hr/tasks" class="form-inline">
    <div class="form-row"><label>العنوان</label><input name="title" required></div>
    <div class="form-row"><label>الوصف</label><input name="description"></div>
    <div class="form-row"><label>إلى الموظف</label>
      <select name="assigned_to" required>
        <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= Helpers::e($e['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الاستحقاق</label><input type="date" name="due_date"></div>
    <button class="btn btn-success">إسناد</button>
  </form>
</div>

<div class="card">
  <h3>المهام</h3>
  <table>
    <tr><th>العنوان</th><th>المكلَّف</th><th>الاستحقاق</th><th>الحالة</th><th>بواسطة</th><th>تحديث</th></tr>
    <?php foreach ($tasks as $t): ?>
    <tr>
      <td><?= Helpers::e($t['title']) ?>
        <?php if ($t['description']): ?><br><small style="color:#6b7280"><?= Helpers::e($t['description']) ?></small><?php endif; ?></td>
      <td><?= Helpers::e($t['assignee']) ?></td>
      <td><?= $t['due_date'] ?? '-' ?>
        <?php if ($t['due_date'] && $t['due_date'] < date('Y-m-d') && $t['status'] !== 'done'): ?>
          <span class="badge badge-danger">متأخرة</span>
        <?php endif; ?></td>
      <td><span class="badge badge-<?= $t['status'] === 'done' ? 'success' : ($t['status'] === 'cancelled' ? 'danger' : 'info') ?>">
        <?= $statusLabels[$t['status']] ?></span></td>
      <td><?= Helpers::e($t['creator'] ?? '-') ?></td>
      <td>
        <?php if (!in_array($t['status'], ['done', 'cancelled'])): ?>
        <form method="post" action="/hr/tasks/<?= $t['id'] ?>/status" class="form-inline">
          <select name="status">
            <option value="in_progress">قيد التنفيذ</option>
            <option value="done">منجزة</option>
            <option value="cancelled">ملغاة</option>
          </select>
          <button class="btn btn-sm">حفظ</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$tasks): ?><tr><td colspan="6">لا توجد مهام</td></tr><?php endif; ?>
  </table>
</div>
