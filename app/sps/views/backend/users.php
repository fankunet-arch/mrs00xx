<?php
/**
 * SPS 用户管理
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$users = $pdo->query("
  SELECT u.*,
         GROUP_CONCAT(d.dept_name ORDER BY d.sort_order SEPARATOR ', ') as dept_names
  FROM sps_users u
  LEFT JOIN sps_user_departments ud ON ud.user_id = u.user_id
  LEFT JOIN sps_departments d ON d.dept_id = ud.dept_id
  GROUP BY u.user_id
  ORDER BY u.role DESC, u.display_name
")->fetchAll();

$departments = $pdo->query("SELECT * FROM sps_departments WHERE status='active' ORDER BY sort_order")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>用户管理 - SPS后台</title>
<link rel="stylesheet" href="/sps/assets/css/sps.css">
</head>
<body class="layout-backend">
<?php include SPS_VIEW_PATH . '/shared/sidebar_backend.php'; ?>

<div class="main-content">
  <div class="page-header">
    <h1>用户管理</h1>
    <button class="btn btn-primary" onclick="openModal()">+ 新增用户</button>
  </div>

  <div class="page-body">
    <div class="card">
      <div class="card-body" style="padding:0">
        <table class="data-table">
          <thead><tr>
            <th>显示名称</th><th>用户名</th><th>角色</th><th>归属部门</th><th>状态</th><th>创建时间</th><th>操作</th>
          </tr></thead>
          <tbody>
          <?php if (empty($users)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--gray-400);padding:40px">暂无用户</td></tr>
          <?php endif; ?>
          <?php foreach ($users as $u): ?>
            <tr>
              <td style="font-weight:600"><?= htmlspecialchars($u['display_name']) ?></td>
              <td><code style="font-size:13px"><?= htmlspecialchars($u['username']) ?></code></td>
              <td>
                <span class="badge <?= $u['role'] === 'admin' ? 'badge-open' : 'badge-pending' ?>">
                  <?= $u['role'] === 'admin' ? '管理员' : 'Staff' ?>
                </span>
              </td>
              <td>
                <?php if ($u['dept_names']): ?>
                  <?php foreach (explode(', ', $u['dept_names']) as $d): ?>
                    <span style="display:inline-block;padding:2px 8px;background:var(--gray-100);border-radius:4px;font-size:12px;margin-right:4px"><?= htmlspecialchars($d) ?></span>
                  <?php endforeach; ?>
                <?php elseif ($u['role'] === 'admin'): ?>
                  <span style="color:var(--gray-400);font-size:12px">（全权限）</span>
                <?php else: ?>
                  <span style="color:var(--danger);font-size:12px">未分配部门</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $u['status'] === 'active' ? 'badge-open' : 'badge-completed' ?>">
                  <?= $u['status'] === 'active' ? '启用' : '停用' ?>
                </span>
              </td>
              <td style="font-size:12px;color:var(--gray-400)"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
              <td>
                <button onclick='openEditModal(<?= json_encode($u, JSON_UNESCAPED_UNICODE) ?>)'
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

<!-- 用户编辑模态框 -->
<div class="modal-overlay" id="userModal">
  <div class="modal" style="width:520px">
    <div class="modal-header">
      <h3 id="userModalTitle">新增用户</h3>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <div class="modal-body">
      <form id="userForm">
        <input type="hidden" id="userId" name="user_id" value="">

        <div class="form-grid form-grid-2" style="margin-bottom:12px">
          <div class="form-group">
            <label>显示名称 <span class="req">*</span></label>
            <input type="text" id="displayName" name="display_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>用户名 <span class="req">*</span></label>
            <input type="text" id="username" name="username" class="form-control" required autocomplete="off">
          </div>
        </div>

        <div class="form-grid form-grid-2" style="margin-bottom:12px">
          <div class="form-group">
            <label>密码 <span class="req" id="pwdReq">*</span></label>
            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password">
            <div class="help-text" id="pwdHint">编辑时留空则不修改密码</div>
          </div>
          <div class="form-group">
            <label>角色 <span class="req">*</span></label>
            <select id="userRole" name="role" class="form-control" onchange="toggleDeptSection()">
              <option value="staff">Staff（前台）</option>
              <option value="admin">管理员（后台）</option>
            </select>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:12px">
          <label>状态</label>
          <select id="userStatus" name="status" class="form-control">
            <option value="active">启用</option>
            <option value="inactive">停用</option>
          </select>
        </div>

        <div class="form-group" id="deptSection">
          <label>归属部门</label>
          <div class="dept-checkboxes" id="deptCheckboxes">
            <?php foreach ($departments as $d): ?>
              <label class="dept-checkbox">
                <input type="checkbox" name="dept_ids[]" value="<?= $d['dept_id'] ?>">
                <?= htmlspecialchars($d['dept_name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="help-text">Staff登录后只能查看并填报所属部门的商品</div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">取消</button>
      <button class="btn btn-primary" onclick="saveUser()">保存</button>
    </div>
  </div>
</div>

<script>
const allDepts = <?= json_encode(array_column($departments, 'dept_id'), JSON_UNESCAPED_UNICODE) ?>;

function openModal() {
  document.getElementById('userModalTitle').textContent = '新增用户';
  document.getElementById('userForm').reset();
  document.getElementById('userId').value = '';
  document.getElementById('pwdReq').textContent = '*';
  document.getElementById('pwdHint').style.display = 'none';
  toggleDeptSection();
  document.getElementById('userModal').classList.add('open');
}

function openEditModal(u) {
  document.getElementById('userModalTitle').textContent = '编辑用户';
  document.getElementById('userId').value      = u.user_id;
  document.getElementById('displayName').value = u.display_name;
  document.getElementById('username').value    = u.username;
  document.getElementById('password').value    = '';
  document.getElementById('userRole').value    = u.role;
  document.getElementById('userStatus').value  = u.status;
  document.getElementById('pwdReq').textContent = '';
  document.getElementById('pwdHint').style.display = '';

  // 部门
  const userDeptNames = (u.dept_names || '').split(', ').filter(Boolean);
  document.querySelectorAll('#deptCheckboxes input').forEach(cb => {
    cb.checked = false;
  });
  // 根据部门名重新勾选（简化方式）
  const deptData = <?= json_encode($departments, JSON_UNESCAPED_UNICODE) ?>;
  deptData.forEach(d => {
    const cb = document.querySelector(`#deptCheckboxes input[value="${d.dept_id}"]`);
    if (cb && userDeptNames.includes(d.dept_name)) cb.checked = true;
  });

  toggleDeptSection();
  document.getElementById('userModal').classList.add('open');
}

function closeModal() { document.getElementById('userModal').classList.remove('open'); }

function toggleDeptSection() {
  const role = document.getElementById('userRole').value;
  document.getElementById('deptSection').style.display = role === 'staff' ? '' : 'none';
}

async function saveUser() {
  const form = document.getElementById('userForm');
  const fd = new FormData(form);
  const payload = {};
  fd.forEach((v, k) => {
    if (k === 'dept_ids[]') {
      if (!payload.dept_ids) payload.dept_ids = [];
      payload.dept_ids.push(parseInt(v));
    } else {
      payload[k] = v;
    }
  });
  if (!payload.dept_ids) payload.dept_ids = [];

  // 新增时密码必填
  if (!payload.user_id && !payload.password) {
    alert('新增用户时密码不能为空'); return;
  }

  const btn = event.target;
  btn.disabled = true;

  const res = await fetch('/sps/ap/index.php?action=user_save', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  if (data.success) { location.reload(); }
  else { alert('保存失败：' + data.message); btn.disabled = false; }
}

document.getElementById('userModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

toggleDeptSection();
</script>
</body>
</html>
