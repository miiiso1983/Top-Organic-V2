<?php use App\Core\Helpers; ?>
<div class="card">
  <h2 style="margin-top:0">إعدادات OpenAI</h2>
  <p style="color:#6b7280; font-size:14px">التكامل مع مفتاح API يفعّل مساعد الحجز الذكي. إذا تم التعطيل أو إخفاء المفتاح، يعود النظام تلقائياً للمحرّك القاعدي.</p>

  <form method="POST" action="/admin/openai-settings">
    <div class="form-row">
      <label class="form-label">تفعيل OpenAI</label>
      <select name="enabled" class="form-input">
        <option value="1" <?= $enabled ? 'selected' : '' ?>>مفعّل</option>
        <option value="0" <?= !$enabled ? 'selected' : '' ?>>معطّل</option>
      </select>
    </div>

    <div class="form-row">
      <label class="form-label">مفتاح API</label>
      <input type="password" name="api_key" class="form-input" value="<?= Helpers::e($apiKey) ?>" placeholder="sk-...">
      <div class="hint">يمكنك إدخال مفتاح OpenAI السري هنا.</div>
    </div>

    <div class="form-row">
      <label class="form-label">النموذج</label>
      <input type="text" name="model" class="form-input" value="<?= Helpers::e($model) ?>" placeholder="gpt-4o-mini">
      <div class="hint">مثال: gpt-4o-mini, gpt-4o, gpt-3.5-turbo</div>
    </div>

    <div class="form-row">
      <label class="form-label">أقصى عدد الرموز (Max Tokens)</label>
      <input type="number" name="max_tokens" class="form-input" value="<?= (int)$maxTokens ?>" min="50" max="4000">
    </div>

    <div class="form-row">
      <label class="form-label">درجة الإبداع (Temperature)</label>
      <input type="number" step="0.1" name="temperature" class="form-input" value="<?= (float)$temperature ?>" min="0" max="1">
      <div class="hint">قيم من 0 إلى 1. أقل = أكثر حصولةً.</div>
    </div>

    <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
  </form>
</div>
