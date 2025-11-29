<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>MRS 管理系统 - <?php echo htmlspecialchars($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/mrs/css/backend.css">
</head>
<body>
    <header>
        <div class="title"><?php echo htmlspecialchars($page_title); ?></div>
        <div class="user">
            欢迎, <?php echo htmlspecialchars($_SESSION['user_display_name'] ?? '用户'); ?> | <a href="/mrs/be/index.php?action=logout">登出</a>
        </div>
    </header>
    <div class="layout">
        <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>
        <main class="content">
            <div class="card">
                <h3>欢迎使用 MRS</h3>
                <p class="muted" style="margin-top: 10px;">请从左侧菜单选择要操作的功能模块。</p>
                <p class="muted">当前系统已完成 `cccp` 架构迁移，所有功能均已迁移至新的 MPA 模式下。</p>
            </div>
        </main>
    </div>
</body>
</html>
