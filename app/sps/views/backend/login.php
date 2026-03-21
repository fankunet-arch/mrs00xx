<?php
/**
 * SPS 后台登录页
 */
if (!defined('SPS_ENTRY')) die('Access denied');

$error = $_SESSION['sps_login_error'] ?? '';
unset($_SESSION['sps_login_error']);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>后台登录 - 备货规划系统</title>
<link rel="stylesheet" href="/sps/assets/css/sps.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <h1>备货规划系统</h1>
      <p>Stock Planning System · 管理后台</p>
    </div>

    <?php if ($error): ?>
      <div class="login-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/sps/ap/index.php?action=do_login">
      <div class="form-group">
        <label>用户名</label>
        <input type="text" name="username" autocomplete="username" required autofocus>
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-login">登录</button>
    </form>
  </div>
</div>
</body>
</html>
