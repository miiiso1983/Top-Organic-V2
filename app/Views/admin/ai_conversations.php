<?php use App\Core\Helpers; ?>
<div class="card">
  <h2 style="margin-top:0">محادثات AI</h2>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>القناة</th>
          <th>العميل</th>
          <th>الحالة</th>
          <th>الموظف</th>
          <th>آخر رسالة</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($conversations as $c): ?>
        <tr>
          <td><?= $c['id'] ?></td>
          <td><?= Helpers::e($c['channel']) ?></td>
          <td><?= Helpers::e($c['customer_name'] ?: $c['channel_id']) ?></td>
          <td>
            <?php if ($c['status'] === 'handoff'): ?>
              <span class="badge badge-danger">محولة</span>
            <?php elseif ($c['status'] === 'active'): ?>
              <span class="badge badge-success">نشطة</span>
            <?php else: ?>
              <span class="badge badge-secondary">مغلقة</span>
            <?php endif; ?>
          </td>
          <td><?= Helpers::e($c['agent_name'] ?: '—') ?></td>
          <td><?= $c['last_message_at'] ?></td>
          <td><a href="/admin/ai-conversations/<?= $c['id'] ?>" class="btn btn-sm btn-primary">عرض</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
