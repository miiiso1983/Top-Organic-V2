<?php use App\Core\Helpers; ?>
<div class="card">
  <h2><i class="fa-solid fa-utensils"></i> استيراد تكلفة الأطباق</h2>
  <p>ارفع ملف Excel الذي يحتوي على الأوراق الأربع: <strong>سعر اخر شراء</strong>، <strong>كلفة المحضر</strong>، <strong>كلفة مواد</strong>، <strong>Cost Of Plates</strong>.</p>

  <form method="post" action="/inventory/plate-cost-imports" enctype="multipart/form-data" class="form-inline" style="margin-top:12px">
    <div class="form-row">
      <label>ملف Excel</label>
      <input type="file" name="import_file" accept=".xlsx,.xls" required>
    </div>
    <button class="btn btn-primary">رفع وحساب</button>
  </form>
</div>

<div class="card" style="margin-top:16px">
  <h3><i class="fa-solid fa-scroll"></i> سجل عمليات الاستيراد</h3>
  <div style="overflow:auto">
    <table>
      <tr>
        <th>#</th>
        <th>الملف</th>
        <th>الحالة</th>
        <th>التاريخ</th>
        <th>إجراء</th>
      </tr>
      <?php foreach ($runs as $r): ?>
      <tr>
        <td><?= (int) $r['id'] ?></td>
        <td><?= Helpers::e($r['source_filename']) ?></td>
        <td>
          <?php if ($r['status'] === 'success'): ?><span class="badge badge-success">نجاح</span>
          <?php elseif ($r['status'] === 'partial'): ?><span class="badge badge-warning">جزئي</span>
          <?php elseif ($r['status'] === 'error'): ?><span class="badge badge-danger">خطأ</span>
          <?php else: ?><span class="badge"><?= Helpers::e($r['status']) ?></span>
          <?php endif; ?>
        </td>
        <td><?= Helpers::e($r['created_at']) ?></td>
        <td><a href="/inventory/plate-cost-imports/<?= (int) $r['id'] ?>" class="btn btn-sm">عرض</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($runs)): ?>
      <tr><td colspan="5" class="text-center">لا توجد عمليات استيراد</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>
