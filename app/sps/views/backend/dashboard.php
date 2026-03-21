<?php
/**
 * SPS 后台仪表盘
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

// 当前开放轮次
$round = $pdo->query("SELECT * FROM sps_rounds WHERE status='open' ORDER BY round_id DESC LIMIT 1")->fetch();

// 统计
$total_products = $pdo->query("SELECT COUNT(*) FROM sps_products WHERE status='active'")->fetchColumn();
$total_suppliers = $pdo->query("SELECT COUNT(*) FROM sps_suppliers WHERE status='active'")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM sps_users WHERE status='active'")->fetchColumn();

$purchase_stats = ['pending' => 0, 'purchased' => 0, 'out_of_stock' => 0];
if ($round) {
    $rows = $pdo->prepare("SELECT purchase_status, COUNT(*) as cnt FROM sps_round_purchase WHERE round_id=? GROUP BY purchase_status");
    $rows->execute([$round['round_id']]);
    foreach ($rows->fetchAll() as $r) {
        $purchase_stats[$r['purchase_status']] = $r['cnt'];
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>仪表盘 - SPS后台</title>
<link rel="stylesheet" href="/sps/assets/css/sps.css">
</head>
<body class="layout-backend">
<?php include SPS_VIEW_PATH . '/shared/sidebar_backend.php'; ?>

<div class="main-content">
  <div class="page-header">
    <h1>仪表盘</h1>
    <?php if ($round): ?>
      <span class="badge badge-open">当前轮次：<?= htmlspecialchars($round['label']) ?></span>
    <?php else: ?>
      <span class="badge badge-completed">暂无开放轮次</span>
    <?php endif; ?>
  </div>

  <div class="page-body">
    <!-- 基础数据统计 -->
    <div class="stat-cards">
      <div class="stat-card">
        <div class="label">商品数</div>
        <div class="value"><?= $total_products ?></div>
        <div class="sub">已启用商品</div>
      </div>
      <div class="stat-card">
        <div class="label">供货商</div>
        <div class="value"><?= $total_suppliers ?></div>
        <div class="sub">已启用</div>
      </div>
      <div class="stat-card">
        <div class="label">用户数</div>
        <div class="value"><?= $total_users ?></div>
        <div class="sub">已启用</div>
      </div>
    </div>

    <?php if ($round): ?>
    <!-- 当前轮次状态 -->
    <div class="card">
      <div class="card-header">
        <h2>当前轮次：<?= htmlspecialchars($round['label']) ?></h2>
        <a href="/sps/ap/index.php?action=round_detail&id=<?= $round['round_id'] ?>" class="btn btn-primary btn-sm">
          进入采购视图 →
        </a>
      </div>
      <div class="card-body">
        <div class="stat-cards">
          <div class="stat-card">
            <div class="label">待采购</div>
            <div class="value" style="color:var(--warning)"><?= $purchase_stats['pending'] ?></div>
            <div class="sub">商品</div>
          </div>
          <div class="stat-card">
            <div class="label">已采购</div>
            <div class="value" style="color:var(--success)"><?= $purchase_stats['purchased'] ?></div>
            <div class="sub">商品</div>
          </div>
          <div class="stat-card">
            <div class="label">缺货</div>
            <div class="value" style="color:var(--danger)"><?= $purchase_stats['out_of_stock'] ?></div>
            <div class="sub">商品</div>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="icon">📋</div>
          <p>暂无开放中的采购轮次</p>
          <a href="/sps/ap/index.php?action=rounds" class="btn btn-primary" style="margin-top:12px">创建新轮次</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- 历史轮次 -->
    <?php
    $history = $pdo->query("SELECT * FROM sps_rounds ORDER BY round_id DESC LIMIT 5")->fetchAll();
    ?>
    <?php if (count($history) > 1 || ($history && $history[0]['status'] === 'completed')): ?>
    <div class="card">
      <div class="card-header">
        <h2>最近轮次</h2>
        <a href="/sps/ap/index.php?action=rounds" class="btn-ghost">查看全部</a>
      </div>
      <div class="card-body" style="padding:0">
        <table class="data-table">
          <thead><tr>
            <th>轮次</th><th>状态</th><th>创建时间</th><th>完成时间</th><th>操作</th>
          </tr></thead>
          <tbody>
          <?php foreach ($history as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['label']) ?></td>
              <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] === 'open' ? '进行中' : '已完成' ?></span></td>
              <td><?= $r['created_at'] ?></td>
              <td><?= $r['completed_at'] ?: '-' ?></td>
              <td><a href="/sps/ap/index.php?action=round_detail&id=<?= $r['round_id'] ?>" class="btn-ghost">查看</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
