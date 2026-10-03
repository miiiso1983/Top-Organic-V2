<?php use App\Core\Helpers; ?>
<div class="card" style="margin-bottom:16px">
  <h2>أعمار الديون — <?= $type === 'customer' ? 'العملاء' : 'الموردين' ?></h2>
  <div class="form-inline" style="margin-top:12px">
    <a href="?type=customer" class="btn <?php if ($type === 'customer') echo 'btn-success'; ?>">العملاء</a>
    <a href="?type=supplier" class="btn <?php if ($type === 'supplier') echo 'btn-success'; ?>">الموردين</a>
  </div>
</div>
<div class="grid grid-5" style="margin-bottom:16px">
  <?php foreach ([30, 60, 90, 120] as $i => $d): ?>
  <div class="card" style="text-align:center">
    <div style="font-size:24px; font-weight:bold"><?= Helpers::money($buckets['buckets'][$i]) ?></div>
    <div>حتى <?= $d ?> يوم</div>
  </div>
  <?php endforeach; ?>
  <div class="card" style="text-align:center">
    <div style="font-size:24px; font-weight:bold"><?= Helpers::money($buckets['buckets'][4]) ?></div>
    <div>أكثر من 120</div>
  </div>
</div>
<div class="card">
  <table>
    <tr><th>الاسم</th><th>الرصيد المستحق</th><th>الحساب المفتوح</th></tr>
    <?php foreach ($parties as $p): ?>
    <tr>
      <td><?= Helpers::e($p['name']) ?></td>
      <td><?= Helpers::money($p['balance']) ?></td>
      <td><?= Helpers::money($p['open_amount']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
