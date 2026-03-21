<?php
/**
 * SPS 后台侧边栏
 */
if (!defined('SPS_ENTRY')) die('Access denied');
$cur = $_GET['action'] ?? 'dashboard';
?>
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="sys-name">备货规划系统</div>
    <div class="sys-sub">Stock Planning System</div>
    <div class="user-info">管理员：<strong><?= htmlspecialchars($_SESSION['sps_display'] ?? '') ?></strong></div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">采购管理</div>
    <a class="nav-link <?= $cur === 'dashboard'   ? 'active' : '' ?>" href="/sps/ap/index.php?action=dashboard">
      <span class="icon">📊</span>仪表盘
    </a>
    <a class="nav-link <?= $cur === 'rounds'      ? 'active' : '' ?>" href="/sps/ap/index.php?action=rounds">
      <span class="icon">📋</span>采购轮次
    </a>

    <div class="nav-section-title">基础数据</div>
    <a class="nav-link <?= $cur === 'products'    ? 'active' : '' ?>" href="/sps/ap/index.php?action=products">
      <span class="icon">🛒</span>商品管理
    </a>
    <a class="nav-link <?= $cur === 'suppliers'   ? 'active' : '' ?>" href="/sps/ap/index.php?action=suppliers">
      <span class="icon">🏪</span>供货商管理
    </a>
    <a class="nav-link <?= $cur === 'departments' ? 'active' : '' ?>" href="/sps/ap/index.php?action=departments">
      <span class="icon">🏢</span>部门管理
    </a>

    <div class="nav-section-title">系统</div>
    <a class="nav-link <?= $cur === 'users'       ? 'active' : '' ?>" href="/sps/ap/index.php?action=users">
      <span class="icon">👤</span>用户管理
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="/sps/ap/index.php?action=do_logout">退出登录</a>
  </div>
</aside>
