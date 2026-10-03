<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-database"></i> ترحيلات قاعدة البيانات</h2>
  <form method="post" action="/admin/migrate/run" onsubmit="return confirm('تشغيل كل الترحيلات المعلقة؟ يُنصح بنسخة احتياطية أولاً')">
    <button class="btn btn-primary"><i class="fa-solid fa-play"></i> تشغيل المعلّق</button>
  </form>
</div>

<?php if ($report): ?>
<div class="card">
  <h3>نتيجة آخر تشغيل</h3>
  <table>
    <tr><th>الملف</th><th>الحالة</th><th>جمل ناجحة</th><th>تحذيرات</th><th>خطأ</th></tr>
    <?php foreach ($report as $file => $r): ?>
    <tr>
      <td><code><?= Helpers::e($file) ?></code></td>
      <td><span class="badge badge-<?= $r['status'] === 'ok' ? 'success' : ($r['status'] === 'warnings' ? 'warning' : 'danger') ?>">
        <?= ['ok' => 'نجح', 'warnings' => 'نجح مع تحذيرات', 'failed' => 'فشل'][$r['status']] ?></span></td>
      <td><?= (int)$r['ok'] ?></td>
      <td><?= (int)$r['warned'] ?></td>
      <td style="max-width:380px;overflow:auto"><small><?= Helpers::e((string)($r['error'] ?? '')) ?></small></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <small style="color:#6b7280">التحذيرات = كائنات موجودة مسبقاً (آمنة — يعني أن الترحيل طُبّق سابقاً).</small>
</div>
<?php endif; ?>

<div class="card">
  <h3>كل ملفات الترحيل (<?= count($files) ?>)</h3>
  <table>
    <tr><th>الملف</th><th>الحالة</th><th>آخر تطبيق</th><th>تفاصيل</th></tr>
    <?php foreach ($files as $f): ?>
    <tr>
      <td><code><?= Helpers::e($f['name']) ?></code></td>
      <td>
        <?php if (!$f['applied']): ?>
          <span class="badge badge-warning">معلّق</span>
        <?php else: ?>
          <span class="badge badge-<?= $f['applied']['status'] === 'ok' ? 'success' : ($f['applied']['status'] === 'warnings' ? 'warning' : 'danger') ?>">
            <?= ['ok' => 'مطبّق', 'warnings' => 'مطبّق بتحذيرات', 'failed' => 'فشل'][$f['applied']['status']] ?>
          </span>
        <?php endif; ?>
      </td>
      <td><?= $f['applied']['applied_at'] ?? '—' ?></td>
      <td>
        <?php if ($f['applied']): ?>
        <small>ناجحة: <?= (int)$f['applied']['statements_ok'] ?> · تحذيرات: <?= (int)$f['applied']['statements_warned'] ?></small>
        <?php endif; ?>
        <form method="post" action="/admin/migrate/run-file" style="display:inline" onsubmit="return confirm('تشغيل <?= Helpers::e($f['name']) ?> الآن؟')">
          <input type="hidden" name="file" value="<?= Helpers::e($f['name']) ?>">
          <button class="btn btn-sm <?= $f['pending'] ? 'btn-primary' : 'btn-secondary' ?>"><?= $f['pending'] ? 'تشغيل' : 'إعادة' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="card">
  <h4>بديل سطر الأوامر (SSH)</h4>
  <code>php scripts/migrate.php</code>
</div>
