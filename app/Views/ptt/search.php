<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2>بحث الرسائل الصوتية</h2>
  <a href="/ptt" class="btn btn-secondary">رجوع</a>
</div>

<div class="card">
  <form method="get" action="/ptt/search" class="form-inline">
    <div class="form-row"><label>الموظف</label>
      <select name="sender_id"><option value="">الكل</option>
        <?php foreach ($users as $u): ?>
        <option value="<?= $u['id'] ?>" <?= (int)($filters['sender_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= Helpers::e($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>القناة</label>
      <select name="channel_id"><option value="">الكل</option>
        <?php foreach ($channels as $c): ?>
        <option value="<?= $c['id'] ?>" <?= (int)($filters['channel_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= Helpers::e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>الأولوية</label>
      <select name="priority"><option value="">الكل</option>
        <?php foreach ($priorities as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($filters['priority'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>بحث في النص (التفريغ)</label><input name="q" value="<?= Helpers::e($filters['q'] ?? '') ?>" placeholder="كلمة من نص الرسالة"></div>
    <div class="form-row"><label>القسم</label><input name="department" value="<?= Helpers::e($filters['department'] ?? '') ?>"></div>
    <div class="form-row"><label>من تاريخ</label><input type="date" name="date_from" value="<?= Helpers::e($filters['date_from'] ?? '') ?>"></div>
    <div class="form-row"><label>إلى تاريخ</label><input type="date" name="date_to" value="<?= Helpers::e($filters['date_to'] ?? '') ?>"></div>
    <button class="btn btn-primary">بحث</button>
    <a href="/ptt/search" class="btn btn-secondary">إعادة تعيين</a>
  </form>
</div>

<div class="card">
  <h3>النتائج (<?= count($results) ?>)</h3>
  <table>
    <tr><th>التاريخ</th><th>المرسل</th><th>القناة</th><th>الأولوية</th><th>المدة</th><th></th></tr>
    <?php foreach ($results as $m): ?>
    <tr>
      <td><?= $m['created_at'] ?></td>
      <td><?= Helpers::e($m['sender_name']) ?></td>
      <td><?= $m['channel_name'] ? '<span style="color:' . Helpers::e($m['channel_color']) . '">●</span> ' . Helpers::e($m['channel_name']) : 'مباشرة' ?></td>
      <td><?= $priorities[$m['priority']] ?? $m['priority'] ?></td>
      <td><?= (float)$m['duration_sec'] ?> ث</td>
      <td><button class="btn btn-sm btn-primary ptt-play" data-src="<?= Helpers::e($m['file_path']) ?>" data-id="<?= $m['id'] ?>"><i class="fa-solid fa-play"></i></button></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$results): ?><tr><td colspan="6">لا نتائج مطابقة</td></tr><?php endif; ?>
  </table>
</div>

<script src="/assets/js/ptt-offline.js?v=1"></script>
<script src="/assets/js/ptt.js?v=2"></script>
