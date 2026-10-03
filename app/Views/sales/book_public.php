<?php use App\Core\{Helpers, Settings}; ?>
<?php
$otpEnabled = (bool)(Settings::get('reservation_otp_enabled', 0));
$brandName = 'Top Organic Restaurant';
$types = $types ?? [];
$defaultType = $types[0]['code'] ?? 'regular';
$jsTypes = [];
foreach ($types as $t) {
    $jsTypes[$t['code']] = ['name_ar'=>$t['name_ar'],'min'=>(int)$t['min_guests'],'max'=>(int)$t['max_guests'],'default'=>(int)$t['default_guests'],'duration'=>(int)$t['duration_minutes'],'price'=>(float)$t['price_per_person'],'deposit'=>(float)$t['deposit_amount'],'deposit_required'=>(bool)$t['deposit_required'],'requirements'=>$t['requirements'] ?? ''];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>حجز طاولة | <?= Helpers::e($brandName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<style>
  :root {
    --primary: #c2410c;
    --primary-dark: #9a3412;
    --primary-light: #fff7ed;
    --success: #15803d;
    --danger: #b91c1c;
    --warning: #d97706;
    --text: #1f2937;
    --muted: #6b7280;
    --border: #e5e7eb;
    --bg: #fafaf9;
    --card: #ffffff;
    --radius: 14px;
    --shadow: 0 10px 30px rgba(0,0,0,0.06);
  }
  * { box-sizing: border-box; }
  body { font-family: 'Cairo', Tahoma, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 0; line-height: 1.6; }
  .page-header { background: linear-gradient(135deg, #10914d 0%, #0a6e3a 100%); color: #fff; padding: 48px 20px 64px; text-align: center; position: relative; overflow: hidden; }
  .page-header::before { content: ''; position: absolute; inset: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.08'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); opacity: 0.6; }
  .page-header .brand { font-size: 26px; font-weight: 700; position: relative; margin-bottom: 8px; }
  .page-header .title { font-size: 32px; font-weight: 700; margin-bottom: 8px; position: relative; }
  .page-header .subtitle { font-size: 15px; opacity: 0.92; position: relative; }
  .booking-wrapper { max-width: 1100px; margin: -32px auto 48px; padding: 0 20px; display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 24px; align-items: start; }
  @media (max-width: 900px) { .booking-wrapper { grid-template-columns: 1fr; margin-top: -24px; } }
  .card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 24px; margin-bottom: 20px; border: 1px solid var(--border); }
  .card-title { font-size: 17px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; color: var(--primary-dark); }
  .stepper { display: flex; justify-content: center; gap: 24px; margin-bottom: 28px; font-size: 13px; color: var(--muted); }
  .stepper .step { display: flex; align-items: center; gap: 8px; }
  .stepper .step span { width: 26px; height: 26px; border-radius: 50%; background: #e5e7eb; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
  .stepper .step.active { color: var(--primary); font-weight: 600; }
  .stepper .step.active span { background: var(--primary); }
  .form-row { margin-bottom: 18px; }
  .form-row label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #374151; }
  .form-row input, .form-row select, .form-row textarea {
    width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 15px; font-family: inherit;
    background: #fff; transition: border-color .15s, box-shadow .15s;
  }
  .form-row input:focus, .form-row select:focus, .form-row textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(194,65,12,0.12); }
  .form-row select { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: left 12px center; padding-left: 36px; }
  .form-row textarea { resize: vertical; min-height: 80px; }
  .guest-stepper { display: inline-flex; align-items: center; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
  .guest-stepper button { width: 44px; height: 44px; border: none; background: #f9fafb; font-size: 20px; cursor: pointer; color: var(--primary); transition: background .15s; }
  .guest-stepper button:hover { background: var(--primary-light); }
  .guest-stepper input { width: 60px; text-align: center; border: none; border-right: 1px solid var(--border); border-left: 1px solid var(--border); font-size: 18px; font-weight: 700; padding: 0; }
  .type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
  .type-card { position: relative; border: 1px solid var(--border); border-radius: 10px; padding: 14px 8px; text-align: center; cursor: pointer; transition: all .15s; background: #fff; }
  .type-card:hover { border-color: var(--type-color, var(--primary)); transform: translateY(-2px); }
  .type-card input { position: absolute; opacity: 0; }
  .type-card .icon { font-size: 26px; margin-bottom: 4px; }
  .type-card .label { font-size: 13px; font-weight: 600; color: #374151; }
  .type-card.selected { border-color: var(--type-color, var(--primary)); background: #f0fdf4; box-shadow: 0 0 0 2px var(--type-color, var(--primary)); }
  .slots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 8px; }
  .slot-btn { padding: 12px 8px; border: 1px solid var(--border); border-radius: 10px; background: #fff; cursor: pointer; text-align: center; font-size: 14px; transition: all .15s; position: relative; }
  .slot-btn:hover:not(.disabled) { border-color: var(--primary); background: var(--primary-light); }
  .slot-btn.selected { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 4px 12px rgba(194,65,12,0.25); }
  .slot-btn.disabled { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; opacity: 0.8; }
  .slot-btn.peak:not(.disabled) { border-color: var(--warning); }
  .slot-btn.peak:not(.disabled)::after { content: 'ذروة'; position: absolute; top: -8px; right: 50%; transform: translateX(50%); background: var(--warning); color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 8px; }
  .slot-time { font-weight: 700; font-size: 15px; }
  .slot-info { font-size: 11px; margin-top: 4px; color: inherit; opacity: 0.85; }
  .slots-empty { color: var(--muted); text-align: center; padding: 20px; font-size: 14px; }
  .btn-primary { width: 100%; padding: 14px; background: var(--primary); color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background .15s, transform .15s; }
  .btn-primary:hover:not(:disabled) { background: var(--primary-dark); transform: translateY(-1px); }
  .btn-primary:disabled { background: #d1d5db; cursor: not-allowed; }
  .btn-secondary { padding: 10px 14px; background: #fff; color: var(--primary); border: 1px solid var(--primary); border-radius: 10px; font-weight: 600; cursor: pointer; transition: all .15s; white-space: nowrap; }
  .btn-secondary:hover { background: var(--primary-light); }
  .alert { padding: 14px; border-radius: 10px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
  .alert-success { background: #dcfce7; color: var(--success); border: 1px solid #86efac; }
  .alert-danger { background: #fee2e2; color: var(--danger); border: 1px solid #fecaca; }
  .summary-card { position: sticky; top: 20px; }
  .summary-card .summary-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed var(--border); font-size: 14px; }
  .summary-card .summary-row:last-child { border-bottom: none; }
  .summary-card .summary-label { color: var(--muted); }
  .summary-card .summary-value { font-weight: 700; color: var(--text); }
  .summary-card .summary-total { background: var(--primary-light); border-radius: 10px; padding: 14px; margin-top: 14px; text-align: center; }
  .summary-card .summary-total .value { font-size: 22px; font-weight: 700; color: var(--primary); }
  .otp-wrap { display: flex; gap: 10px; }
  .otp-wrap input { flex: 1; text-align: center; letter-spacing: 6px; font-size: 18px; font-weight: 700; }
  .success-page { text-align: center; padding: 40px 24px; }
  .success-icon { width: 90px; height: 90px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 44px; margin: 0 auto 20px; }
  .success-title { font-size: 24px; font-weight: 700; color: var(--success); margin-bottom: 8px; }
  .ticket { background: #fff; border: 2px dashed var(--border); border-radius: var(--radius); padding: 24px; margin-top: 24px; position: relative; }
  .ticket::before, .ticket::after { content: ''; position: absolute; width: 20px; height: 20px; background: var(--bg); border-radius: 50%; top: -10px; }
  .ticket::before { right: 30px; } .ticket::after { left: 30px; }
  .ticket-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
  .ticket-row:last-child { border-bottom: none; }
  .ticket-label { color: var(--muted); }
  .ticket-value { font-weight: 600; }
  .page-footer { text-align: center; padding: 24px; color: var(--muted); font-size: 13px; }
  .loading-skeleton { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; }
  .skeleton { height: 52px; background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; border-radius: 10px; }
  @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .hint { font-size: 12px; color: var(--muted); margin-top: 4px; }
  .inline-icon { font-size: 1.1em; }
  .ai-fab { position: fixed; bottom: 24px; left: 24px; width: 56px; height: 56px; border-radius: 50%; background: #10914d; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 26px; text-decoration: none; box-shadow: 0 4px 14px rgba(0,0,0,0.2); z-index: 90; transition: transform .15s; }
  .ai-fab:hover { transform: scale(1.08); }
</style>
</head>
<body>

<?php if (!empty($saved) && !empty($reservation)): ?>
  <div class="page-header">
    <div class="brand"><?= Helpers::e($brandName) ?></div>
  </div>
  <div class="booking-wrapper">
    <div class="card success-page">
      <div class="success-icon"><i class="fa-solid fa-gift"></i></div>
      <div class="success-title">تم استلام طلب الحجز بنجاح</div>
      <p style="color:var(--muted); margin-bottom: 4px;">سنتواصل معك قريباً لتأكيد التفاصيل</p>
      <div class="ticket">
        <div class="ticket-row">
          <span class="ticket-label">رقم الحجز</span>
          <span class="ticket-value" style="font-size:18px; color:var(--primary)"><?= Helpers::e($reservation['reservation_no']) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-label">العميل</span>
          <span class="ticket-value"><?= Helpers::e($reservation['customer_name']) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-label">الموعد</span>
          <span class="ticket-value"><?= date('Y-m-d', strtotime($reservation['reserved_at'])) ?> <?= date('H:i', strtotime($reservation['reserved_at'])) ?></span>
        </div>
        <div class="ticket-row">
          <span class="ticket-label">عدد الضيوف</span>
          <span class="ticket-value"><?= (int)$reservation['guests'] ?> أشخاص</span>
        </div>
        <div class="ticket-row">
          <span class="ticket-label">الفرع</span>
          <span class="ticket-value"><?= Helpers::e($reservation['branch_name'] ?? '—') ?></span>
        </div>
      </div>
      <a href="/book" class="btn btn-primary" style="margin-top:24px; text-decoration:none; display:inline-block; width:auto; padding:12px 32px;">حجز جديد</a>
    </div>
  </div>
<?php else: ?>
  <div class="page-header">
    <div class="brand"><?= Helpers::e($brandName) ?></div>
    <div class="title">احجز طاولتك الآن</div>
    <div class="subtitle">اختر الفرع والموعد المفضل، وسنؤكد لك الحجز خلال دقائق</div>
  </div>

  <div class="booking-wrapper">
    <div class="main-col">
      <div class="stepper">
        <div class="step active"><span>1</span> اختيار الموعد</div>
        <div class="step"><span>2</span> بياناتك</div>
        <div class="step"><span>3</span> تأكيد</div>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" action="/book" id="bookingForm">
        <div class="card">
          <div class="card-title"><span class="inline-icon"><i class="fa-solid fa-location-dot"></i></span> اختيار الفرع</div>
          <div class="form-row">
            <select name="branch_id" id="branchId" required>
              <option value="">اختر الفرع المناسب</option>
              <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= (isset($form['branch_id']) && $form['branch_id'] == $b['id']) ? 'selected' : '' ?>><?= Helpers::e($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="card">
          <div class="card-title"><span class="inline-icon"><i class="fa-solid fa-gift"></i></span> نوع الحجز</div>
          <div class="type-grid" id="typeGrid">
            <?php foreach ($types as $t): ?>
              <label class="type-card <?= (isset($form['reservation_type']) && $form['reservation_type'] === $t['code']) ? 'selected' : '' ?>" style="--type-color:<?= Helpers::e($t['color'] ?? '#10914d') ?>" data-code="<?= $t['code'] ?>">
                <input type="radio" name="reservation_type" value="<?= $t['code'] ?>" <?= (isset($form['reservation_type']) && $form['reservation_type'] === $t['code']) ? 'checked' : (!$form && $t['code'] === $defaultType ? 'checked' : '') ?>>
                <div class="icon"><?= ($t['icon'] ?? '<i class="fa-solid fa-utensils"></i>') ?></div>
                <div class="label"><?= Helpers::e($t['name_ar']) ?></div>
                <div class="hint"><?= (int)$t['min_guests'] ?> - <?= (int)$t['max_guests'] ?> شخص</div>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="hint" id="typeRequirements" style="margin-top:10px"></div>
        </div>

        <div class="card">
          <div class="card-title"><span class="inline-icon"><i class="fa-solid fa-users"></i></span> عدد الضيوف</div>
          <div class="guest-stepper">
            <button type="button" id="decGuests">−</button>
            <input type="number" id="guests" name="guests" value="<?= isset($form['guests']) ? (int)$form['guests'] : 2 ?>" min="1" max="99" required>
            <button type="button" id="incGuests">+</button>
          </div>
          <div class="hint">حدد عدد الأشخاص بما فيهم الأطفال لحساب الطاقة الاستيعابية</div>
        </div>

        <div class="card">
          <div class="card-title"><span class="inline-icon"><i class="fa-solid fa-calendar-days"></i></span> التاريخ والوقت</div>
          <div class="form-row">
            <label>التاريخ</label>
            <input type="text" id="datePicker" name="date" placeholder="اختر اليوم" required autocomplete="off">
          </div>
          <div class="form-row">
            <label>الوقت المتاح</label>
            <input type="hidden" name="reserved_at" id="reservedAt" required>
            <div class="slots-grid" id="slotsGrid"><div class="slots-empty">اختر الفرع والتاريخ أولاً لعرض الفترات المتاحة</div></div>
          </div>
          <div class="hint" style="margin-top:10px"><i class="fa-solid fa-diamond"></i> الفترات المميزة باللون البرتقالي هي ساعات الذروة (الطلب مرتفع)</div>
        </div>

        <div class="card">
          <div class="card-title"><span class="inline-icon"><i class="fa-solid fa-user"></i></span> بيانات العميل</div>
          <div class="form-row">
            <label>الاسم الكامل</label>
            <input name="customer_name" value="<?= Helpers::e($form['customer_name'] ?? '') ?>" required placeholder="مثال: أحمد محمد">
          </div>
          <div class="form-row">
            <label>رقم الهاتف</label>
            <input name="customer_phone" id="customerPhone" value="<?= Helpers::e($form['customer_phone'] ?? '') ?>" required placeholder="05xxxxxxxx">
          </div>
          <div class="form-row">
            <label>البريد الإلكتروني (اختياري)</label>
            <input name="customer_email" id="customerEmail" type="email" value="<?= Helpers::e($form['customer_email'] ?? '') ?>" placeholder="email@example.com">
          </div>
          <div class="form-row">
            <label>ملاحظات أو طلبات خاصة</label>
            <textarea name="notes" rows="3" placeholder="مناسبة خاصة، طلبات غذائية، مكان مفضل..."><?= Helpers::e($form['notes'] ?? '') ?></textarea>
          </div>
          <?php if ($otpEnabled): ?>
          <div class="form-row" id="otpSection">
            <label>رمز التحقق (OTP)</label>
            <div class="otp-wrap">
              <input name="otp" id="otpInput" maxlength="6" placeholder="••••" autocomplete="off">
              <button type="button" id="sendOtpBtn" class="btn btn-secondary">إرسال الرمز</button>
            </div>
            <div class="hint" id="otpHint"></div>
          </div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
          <span id="submitText">إرسال طلب الحجز</span>
        </button>
      </form>
    </div>

    <div class="side-col">
      <div class="card summary-card">
        <div class="card-title"><i class="fa-solid fa-receipt"></i> ملخص الحجز</div>
        <div class="summary-row">
          <span class="summary-label">الفرع</span>
          <span class="summary-value" id="summaryBranch">—</span>
        </div>
        <div class="summary-row">
          <span class="summary-label">التاريخ</span>
          <span class="summary-value" id="summaryDate">—</span>
        </div>
        <div class="summary-row">
          <span class="summary-label">الوقت</span>
          <span class="summary-value" id="summaryTime">—</span>
        </div>
        <div class="summary-row">
          <span class="summary-label">نوع الحجز</span>
          <span class="summary-value" id="summaryType">طعام في المطعم</span>
        </div>
        <div class="summary-row">
          <span class="summary-label">الضيوف</span>
          <span class="summary-value" id="summaryGuests">2</span>
        </div>
        <div class="summary-total">
          <div style="font-size:13px; color:var(--muted); margin-bottom:4px">الحالة</div>
          <div class="value" id="summaryStatus">في انتظار التأكيد</div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="page-footer">© <?= date('Y') ?> <?= Helpers::e($brandName) ?> — جميع الحقوق محفوظة</div>

<a href="/ai-assistant" target="_blank" class="ai-fab" title="تحدث مع مساعد الحجز الذكي"><i class="fa-solid fa-robot"></i></a>

<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ar.js"></script>
<script>
(function() {
  const types = <?= json_encode($jsTypes, JSON_UNESCAPED_UNICODE) ?>;
  const typeLabels = Object.fromEntries(Object.entries(types).map(([k,v]) => [k, v.name_ar || k]));
  const branchId = document.getElementById('branchId');
  const datePicker = document.getElementById('datePicker');
  const slotsGrid = document.getElementById('slotsGrid');
  const reservedAt = document.getElementById('reservedAt');
  const submitBtn = document.getElementById('submitBtn');
  const submitText = document.getElementById('submitText');
  const customerPhone = document.getElementById('customerPhone');
  const customerEmail = document.getElementById('customerEmail');
  const guestsInput = document.getElementById('guests');
  const incGuests = document.getElementById('incGuests');
  const decGuests = document.getElementById('decGuests');
  const typeCards = document.querySelectorAll('.type-card');
  const typeRequirements = document.getElementById('typeRequirements');

  const summaryBranch = document.getElementById('summaryBranch');
  const summaryDate = document.getElementById('summaryDate');
  const summaryTime = document.getElementById('summaryTime');
  const summaryType = document.getElementById('summaryType');
  const summaryGuests = document.getElementById('summaryGuests');

  function updateSummary() {
    summaryBranch.textContent = branchId.options[branchId.selectedIndex]?.text || '—';
    summaryDate.textContent = datePicker.value ? new Date(datePicker.value).toLocaleDateString('ar-SA') : '—';
    summaryTime.textContent = reservedAt.value ? reservedAt.value.split(' ')[1] : '—';
    const selectedType = document.querySelector('input[name="reservation_type"]:checked');
    summaryType.textContent = selectedType ? typeLabels[selectedType.value] : '—';
    summaryGuests.textContent = guestsInput.value || '—';
  }

  function selectedType() {
    const input = document.querySelector('input[name="reservation_type"]:checked');
    return input ? types[input.value] : null;
  }

  function applyTypeConstraints() {
    const t = selectedType();
    if (!t) return;
    let v = parseInt(guestsInput.value, 10) || t.default;
    v = Math.max(t.min, Math.min(t.max, v));
    guestsInput.value = v;
    typeRequirements.textContent = t.requirements ? t.requirements : '';
  }

  function setGuests(delta) {
    const t = selectedType();
    let v = parseInt(guestsInput.value, 10) || (t ? t.default : 2);
    v = Math.max(t ? t.min : 1, Math.min(t ? t.max : 99, v + delta));
    guestsInput.value = v;
    updateSummary();
    loadSlots();
  }
  incGuests.addEventListener('click', () => setGuests(1));
  decGuests.addEventListener('click', () => setGuests(-1));
  guestsInput.addEventListener('change', () => { applyTypeConstraints(); updateSummary(); loadSlots(); });

  typeCards.forEach(card => {
    card.addEventListener('click', () => {
      typeCards.forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      card.querySelector('input').checked = true;
      applyTypeConstraints();
      updateSummary();
      loadSlots();
    });
  });

  branchId.addEventListener('change', () => { updateSummary(); loadSlots(); });

  flatpickr(datePicker, {
    locale: 'ar',
    minDate: 'today',
    maxDate: new Date().fp_incr(30),
    dateFormat: 'Y-m-d',
    onChange: () => { updateSummary(); loadSlots(); }
  });

  function loadSlots() {
    const b = branchId.value;
    const d = datePicker.value;
    const g = guestsInput.value;
    const t = document.querySelector('input[name="reservation_type"]:checked');
    const typeCode = t ? t.value : 'regular';
    if (!b || !d) return;
    slotsGrid.innerHTML = '<div class="loading-skeleton">' + Array(8).fill('<div class="skeleton"></div>').join('') + '</div>';
    reservedAt.value = '';
    submitBtn.disabled = true;
    submitText.textContent = 'إرسال طلب الحجز';
    updateSummary();
    fetch('/book/slots?branch_id=' + b + '&date=' + d + '&guests=' + g + '&type=' + encodeURIComponent(typeCode))
      .then(r => r.json())
      .then(data => {
        slotsGrid.innerHTML = '';
        if (!data.ok || !data.slots.length) {
          slotsGrid.innerHTML = '<div class="slots-empty">لا توجد فترات متاحة في هذا اليوم، جرب يوماً آخر</div>';
          return;
        }
        data.slots.forEach(slot => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'slot-btn' + (slot.available ? '' : ' disabled') + (slot.peak ? ' peak' : '');
          btn.innerHTML = '<div class="slot-time">' + slot.time + '</div><div class="slot-info">' + (slot.available ? 'متبقي ' + slot.remaining : 'ممتلئ') + '</div>';
          if (slot.available) {
            btn.addEventListener('click', function() {
              document.querySelectorAll('.slot-btn').forEach(el => el.classList.remove('selected'));
              btn.classList.add('selected');
              reservedAt.value = d + ' ' + slot.time;
              submitBtn.disabled = false;
              updateSummary();
            });
          }
          slotsGrid.appendChild(btn);
        });
      })
      .catch(() => {
        slotsGrid.innerHTML = '<div class="slots-empty" style="color:#b91c1c">تعذر تحميل الفترات، تحقق من الاتصال وأعد المحاولة</div>';
      });
  }

  <?php if ($otpEnabled): ?>
  const sendOtpBtn = document.getElementById('sendOtpBtn');
  const otpHint = document.getElementById('otpHint');
  sendOtpBtn.addEventListener('click', function() {
    const phone = customerPhone.value.trim();
    if (!phone) { otpHint.textContent = 'أدخل رقم الهاتف أولاً'; otpHint.style.color = 'var(--danger)'; return; }
    otpHint.textContent = 'جاري إرسال الرمز...';
    otpHint.style.color = 'var(--muted)';
    fetch('/book/send-otp', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({customer_phone: phone, customer_email: customerEmail.value.trim()})
    })
    .then(r => r.json())
    .then(data => {
      otpHint.textContent = data.ok ? 'تم الإرسال! تحقق من البريد أو الواتساب' : data.error;
      otpHint.style.color = data.ok ? 'var(--success)' : 'var(--danger)';
    })
    .catch(() => { otpHint.textContent = 'فشل الإرسال، حاول مرة أخرى'; otpHint.style.color = 'var(--danger)'; });
  });
  <?php endif; ?>

  document.getElementById('bookingForm')?.addEventListener('submit', function() {
    submitBtn.disabled = true;
    submitText.textContent = 'جاري الإرسال...';
  });

  updateSummary();
})();
</script>
</body>
</html>
