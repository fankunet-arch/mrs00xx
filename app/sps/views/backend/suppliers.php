<?php
/**
 * SPS 供货商管理
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$suppliers = $pdo->query("
  SELECT s.*, COUNT(p.product_id) as product_count
  FROM sps_suppliers s
  LEFT JOIN sps_products p ON p.supplier_id = s.supplier_id AND p.status='active'
  GROUP BY s.supplier_id
  ORDER BY s.sort_order, s.supplier_name
")->fetchAll();

$flash = $_SESSION['sps_flash'] ?? null;
unset($_SESSION['sps_flash']);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>供货商管理 - SPS后台</title>
<link rel="stylesheet" href="/sps/assets/css/sps.css">
</head>
<body class="layout-backend">
<?php include SPS_VIEW_PATH . '/shared/sidebar_backend.php'; ?>

<div class="main-content">
  <div class="page-header">
    <h1>供货商管理</h1>
    <button class="btn btn-primary" onclick="openModal()">+ 新增供货商</button>
  </div>

  <div class="page-body">
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body" style="padding:0">
        <table class="data-table">
          <thead><tr>
            <th>排序</th><th>供货商名称</th><th>联系人</th><th>联系电话</th><th>关联商品</th><th>状态</th><th>操作</th>
          </tr></thead>
          <tbody>
          <?php if (empty($suppliers)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--gray-400);padding:40px">暂无供货商</td></tr>
          <?php endif; ?>
          <?php foreach ($suppliers as $s): ?>
            <tr>
              <td style="color:var(--gray-400)"><?= $s['sort_order'] ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($s['supplier_name']) ?></td>
              <td><?= htmlspecialchars($s['contact_name'] ?: '-') ?></td>
              <td><?= htmlspecialchars($s['contact_phone'] ?: '-') ?></td>
              <td><?= $s['product_count'] ?> 个商品</td>
              <td>
                <span class="badge <?= $s['status'] === 'active' ? 'badge-open' : 'badge-completed' ?>">
                  <?= $s['status'] === 'active' ? '启用' : '停用' ?>
                </span>
              </td>
              <td>
                <button onclick='editSupplier(<?= json_encode($s, JSON_UNESCAPED_UNICODE) ?>)'
                        class="btn btn-secondary btn-sm">编辑</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- 供货商编辑模态框 -->
<div class="modal-overlay" id="supplierModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modalTitle">新增供货商</h3>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <form id="supplierForm">
        <input type="hidden" id="supplierId" name="supplier_id" value="">
        <div class="form-grid form-grid-2" style="margin-bottom:12px">
          <div class="form-group">
            <label>供货商名称 <span class="req">*</span></label>
            <input type="text" id="supplierName" name="supplier_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>排序</label>
            <input type="number" id="supplierSort" name="sort_order" class="form-control" min="0" value="0">
          </div>
        </div>
        <div class="form-grid form-grid-2" style="margin-bottom:12px">
          <div class="form-group">
            <label>联系人</label>
            <input type="text" id="supplierContact" name="contact_name" class="form-control">
          </div>
          <div class="form-group">
            <label>联系电话</label>
            <input type="text" id="supplierPhone" name="contact_phone" class="form-control">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label>状态</label>
          <select id="supplierStatus" name="status" class="form-control">
            <option value="active">启用</option>
            <option value="inactive">停用</option>
          </select>
        </div>
        <div class="form-group">
          <label>备注</label>
          <input type="text" id="supplierRemark" name="remark" class="form-control">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">取消</button>
      <button class="btn btn-primary" onclick="saveSupplier()">保存</button>
    </div>
  </div>
</div>

<script>
function openModal() {
  document.getElementById('modalTitle').textContent = '新增供货商';
  document.getElementById('supplierForm').reset();
  document.getElementById('supplierId').value = '';
  document.getElementById('supplierModal').classList.add('open');
}

function editSupplier(s) {
  document.getElementById('modalTitle').textContent = '编辑供货商';
  document.getElementById('supplierId').value     = s.supplier_id;
  document.getElementById('supplierName').value   = s.supplier_name;
  document.getElementById('supplierSort').value   = s.sort_order;
  document.getElementById('supplierContact').value = s.contact_name || '';
  document.getElementById('supplierPhone').value  = s.contact_phone || '';
  document.getElementById('supplierStatus').value = s.status;
  document.getElementById('supplierRemark').value = s.remark || '';
  document.getElementById('supplierModal').classList.add('open');
}

function closeModal() { document.getElementById('supplierModal').classList.remove('open'); }

async function saveSupplier() {
  const form = document.getElementById('supplierForm');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const fd = new FormData(form);
  const payload = Object.fromEntries(fd.entries());

  const btn = event.target;
  btn.disabled = true;

  const res = await fetch('/sps/ap/index.php?action=supplier_save', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  if (data.success) { location.reload(); }
  else { alert('保存失败：' + data.message); btn.disabled = false; }
}

document.getElementById('supplierModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body>
</html>
