<?php use App\Core\Helpers; ?>
<?php
$typeLabels = ['raw'=>'مواد أولية','prepared'=>'مواد محضرة','asset'=>'موجودات','tool'=>'أدوات','clothing'=>'ملابس','all'=>'الكل'];
$current = $type ?? 'all';
?>
<div class="card">
  <h3>تقرير المخزون حسب التصنيف</h3>
  <form method="get" action="/inventory/reports" class="form-inline">
    <div class="form-row"><label>التصنيف</label>
      <select name="type">
        <?php foreach ($typeLabels as $k => $v): ?>
          <option value="<?= $k ?>" <?= $k === $current ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-secondary">عرض</button>
  </form>
</div>

<div class="card">
  <table>
    <tr><th>التصنيف</th><th>عدد الأصناف</th><th>إجمالي الكمية</th><th>القيمة الإجمالية</th></tr>
    <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= $typeLabels[$row['category_type']] ?? $row['category_type'] ?></td>
      <td><?= (int) $row['items_count'] ?></td>
      <td><?= number_format((float) $row['total_qty'], 3) ?></td>
      <td><?= Helpers::money($row['total_value']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4">لا توجد بيانات</td></tr><?php endif; ?>
  </table>
</div>
