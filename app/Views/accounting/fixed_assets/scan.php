<?php use App\Core\Helpers; ?>
<div class="card">
  <h2>مسح باركود / QR</h2>
  <form method="get" class="form-inline">
    <input type="text" name="code" value="<?= Helpers::e($code) ?>" placeholder="أدخل الباركود أو رقم الأصل" autofocus>
    <button class="btn btn-success">بحث</button>
  </form>
  <?php if ($code && !$asset): ?>
  <div class="alert alert-danger" style="margin-top:12px">لم يتم العثور على أصل مطابق.</div>
  <?php endif; ?>
</div>
