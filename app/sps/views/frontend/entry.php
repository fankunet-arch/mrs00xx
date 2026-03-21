<?php
/**
 * SPS 前台：Staff 填报页
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_login('/sps/index.php?action=login');

$user_id = $_SESSION['sps_user_id'];

// 当前开放轮次
$round = $pdo->query("SELECT * FROM sps_rounds WHERE status='open' ORDER BY round_id DESC LIMIT 1")->fetch();

// 用户归属部门
$dept_stmt = $pdo->prepare("
  SELECT d.dept_id, d.dept_name, d.sort_order
  FROM sps_user_departments ud
  JOIN sps_departments d ON d.dept_id = ud.dept_id
  WHERE ud.user_id = ? AND d.status='active'
  ORDER BY d.sort_order
");
$dept_stmt->execute([$user_id]);
$my_depts = $dept_stmt->fetchAll();
$my_dept_ids = array_column($my_depts, 'dept_id');

$products_by_dept = [];

if ($round && !empty($my_dept_ids)) {
    // 获取各部门对应商品及当前填报值
    $placeholders = implode(',', array_fill(0, count($my_dept_ids), '?'));
    $sql = "
      SELECT
        p.product_id, p.name_cn, p.name_es, p.unit, p.sort_order,
        pd.dept_id,
        COALESCE(re.qty, 0) as qty,
        re.updated_at
      FROM sps_products p
      JOIN sps_product_departments pd ON pd.product_id = p.product_id AND pd.dept_id IN ($placeholders)
      LEFT JOIN sps_round_entries re
        ON re.product_id = p.product_id
        AND re.round_id = ?
        AND re.dept_id = pd.dept_id
      WHERE p.status = 'active'
      ORDER BY pd.dept_id, p.sort_order, p.product_id
    ";
    $stmt = $pdo->prepare($sql);
    $params = array_merge($my_dept_ids, [$round['round_id']]);
    $stmt->execute($params);

    foreach ($stmt->fetchAll() as $row) {
        $products_by_dept[$row['dept_id']][] = $row;
    }
}

$dept_map = array_column($my_depts, 'dept_name', 'dept_id');
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>备货填报 - 备货规划系统</title>
<link rel="stylesheet" href="/sps/assets/css/sps.css">
<style>
.dept-section { margin-bottom: 28px; }
.dept-title { font-size: 16px; font-weight: 700; color: var(--gray-800); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid var(--primary); display: flex; align-items: center; gap: 8px; }
.entry-row { display: grid; grid-template-columns: 1fr auto auto; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--gray-100); }
.entry-row:last-child { border-bottom: none; }
.entry-info .name { font-weight: 600; font-size: 15px; }
.entry-info .name-es { font-size: 12px; color: var(--gray-400); margin-top: 2px; }
.qty-wrap { display: flex; align-items: center; gap: 8px; }
.qty-input { width: 100px; padding: 8px 12px; border: 1.5px solid var(--gray-200); border-radius: 6px; font-size: 15px; text-align: right; transition: border-color .2s; }
.qty-input:focus { border-color: var(--primary); outline: none; }
.qty-input.saving { border-color: var(--warning); }
.qty-input.saved  { border-color: var(--success); }
.unit-label { font-size: 14px; color: var(--gray-600); min-width: 30px; }
.save-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--gray-200); transition: background .3s; flex-shrink: 0; }
.save-dot.saving { background: var(--warning); }
.save-dot.saved   { background: var(--success); }
.round-badge { display: inline-flex; align-items: center; gap: 6px; background: #eff6ff; color: var(--primary); border: 1px solid #bfdbfe; border-radius: 20px; padding: 4px 14px; font-size: 13px; font-weight: 600; }
</style>
</head>
<body class="layout-frontend">
<div class="top-bar">
  <div class="brand">备货规划系统</div>
  <div class="user-area">
    <span>👋 <?= htmlspecialchars($_SESSION['sps_display'] ?? '') ?></span>
    <?php if (!empty($my_depts)): ?>
      <span style="color:#64748b">
        <?= implode(' · ', array_map(fn($d) => htmlspecialchars($d['dept_name']), $my_depts)) ?>
      </span>
    <?php endif; ?>
    <a href="/sps/index.php?action=do_logout">退出</a>
  </div>
</div>

<div class="fe-body">
  <!-- 轮次信息 -->
  <?php if (!$round): ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="icon">📋</div>
          <p>暂无开放的采购轮次，请等待管理员开启</p>
        </div>
      </div>
    </div>
  <?php elseif (empty($my_depts)): ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="icon">🏢</div>
          <p>您尚未分配到任何部门，请联系管理员</p>
        </div>
      </div>
    </div>
  <?php else: ?>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <div class="round-badge">
      📋 当前轮次：<?= htmlspecialchars($round['label']) ?>
    </div>
    <div style="font-size:13px;color:var(--gray-400)">填写后自动保存，关闭前可随时修改</div>
  </div>

  <!-- 各部门填报区块 -->
  <?php foreach ($my_depts as $dept): ?>
    <?php $dept_id = $dept['dept_id']; ?>
    <?php $items = $products_by_dept[$dept_id] ?? []; ?>

    <div class="dept-section card">
      <div class="card-header">
        <div class="dept-title" style="margin-bottom:0;border-bottom:none">
          🏢 <?= htmlspecialchars($dept['dept_name']) ?>
        </div>
        <span style="font-size:13px;color:var(--gray-400)"><?= count($items) ?> 个商品</span>
      </div>
      <div class="card-body">
        <?php if (empty($items)): ?>
          <div style="color:var(--gray-400);text-align:center;padding:20px">此部门暂无商品</div>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <div class="entry-row">
              <div class="entry-info">
                <div class="name"><?= htmlspecialchars($item['name_cn']) ?></div>
                <?php if ($item['name_es']): ?>
                  <div class="name-es"><?= htmlspecialchars($item['name_es']) ?></div>
                <?php endif; ?>
              </div>
              <div class="qty-wrap">
                <input
                  type="number"
                  class="qty-input"
                  min="0"
                  step="0.1"
                  value="<?= $item['qty'] > 0 ? rtrim(rtrim(number_format($item['qty'], 2),'0'),'.') : '' ?>"
                  placeholder="0"
                  data-product-id="<?= $item['product_id'] ?>"
                  data-dept-id="<?= $dept_id ?>"
                  data-round-id="<?= $round['round_id'] ?>"
                  onchange="saveEntry(this)"
                >
                <span class="unit-label"><?= htmlspecialchars($item['unit']) ?></span>
                <span class="save-dot" id="dot-<?= $item['product_id'] ?>-<?= $dept_id ?>"></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php endif; ?>
</div>

<script>
const saveTimers = {};

function saveEntry(input) {
  const productId = input.dataset.productId;
  const deptId    = input.dataset.deptId;
  const roundId   = input.dataset.roundId;
  const key = `${productId}-${deptId}`;
  const dot = document.getElementById('dot-' + key);

  // 防抖：600ms后提交
  clearTimeout(saveTimers[key]);
  input.classList.remove('saved');
  input.classList.add('saving');
  if (dot) { dot.className = 'save-dot saving'; }

  saveTimers[key] = setTimeout(async () => {
    const qty = parseFloat(input.value) || 0;
    try {
      const res = await fetch('/sps/index.php?action=entry_save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          round_id:   parseInt(roundId),
          product_id: parseInt(productId),
          dept_id:    parseInt(deptId),
          qty:        qty
        })
      });
      const data = await res.json();
      input.classList.remove('saving');
      if (data.success) {
        input.classList.add('saved');
        if (dot) { dot.className = 'save-dot saved'; }
        setTimeout(() => {
          input.classList.remove('saved');
          if (dot) { dot.className = 'save-dot'; }
        }, 2000);
      } else {
        input.style.borderColor = 'var(--danger)';
        console.error('保存失败:', data.message);
      }
    } catch(e) {
      input.classList.remove('saving');
      console.error('网络错误:', e);
    }
  }, 600);
}
</script>
</body>
</html>
