<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>الوصفات وتكلفة الوجبات</h3>
  <table>
    <tr><th>الصنف</th><th>سعر البيع</th><th>التكلفة</th><th>هامش الربح</th><th>عدد المكونات</th><th></th></tr>
    <?php foreach ($items as $it): ?>
    <?php $margin = (float)$it['price'] - (float)$it['cost']; ?>
    <tr>
      <td><?= Helpers::e($it['name']) ?></td>
      <td><?= Helpers::money($it['price']) ?></td>
      <td><?= Helpers::money($it['cost']) ?></td>
      <td style="color:<?= $margin >= 0 ? '#16a34a' : '#dc2626' ?>"><?= Helpers::money($margin) ?></td>
      <td><?= $it['ingredients_count'] ?></td>
      <td><a class="btn btn-sm" href="/recipes/<?= $it['id'] ?>">تحرير الوصفة</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="6">أضف أصنافاً في قائمة الطعام أولاً</td></tr><?php endif; ?>
  </table>
</div>
