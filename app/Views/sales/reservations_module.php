<?php use App\Core\{Helpers, Auth}; ?>
<style>
.res-module-header { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.res-module-header form { display: flex; gap: 8px; align-items: center; }
.res-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
.res-stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; text-align: center; }
.res-stat .value { font-size: 22px; font-weight: 700; color: #111827; }
.res-stat .label { font-size: 12px; color: #6b7280; margin-top: 4px; }
.res-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
.res-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 20px; text-align: center; text-decoration: none; color: #111827; transition: transform .15s, box-shadow .15s; }
.res-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
.res-card .icon { font-size: 36px; margin-bottom: 10px; }
.res-card .title { font-size: 16px; font-weight: 700; }
.res-card .desc { font-size: 12px; color: #6b7280; margin-top: 6px; }
@media (max-width: 768px) {
  .res-module-header { flex-direction: column; align-items: stretch; }
  .res-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php if (!empty($tomorrow_error)): ?>
<div class="alert alert-danger" style="margin-bottom:16px">
  <i class="fa-solid fa-circle-exclamation"></i>
  خطأ في استعلام حجوزات الغد: <?= Helpers::e($tomorrow_error) ?>
</div>
<?php endif; ?>

<div class="res-module-header">
  <h2 style="margin:0"><i class="fa-solid fa-utensils"></i> وحدة الحجوزات والاستقبال</h2>
  <form method="GET" action="/reservations-module">
    <select name="branch_id" class="form-input">
      <option value="0">كل الفروع</option>
      <?php foreach ($branches as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">عرض</button>
  </form>
</div>

<div class="res-stats">
  <div class="res-stat"><div class="value"><?= (int)$stats['total_reservations'] ?></div><div class="label">إجمالي الحجوزات</div></div>
  <div class="res-stat"><div class="value"><?= (int)$stats['confirmed'] ?></div><div class="label">مؤكدة</div></div>
  <div class="res-stat"><div class="value"><?= (int)$stats['seated'] ?></div><div class="label">تم الجلوس</div></div>
  <div class="res-stat"><div class="value"><?= (int)$stats['waiting'] ?></div><div class="label">قائمة الانتظار</div></div>
  <div class="res-stat"><div class="value"><?= (int)$stats['cancelled'] ?></div><div class="label">الملغاة</div></div>
  <div class="res-stat"><div class="value"><?= (int)$stats['no_show'] ?></div><div class="label">No Show</div></div>
</div>

<div class="res-grid">
  <?php if (Auth::can('reservations.manage')): ?>
  <a href="/reservations" class="res-card">
    <div class="icon"><i class="fa-solid fa-calendar-days"></i></div>
    <div class="title">الحجوزات</div>
    <div class="desc">إدارة وعرض جميع الحجوزات</div>
  </a>

  <a href="/reservations/tomorrow-reminders" class="res-card" style="<?= (int)$tomorrow['large_groups'] ? 'border-color:#f97316; background:#fff7ed;' : '' ?>">
    <div class="icon"><i class="fa-solid fa-phone" style="<?= (int)$tomorrow['large_groups'] ? 'color:#f97316;' : '' ?>"></i></div>
    <div class="title">التأكيد والتذكير</div>
    <div class="desc">
      <?= (int)$tomorrow['total'] ?> حجز ليوم الغد
      <?php if ((int)$tomorrow['large_groups']): ?>
      <br><span style="color:#f97316; font-weight:600">(<?= (int)$tomorrow['large_groups'] ?> يتطلب اتصال)</span>
      <?php endif; ?>
    </div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reservations.manage')): ?>
  <a href="/reservations/floor-plan" class="res-card">
    <div class="icon"><i class="fa-solid fa-map"></i></div>
    <div class="title">مخطط الطاولات</div>
    <div class="desc">عرض حالات الطاولات والتخصيص</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reservations.types.manage')): ?>
  <a href="/admin/reservation-types" class="res-card">
    <div class="icon"><i class="fa-solid fa-masks-theater"></i></div>
    <div class="title">أنواع الحجز</div>
    <div class="desc">إعدادات أنواع الحجز والقواعد</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('call_center.manage')): ?>
  <a href="/call-center" class="res-card">
    <div class="icon"><i class="fa-solid fa-phone"></i></div>
    <div class="title">Call Center</div>
    <div class="desc">إدارة حجوزات الكول سنتر</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reception.manage')): ?>
  <a href="/reception" class="res-card">
    <div class="icon"><i class="fa-solid fa-bell-concierge"></i></div>
    <div class="title">الاستقبال</div>
    <div class="desc">شاشة استقبال العملاء</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('waiting_list.manage')): ?>
  <a href="/waiting-list" class="res-card">
    <div class="icon"><i class="fa-solid fa-hourglass-half"></i></div>
    <div class="title">قائمة الانتظار</div>
    <div class="desc">إدارة قائمة الانتظار</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('ai_assistant.view')): ?>
  <a href="/admin/ai-conversations" class="res-card">
    <div class="icon"><i class="fa-solid fa-robot"></i></div>
    <div class="title">محادثات AI</div>
    <div class="desc">سجل محادثات المساعد الذكي</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('ai_assistant.manage')): ?>
  <a href="/admin/openai-settings" class="res-card">
    <div class="icon"><i class="fa-solid fa-gear"></i></div>
    <div class="title">إعدادات OpenAI</div>
    <div class="desc">إعدادات المساعد الذكي</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reports.view')): ?>
  <a href="/reports/reservations" class="res-card">
    <div class="icon"><i class="fa-solid fa-chart-column"></i></div>
    <div class="title">تقارير الحجوزات</div>
    <div class="desc">تقارير وإحصائيات الحجوزات</div>
  </a>
  <?php endif; ?>
</div>
