<?php
/**
 * Express 系统 - 标准页面布局模板
 * 文件路径: app/express/views/shared/layout.php
 *
 * 使用方法：
 * 1. 在控制器中设置变量：$page_title, $page_css, $page_js, $content_file
 * 2. include此布局文件
 */

// 防止直接访问
if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

// 包含头部
include EXPRESS_VIEW_PATH . '/shared/header.php';
?>

<div class="layout">
    <?php include EXPRESS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <main class="content">
        <?php
        // 包含页面内容
        if (isset($content_file) && file_exists($content_file)) {
            include $content_file;
        } elseif (isset($page_content)) {
            echo $page_content;
        }
        ?>
    </main>
</div>

<?php
// 包含尾部
include EXPRESS_VIEW_PATH . '/shared/footer.php';
?>
