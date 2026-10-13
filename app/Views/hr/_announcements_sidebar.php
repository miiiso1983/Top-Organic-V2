<?php
use App\Core\Helpers;
$targetLabels = ['all'=>'الجميع','level'=>'مستوى إداري','role'=>'دور','branch'=>'فرع','department'=>'قسم','user'=>'موظف'];
$recurrenceLabels = ['once'=>'مرة واحدة','daily'=>'يومي','weekly'=>'أسبوعي','monthly'=>'شهري'];
$activeId = $activeId ?? null;
$reminders = $reminders ?? [];
$historyItems = $historyItems ?? [];
?>
<aside class="mail-sidebar">
  <a href="/hr/announcements" class="btn btn-success mail-compose-btn"><i class="fa-solid fa-pen"></i> تعميم جديد</a>

  <?php if ($reminders): ?>
  <div class="mail-section">
    <h4><i class="fa-solid fa-bell"></i> التذكيرات المجدولة</h4>
    <ul class="mail-reminder-list">
      <?php foreach ($reminders as $r):
        $editData = [
            'reminder_id' => (int)$r['id'],
            'title' => $r['title'],
            'body' => $r['body'],
            'link' => $r['link'],
            'target_type' => $r['target_type'],
            'target_level' => $r['target_level'],
            'target_role_id' => $r['target_role_id'],
            'target_branch_id' => $r['target_branch_id'],
            'target_department_id' => $r['target_department_id'],
            'target_user_ids' => !empty($r['target_user_ids']) ? (json_decode($r['target_user_ids'], true) ?: []) : [],
            'recurrence_type' => $r['recurrence_type'],
            'remind_at' => $r['next_run_at'] ? date('Y-m-d\TH:i', strtotime($r['next_run_at'])) : '',
        ];
      ?>
      <li class="mail-reminder-item <?= $r['is_active'] ? '' : 'is-paused' ?>">
        <div class="mail-reminder-title"><?= Helpers::e($r['title']) ?></div>
        <div class="mail-reminder-meta">
          <span class="badge badge-light"><?= $targetLabels[$r['target_type']] ?? $r['target_type'] ?></span>
          <span class="badge badge-info"><?= $recurrenceLabels[$r['recurrence_type']] ?? $r['recurrence_type'] ?></span>
        </div>
        <div class="mail-reminder-meta">
          <i class="fa-regular fa-clock"></i> <?= $r['is_active'] ? date('Y-m-d H:i', strtotime($r['next_run_at'])) : 'موقوف' ?>
        </div>
        <div class="mail-reminder-actions">
          <a href="#" class="btn btn-xs btn-light" title="تعديل" data-edit-reminder="1" data-edit='<?= Helpers::e(json_encode($editData, JSON_UNESCAPED_UNICODE)) ?>'>
            <i class="fa-solid fa-pen"></i>
          </a>
          <a href="/hr/announcement-reminders/<?= $r['id'] ?>/toggle" class="btn btn-xs btn-light" title="<?= $r['is_active'] ? 'إيقاف' : 'تفعيل' ?>">
            <i class="fa-solid <?= $r['is_active'] ? 'fa-pause' : 'fa-play' ?>"></i>
          </a>
          <a href="/hr/announcement-reminders/<?= $r['id'] ?>/delete" class="btn btn-xs btn-light" title="حذف" onclick="return confirm('حذف هذا التذكير؟')">
            <i class="fa-solid fa-trash"></i>
          </a>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="mail-section mail-history-section">
    <h4><i class="fa-solid fa-inbox"></i> سجل التعميمات</h4>
    <?php if ($historyItems): ?>
    <div class="mail-history-search">
      <input type="text" id="mailHistorySearch" placeholder="بحث بعنوان التعميم..." oninput="filterMailHistory(this.value)">
    </div>
    <?php endif; ?>
    <ul class="mail-history-list" id="mailHistoryList">
      <?php foreach ($historyItems as $h): ?>
      <li class="mail-history-item <?= $activeId == $h['id'] ? 'is-active' : '' ?>" data-title="<?= Helpers::e(mb_strtolower($h['title'])) ?>">
        <a href="/hr/notifications-history/<?= $h['id'] ?>" data-history-link="1">
          <div class="mail-history-title"><?= Helpers::e($h['title']) ?></div>
          <div class="mail-history-meta">
            <span><?= Helpers::e($h['sender_name'] ?? '-') ?></span>
            <span><?= $h['created_at'] ? date('Y-m-d H:i', strtotime($h['created_at'])) : '-' ?></span>
          </div>
          <div class="mail-history-meta">
            <span class="badge badge-light"><?= $targetLabels[$h['target_type']] ?? $h['target_type'] ?></span>
            <span><?= (int)($h['recipient_count'] ?? 0) ?> مستلم</span>
            <span class="badge badge-info" title="عدد المشاهدات (من فتحوا التعميم)"><i class="fa-solid fa-eye"></i> <?= (int)($h['read_count'] ?? 0) ?></span>
          </div>
        </a>
      </li>
      <?php endforeach; ?>
      <?php if (!$historyItems): ?><li class="mail-history-empty">لا توجد تعميمات بعد</li><?php endif; ?>
      <li class="mail-history-empty" id="mailHistoryNoResults" style="display:none">لا توجد نتائج مطابقة للبحث</li>
    </ul>
    <a href="/hr/notifications-history" class="mail-history-more">عرض كل السجل</a>
  </div>
</aside>

<script>
function filterMailHistory(q) {
  q = (q || '').trim().toLowerCase();
  var items = document.querySelectorAll('#mailHistoryList .mail-history-item');
  var anyVisible = false;
  items.forEach(function(li) {
    var title = li.getAttribute('data-title') || '';
    var match = !q || title.indexOf(q) !== -1;
    li.style.display = match ? '' : 'none';
    if (match) anyVisible = true;
  });
  var noResults = document.getElementById('mailHistoryNoResults');
  if (noResults) noResults.style.display = (items.length && q !== '' && !anyVisible) ? '' : 'none';
}

(function() {
  document.addEventListener('click', function(e) {
    var link = e.target.closest('[data-history-link]');
    if (!link) return;
    var detailContainer = document.getElementById('mailDetailContainer');
    if (!detailContainer) return; // لا يوجد وعاء AJAX في هذه الصفحة: اتركه ينتقل بشكل طبيعي
    e.preventDefault();
    var url = link.getAttribute('href');
    fetch(url + '?ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(res) { if (!res.ok) throw new Error('failed'); return res.text(); })
      .then(function(html) {
        var composeContainer = document.getElementById('mailComposeContainer');
        if (composeContainer) composeContainer.style.display = 'none';
        detailContainer.style.display = '';
        detailContainer.innerHTML = html;
        document.querySelectorAll('#mailHistoryList .mail-history-item.is-active').forEach(function(li) { li.classList.remove('is-active'); });
        var li = link.closest('.mail-history-item');
        if (li) li.classList.add('is-active');
        if (window.history && window.history.pushState) {
          window.history.pushState({ historyAjax: true }, '', url);
        }
      })
      .catch(function() { window.location.href = url; });
  });
})();
</script>

<style>
.mail-layout { display: flex; gap: 16px; align-items: flex-start; }
.mail-sidebar { width: 320px; flex: 0 0 320px; background: #fff; border: 1px solid var(--border, #e2e2e2); border-radius: 10px; padding: 12px; max-height: calc(100vh - 140px); overflow-y: auto; position: sticky; top: 12px; }
.mail-main { flex: 1; min-width: 0; }
.mail-compose-btn { width: 100%; text-align: center; margin-bottom: 12px; }
.mail-section { margin-bottom: 16px; }
.mail-section h4 { font-size: 14px; color: #666; margin: 0 0 8px; display: flex; align-items: center; gap: 6px; }
.mail-history-search { margin-bottom: 8px; }
.mail-history-search input { width: 100%; box-sizing: border-box; padding: 6px 8px; border: 1px solid var(--border, #ddd); border-radius: 6px; font-size: 13px; }
.mail-reminder-list, .mail-history-list { list-style: none; margin: 0; padding: 0; }
.mail-reminder-item { border: 1px solid var(--border, #eee); border-radius: 8px; padding: 8px; margin-bottom: 8px; }
.mail-reminder-item.is-paused { opacity: .55; }
.mail-reminder-title { font-weight: 600; font-size: 13px; margin-bottom: 4px; }
.mail-reminder-meta { font-size: 12px; color: #777; display: flex; gap: 6px; align-items: center; margin-bottom: 4px; flex-wrap: wrap; }
.mail-reminder-actions { display: flex; gap: 6px; }
.mail-history-item { border-bottom: 1px solid var(--border, #eee); }
.mail-history-item a { display: block; padding: 8px 4px; color: inherit; text-decoration: none; }
.mail-history-item a:hover { background: #f7f7f7; border-radius: 6px; }
.mail-history-item.is-active a { background: rgba(46,125,50,.10); border-radius: 6px; }
.mail-history-title { font-weight: 600; font-size: 13px; margin-bottom: 3px; }
.mail-history-meta { font-size: 11px; color: #888; display: flex; justify-content: space-between; gap: 6px; }
.mail-history-empty { color: #999; font-size: 13px; padding: 8px 0; }
.mail-history-more { display: block; text-align: center; margin-top: 8px; font-size: 12px; }
.mail-reminder-fields { border: 1px dashed var(--border, #ccc); border-radius: 8px; padding: 10px; margin-top: 6px; display: none; }
@media (max-width: 900px) {
  .mail-layout { flex-direction: column; }
  .mail-sidebar { width: 100%; flex: none; position: static; max-height: none; }
}
</style>
