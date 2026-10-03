<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-chart-line"></i> لوحة تحليلات PTT</h2>
  <a href="/ptt" class="btn btn-secondary">رجوع</a>
</div>

<div class="grid grid-3">
  <div class="card stat"><div class="value"><?= $stats['today']['total'] ?></div><div class="label">رسائل اليوم</div></div>
  <div class="card stat"><div class="value" style="color:#d97706"><?= $stats['today']['urgent'] ?></div><div class="label">عاجلة اليوم</div></div>
  <div class="card stat"><div class="value" style="color:#dc2626"><?= $stats['today']['emergency'] ?></div><div class="label">طارئة اليوم</div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>أكثر القنوات استخداماً (7 أيام)</h3>
    <table>
      <tr><th>القناة</th><th>الرسائل</th></tr>
      <?php foreach ($stats['top_channels'] as $c): ?>
      <tr>
        <td><span style="color:<?= Helpers::e($c['color']) ?>">●</span> <?= Helpers::e($c['name']) ?></td>
        <td><strong><?= (int)$c['c'] ?></strong></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$stats['top_channels']): ?><tr><td colspan="2">لا بيانات بعد</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="card">
    <h3>أكثر الموظفين نشاطاً (7 أيام)</h3>
    <table>
      <tr><th>الموظف</th><th>الرسائل</th></tr>
      <?php foreach ($stats['top_senders'] as $s): ?>
      <tr><td><?= Helpers::e($s['full_name']) ?></td><td><strong><?= (int)$s['c'] ?></strong></td></tr>
      <?php endforeach; ?>
      <?php if (!$stats['top_senders']): ?><tr><td colspan="2">لا بيانات بعد</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<div class="grid grid-2">
  <div class="card stat">
    <div class="value" style="color:#dc2626"><?= (int)($stats['unplayed']['c'] ?? 0) ?></div>
    <div class="label">رسائل غير مسموعة
      <?php if ((int)($stats['unplayed']['oldest_hours'] ?? 0) > 0): ?>
      <br><small>أقدمها منذ <?= (int)$stats['unplayed']['oldest_hours'] ?> ساعة</small>
      <?php endif; ?>
    </div>
  </div>
  <div class="card stat">
    <div class="value" style="color:#16a34a">
      <?= $stats['avg_response_minutes'] >= 60
          ? round($stats['avg_response_minutes'] / 60, 1) . ' ساعة'
          : round($stats['avg_response_minutes']) . ' دقيقة' ?>
    </div>
    <div class="label">متوسط زمن الاستجابة (إرسال ← استماع)</div>
  </div>
</div>
