<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-users-rectangle"></i> مجموعات البريد</h2>
  <div style="display:flex;gap:8px">
    <a href="/mail/groups/create" class="btn btn-success"><i class="fa-solid fa-plus"></i> مجموعة جديدة</a>
    <a href="/mail" class="btn btn-secondary"><i class="fa-solid fa-arrow-right"></i> رجوع للبريد</a>
  </div>
</div>

<div class="card">
  <table class="table">
    <thead><tr><th>الاسم</th><th>المُنشئ</th><th>عدد الأعضاء</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($groups as $g): ?>
      <tr>
        <td><?= Helpers::e($g['name']) ?></td>
        <td><?= Helpers::e($g['creator_name']) ?></td>
        <td><?= (int)$g['members_count'] ?></td>
        <td>
          <?php if ((int)$g['created_by'] === $uid): ?>
          <div style="display:flex;gap:6px">
            <a href="/mail/groups/<?= (int)$g['id'] ?>/edit" class="btn btn-sm btn-secondary" title="تعديل"><i class="fa-solid fa-pen"></i></a>
            <form method="post" action="/mail/groups/<?= (int)$g['id'] ?>/delete" onsubmit="return confirm('حذف هذه المجموعة؟')">
              <button class="btn btn-sm btn-danger" title="حذف"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$groups): ?>
      <tr><td colspan="4" style="text-align:center;color:#6b7280;padding:24px">لا توجد مجموعات بعد</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
