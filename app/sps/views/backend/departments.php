<?php
/**
 * SPS 部门管理
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$departments = $pdo->query("SELECT * FROM sps_departments ORDER BY sort_order")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>部门管理 - SPS后台</title>
<link rel="stylesheet" href="/sps/assets/css/sps.css">
</head>
<body class="layout-backend">
<?php include SPS_VIEW_PATH . '/shared/sidebar_backend.php'; ?>

<div class="main-content">
  <div class="page-header">
    <h1>部门管理</h1>
    <button class="btn btn-primary" onclick="openModal()">+ 新增部门</button>
  </div>

  <div class="page-body">
    <div class="card">
      <div class="card-body" style="padding:0">
        <table class="data-table">
          <thead><tr><th>排序</th><th>部门名称</th><th>状态</th><th>操作</th></tr></thead>
          <tbody>
          <?php foreach ($departments as $d): ?>
            <tr>
              <td style="color:var(--gray-400)"><?= $d['sort_order'] ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($d['dept_name']) ?></td>
              <td><span class="badge <?= $d['status']==='active' ? 'badge-open':'badge-completed' ?>">
                <?= $d['status']==='active' ? '启用':'停用' ?></span></td>
              <td><button onclick='editDept(<?= json_encode($d, JSON_UNESCAPED_UNICODE) ?>)' class="btn btn-secondary btn-sm">编辑</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="deptModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="deptModalTitle">新增部门</h3>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <form id="deptForm">
        <input type="hidden" id="deptId" name="dept_id" value="">
        <div class="form-grid form-grid-2" style="margin-bottom:12px">
          <div class="form-group">
            <label>部门名称 <span class="req">*</span></label>
            <input type="text" id="deptName" name="dept_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>排序</label>
            <input type="number" id="deptSort" name="sort_order" class="form-control" min="0" value="0">
          </div>
        </div>
        <div class="form-group">
          <label>状态</label>
          <select id="deptStatus" name="status" class="form-control">
            <option value="active">启用</option>
            <option value="inactive">停用</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">取消</button>
      <button class="btn btn-primary" onclick="saveDept()">保存</button>
    </div>
  </div>
</div>

<script>
function openModal() {
  document.getElementById('deptModalTitle').textContent = '新增部门';
  document.getElementById('deptForm').reset();
  document.getElementById('deptId').value = '';
  document.getElementById('deptModal').classList.add('open');
}
function editDept(d) {
  document.getElementById('deptModalTitle').textContent = '编辑部门';
  document.getElementById('deptId').value   = d.dept_id;
  document.getElementById('deptName').value = d.dept_name;
  document.getElementById('deptSort').value = d.sort_order;
  document.getElementById('deptStatus').value = d.status;
  document.getElementById('deptModal').classList.add('open');
}
function closeModal() { document.getElementById('deptModal').classList.remove('open'); }
async function saveDept() {
  const form = document.getElementById('deptForm');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const payload = Object.fromEntries(new FormData(form).entries());
  const btn = event.target; btn.disabled = true;
  const res = await fetch('/sps/ap/index.php?action=dept_save', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
  });
  const data = await res.json();
  if (data.success) location.reload();
  else { alert('保存失败：' + data.message); btn.disabled = false; }
}
document.getElementById('deptModal').addEventListener('click', e => { if(e.target===document.getElementById('deptModal')) closeModal(); });
</script>
</body>
</html>
