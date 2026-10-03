<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>إضافة قسم مطبخ جديد</h3>
  <form method="post" action="/kitchen/sections" class="form-inline">
    <div class="form-row"><label>اسم القسم</label><input name="name" required placeholder="مثال: المطبخ الشرقي"></div>
    <div class="form-row"><label>الترتيب</label><input type="number" name="sort_order" value="0"></div>
    <button class="btn btn-success">حفظ</button>
  </form>
</div>

<div class="card">
  <h3>أقسام المطبخ</h3>
  <table>
    <tr><th>الاسم</th><th>الترتيب</th><th>إجراء</th></tr>
    <?php foreach ($sections as $s): ?>
    <tr>
      <td>
        <form method="post" action="/kitchen/sections/<?= (int)$s['id'] ?>" class="form-inline" style="margin:0">
          <input name="name" value="<?= Helpers::e($s['name']) ?>" required>
      </td>
      <td><input type="number" name="sort_order" value="<?= (int)$s['sort_order'] ?>" style="width:80px"></td>
      <td>
          <button class="btn btn-sm btn-success">تحديث</button>
        </form>
        <form method="post" action="/kitchen/sections/<?= (int)$s['id'] ?>/delete" style="display:inline" onsubmit="return confirm('هل أنت متأكد من حذف القسم؟');">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$sections): ?><tr><td colspan="3">لا توجد أقسام مطبخ بعد</td></tr><?php endif; ?>
  </table>
</div>
