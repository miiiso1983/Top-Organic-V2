<?php use App\Core\Helpers; ?>
<div class="page-head">
  <h2><i class="fa-solid fa-diagram-project"></i> <?= Helpers::e($def['name']) ?>
    <small style="color:#6b7280">مسودة v<?= (int)$version['version_no'] ?></small>
    <?php if ($published): ?><span class="badge badge-success">منشور: v<?= (int)$published['version_no'] ?></span><?php endif; ?>
  </h2>
  <div>
    <a href="/workflow-engine" class="btn btn-secondary">رجوع</a>
    <button id="btnSave" class="btn btn-primary">حفظ</button>
    <button id="btnPublish" class="btn btn-success">نشر</button>
  </div>
</div>

<style>
#wfd-wrap { display:flex; gap:10px; direction:ltr; height:calc(100vh - 170px); min-height:520px; }
#wfd-palette { width:150px; flex-shrink:0; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:8px; overflow-y:auto; }
.wfd-pal-item { padding:8px 10px; margin-bottom:6px; border-radius:8px; color:#fff; font-size:13px; cursor:grab; text-align:center; user-select:none; }
#wfd-canvas-wrap { flex:1; position:relative; background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; overflow:auto;
  background-image:radial-gradient(circle,#cbd5e1 1px,transparent 1px); background-size:22px 22px; }
#wfd-canvas { position:relative; width:2000px; height:1400px; }
#wfd-svg { position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; }
.wfd-node { position:absolute; width:170px; background:#fff; border:2px solid #94a3b8; border-radius:10px; cursor:move; user-select:none; box-shadow:0 2px 6px rgba(0,0,0,.08); }
.wfd-node.selected { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.2); }
.wfd-node .wfd-head { padding:6px 10px; border-radius:8px 8px 0 0; color:#fff; font-size:12px; font-weight:bold; }
.wfd-node .wfd-body { padding:6px 10px; font-size:13px; }
.wfd-port { position:absolute; width:14px; height:14px; border-radius:50%; background:#fff; border:3px solid #475569; cursor:crosshair; z-index:5; }
.wfd-port.out { bottom:-8px; left:50%; margin-left:-7px; }
.wfd-port.in { top:-8px; left:50%; margin-left:-7px; }
.wfd-port.yes { bottom:-8px; left:25%; margin-left:-7px; background:#dcfce7; border-color:#16a34a; }
.wfd-port.no { bottom:-8px; left:75%; margin-left:-7px; background:#fee2e2; border-color:#dc2626; }
.wfd-port.pending { box-shadow:0 0 0 4px rgba(37,99,235,.4); }
.wfd-edge-label { position:absolute; font-size:11px; background:#fff; padding:0 4px; border-radius:4px; border:1px solid #e5e7eb; }
#wfd-props { width:320px; flex-shrink:0; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:12px; overflow-y:auto; direction:rtl; }
#wfd-props h4 { margin:0 0 10px; }
#wfd-props .form-row { margin-bottom:8px; }
#wfd-props label { font-size:12px; color:#374151; }
#wfd-props input, #wfd-props select, #wfd-props textarea { width:100%; box-sizing:border-box; }
.wfd-rule-row, .wfd-rule-group { border:1px dashed #cbd5e1; border-radius:8px; padding:6px; margin-bottom:6px; }
.wfd-rule-group { background:#f8fafc; }
.wfd-mini { font-size:11px; padding:2px 8px; }
#wfd-toast { position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:#111827; color:#fff;
  padding:10px 22px; border-radius:8px; display:none; z-index:999; }
</style>

<div id="wfd-wrap">
  <div id="wfd-palette"></div>
  <div id="wfd-canvas-wrap">
    <div id="wfd-canvas"><svg id="wfd-svg"></svg></div>
  </div>
  <div id="wfd-props"><h4>خصائص العقدة</h4><div id="wfd-props-body" style="color:#9ca3af;font-size:13px">اختر عقدة من المخطط</div></div>
</div>
<div id="wfd-toast"></div>

<script>
window.WFD = {
  defId: <?= (int)$def['id'] ?>,
  versionId: <?= (int)$version['id'] ?>,
  urls: {
    graph: '/workflow-engine/versions/<?= (int)$version['id'] ?>/graph',
    meta: '/workflow-engine/<?= (int)$def['id'] ?>/meta',
    publish: '/workflow-engine/versions/<?= (int)$version['id'] ?>/publish'
  }
};
</script>
<script src="/assets/js/workflow-designer.js?v=1"></script>
