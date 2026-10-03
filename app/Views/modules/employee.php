<?php use App\Core\Auth; ?>
<style>
.res-module-header { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between; margin-bottom: 20px; }
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

<div class="res-module-header">
  <h2 style="margin:0"><i class="fa-solid fa-user"></i> وحدة الموظف</h2>
</div>

<div class="res-grid">
  <?php if (Auth::canAny(['hr.clock', 'mobile.attendance', 'attendance.self'])): ?>
  <a href="/hr/clock" class="res-card">
    <div class="icon"><i class="fa-solid fa-fingerprint"></i></div>
    <div class="title">بصمة الحضور/الانصراف</div>
    <div class="desc">تسجيل الحضور والانصراف</div>
  </a>
  <?php endif; ?>

  <?php if (Auth::canAny(['tasks.self', 'mobile.tasks'])): ?>
  <a href="/hr/tasks" class="res-card">
    <div class="icon"><i class="fa-solid fa-check"></i></div>
    <div class="title">المهام</div>
    <div class="desc">متابعة المهام الشخصية</div>
  </a>
  <?php endif; ?>
</div>
