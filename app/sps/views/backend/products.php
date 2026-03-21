<?php
/**
 * SPS 商品管理列表
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$products = $pdo->query("
  SELECT p.*,
         s.supplier_name,
         GROUP_CONCAT(d.dept_name ORDER BY d.sort_order SEPARATOR ', ') as dept_names
  FROM sps_products p
  LEFT JOIN sps_suppliers s ON s.supplier_id = p.supplier_id
  LEFT JOIN sps_product_departments pd ON pd.product_id = p.product_id
  LEFT JOIN sps_departments d ON d.dept_id = pd.dept_id
  GROUP BY p.product_id
  ORDER BY p.sort_order, p.product_id
")->fetchAll();

$flash = $_SESSION['sps_flash'] ?? null;
unset($_SESSION['sps_flash']);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>商品管理 - SPS后台</title>
<link rel="stylesheet" href="/sps/assets/css/sps.css">
</head>
<body class="layout-backend">
<?php include SPS_VIEW_PATH . '/shared/sidebar_backend.php'; ?>

<div class="main-content">
  <div class="page-header">
    <h1>商品管理</h1>
    <a href="/sps/ap/index.php?action=product_edit" class="btn btn-primary">+ 新增商品</a>
  </div>

  <div class="page-body">
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
      <div class="card"><div class="card-body">
        <div class="empty-state"><div class="icon">🛒</div><p>还没有商品</p></div>
      </div></div>
    <?php else: ?>
    <div class="card">
      <div class="card-body" style="padding:0">
        <table class="data-table">
          <thead><tr>
            <th>排序</th>
            <th>中文名称</th>
            <th>西班牙语名称</th>
            <th>供货商</th>
            <th>单位</th>
            <th>归属部门</th>
            <th>状态</th>
            <th>操作</th>
          </tr></thead>
          <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td style="color:var(--gray-400)"><?= $p['sort_order'] ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($p['name_cn']) ?></td>
              <td style="color:var(--gray-600)"><?= htmlspecialchars($p['name_es'] ?: '-') ?></td>
              <td><?= htmlspecialchars($p['supplier_name'] ?: '—') ?></td>
              <td><?= htmlspecialchars($p['unit']) ?></td>
              <td>
                <?php if ($p['dept_names']): ?>
                  <?php foreach (explode(', ', $p['dept_names']) as $d): ?>
                    <span style="display:inline-block;padding:2px 8px;background:var(--gray-100);border-radius:4px;font-size:12px;margin-right:4px"><?= htmlspecialchars($d) ?></span>
                  <?php endforeach; ?>
                <?php else: ?>
                  <span style="color:var(--gray-400)">未分配</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $p['status'] === 'active' ? 'badge-open' : 'badge-completed' ?>">
                  <?= $p['status'] === 'active' ? '启用' : '停用' ?>
                </span>
              </td>
              <td>
                <a href="/sps/ap/index.php?action=product_edit&id=<?= $p['product_id'] ?>" class="btn btn-secondary btn-sm">编辑</a>
                <button onclick="deleteProduct(<?= $p['product_id'] ?>, '<?= htmlspecialchars(addslashes($p['name_cn'])) ?>')"
                        class="btn btn-danger btn-sm">删除</button>
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

<script>
async function deleteProduct(id, name) {
  if (!confirm('确认删除商品「' + name + '」？\n注意：已有填报记录的商品不可删除。')) return;
  const res = await fetch('/sps/ap/index.php?action=product_delete', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ product_id: id })
  });
  const data = await res.json();
  if (data.success) { location.reload(); }
  else { alert('删除失败：' + data.message); }
}
</script>
</body>
</html>
