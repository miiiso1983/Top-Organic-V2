<?php use App\Core\{Auth, Helpers}; ?>
<style>
.mr-header { display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between; margin-bottom:18px; }
.mr-filters { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.mr-table { width:100%; border-collapse:collapse; }
.mr-table th, .mr-table td { border:1px solid #e5e7eb; padding:10px; text-align:right; }
.mr-table th { background:#f3f4f6; }
.badge { display:inline-block; padding:4px 8px; border-radius:6px; font-size:12px; }
.badge-draft { background:#e5e7eb; color:#374151; }
.badge-submitted { background:#dbeafe; color:#1e40af; }
.badge-approved { background:#d1fae5; color:#065f46; }
.badge-fulfilled { background:#dcfce7; color:#166534; }
.badge-partially { background:#fef3c7; color:#92400e; }
.badge-rejected, .badge-cancelled { background:#fee2e2; color:#991b1b; }
</style>

<div class="mr-header">
  <h2 style="margin:0"><i class="fa-solid fa-box"></i> طلبات المواد والمشتريات</h2>
  <?php if (Auth::can('material_request.manage')): ?>
  <a href="/material-requests/create" class="btn btn-primary">+ طلب جديد</a>
  <?php endif; ?>
</div>

<form class="mr-filters" method="GET" action="/material-requests">
  <select name="status" class="form-input">
    <option value="">كل الحالات</option>
    <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>مسودة</option>
    <option value="submitted" <?= ($filters['status'] ?? '') === 'submitted' ? 'selected' : '' ?>>مُرسل</option>
    <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>قيد الموافقة</option>
    <option value="approved" <?= ($filters['status'] ?? '') === 'approved' ? 'selected' : '' ?>>معتمد</option>
    <option value="fulfilling" <?= ($filters['status'] ?? '') === 'fulfilling' ? 'selected' : '' ?>>قيد التلبية</option>
    <option value="partially_fulfilled" <?= ($filters['status'] ?? '') === 'partially_fulfilled' ? 'selected' : '' ?>>تلبية جزئية</option>
    <option value="fulfilled" <?= ($filters['status'] ?? '') === 'fulfilled' ? 'selected' : '' ?>>مكتمل</option>
    <option value="rejected" <?= ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
    <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>ملغي</option>
  </select>
  <select name="branch_id" class="form-input">
    <option value="">كل الفروع</option>
    <?php foreach ($branches as $b): ?>
    <option value="<?= $b['id'] ?>" <?= ($filters['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="priority" class="form-input">
    <option value="">كل الأولويات</option>
    <option value="urgent" <?= ($filters['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>عاجل</option>
    <option value="high" <?= ($filters['priority'] ?? '') === 'high' ? 'selected' : '' ?>>مرتفع</option>
    <option value="normal" <?= ($filters['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>عادي</option>
    <option value="low" <?= ($filters['priority'] ?? '') === 'low' ? 'selected' : '' ?>>منخفض</option>
  </select>
  <input type="text" name="q" class="form-input" placeholder="رقم أو سبب" value="<?= Helpers::e($filters['q'] ?? '') ?>">
  <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass"></i> بحث</button>
</form>

<table class="mr-table" style="margin-top:18px">
  <thead>
    <tr><th>الرقم</th><th>الفرع</th><th>القسم</th><th>المقدم</th><th>التاريخ</th><th>الأولوية</th><th>الحالة</th><th>التكلفة</th><th>الإجراء</th></tr>
  </thead>
  <tbody>
    <?php foreach ($requests as $r): ?>
    <tr>
      <td><?= Helpers::e($r['request_no']) ?></td>
      <td><?= Helpers::e($r['branch_name'] ?? '-') ?></td>
      <td><?= Helpers::e($r['department_name'] ?? '-') ?></td>
      <td><?= Helpers::e($r['requester_name'] ?? '-') ?></td>
      <td><?= $r['request_date'] ?></td>
      <td><?= Helpers::e($r['priority']) ?></td>
      <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
      <td><?= number_format((float)$r['total_estimated_cost'], 2) ?> <?= Helpers::e($r['currency_code']) ?></td>
      <td><a href="/material-requests/<?= $r['id'] ?>" class="btn btn-sm btn-secondary">عرض</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$requests): ?>
    <tr><td colspan="9" style="text-align:center">لا توجد طلبات</td></tr>
    <?php endif; ?>
  </tbody>
</table>
