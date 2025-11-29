<?php
/**
 * Express Package Management System - Quick Operations Page
 * 文件路径: app/express/actions/quick_ops.php
 * 说明: 前台统一操作页面
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

// 获取批次列表供选择
$batches = express_get_batches($pdo, 'active', 50);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>快递单统一操作页面 - Express Package Management</title>
    <link rel="stylesheet" href="./css/quick_ops.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>快递单统一操作页面</h1>
            <div class="header-info">
                <span id="current-time"></span>
            </div>
        </header>

        <!-- 批次选择区域 -->
        <section class="batch-section">
            <h2>选择批次</h2>
            <div class="batch-selector">
                <select id="batch-select" class="form-control">
                    <option value="">-- 请选择批次 --</option>
                    <?php foreach ($batches as $batch): ?>
                        <option value="<?= $batch['batch_id'] ?>"
                                data-total="<?= $batch['total_count'] ?>"
                                data-verified="<?= $batch['verified_count'] ?>"
                                data-counted="<?= $batch['counted_count'] ?>"
                                data-adjusted="<?= $batch['adjusted_count'] ?>">
                            <?= htmlspecialchars($batch['batch_name']) ?>
                            (<?= $batch['total_count'] ?>个包裹)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="refresh-batches" class="btn btn-secondary">刷新批次</button>
            </div>

            <!-- 批次统计信息 -->
            <div id="batch-stats" class="batch-stats" style="display: none;">
                <div class="stat-item">
                    <span class="stat-label">总数:</span>
                    <span id="stat-total" class="stat-value">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">已核实:</span>
                    <span id="stat-verified" class="stat-value">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">已清点:</span>
                    <span id="stat-counted" class="stat-value">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">已调整:</span>
                    <span id="stat-adjusted" class="stat-value">0</span>
                </div>
                <div class="stat-item progress-bar">
                    <div id="progress-fill" class="progress-fill" style="width: 0%"></div>
                    <span id="progress-text" class="progress-text">0%</span>
                </div>
            </div>
        </section>

        <!-- 操作类型选择 -->
        <section class="operation-section" id="operation-section" style="display: none;">
            <h2>选择操作类型</h2>
            <div class="operation-buttons">
                <button id="btn-verify" class="btn btn-operation" data-operation="verify">核实</button>
                <button id="btn-count" class="btn btn-operation" data-operation="count">清点</button>
                <button id="btn-adjust" class="btn btn-operation" data-operation="adjust">调整</button>
            </div>
        </section>

        <!-- 快递单号输入区域 -->
        <section class="input-section" id="input-section" style="display: none;">
            <h2 id="operation-title">操作：<span id="operation-name">--</span></h2>

            <div class="input-group">
                <label for="tracking-input">快递单号:</label>
                <input type="text" id="tracking-input" class="form-control"
                       placeholder="输入快递单号（模糊搜索）" autocomplete="off">
                <button id="btn-clear-input" class="btn btn-clear">清空</button>
            </div>

            <!-- 搜索结果候选列表 -->
            <div id="search-results" class="search-results" style="display: none;"></div>

            <!-- 内容备注（清点时显示） -->
            <div id="content-note-group" class="input-group" style="display: none;">
                <label for="content-note">内容备注:</label>
                <textarea id="content-note" class="form-control" rows="3"
                          placeholder="例如：番茄酱×2"></textarea>
            </div>

            <!-- 调整备注（调整时显示） -->
            <div id="adjustment-note-group" class="input-group" style="display: none;">
                <label for="adjustment-note">调整备注:</label>
                <textarea id="adjustment-note" class="form-control" rows="3"
                          placeholder="例如：包裹破损，已重新包装"></textarea>
            </div>

            <div class="action-buttons">
                <button id="btn-submit" class="btn btn-primary">确认</button>
                <button id="btn-reset" class="btn btn-secondary">重置</button>
                <button id="btn-change-operation" class="btn btn-secondary">切换操作</button>
            </div>
        </section>

        <!-- 操作反馈区域 -->
        <section class="feedback-section">
            <div id="message-box" class="message-box" style="display: none;"></div>
        </section>

        <!-- 最近操作记录 -->
        <section class="history-section">
            <h2>最近操作记录</h2>
            <div id="operation-history" class="operation-history">
                <p class="empty-text">暂无操作记录</p>
            </div>
        </section>
    </div>

    <script src="./js/quick_ops.js"></script>
</body>
</html>
