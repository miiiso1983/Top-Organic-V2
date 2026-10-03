<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>ملصق الأصل <?= \App\Core\Helpers::e($asset['asset_no']) ?></title>
<style>
body { font-family: 'Cairo', sans-serif; text-align: center; padding: 24px; }
.label { border: 2px solid #333; border-radius: 8px; padding: 20px; display: inline-block; min-width: 240px; }
.asset-no { font-size: 22px; font-weight: bold; margin: 10px 0; }
.name { font-size: 16px; color: #555; margin-bottom: 14px; }
.qr { width: 160px; height: 160px; margin: 0 auto; }
.qr svg { width: 100%; height: 100%; }
</style>
</head>
<body>
<div class="label">
  <div class="asset-no"><?= \App\Core\Helpers::e($asset['asset_no']) ?></div>
  <div class="name"><?= \App\Core\Helpers::e($asset['name']) ?></div>
  <div class="qr" id="qr"></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qr'), { text: '<?= \App\Core\Helpers::e($asset['qr_code'] ?: $asset['asset_no']) ?>', width: 160, height: 160 });
window.print();
</script>
</body>
</html>
