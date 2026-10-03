<?php use App\Core\Helpers; ?>
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
    <h2 style="margin:0">أنواع الحجز</h2>
    <button class="btn btn-primary" onclick="document.getElementById('newTypeForm').classList.toggle('hidden')">+ نوع جديد</button>
  </div>

  <form id="newTypeForm" method="post" action="/admin/reservation-types" class="hidden" style="margin-bottom:24px; border:1px dashed #d1d5db; padding:16px; border-radius:10px">
    <h3 style="margin-top:0">إضافة نوع حجز</h3>
    <?= $this->partial('admin/reservation_type_form', ['templates'=>$templates]) ?>
  </form>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>الرمز</th>
          <th>الاسم</th>
          <th>الضيوف</th>
          <th>المدة</th>
          <th>السعر</th>
          <th>العربون</th>
          <th>الموافقة</th>
          <th>الحالة</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($types as $t): ?>
        <tr>
          <td><code><?= Helpers::e($t['code']) ?></code></td>
          <td><span style="font-size:18px"><?= $t['icon'] ?></span> <?= Helpers::e($t['name_ar']) ?></td>
          <td><?= (int)$t['min_guests'] ?> - <?= (int)$t['max_guests'] ?></td>
          <td><?= (int)$t['duration_minutes'] ?> د</td>
          <td><?= $t['price_per_person'] ? Helpers::e($t['price_per_person']) . ' / شخص' : '—' ?></td>
          <td><?= $t['deposit_required'] ? Helpers::e($t['deposit_amount']) : '—' ?></td>
          <td><?= $t['requires_approval'] ? '<i class="fa-solid fa-check"></i>' : '—' ?></td>
          <td><?= $t['is_active'] ? '<span class="badge badge-success">مفعل</span>' : '<span class="badge badge-danger">معطل</span>' ?></td>
          <td>
            <button class="btn btn-sm btn-secondary" onclick="document.getElementById('edit<?= $t['id'] ?>').classList.toggle('hidden')">تعديل</button>
            <form method="post" action="/admin/reservation-types/<?= $t['id'] ?>/delete" style="display:inline" onsubmit="return confirm('تأكيد الحذف؟')">
              <button class="btn btn-sm btn-danger">حذف</button>
            </form>
          </td>
        </tr>
        <tr id="edit<?= $t['id'] ?>" class="hidden">
          <td colspan="9" style="background:#f9fafb">
            <form method="post" action="/admin/reservation-types/<?= $t['id'] ?>">
              <?= $this->partial('admin/reservation_type_form', ['type'=>$t, 'templates'=>$templates]) ?>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
.hidden { display: none; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
.form-grid .form-row { margin-bottom: 0; }
.form-grid label { display: block; font-size: 12px; color: #6b7280; margin-bottom: 4px; }
.form-grid input, .form-grid select { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; }
</style>
