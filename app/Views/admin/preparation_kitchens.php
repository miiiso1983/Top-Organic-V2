<?php use App\Core\{Helpers, Auth}; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-utensils"></i> مراكز التحضير</h2>
</div>

<div class="grid grid-2">
  <div class="card" id="form-card">
    <h3 id="form-title">إضافة مركز تحضير</h3>
    <form id="kitchen-form" method="post" action="/admin/preparation-kitchens">
      <input type="hidden" id="kitchen-id" value="">
      <div class="form-row">
        <label>الاسم</label>
        <input id="kitchen-name" name="name" required>
      </div>
      <div class="form-row">
        <label>الترتيب</label>
        <input id="kitchen-sort" type="number" name="sort_order" value="0">
      </div>
      <div class="form-inline">
        <button class="btn btn-success" id="form-submit">حفظ</button>
        <button type="button" class="btn btn-secondary" id="form-cancel" style="display:none" onclick="resetForm()">إلغاء</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>المراكز الحالية</h3>
    <table>
      <tr><th>الاسم</th><th>الطابعة</th><th>النوع</th><th>الترتيب</th><th>شاشة KDS</th><th></th></tr>
      <?php foreach ($kitchens as $k): ?>
      <tr>
        <td><?= Helpers::e($k['name']) ?></td>
        <td><?= Helpers::e($k['printer_name'] ?: '-') ?></td>
      <td><?= Helpers::e($k['printer_type'] ? ($printerTypes[$k['printer_type']] ?? $k['printer_type']) : '-') ?></td>
        <td><?= (int) $k['sort_order'] ?></td>
        <td><a href="/kitchen/preparation/<?= (int) $k['id'] ?>" target="_blank" class="btn btn-sm btn-secondary">فتح الشاشة</a></td>
        <td>
          <button type="button" class="btn btn-sm btn-primary" onclick='editKitchen(<?= json_encode($k, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>تعديل</button>
          <form method="post" action="/admin/preparation-kitchens/<?= (int) $k['id'] ?>/delete" onsubmit="return confirm('تأكيد الحذف؟')" style="display:inline">
            <button class="btn btn-sm btn-danger">حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$kitchens): ?><tr><td colspan="5">لا توجد مراكز تحضير</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<script>
function editKitchen(k) {
  const form = document.getElementById('kitchen-form');
  form.action = '/admin/preparation-kitchens/' + k.id;
  document.getElementById('form-title').textContent = 'تعديل: ' + k.name;
  document.getElementById('form-submit').textContent = 'حفظ التعديلات';
  document.getElementById('form-cancel').style.display = '';
  document.getElementById('kitchen-id').value = k.id;
  document.getElementById('kitchen-name').value = k.name || '';
  document.getElementById('kitchen-sort').value = k.sort_order || 0;
  document.getElementById('form-card').scrollIntoView({ behavior: 'smooth' });
}
function resetForm() {
  const form = document.getElementById('kitchen-form');
  form.action = '/admin/preparation-kitchens';
  form.reset();
  document.getElementById('form-title').textContent = 'إضافة مركز تحضير';
  document.getElementById('form-submit').textContent = 'حفظ';
  document.getElementById('form-cancel').style.display = 'none';
  document.getElementById('kitchen-id').value = '';
}
</script>
