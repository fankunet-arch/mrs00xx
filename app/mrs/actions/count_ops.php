<?php
/**
 * MRS Count Operations Page
 * 文件路径: app/mrs/actions/count_ops.php
 * 说明: 清点操作页面 - 核心清点功能界面
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取session_id
$session_id = $_GET['session_id'] ?? null;

if (!$session_id) {
    header('Location: /mrs/index.php?action=count_home');
    exit;
}

// 加载清点业务逻辑库
require_once MRS_LIB_PATH . '/count_lib.php';

// 获取清点任务信息
$stmt = $pdo->prepare("SELECT * FROM mrs_count_session WHERE session_id = :session_id");
$stmt->execute([':session_id' => $session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die('清点任务不存在');
}

if ($session['status'] !== 'counting') {
    die('该清点任务已结束，无法继续清点');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>清点操作 - <?= htmlspecialchars($session['session_name']) ?></title>
    <link rel="stylesheet" href="./css/count.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div class="header-top">
                <a href="/mrs/index.php?action=count_home" class="btn-back">← 返回</a>
                <h1><?= htmlspecialchars($session['session_name']) ?></h1>
            </div>
            <div class="session-stats">
                <div class="stat-item">
                    <span class="stat-label">已清点:</span>
                    <span class="stat-value" id="total-counted"><?= $session['total_counted'] ?></span>
                    <span class="stat-unit">箱</span>
                </div>
                <div class="stat-item">
                    <span id="current-time"></span>
                </div>
            </div>
        </header>

        <section class="search-section">
            <h2>输入箱号</h2>
            <div class="search-group">
                <input type="text" id="box-number-input" class="form-control input-large"
                       placeholder="请输入箱号进行搜索" autocomplete="off" autofocus>
                <button id="btn-search" class="btn btn-primary">搜索</button>
            </div>
        </section>

        <section class="result-section" id="result-section" style="display: none;">
            <div id="search-result-container">
                <!-- 搜索结果将动态加载到这里 -->
            </div>
        </section>

        <section class="history-section">
            <div class="section-header">
                <h2>最近清点记录</h2>
                <button id="btn-refresh-history" class="btn btn-secondary btn-small">刷新</button>
            </div>
            <div id="history-container">
                <p class="empty-text">暂无记录</p>
            </div>
        </section>

        <section class="actions-section">
            <button id="btn-finish-session" class="btn btn-success btn-large">完成清点并生成报告</button>
        </section>
    </div>

    <!-- 清点表单模态框 -->
    <div id="count-modal" class="modal-overlay" style="display: none;">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3 class="modal-title">清点箱号: <span id="modal-box-number"></span></h3>
                <button type="button" class="modal-close" id="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-ledger-id">
                <input type="hidden" id="modal-system-content">

                <div class="system-info" id="system-info-container">
                    <!-- 系统信息将动态显示 -->
                </div>

                <div class="form-group">
                    <label>清点方式</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="check-mode" value="box_only" checked>
                            <span>只确认箱子存在</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="check-mode" value="with_qty">
                            <span>核对箱内数量</span>
                        </label>
                    </div>
                </div>

                <!-- 数量核对区域（默认隐藏） -->
                <div id="qty-check-section" class="qty-check-section" style="display: none;">
                    <h4>箱内物品清点</h4>
                    <div id="items-container">
                        <!-- 物品列表将动态生成 -->
                    </div>
                    <button type="button" id="btn-add-item" class="btn btn-secondary btn-small">+ 添加物品</button>
                </div>

                <div class="form-group">
                    <label for="count-remark">备注</label>
                    <textarea id="count-remark" class="form-control" rows="2"
                              placeholder="可选填写备注信息"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modal-cancel-btn">取消</button>
                <button type="button" class="btn btn-primary" id="modal-save-btn">保存</button>
            </div>
        </div>
    </div>

    <!-- 快速录入新箱模态框 -->
    <div id="quick-add-modal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">快速录入新箱</h3>
                <button type="button" class="modal-close" id="quick-add-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="quick-add-box-number">

                <div class="alert alert-info">
                    系统中未找到箱号 <strong id="quick-add-box-display"></strong>，您可以现场录入。
                </div>

                <div class="form-group">
                    <label for="quick-add-sku">SKU名称 *</label>
                    <input type="text" id="quick-add-sku" class="form-control"
                           placeholder="输入SKU名称进行搜索" autocomplete="off">
                    <div id="sku-suggestions" class="suggestions-list" style="display: none;"></div>
                    <input type="hidden" id="quick-add-sku-id">
                </div>

                <div class="form-group">
                    <label for="quick-add-qty">数量</label>
                    <input type="number" id="quick-add-qty" class="form-control"
                           placeholder="请输入数量（可选）" min="0" step="0.01">
                </div>

                <div class="form-group">
                    <label for="quick-add-content">内容备注</label>
                    <textarea id="quick-add-content" class="form-control" rows="2"
                              placeholder="可选填写内容描述"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="quick-add-cancel-btn">取消</button>
                <button type="button" class="btn btn-primary" id="quick-add-save-btn">保存并清点</button>
            </div>
        </div>
    </div>

    <!-- 完成清点报告模态框 -->
    <div id="report-modal" class="modal-overlay" style="display: none;">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3 class="modal-title">清点报告</h3>
                <button type="button" class="modal-close" id="report-close-btn">&times;</button>
            </div>
            <div class="modal-body" id="report-content">
                <!-- 报告内容将动态生成 -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="report-ok-btn">确定</button>
            </div>
        </div>
    </div>

    <script>
        const SESSION_ID = <?= $session_id ?>;
    </script>
    <script src="./js/count_ops.js?v=<?php echo time(); ?>"></script>
</body>
</html>
