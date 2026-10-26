<?php use App\Core\Helpers; ?>
<h2>المقيمون في السكن</h2>
<p>جميع المقيمين من مختلف الأقسام · العدد: <?= count($residents) ?></p>
<label>بحث بالاسم أو الكود أو القسم أو الموقع <input id="resident-search" type="search" style="max-width:100%;padding:8px"></label>
<div style="overflow-x:auto"><table class="table" id="resident-table"><thead><tr><th>الاسم</th><th>الكود</th><th>القسم</th><th>الموقع</th><th>الشقة</th><th>الغرفة</th></tr></thead><tbody>
<?php foreach($residents as $e): ?><tr><?php foreach(['full_name','emp_code','department_name','site_name','apartment_number','room_number'] as $k): ?><td><?= Helpers::e($e[$k] ?? '—') ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></div>
<script>document.getElementById('resident-search').addEventListener('input',function(){const q=this.value.trim().toLowerCase();document.querySelectorAll('#resident-table tbody tr').forEach(r=>r.hidden=!r.textContent.toLowerCase().includes(q));});</script>
