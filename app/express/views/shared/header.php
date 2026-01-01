<?php
/**
 * Express 系统 - 页面头部模板
 * 文件路径: app/express/views/shared/header.php
 */

// 防止直接访问
if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Express 快递管理系统'); ?></title>

    <!-- 公共CSS -->
    <link rel="stylesheet" href="/express/css/backend.css">
    <link rel="stylesheet" href="/express/exp/css/modal.css">

    <!-- 页面特定CSS -->
    <?php if (isset($page_css)): ?>
        <?php foreach ((array)$page_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css_file); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <header>
        <div class="title"><?php echo htmlspecialchars($page_title ?? 'Express 快递管理系统'); ?></div>
        <div class="user">
            欢迎, <?php echo htmlspecialchars($_SESSION['user_display_name'] ?? '用户'); ?> |
            <a href="/express/index.php?action=logout" class="logout-link">登出</a>
        </div>
    </header>
