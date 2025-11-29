<?php
/**
 * Shared Sidebar Component
 * 文件路径: app/express/views/shared/sidebar.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$current_action = $_GET['action'] ?? 'batch_list';
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>Express 后台</h2>
        <p>欢迎, <?= htmlspecialchars($_SESSION['user_display_name'] ?? $_SESSION['user_login'] ?? 'Admin') ?></p>
    </div>

    <nav class="sidebar-nav">
        <a href="/express/exp/index.php?action=batch_list"
           class="nav-link <?= $current_action === 'batch_list' ? 'active' : '' ?>">
            批次列表
        </a>
        <a href="/express/exp/index.php?action=batch_create"
           class="nav-link <?= $current_action === 'batch_create' ? 'active' : '' ?>">
            创建批次
        </a>
        <a href="/express/exp/index.php?action=content_search"
           class="nav-link <?= $current_action === 'content_search' ? 'active' : '' ?>">
            物品内容搜索
        </a>
        <a href="/express/index.php?action=quick_ops" class="nav-link" target="_blank">
            前台操作页面
        </a>
        <a href="/express/exp/index.php?action=logout" class="nav-link">
            退出登录
        </a>
    </nav>
</div>
