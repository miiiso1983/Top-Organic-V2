<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>إعدادات الطابعات الحرارية</h3>
  <p style="color:#666">اضبط الطابعات المتصلة بالأجهزة المحلية. قالب الفاتورة مصمم لطابعات 58mm و 80mm.</p>
  <form method="post" action="/admin/printer">
    <div class="grid grid-2">
      <div class="form-row">
        <label>عرض الورق (mm)</label>
        <select name="receipt_width">
          <option value="58" <?= $settings['receipt_width'] == 58 ? 'selected' : '' ?>>58mm</option>
          <option value="80" <?= $settings['receipt_width'] == 80 ? 'selected' : '' ?>>80mm</option>
        </select>
      </div>
      <div class="form-row">
        <label>عدد نسخ الفاتورة</label>
        <input type="number" name="copies" value="<?= (int)$settings['copies'] ?>" min="1" max="5">
      </div>
      <div class="form-row">
        <label>اسم الطابعة (اختياري)</label>
        <input name="printer_name" value="<?= Helpers::e($settings['printer_name']) ?>" placeholder="EPSON TM-T20III">
      </div>
      <div class="form-row">
        <label>طابعة المطبخ (اختياري)</label>
        <input name="kitchen_printer" value="<?= Helpers::e($settings['kitchen_printer']) ?>" placeholder="Kitchen Printer">
      </div>
      <div class="form-row">
        <label>رقم الضريبة</label>
        <input name="tax_number" value="<?= Helpers::e($settings['tax_number']) ?>">
      </div>
    </div>

    <div class="form-row">
      <label>رأس الفاتورة</label>
      <textarea name="receipt_header" rows="3"><?= Helpers::e($settings['receipt_header']) ?></textarea>
      <small>كل سطر جديد = سطر</small>
    </div>

    <div class="form-row">
      <label>ذيل الفاتورة</label>
      <textarea name="receipt_footer" rows="3"><?= Helpers::e($settings['receipt_footer']) ?></textarea>
    </div>

    <div class="form-inline" style="margin-top:12px">
      <label style="margin-inline-end:16px"><input type="checkbox" name="show_logo" value="1" <?= $settings['show_logo'] ? 'checked' : '' ?>> إظهار اسم المطعم</label>
      <label style="margin-inline-end:16px"><input type="checkbox" name="show_tax" value="1" <?= $settings['show_tax'] ? 'checked' : '' ?>> إظهار رقم الضريبة</label>
      <label style="margin-inline-end:16px"><input type="checkbox" name="show_cashier" value="1" <?= $settings['cashier_name'] ? 'checked' : '' ?>> إظهار اسم الكاشير</label>
      <label><input type="checkbox" name="auto_print" value="1" <?= $settings['auto_print'] ? 'checked' : '' ?>> فتح مربع الطباعة تلقائياً</label>
    </div>

    <button class="btn btn-success" style="margin-top:16px">حفظ الإعدادات</button>
  </form>
</div>

<div class="card">
  <h3>اختبار الطباعة</h3>
  <p>افتح صفحة <a href="/orders" target="_blank">الطلبات</a> واضغط زر طباعة. إذا كانت الطابعة مربوطة بالجهاز، تأكد من أنه محدد كالبرنت الطباعة الافتراضية.</p>
</div>


<div class="card" id="printer-card">
  <h3>إدارة الطابعات</h3>
  <p style="color:#666">أضف طابعات متعددة واربط كل طابعة بمطبخ تحضير. عند الطباعة، يتم توجيه البون إلى الطابعة المرتبطة بالمطبخ.</p>

  <form id="printer-form" method="post" action="/admin/printers" class="form-inline">
    <input type="hidden" id="printer-id" value="">
    <div class="form-row"><label>اسم الطابعة</label><input id="printer-name" name="name" placeholder="مثلاً: Kitchen Thermal" required></div>
    <div class="form-row"><label>النوع</label>
      <select id="printer-type" name="type">
        <?php foreach ($printerTypes as $key => $label): ?>
        <option value="<?= $key ?>"><?= Helpers::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>مركز التحضير</label>
      <select id="printer-kitchen" name="kitchen_id">
        <option value="">— بدون ربط —</option>
        <?php foreach ($kitchens as $k): ?>
        <option value="<?= $k['id'] ?>"><?= Helpers::e($k['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>IP (اختياري)</label><input id="printer-ip" name="ip" placeholder="192.168.1.100"></div>
    <div class="form-row"><label>Port (اختياري)</label><input id="printer-port" name="port" placeholder="9100"></div>
    <div class="form-row" style="min-width:100%">
      <label style="display:inline; margin-left:12px"><input type="checkbox" id="printer-active" name="is_active" value="1" checked> نشط</label>
    </div>
    <div class="form-inline">
      <button class="btn btn-success" id="printer-submit">إضافة طابعة</button>
      <button type="button" class="btn btn-secondary" id="printer-cancel" style="display:none" onclick="resetPrinterForm()">إلغاء</button>
    </div>
  </form>
</div>

<div class="card">
  <h3>الطابعات المسجلة</h3>
  <table>
    <tr><th>الاسم</th><th>النوع</th><th>المطبخ</th><th>IP</th><th>Port</th><th>الحالة</th><th></th></tr>
    <?php foreach ($printers as $p): ?>
    <?php $config = json_decode($p['config'] ?? '{}', true); ?>
    <tr>
      <td><?= Helpers::e($p['name']) ?></td>
      <td><?= Helpers::e($printerTypes[$p['type']] ?? $p['type']) ?></td>
      <td><?= Helpers::e($p['kitchen_name'] ?? '-') ?></td>
      <td><?= Helpers::e($config['ip'] ?? '-') ?></td>
      <td><?= Helpers::e($config['port'] ?? '-') ?></td>
      <td><?= $p['is_active'] ? 'نشط' : 'معطل' ?></td>
      <td>
        <button type="button" class="btn btn-sm btn-primary" onclick='editPrinter(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>تعديل</button>
        <form method="post" action="/admin/printers/<?= (int)$p['id'] ?>/delete" style="display:inline" onsubmit="return confirm('حذف الطابعة؟')">
          <button class="btn btn-sm btn-danger">حذف</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$printers): ?><tr><td colspan="7">لا توجد طابعات مسجلة</td></tr><?php endif; ?>
  </table>
</div>

<script>
function editPrinter(p) {
  const form = document.getElementById('printer-form');
  form.action = '/admin/printers/' + p.id;
  document.getElementById('printer-id').value = p.id;
  document.getElementById('printer-name').value = p.name || '';
  document.getElementById('printer-type').value = p.type || 'thermal';
  document.getElementById('printer-kitchen').value = p.kitchen_id || '';
  const config = JSON.parse(p.config || '{}');
  document.getElementById('printer-ip').value = config.ip || '';
  document.getElementById('printer-port').value = config.port || '';
  document.getElementById('printer-active').checked = p.is_active == 1;
  document.getElementById('printer-submit').textContent = 'حفظ التعديلات';
  document.getElementById('printer-cancel').style.display = '';
  document.getElementById('printer-card').scrollIntoView({ behavior: 'smooth' });
}
function resetPrinterForm() {
  const form = document.getElementById('printer-form');
  form.action = '/admin/printers';
  form.reset();
  document.getElementById('printer-id').value = '';
  document.getElementById('printer-submit').textContent = 'إضافة طابعة';
  document.getElementById('printer-cancel').style.display = 'none';
}
</script>
