<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h3>تقرير الأصول الثابتة</h3>
</div>
<div class="card">
  <table>
    <tr><th>الرقم</th><th>الاسم</th><th>الفئة</th><th>الفرع</th><th>التكلفة</th><th>القيمة الدفترية</th><th>الحالة</th></tr>
    <?php foreach ($data as $a): ?>
    <tr>
      <td><?= Helpers::e($a['asset_no']) ?></td>
      <td><?= Helpers::e($a['name']) ?></td>
      <td><?= Helpers::e($a['category_name'] ?? '-') ?></td>
      <td><?= Helpers::e($a['branch_name'] ?? '-') ?></td>
      <td><?= Helpers::money($a['purchase_cost']) ?></td>
      <td><?= Helpers::money($a['book_value']) ?></td>
      <td><?= Helpers::e($a['status']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
