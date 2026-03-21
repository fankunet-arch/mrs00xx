<?php
/**
 * SPS 采购轮次详情（核心采购视图）
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$round_id = (int)($_GET['id'] ?? 0);
if (!$round_id) { header('Location: /sps/ap/index.php?action=rounds'); exit; }

$round = $pdo->prepare("SELECT * FROM sps_rounds WHERE round_id = ?");
$round->execute([$round_id]);
$round = $round->fetch();
if (!$round) { header('Location: /sps/ap/index.php?action=rounds'); exit; }

// 获取所有部门（用于表头和明细显示）
$depts = $pdo->query("SELECT * FROM sps_departments WHERE status='active' ORDER BY sort_order")->fetchAll();
$dept_ids   = array_column($depts, 'dept_id');
$dept_names = array_column($depts, 'dept_name', 'dept_id');

// 获取本轮所有有效商品（至少一个部门有商品）及其汇总数量
// 商品 → 供货商分组
$sql = "
  SELECT
    p.product_id, p.name_cn, p.name_es, p.unit,
    s.supplier_id, s.supplier_name, s.sort_order as supplier_sort,
    COALESCE(rp.purchase_status, 'pending') as purchase_status,
    COALESCE(SUM(re.qty), 0) as total_qty
  FROM sps_products p
  JOIN sps_product_departments pd ON pd.product_id = p.product_id
  LEFT JOIN sps_suppliers s ON s.supplier_id = p.supplier_id
  LEFT JOIN sps_round_entries re ON re.product_id = p.product_id AND re.round_id = ?
  LEFT JOIN sps_round_purchase rp ON rp.product_id = p.product_id AND rp.round_id = ?
  WHERE p.status = 'active'
  GROUP BY p.product_id, p.name_cn, p.name_es, p.unit, s.supplier_id, s.supplier_name, s.sort_order, rp.purchase_status
  ORDER BY COALESCE(s.sort_order, 9999), COALESCE(s.supplier_name,''), p.product_id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$round_id, $round_id]);
$products = $stmt->fetchAll();

// 获取各部门明细（用于模态框）
$entries_sql = "
  SELECT re.product_id, re.dept_id, re.qty, re.updated_at, u.display_name as updated_by
  FROM sps_round_entries re
  LEFT JOIN sps_users u ON u.user_id = re.updated_by
  WHERE re.round_id = ? AND re.qty > 0
";
$entries_stmt = $pdo->prepare($entries_sql);
$entries_stmt->execute([$round_id]);
$all_entries = [];
foreach ($entries_stmt->fetchAll() as $e) {
    $all_entries[$e['product_id']][$e['dept_id']] = $e;
}

// 按供货商分组
$grouped = [];
foreach ($products as $p) {
    $key = $p['supplier_id'] ?: 0;
    $grouped[$key]['supplier_name'] = $p['supplier_name'] ?: '（未指定供货商）';
    $grouped[$key]['supplier_sort'] = $p['supplier_sort'] ?? 9999;
    $grouped[$key]['items'][] = $p;
}
uasort($grouped, fn($a, $b) => $a['supplier_sort'] <=> $b['supplier_sort']);

// 统计
$total = count($products);
$purchased    = count(array_filter($products, fn($p) => $p['purchase_status'] === 'purchased'));
$out_of_stock = count(array_filter($products, fn($p) => $p['purchase_status'] === 'out_of_stock'));
$pending      = $total - $purchased - $out_of_stock;
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($round['label']) ?> - SPS后台</title>
<link rel="stylesheet" href="/sps/assets/css/sps.css">
<style>
.status-btn-group { display:flex; gap:6px; }
.status-btn-group .btn { min-width:80px; }
.product-row.purchased td:first-child { border-left:3px solid var(--success); }
.product-row.out_of_stock td:first-child { border-left:3px solid var(--danger); }
.product-row.pending td:first-child { border-left:3px solid var(--gray-200); }
.qty-link { cursor:pointer; color:var(--primary); text-decoration:underline; font-weight:600; }
.qty-link:hover { color:var(--primary-dk); }
.progress-bar-wrap { height:8px; background:var(--gray-200); border-radius:4px; overflow:hidden; margin-top:8px; }
.progress-bar { height:100%; background:var(--success); border-radius:4px; transition:width .3s; }
</style>
</head>
<body class="layout-backend">
<?php include SPS_VIEW_PATH . '/shared/sidebar_backend.php'; ?>

<div class="main-content">
  <div class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
      <a href="/sps/ap/index.php?action=rounds" style="color:var(--gray-400);text-decoration:none">← 轮次列表</a>
      <h1><?= htmlspecialchars($round['label']) ?></h1>
      <span class="badge badge-<?= $round['status'] ?>"><?= $round['status'] === 'open' ? '进行中' : '已完成' ?></span>
    </div>
    <?php if ($round['status'] === 'open'): ?>
    <button class="btn btn-success" onclick="completeRound(<?= $round_id ?>)">
      ✓ 完成本次采购
    </button>
    <?php endif; ?>
  </div>

  <div class="page-body">
    <!-- 进度概览 -->
    <div class="stat-cards" style="grid-template-columns:repeat(4,1fr)">
      <div class="stat-card">
        <div class="label">总商品数</div>
        <div class="value"><?= $total ?></div>
      </div>
      <div class="stat-card">
        <div class="label">待采购</div>
        <div class="value" style="color:var(--warning)"><?= $pending ?></div>
      </div>
      <div class="stat-card">
        <div class="label">已采购</div>
        <div class="value" style="color:var(--success)"><?= $purchased ?></div>
      </div>
      <div class="stat-card">
        <div class="label">缺货</div>
        <div class="value" style="color:var(--danger)"><?= $out_of_stock ?></div>
      </div>
    </div>
    <?php if ($total > 0): ?>
    <div style="margin-bottom:20px">
      <div style="font-size:13px;color:var(--gray-600)">采购进度：<?= $purchased + $out_of_stock ?> / <?= $total ?></div>
      <div class="progress-bar-wrap">
        <div class="progress-bar" style="width:<?= $total > 0 ? round(($purchased + $out_of_stock) / $total * 100) : 0 ?>%"></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
      <div class="card"><div class="card-body">
        <div class="empty-state"><div class="icon">🛒</div><p>本轮暂无填报数据。请等待各部门提交用量后再来查看。</p></div>
      </div></div>
    <?php else: ?>

    <!-- 按供货商分组 -->
    <?php foreach ($grouped as $sup_id => $group): ?>
    <div class="supplier-group">
      <div class="supplier-group-header">
        <span class="name">🏪 <?= htmlspecialchars($group['supplier_name']) ?></span>
        <?php
        $g_pending = count(array_filter($group['items'], fn($i) => $i['purchase_status'] === 'pending'));
        ?>
        <?php if ($g_pending > 0): ?>
          <span class="count"><?= $g_pending ?> 件待处理</span>
        <?php else: ?>
          <span class="count" style="color:var(--success)">✓ 全部处理完毕</span>
        <?php endif; ?>
      </div>

      <table class="data-table">
        <thead><tr>
          <th>商品名称</th>
          <th class="num">汇总数量</th>
          <th style="width:220px">采购状态</th>
        </tr></thead>
        <tbody>
        <?php foreach ($group['items'] as $item): ?>
          <tr class="product-row <?= $item['purchase_status'] ?>" id="row-<?= $item['product_id'] ?>">
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($item['name_cn']) ?></div>
              <?php if ($item['name_es']): ?>
                <div style="font-size:12px;color:var(--gray-400)"><?= htmlspecialchars($item['name_es']) ?></div>
              <?php endif; ?>
            </td>
            <td class="num">
              <?php if ($item['total_qty'] > 0): ?>
                <span class="qty-link" onclick="showBreakdown(<?= $item['product_id'] ?>, '<?= htmlspecialchars(addslashes($item['name_cn'])) ?>', '<?= htmlspecialchars(addslashes($item['unit'])) ?>')">
                  <?= rtrim(rtrim(number_format($item['total_qty'], 2), '0'), '.') ?> <?= htmlspecialchars($item['unit']) ?>
                </span>
              <?php else: ?>
                <span style="color:var(--gray-400)">— 暂无填报</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($round['status'] === 'open'): ?>
              <div class="status-btn-group" id="btns-<?= $item['product_id'] ?>">
                <button class="btn btn-sm <?= $item['purchase_status'] === 'purchased' ? 'btn-success' : 'btn-secondary' ?>"
                        onclick="setStatus(<?= $round_id ?>, <?= $item['product_id'] ?>, 'purchased')">
                  ✓ 已采购
                </button>
                <button class="btn btn-sm <?= $item['purchase_status'] === 'out_of_stock' ? 'btn-danger' : 'btn-secondary' ?>"
                        onclick="setStatus(<?= $round_id ?>, <?= $item['product_id'] ?>, 'out_of_stock')">
                  ✗ 缺货
                </button>
                <?php if ($item['purchase_status'] !== 'pending'): ?>
                <button class="btn btn-sm btn-secondary" onclick="setStatus(<?= $round_id ?>, <?= $item['product_id'] ?>, 'pending')" title="撤销">
                  ↺
                </button>
                <?php endif; ?>
              </div>
              <?php else: ?>
                <span class="badge badge-<?= $item['purchase_status'] ?>">
                  <?= ['pending'=>'待采购','purchased'=>'已采购','out_of_stock'=>'缺货'][$item['purchase_status']] ?>
                </span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
  </div>
</div>

<!-- 部门明细模态框 -->
<div class="modal-overlay" id="breakdownModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="breakdownTitle">部门明细</h3>
      <button class="modal-close" onclick="closeBreakdown()">×</button>
    </div>
    <div class="modal-body" id="breakdownBody"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeBreakdown()">关闭</button>
    </div>
  </div>
</div>

<!-- 完成轮次确认模态框 -->
<div class="modal-overlay" id="completeModal">
  <div class="modal">
    <div class="modal-header">
      <h3>完成本次采购</h3>
      <button class="modal-close" onclick="document.getElementById('completeModal').classList.remove('open')">×</button>
    </div>
    <div class="modal-body">
      <?php if ($pending > 0): ?>
        <div class="flash flash-error" style="margin-bottom:12px">
          还有 <strong><?= $pending ?></strong> 件商品处于"待采购"状态，完成后将无法修改。
        </div>
      <?php endif; ?>
      <p style="color:var(--gray-600)">确认完成「<?= htmlspecialchars($round['label']) ?>」？</p>
      <p style="color:var(--gray-600);margin-top:8px;font-size:13px">完成后将自动开启下一轮次，各部门可重新填报。</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="document.getElementById('completeModal').classList.remove('open')">取消</button>
      <button class="btn btn-success" id="confirmCompleteBtn" onclick="doComplete(<?= $round_id ?>)">确认完成</button>
    </div>
  </div>
</div>

<script>
// ── 采购状态更新 ──
async function setStatus(roundId, productId, status) {
  try {
    const res = await fetch('/sps/ap/index.php?action=purchase_status', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ round_id: roundId, product_id: productId, status })
    });
    const data = await res.json();
    if (data.success) {
      updateRowUI(productId, status);
    } else {
      alert('操作失败：' + data.message);
    }
  } catch(e) { alert('网络错误'); }
}

function updateRowUI(productId, status) {
  const row = document.getElementById('row-' + productId);
  const btns = document.getElementById('btns-' + productId);
  if (!row || !btns) return;

  row.className = 'product-row ' + status;

  const labels = { purchased: '✓ 已采购', out_of_stock: '✗ 缺货', pending: '↺' };
  const btnClasses = {
    purchased:    ['btn-success', 'btn-secondary', null],
    out_of_stock: ['btn-secondary', 'btn-danger', null],
    pending:      ['btn-secondary', 'btn-secondary', null],
  };

  const allBtns = btns.querySelectorAll('.btn');
  const cls = btnClasses[status];
  if (allBtns[0]) allBtns[0].className = 'btn btn-sm ' + cls[0];
  if (allBtns[1]) allBtns[1].className = 'btn btn-sm ' + cls[1];

  // 撤销按钮
  let resetBtn = btns.querySelector('.reset-btn');
  if (status !== 'pending') {
    if (!resetBtn) {
      resetBtn = document.createElement('button');
      resetBtn.className = 'btn btn-sm btn-secondary reset-btn';
      resetBtn.title = '撤销';
      resetBtn.textContent = '↺';
      resetBtn.onclick = () => setStatus(<?= $round_id ?>, productId, 'pending');
      btns.appendChild(resetBtn);
    }
  } else {
    if (resetBtn) resetBtn.remove();
  }

  // 更新头部计数
  refreshGroupCounts();
}

function refreshGroupCounts() {
  // 简单刷新——重载页面或精细更新均可
  // 这里采用刷新进度条区域的简单方案
}

// ── 部门明细模态框 ──
const allEntries = <?= json_encode($all_entries, JSON_UNESCAPED_UNICODE) ?>;
const deptNames  = <?= json_encode($dept_names, JSON_UNESCAPED_UNICODE) ?>;

function showBreakdown(productId, productName, unit) {
  const entries = allEntries[productId] || {};
  document.getElementById('breakdownTitle').textContent = productName + ' · 部门明细';

  let html = '<table class="data-table"><thead><tr><th>部门</th><th class="num">数量</th><th class="num">最后更新</th></tr></thead><tbody>';
  let hasData = false;
  let total = 0;

  for (const [deptId, deptName] of Object.entries(deptNames)) {
    const e = entries[deptId];
    if (e && parseFloat(e.qty) > 0) {
      html += `<tr><td>${deptName}</td><td class="num"><strong>${parseFloat(e.qty)} ${unit}</strong></td><td class="num" style="font-size:12px;color:var(--gray-400)">${e.updated_at}</td></tr>`;
      total += parseFloat(e.qty);
      hasData = true;
    }
  }

  if (!hasData) {
    html += '<tr><td colspan="3" style="text-align:center;color:var(--gray-400)">暂无填报数据</td></tr>';
  } else {
    html += `<tr style="background:var(--gray-50);font-weight:700"><td>合计</td><td class="num">${total} ${unit}</td><td></td></tr>`;
  }

  html += '</tbody></table>';
  document.getElementById('breakdownBody').innerHTML = html;
  document.getElementById('breakdownModal').classList.add('open');
}

function closeBreakdown() {
  document.getElementById('breakdownModal').classList.remove('open');
}

document.getElementById('breakdownModal').addEventListener('click', function(e) {
  if (e.target === this) closeBreakdown();
});

// ── 完成轮次 ──
function completeRound(roundId) {
  document.getElementById('completeModal').classList.add('open');
}

async function doComplete(roundId) {
  const btn = document.getElementById('confirmCompleteBtn');
  btn.disabled = true;
  btn.textContent = '处理中...';

  try {
    const res = await fetch('/sps/ap/index.php?action=round_complete', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ round_id: roundId })
    });
    const data = await res.json();
    if (data.success) {
      location.href = '/sps/ap/index.php?action=round_detail&id=' + data.data.new_round_id;
    } else {
      alert('操作失败：' + data.message);
      btn.disabled = false;
      btn.textContent = '确认完成';
    }
  } catch(e) {
    alert('网络错误');
    btn.disabled = false;
    btn.textContent = '确认完成';
  }
}

document.getElementById('completeModal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
</script>
</body>
</html>
