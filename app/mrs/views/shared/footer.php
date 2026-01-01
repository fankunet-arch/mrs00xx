<?php
/**
 * MRS 系统 - 页面尾部模板
 * 文件路径: app/mrs/views/shared/footer.php
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}
?>
    <!-- 页面特定JavaScript -->
    <?php if (isset($page_js)): ?>
        <?php foreach ((array)$page_js as $js_file): ?>
            <script src="<?php echo htmlspecialchars($js_file); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- 内联JavaScript（如果需要） -->
    <?php if (isset($inline_js)): ?>
        <script>
            <?php echo $inline_js; ?>
        </script>
    <?php endif; ?>
</body>
</html>
