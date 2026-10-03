<?php
// مخطط متابعة مرئي للطلب — يُدرج من request_view.php
// المتطلبات: $graph (من Engine::graphForRequest), $request
use App\Core\Helpers;

if (!$graph || !$graph['nodes']) return;

$tasksByNode = [];
foreach ($graph['tasks'] as $t) { $tasksByNode[(int)$t['node_id']][] = $t; }

$typeMeta = [
    'start' => ['بداية', '#16a34a'], 'approval' => ['موافقة', '#2563eb'], 'condition' => ['شرط', '#d97706'],
    'action' => ['إجراء', '#7c3aed'], 'notify' => ['إشعار', '#0891b2'], 'wait' => ['انتظار', '#64748b'],
    'return' => ['إعادة', '#ea580c'], 'reject' => ['رفض', '#dc2626'], 'end' => ['نهاية', '#111827'],
];
$stateColors = ['done' => '#16a34a', 'current' => '#f59e0b', 'rejected' => '#dc2626', 'returned' => '#ea580c', 'upcoming' => '#94a3b8'];
$stateLabels = ['done' => '✔', 'current' => '⏳', 'rejected' => '✖', 'returned' => '↩', 'upcoming' => ''];

$stateOf = function (array $node) use ($graph, $tasksByNode, $request) {
    $id = (int)$node['id'];
    $tasks = $tasksByNode[$id] ?? [];
    if ($request['status'] === 'pending' && (int)$graph['current_node_id'] === $id) return 'current';
    foreach ($tasks as $t) { if ($t['status'] === 'rejected') return 'rejected'; }
    foreach ($tasks as $t) { if ($t['status'] === 'returned') return 'returned'; }
    if ($node['node_type'] === 'start') return 'done';
    if ($node['node_type'] === 'end') return $request['status'] === 'approved' ? 'done' : ($request['status'] === 'rejected' ? 'rejected' : 'upcoming');
    if ($tasks) {
        $pending = array_filter($tasks, fn($t) => $t['status'] === 'pending');
        if (!$pending) return 'done';
    }
    return 'upcoming';
};

$duration = function (array $t): string {
    $start = strtotime((string)$t['created_at']);
    $end = $t['decided_at'] ? strtotime((string)$t['decided_at']) : time();
    $mins = max(0, (int)round(($end - $start) / 60));
    if ($mins < 60) return $mins . ' د';
    $h = intdiv($mins, 60); $m = $mins % 60;
    return $h . ' س' . ($m ? ' ' . $m . ' د' : '');
};
$nodeById = [];
foreach ($graph['nodes'] as $n) { $nodeById[(int)$n['id']] = $n; }
$maxY = max(array_map(fn($n) => (int)$n['pos_y'], $graph['nodes'])) + 120;
$taskStatusLabels = ['pending'=>'معلق','approved'=>'✔','rejected'=>'✖','returned'=>'↩','skipped'=>'—','cancelled'=>'ملغي','escalated'=>'⬆'];
?>
<style>
.wfg-wrap { position:relative; direction:ltr; background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; overflow:auto;
  background-image:radial-gradient(circle,#cbd5e1 1px,transparent 1px); background-size:22px 22px; }
.wfg-canvas { position:relative; height:<?= (int)$maxY ?>px; min-width:700px; }
.wfg-node { position:absolute; width:170px; background:#fff; border-radius:10px; border:2px solid #94a3b8; box-shadow:0 2px 6px rgba(0,0,0,.08); }
.wfg-node .h { padding:4px 8px; border-radius:8px 8px 0 0; color:#fff; font-size:11px; font-weight:bold; }
.wfg-node .b { padding:5px 8px; font-size:12px; }
.wfg-node .t { font-size:10px; color:#6b7280; padding:0 8px 5px; }
.wfg-badge { position:absolute; top:-10px; right:-8px; width:22px; height:22px; border-radius:50%; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; }
.wfg-el { position:absolute; font-size:10px; background:#fff; border:1px solid #e5e7eb; border-radius:4px; padding:0 3px; }
</style>
<div class="card">
  <h3>مخطط سير الطلب</h3>
  <div class="wfg-wrap">
    <div class="wfg-canvas">
      <svg style="position:absolute;top:0;left:0;width:100%;height:100%">
        <defs><marker id="wfg-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#475569"/></marker></defs>
        <?php foreach ($graph['edges'] as $e):
          $a = $nodeById[(int)$e['from_node_id']] ?? null; $b = $nodeById[(int)$e['to_node_id']] ?? null;
          if (!$a || !$b) continue;
          $ax = (int)$a['pos_x'] + 85; $ay = (int)$a['pos_y'] + 62;
          if ($e['handle'] === 'yes') $ax = (int)$a['pos_x'] + 42;
          if ($e['handle'] === 'no')  $ax = (int)$a['pos_x'] + 128;
          $bx = (int)$b['pos_x'] + 85; $by = (int)$b['pos_y'];
          $midY = ($ay + $by) / 2;
          $color = $e['handle'] === 'yes' ? '#16a34a' : ($e['handle'] === 'no' ? '#dc2626' : '#64748b');
        ?>
        <path d="M <?= $ax ?> <?= $ay ?> C <?= $ax ?> <?= $midY ?>, <?= $bx ?> <?= $midY ?>, <?= $bx ?> <?= $by ?>"
              fill="none" stroke="<?= $color ?>" stroke-width="2.5" marker-end="url(#wfg-arrow)"/>
        <?php if ($e['label']): ?>
        <?php endif; endforeach; ?>
      </svg>
      <?php foreach ($graph['edges'] as $e):
        if (!$e['label']) continue;
        $a = $nodeById[(int)$e['from_node_id']] ?? null; $b = $nodeById[(int)$e['to_node_id']] ?? null;
        if (!$a || !$b) continue;
        $lx = ((int)$a['pos_x'] + 85 + (int)$b['pos_x'] + 85) / 2;
        $ly = ((int)$a['pos_y'] + 62 + (int)$b['pos_y']) / 2 - 8;
      ?>
      <div class="wfg-el" style="left:<?= (int)$lx ?>px;top:<?= (int)$ly ?>px"><?= Helpers::e($e['label']) ?></div>
      <?php endforeach; ?>
      <?php foreach ($graph['nodes'] as $n):
        $st = $stateOf($n);
        $meta = $typeMeta[$n['node_type']] ?? [$n['node_type'], '#64748b'];
        $tasks = $tasksByNode[(int)$n['id']] ?? [];
      ?>
      <div class="wfg-node" style="left:<?= (int)$n['pos_x'] ?>px;top:<?= (int)$n['pos_y'] ?>px;border-color:<?= $stateColors[$st] ?>">
        <div class="wfg-badge" style="background:<?= $stateColors[$st] ?>"><?= $stateLabels[$st] ?></div>
        <div class="h" style="background:<?= $meta[1] ?>"><?= Helpers::e($meta[0]) ?></div>
        <div class="b"><?= Helpers::e($n['title']) ?></div>
        <?php foreach ($tasks as $t): ?>
        <div class="t">
          <?= Helpers::e($t['full_name']) ?> <?= $taskStatusLabels[$t['status']] ?? $t['status'] ?>
          · <?= $duration($t) ?>
          <?php if ($t['status'] === 'pending' && $t['due_at'] && strtotime($t['due_at']) < time()): ?>
            <span style="color:#dc2626;font-weight:bold">متأخر!</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
