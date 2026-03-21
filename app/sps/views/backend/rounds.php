<?php
/**
 * SPS 采购轮次列表
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$rounds = $pdo->query("SELECT * FROM sps_rounds ORDER BY round_id DESC")->fetchAll();

// 计算下一个轮次标签（预览用）
$latest = $pdo->query("SELECT * FROM sps_rounds ORDER BY round_id DESC LIMIT 1")->fetch();
$next_label = '';
if ($latest) {
    $now_year  = (int)date('Y');
    $now_month = (int)date('n');
    if ($now_year === (int)$latest['round_year'] && $now_month === (int)$latest['round_month']) {
        $next_order = (int)$latest['order_in_month'] + 1;
        $next_label = $latest['round_month'] . '月 第' . $next_order . '次';
    } else {
        $next_label = $now_month . '月 第1次（' . $now_year . '）';
    }
} else {
    $next_label = date('n') . '月 第1次（' . date('Y') . '）';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>采购轮次 - SPS后台</title>
<link rel="stylesheet" href="/sps/assets/css/sps.css">
</head>
<body class="layout-backend">
<?php include SPS_VIEW_PATH . '/shared/sidebar_backend.php'; ?>

<div class="main-content">
  <div class="page-header">
    <h1>采购轮次</h1>
    <button class="btn btn-primary" onclick="openCreateModal()">+ 创建新轮次</button>
  </div>

  <div class="page-body">
    <?php if (empty($rounds)): ?>
      <div class="card"><div class="card-body">
        <div class="empty-state"><div class="icon">📋</div><p>还没有轮次，点击右上角创建第一个</p></div>
      </div></div>
    <?php else: ?>
    <div class="card">
      <div class="card-body" style="padding:0">
        <table class="data-table">
          <thead><tr>
            <th>轮次标签</th><th>年份</th><th>状态</th><th>创建时间</th><th>完成时间</th><th>操作</th>
          </tr></thead>
          <tbody>
          <?php foreach ($rounds as $r): ?>
            <tr>
              <td style="font-weight:600"><?= htmlspecialchars($r['label']) ?></td>
              <td><?= $r['round_year'] ?></td>
              <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] === 'open' ? '进行中' : '已完成' ?></span></td>
              <td><?= $r['created_at'] ?></td>
              <td><?= $r['completed_at'] ?: '-' ?></td>
              <td>
                <a href="/sps/ap/index.php?action=round_detail&id=<?= $r['round_id'] ?>" class="btn btn-primary btn-sm">
                  <?= $r['status'] === 'open' ? '采购视图' : '查看记录' ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- 创建轮次模态框 -->
<div class="modal-overlay" id="createModal">
  <div class="modal">
    <div class="modal-header">
      <h3>创建新轮次</h3>
      <button class="modal-close" onclick="closeCreateModal()">×</button>
    </div>
    <div class="modal-body">
      <p style="color:var(--gray-600);margin-bottom:16px">系统将自动计算轮次标签，预计下一轮为：</p>
      <div style="font-size:20px;font-weight:700;color:var(--primary);text-align:center;padding:16px;background:var(--gray-50);border-radius:6px;margin-bottom:16px">
        <?= htmlspecialchars($next_label) ?>
      </div>
      <?php
      $open_round = $pdo->query("SELECT round_id FROM sps_rounds WHERE status='open' LIMIT 1")->fetch();
      if ($open_round):
      ?>
      <div class="flash flash-error">当前有进行中的轮次，请先完成后再创建新轮次。</div>
      <?php else: ?>
      <div class="form-group">
        <label>备注（可选）</label>
        <input type="text" id="roundRemark" class="form-control" placeholder="如：节日特供、临时采购等">
      </div>
      <?php endif; ?>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeCreateModal()">取消</button>
      <?php if (!$open_round): ?>
      <button class="btn btn-primary" onclick="createRound()">确认创建</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function openCreateModal()  { document.getElementById('createModal').classList.add('open'); }
function closeCreateModal() { document.getElementById('createModal').classList.remove('open'); }

async function createRound() {
  const remark = document.getElementById('roundRemark')?.value || '';
  const btn = event.target;
  btn.disabled = true;
  btn.textContent = '创建中...';

  try {
    const res = await fetch('/sps/ap/index.php?action=round_create', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ remark })
    });
    const data = await res.json();
    if (data.success) {
      location.href = '/sps/ap/index.php?action=round_detail&id=' + data.data.round_id;
    } else {
      alert('创建失败：' + data.message);
      btn.disabled = false;
      btn.textContent = '确认创建';
    }
  } catch(e) {
    alert('网络错误');
    btn.disabled = false;
    btn.textContent = '确认创建';
  }
}

document.getElementById('createModal').addEventListener('click', function(e) {
  if (e.target === this) closeCreateModal();
});
</script>
</body>
</html>
