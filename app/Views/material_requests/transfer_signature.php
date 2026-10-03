<?php use App\Core\Helpers; ?>
<style>
.signature-pad { border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; cursor: crosshair; width: 100%; max-width: 500px; height: 200px; }
.mr-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
.mr-table { width: 100%; border-collapse: collapse; }
.mr-table th, .mr-table td { border: 1px solid #e5e7eb; padding: 10px; text-align: right; }
.mr-table th { background: #f3f4f6; }
</style>

<div class="mr-card">
  <h3>تسليم مناقلة <?= Helpers::e($transfer['transfer_no']) ?></h3>
  <p>طلب المواد: <a href="/material-requests/<?= $transfer['mr_id'] ?>"><?= Helpers::e($transfer['request_no']) ?></a></p>
  <p>من: <?= Helpers::e($transfer['from_warehouse']) ?> — إلى: <?= Helpers::e($transfer['to_warehouse']) ?></p>
  <p>الحالة: <?= Helpers::e($transfer['status']) ?></p>
</div>

<div class="mr-card">
  <h4>البنود</h4>
  <table class="mr-table">
    <tr><th>المادة</th><th>الكمية</th><th>الوحدة</th></tr>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= Helpers::e($it['item_name']) ?></td>
      <td><?= number_format((float)$it['qty'], 2) ?></td>
      <td><?= Helpers::e($it['unit_name'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($transfer['status'] === 'approved'): ?>
<div class="mr-card">
  <form method="POST" action="/material-requests/transfer/<?= $transfer['id'] ?>/prepare">
    <button type="submit" class="btn btn-primary">تجهيز المادة</button>
  </form>
</div>
<?php endif; ?>

<?php if (in_array($transfer['status'], ['approved', 'prepared'], true)): ?>
<div class="mr-card">
  <h4>توقيع المستلم</h4>
  <form method="POST" action="/material-requests/transfer/<?= $transfer['id'] ?>/deliver" onsubmit="return captureSignature()">
    <div class="form-group">
      <label>اسم المستلم</label>
      <input type="text" name="recipient_name" class="form-input" required>
    </div>
    <div class="form-group">
      <label>التوقيع الإلكتروني</label>
      <canvas id="sig-canvas" class="signature-pad"></canvas>
      <input type="hidden" name="recipient_signature" id="sig-data">
    </div>
    <div class="form-group">
      <button type="button" class="btn btn-light" onclick="clearSignature()">مسح التوقيع</button>
      <button type="submit" class="btn btn-success">تسليم للقسم الطالب</button>
    </div>
  </form>
</div>
<?php endif; ?>

<script>
const canvas = document.getElementById('sig-canvas');
const ctx = canvas.getContext('2d');
let drawing = false;
function resizeCanvas() {
  const rect = canvas.getBoundingClientRect();
  canvas.width = rect.width;
  canvas.height = rect.height;
  ctx.strokeStyle = '#000';
  ctx.lineWidth = 2;
}
window.addEventListener('load', resizeCanvas);
window.addEventListener('resize', resizeCanvas);
function getPos(e) {
  const rect = canvas.getBoundingClientRect();
  const clientX = e.touches ? e.touches[0].clientX : e.clientX;
  const clientY = e.touches ? e.touches[0].clientY : e.clientY;
  return { x: clientX - rect.left, y: clientY - rect.top };
}
function startDraw(e) { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); }
function draw(e) { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
function stopDraw() { drawing = false; }
canvas.addEventListener('mousedown', startDraw);
canvas.addEventListener('mousemove', draw);
canvas.addEventListener('mouseup', stopDraw);
canvas.addEventListener('mouseleave', stopDraw);
canvas.addEventListener('touchstart', startDraw);
canvas.addEventListener('touchmove', draw);
canvas.addEventListener('touchend', stopDraw);
function clearSignature() { ctx.clearRect(0, 0, canvas.width, canvas.height); document.getElementById('sig-data').value = ''; }
function captureSignature() {
  const data = canvas.toDataURL('image/png');
  document.getElementById('sig-data').value = data;
  return true;
}
</script>
