<?php $backPage = $backPage ?? 1; $backQ = $backQ ?? ''; ?>

<div class="mail-layout">
  <?php include __DIR__ . '/_announcements_sidebar.php'; ?>

  <div class="mail-main">
    <a href="/hr/notifications-history?page=<?= $backPage ?>&q=<?= urlencode($backQ) ?>#row-<?= (int)$notification['id'] ?>" class="btn btn-sm btn-light" style="margin-bottom:12px"><i class="fa-solid fa-arrow-right"></i> العودة إلى سجل التعميمات</a>
    <div id="mailDetailContainer">
      <?php include __DIR__ . '/_announcement_detail.php'; ?>
    </div>
  </div>
</div>
