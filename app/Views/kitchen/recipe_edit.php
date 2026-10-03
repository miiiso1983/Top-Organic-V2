<?php use App\Core\Helpers; ?>
<div class="card">
  <h3>مكونات: <?= Helpers::e($menuItem['name']) ?>
      <small style="color:#6b7280">(التكلفة الحالية: <?= Helpers::money($menuItem['cost']) ?>)</small></h3>
  <form method="post" action="/recipes/<?= $menuItem['id'] ?>">
    <table id="ing-table">
      <tr><th>المادة</th><th>الكمية (بالوحدة العامة)</th><th></th></tr>
      <?php foreach ($ingredients as $ing): ?>
      <tr>
        <td>
          <select name="item_id[]">
            <?php foreach ($stockItems as $si): ?>
              <option value="<?= $si['id'] ?>" <?= $si['id'] == $ing['item_id'] ? 'selected' : '' ?>>
                <?= Helpers::e($si['name']) ?> (<?= Helpers::e($si['unit']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input type="number" name="qty[]" value="<?= $ing['qty'] ?>" step="0.001" min="0"></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fa-solid fa-xmark"></i></button></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <button type="button" class="btn btn-secondary" style="margin-top:10px" onclick="addRow()">+ إضافة مكون</button>
    <button class="btn btn-success" style="margin-top:10px"><i class="fa-solid fa-lock"></i> حفظ الوصفة وحساب التكلفة</button>
  </form>
  <a href="/recipes" style="display:inline-block;margin-top:12px">← العودة للوصفات</a>
</div>

<template id="row-template">
  <tr>
    <td>
      <select name="item_id[]">
        <?php foreach ($stockItems as $si): ?>
          <option value="<?= $si['id'] ?>"><?= Helpers::e($si['name']) ?> (<?= Helpers::e($si['unit']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="number" name="qty[]" value="0" step="0.001" min="0"></td>
    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="fa-solid fa-xmark"></i></button></td>
  </tr>
</template>

<script>
function addRow() {
  const tpl = document.getElementById('row-template');
  document.querySelector('#ing-table').appendChild(tpl.content.cloneNode(true));
}
<?php if (!$ingredients): ?>addRow();<?php endif; ?>
</script>
