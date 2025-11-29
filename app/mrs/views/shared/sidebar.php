<?php
$current_action = $action ?? ($_GET['action'] ?? 'dashboard');
$user_name = $_SESSION['user_display_name'] ?? $_SESSION['user_login'] ?? '用户';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>MRS 后台</h2>
        <p>欢迎, <?php echo htmlspecialchars($user_name); ?></p>
    </div>

    <nav class="sidebar-nav">
        <a href="/mrs/be/index.php?action=dashboard" class="nav-link <?php echo ($current_action === 'dashboard') ? 'active' : ''; ?>">仪表盘</a>
        <a href="/mrs/be/index.php?action=batch_list" class="nav-link <?php echo (strpos($current_action, 'batch_') === 0) ? 'active' : ''; ?>">入库管理</a>
        <a href="/mrs/be/index.php?action=outbound_list" class="nav-link <?php echo (strpos($current_action, 'outbound_') === 0) ? 'active' : ''; ?>">出库管理</a>
        <a href="/mrs/be/index.php?action=inventory_list" class="nav-link <?php echo (strpos($current_action, 'inventory_') === 0) ? 'active' : ''; ?>">库存管理</a>
        <a href="/mrs/be/index.php?action=sku_list" class="nav-link <?php echo (strpos($current_action, 'sku_') === 0) ? 'active' : ''; ?>">物料管理</a>
        <a href="/mrs/be/index.php?action=category_list" class="nav-link <?php echo (strpos($current_action, 'category_') === 0) ? 'active' : ''; ?>">品类管理</a>
        <a href="/mrs/be/index.php?action=reports" class="nav-link <?php echo ($current_action === 'reports') ? 'active' : ''; ?>">数据报表</a>
        <a href="/mrs/be/index.php?action=logout" class="nav-link">退出登录</a>
    </nav>
</aside>
