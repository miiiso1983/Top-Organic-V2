<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>حالة المزامنة الهجينة (أوف لاين / أون لاين)</h3>
  <table>
    <tr>
      <th>الحالة</th>
      <td>
        <?php if ($status['enabled']): ?>
          <span class="badge badge-success">مفعّلة</span>
        <?php else: ?>
          <span class="badge badge-danger">غير مفعّلة</span> — فعّلها من <code>config/config.php</code> (المفتاح <code>sync.enabled</code>)
        <?php endif; ?>
      </td>
    </tr>
    <tr><th>الخادم المركزي</th><td><?= Helpers::e($status['remote_url'] ?: '—') ?></td></tr>
    <tr><th>عمليات بانتظار الإرسال</th><td><span class="badge badge-warning"><?= (int)$status['pending'] ?></span></td></tr>
    <tr><th>آخر مزامنة ناجحة</th><td><?= Helpers::e($status['last_sync_at'] ?? 'لم تتم بعد') ?></td></tr>
  </table>

  <form method="post" action="/admin/sync/run" style="margin-top:12px">
    <button class="btn btn-success" <?= $status['enabled'] ? '' : 'disabled' ?>><i class="fa-solid fa-arrow-right-arrow-left"></i> مزامنة الآن</button>
  </form>
  <p style="color:#64748b;font-size:13px;margin-top:8px">
    يعمل النظام محلياً بشكل كامل دون اتصال بالإنترنت. عند توفر الاتصال وتفعيل المزامنة،
    يتم إرسال جميع العمليات المسجّلة محلياً (مبيعات، مخزون، مالية، موارد بشرية) إلى الخادم المركزي.
  </p>
</div>

<div class="card">
  <h3>عمليات واردة من الخادم المركزي (للمراجعة)</h3>
  <table>
    <tr><th>#</th><th>الكيان</th><th>المعرّف</th><th>العملية</th><th>التاريخ</th></tr>
    <?php foreach ($recentIncoming as $row): ?>
    <tr>
      <td><?= (int)$row['id'] ?></td>
      <td><?= Helpers::e($row['entity']) ?></td>
      <td><?= (int)$row['entity_id'] ?></td>
      <td><?= Helpers::e($row['action']) ?></td>
      <td><?= Helpers::e($row['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$recentIncoming): ?>
    <tr><td colspan="5" style="text-align:center;color:#94a3b8">لا توجد عمليات واردة بعد</td></tr>
    <?php endif; ?>
  </table>
</div>
