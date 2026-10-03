<?php use App\Core\Helpers; ?>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-value" style="color:#16a34a"><?= Helpers::money($data['cash']) ?></div><div class="stat-label">الصناديق</div></div>
  <div class="stat-card"><div class="stat-value" style="color:#2980b9"><?= Helpers::money($data['bank']) ?></div><div class="stat-label">البنوك</div></div>
  <div class="stat-card"><div class="stat-value" style="color:#f39c12"><?= Helpers::money($data['ar']) ?></div><div class="stat-label">الذمم المدينة</div></div>
  <div class="stat-card"><div class="stat-value" style="color:#c0392b"><?= Helpers::money($data['ap']) ?></div><div class="stat-label">الذمم الدائنة</div></div>
  <div class="stat-card"><div class="stat-value"><?= Helpers::money($data['monthRevenue']) ?></div><div class="stat-label">إيرادات الشهر</div></div>
  <div class="stat-card"><div class="stat-value"><?= Helpers::money($data['monthExpenses']) ?></div><div class="stat-label">مصروفات الشهر</div></div>
  <div class="stat-card"><div class="stat-value" style="color:<?= $data['netProfit'] >= 0 ? '#16a34a' : '#c0392b' ?>"><?= Helpers::money($data['netProfit']) ?></div><div class="stat-label">صافي الشهر</div></div>
</div>

<div class="card">
  <h3>إيرادات آخر 30 يوم</h3>
  <table>
    <tr><th>التاريخ</th><th>الإيراد</th></tr>
    <?php foreach ($data['dailyRevenue'] as $d): ?>
    <tr><td><?= $d['d'] ?></td><td><?= Helpers::money($d['total']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>
