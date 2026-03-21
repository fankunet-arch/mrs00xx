<?php
/**
 * SPS 商品编辑/新增
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$product_id = (int)($_GET['id'] ?? 0);
$product = null;
$product_dept_ids = [];

if ($product_id) {
    $stmt = $pdo->prepare("SELECT * FROM sps_products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    if (!$product) { header('Location: /sps/ap/index.php?action=products'); exit; }

    $pdstmt = $pdo->prepare("SELECT dept_id FROM sps_product_departments WHERE product_id = ?");
    $pdstmt->execute([$product_id]);
    $product_dept_ids = array_column($pdstmt->fetchAll(), 'dept_id');
}

$suppliers = $pdo->query("SELECT * FROM sps_suppliers WHERE status='active' ORDER BY sort_order, supplier_name")->fetchAll();
$departments = $pdo->query("SELECT * FROM sps_departments WHERE status='active' ORDER BY sort_order")->fetchAll();

$is_edit = !empty($product);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $is_edit ? '编辑商品' : '新增商品' ?> - SPS后台</title>
<link rel="stylesheet" href="/sps/assets/css/sps.css">
</head>
<body class="layout-backend">
<?php include SPS_VIEW_PATH . '/shared/sidebar_backend.php'; ?>

<div class="main-content">
  <div class="page-header">
    <h1><?= $is_edit ? '编辑商品' : '新增商品' ?></h1>
    <a href="/sps/ap/index.php?action=products" class="btn btn-secondary">← 返回列表</a>
  </div>

  <div class="page-body">
    <div class="card" style="max-width:680px">
      <div class="card-body">
        <form id="productForm">
          <?php if ($is_edit): ?>
            <input type="hidden" name="product_id" value="<?= $product_id ?>">
          <?php endif; ?>

          <div class="form-grid form-grid-2" style="margin-bottom:16px">
            <div class="form-group">
              <label>中文名称 <span class="req">*</span></label>
              <input type="text" name="name_cn" class="form-control" required
                     value="<?= htmlspecialchars($product['name_cn'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>西班牙语名称</label>
              <input type="text" name="name_es" class="form-control"
                     value="<?= htmlspecialchars($product['name_es'] ?? '') ?>"
                     placeholder="Nombre en español">
            </div>
          </div>

          <div class="form-grid form-grid-2" style="margin-bottom:16px">
            <div class="form-group">
              <label>供货商</label>
              <select name="supplier_id" class="form-control">
                <option value="">— 未指定 —</option>
                <?php foreach ($suppliers as $s): ?>
                  <option value="<?= $s['supplier_id'] ?>"
                    <?= ($product['supplier_id'] ?? '') == $s['supplier_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['supplier_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>采购单位 <span class="req">*</span></label>
              <input type="text" name="unit" class="form-control" required
                     value="<?= htmlspecialchars($product['unit'] ?? '件') ?>"
                     placeholder="如：公斤、瓶、箱、袋">
              <div class="help-text">填报和汇总时显示的统一单位</div>
            </div>
          </div>

          <div class="form-grid form-grid-2" style="margin-bottom:16px">
            <div class="form-group">
              <label>排序</label>
              <input type="number" name="sort_order" class="form-control" min="0"
                     value="<?= $product['sort_order'] ?? 0 ?>">
              <div class="help-text">数字越小越靠前</div>
            </div>
            <div class="form-group">
              <label>状态</label>
              <select name="status" class="form-control">
                <option value="active"   <?= ($product['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>启用</option>
                <option value="inactive" <?= ($product['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>停用</option>
              </select>
            </div>
          </div>

          <div class="form-group" style="margin-bottom:20px">
            <label>归属部门 <span class="req">*</span></label>
            <div class="dept-checkboxes">
              <?php foreach ($departments as $d): ?>
                <label class="dept-checkbox">
                  <input type="checkbox" name="dept_ids[]" value="<?= $d['dept_id'] ?>"
                         <?= in_array($d['dept_id'], $product_dept_ids) ? 'checked' : '' ?>>
                  <?= htmlspecialchars($d['dept_name']) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="help-text">商品将只对选定部门的 staff 可见</div>
          </div>

          <div style="display:flex;gap:10px;justify-content:flex-end">
            <a href="/sps/ap/index.php?action=products" class="btn btn-secondary">取消</a>
            <button type="submit" class="btn btn-primary">
              <?= $is_edit ? '保存更改' : '创建商品' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('productForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const payload = {};
  formData.forEach((v, k) => {
    if (k === 'dept_ids[]') {
      if (!payload.dept_ids) payload.dept_ids = [];
      payload.dept_ids.push(parseInt(v));
    } else {
      payload[k] = v;
    }
  });
  if (!payload.dept_ids) payload.dept_ids = [];

  const btn = this.querySelector('[type=submit]');
  btn.disabled = true;

  try {
    const res = await fetch('/sps/ap/index.php?action=product_save', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      location.href = '/sps/ap/index.php?action=products';
    } else {
      alert('保存失败：' + data.message);
      btn.disabled = false;
    }
  } catch(err) {
    alert('网络错误');
    btn.disabled = false;
  }
});
</script>
</body>
</html>
