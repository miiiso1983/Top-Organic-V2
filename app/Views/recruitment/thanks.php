<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تم استلام طلبك - Top Organic</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="/assets/css/app.css?v=10">
<meta name="theme-color" content="#1B5E20">
<style>
body { background: linear-gradient(160deg, var(--brand-dark), var(--brand)); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.thanks-box { background: var(--surface); border-radius: 14px; padding: 40px; width: 420px; max-width: 92vw; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,.2); }
.thanks-box i { font-size: 52px; color: var(--success); margin-bottom: 14px; }
.thanks-box h2 { margin: 0 0 8px; }
.thanks-box p { color: var(--text-muted); margin: 6px 0; }
.app-no { display: inline-block; background: var(--surface-alt); padding: 8px 16px; border-radius: 8px; font-weight: 700; margin-top: 10px; letter-spacing: 1px; }
</style>
</head>
<body>
<div class="thanks-box">
  <i class="fa-solid fa-circle-check"></i>
  <h2>تم استلام طلبك بنجاح</h2>
  <p>سيقوم فريق الموارد البشرية بمراجعة طلبك والتواصل معك في حال توافقك مع الوظيفة.</p>
  <?php if ($applicationNo): ?>
  <p>رقم استمارتك:</p>
  <div class="app-no"><?= htmlspecialchars($applicationNo) ?></div>
  <?php endif; ?>
  <p style="margin-top:20px"><a href="/careers/apply">تقديم طلب آخر</a></p>
</div>
</body>
</html>
