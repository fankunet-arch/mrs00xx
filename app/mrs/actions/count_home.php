<?php
/**
 * MRS Count Home Page
 * 文件路径: app/mrs/actions/count_home.php
 * 说明: 清点首页 - 显示清点任务列表
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载清点业务逻辑库
require_once MRS_LIB_PATH . '/count_lib.php';

// 获取最近的清点任务
$sessions = mrs_count_get_sessions($pdo, null, 30);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MRS 仓库清点</title>
    <link rel="stylesheet" href="./css/count.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>MRS 仓库清点</h1>
            <div class="header-info">
                <span id="current-time"></span>
            </div>
        </header>

        <section class="action-section">
            <button id="btn-new-session" class="btn btn-primary btn-large">+ 开始新清点</button>
        </section>

        <section class="sessions-section">
            <h2>清点任务列表</h2>

            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <p>暂无清点任务</p>
                    <p class="empty-hint">点击上方按钮开始新的清点任务</p>
                </div>
            <?php else: ?>
                <div class="sessions-list">
                    <?php foreach ($sessions as $session):
                        $status_class = '';
                        $status_text = '';

                        switch ($session['status']) {
                            case 'counting':
                                $status_class = 'status-counting';
                                $status_text = '进行中';
                                break;
                            case 'completed':
                                $status_class = 'status-completed';
                                $status_text = '已完成';
                                break;
                            case 'cancelled':
                                $status_class = 'status-cancelled';
                                $status_text = '已取消';
                                break;
                        }

                        $start_time = date('Y-m-d H:i', strtotime($session['start_time']));
                    ?>
                    <div class="session-card" data-session-id="<?= $session['session_id'] ?>">
                        <div class="session-header">
                            <h3 class="session-name"><?= htmlspecialchars($session['session_name']) ?></h3>
                            <span class="session-status <?= $status_class ?>"><?= $status_text ?></span>
                        </div>
                        <div class="session-info">
                            <div class="info-item">
                                <span class="info-label">开始时间:</span>
                                <span class="info-value"><?= $start_time ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">已清点:</span>
                                <span class="info-value"><?= $session['total_counted'] ?> 箱</span>
                            </div>
                            <?php if ($session['created_by']): ?>
                            <div class="info-item">
                                <span class="info-label">创建人:</span>
                                <span class="info-value"><?= htmlspecialchars($session['created_by']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($session['remark']): ?>
                        <div class="session-remark">
                            备注: <?= htmlspecialchars($session['remark']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="session-actions">
                            <?php if ($session['status'] === 'counting'): ?>
                                <button class="btn btn-primary btn-continue" data-session-id="<?= $session['session_id'] ?>">
                                    继续清点
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-view-report" data-session-id="<?= $session['session_id'] ?>">
                                    查看报告
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- 新建清点任务模态框 -->
    <div id="new-session-modal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">开始新清点</h3>
                <button type="button" class="modal-close" id="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="session-name">清点任务名称 *</label>
                    <input type="text" id="session-name" class="form-control"
                           placeholder="例如: 2025年1月仓库盘点" required>
                </div>
                <div class="form-group">
                    <label for="created-by">创建人</label>
                    <input type="text" id="created-by" class="form-control"
                           placeholder="请输入您的姓名（可选）">
                </div>
                <div class="form-group">
                    <label for="session-remark">备注</label>
                    <textarea id="session-remark" class="form-control" rows="3"
                              placeholder="可选填写备注信息"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modal-cancel-btn">取消</button>
                <button type="button" class="btn btn-primary" id="modal-confirm-btn">开始清点</button>
            </div>
        </div>
    </div>

    <script src="./js/count_home.js?v=<?php echo time(); ?>"></script>
</body>
</html>
