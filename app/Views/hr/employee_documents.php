<?php use App\Core\Helpers; ?>
<?php $isModal = !empty($_GET['modal']); $modalQuery = $isModal ? '?modal=1' : ''; ?>
<div class="card">
  <h3><i class="fa-solid fa-paperclip"></i> مستندات الموظف: <?= Helpers::e($employee['full_name']) ?> <small>(<?= Helpers::e($employee['emp_code']) ?>)</small></h3>
  <p>
    <a href="/hr/employees/<?= $employee['id'] ?><?= $modalQuery ?>" class="btn btn-sm btn-secondary">← الملف الشخصي</a>
    <?php if ($isModal): ?>
    <button type="button" class="btn btn-sm btn-danger" onclick="if(window.parent && window.parent.closeEmployeeModal){ window.parent.closeEmployeeModal(); } else { history.back(); }">
      <i class="fa-solid fa-xmark"></i> إغلاق
    </button>
    <?php endif; ?>
    <a href="/hr/employees/<?= $employee['id'] ?>/edit<?= $modalQuery ?>" class="btn btn-sm btn-primary">تعديل البيانات</a>
    <a href="/hr/employees/<?= $employee['id'] ?>/audit<?= $modalQuery ?>" class="btn btn-sm btn-secondary">سجل التدقيق</a>
  </p>
</div>

<div class="card" style="margin-top:16px">
  <h4>إضافة مستند / عقد</h4>
  <form method="post" action="/hr/employees/<?= $employee['id'] ?>/documents<?= $modalQuery ?>" enctype="multipart/form-data" class="form-inline">
    <div class="form-row"><label>نوع المستند</label>
      <select name="doc_type">
        <option value="id">هوية/إقامة</option>
        <option value="contract">عقد عمل</option>
        <option value="certificate">شهادة</option>
        <option value="medical">تقرير طبي</option>
        <option value="visa">تأشيرة</option>
        <option value="other">أخرى</option>
      </select>
    </div>
    <div class="form-row"><label>العنوان</label><input name="title" required></div>
    <div class="form-row"><label>تاريخ الإصدار</label><input type="date" name="issue_date"></div>
    <div class="form-row"><label>تاريخ الانتهاء</label><input type="date" name="expiry_date"></div>
    <div class="form-row"><label>ملف</label><input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
    <div class="form-row"><label>ملاحظات</label><input name="notes"></div>
    <button class="btn btn-success">رفع المستند</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <h4>قائمة المستندات</h4>
  <div style="overflow:auto">
    <table>
      <tr><th>#</th><th>النوع</th><th>العنوان</th><th>تاريخ الإصدار</th><th>تاريخ الانتهاء</th><th>ملاحظات</th><th>أضيف بواسطة</th><th>التاريخ</th><th></th></tr>
      <?php foreach ($documents as $i => $d): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= Helpers::e($d['doc_type']) ?></td>
        <td><?= Helpers::e($d['title']) ?></td>
        <td><?= $d['issue_date'] ?? '-' ?></td>
        <td><?= $d['expiry_date'] ?? '-' ?></td>
        <td><?= Helpers::e($d['notes'] ?? '-') ?></td>
        <td><?= Helpers::e($d['uploaded_by_name'] ?? '-') ?></td>
        <td><?= $d['created_at'] ?></td>
        <td>
          <?php if ($d['file_path']): ?><a href="<?= Helpers::e($d['file_path']) ?>" target="_blank" class="btn btn-sm btn-info">عرض</a><?php endif; ?>
          <form method="post" action="/hr/employees/documents/<?= $d['id'] ?>/delete<?= $modalQuery ?>" style="display:inline" onsubmit="return confirm('حذف المستند؟')">
            <button class="btn btn-sm btn-danger">حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$documents): ?><tr><td colspan="9">لا توجد مستندات</td></tr><?php endif; ?>
    </table>
  </div>
</div>
