<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول - Top Organic</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="/assets/css/app.css?v=10">
<meta name="theme-color" content="#1B5E20">
<script>
(function() {
  if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.classList.add('dark');
  }
})();
</script>
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <img src="/assets/images/logo.webp" alt="Top Organic" class="login-logo">
<h1>Top Organic</h1>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/login">
      <div class="form-row">
        <label>اسم المستخدم</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-row">
        <label>كلمة المرور</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn" style="width:100%">دخول</button>
    </form>
  </div>
</div>
</body>
</html>
