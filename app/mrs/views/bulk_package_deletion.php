<?php
/**
 * 批量删除包裹 - 库存修正
 * 文件路径: app/mrs/views/bulk_package_deletion.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批量删除包裹 - 库存修正 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
    <style>
        /* 页面特定样式优化 */
        .content-wrapper {
            padding: 15px;
        }

        /* 警告框优化 */
        .callout-warning {
            border-left: 5px solid #ffc107;
            background-color: #fff3cd;
            padding: 15px;
            margin-bottom: 20px;
        }

        /* 统计栏优化 - 更紧凑 */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .mini-stat-box {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            min-width: 140px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .mini-stat-icon {
            font-size: 24px;
            margin-right: 15px;
            opacity: 0.7;
        }

        .mini-stat-info h6 {
            margin: 0;
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }

        .mini-stat-info span {
            font-size: 20px;
            font-weight: bold;
            color: #343a40;
        }

        /* 表格优化 */
        .table-responsive {
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 13px;
        }

        .table td {
            font-size: 13px;
            vertical-align: middle;
        }

        /* 输入框样式 */
        .tracking-input {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 13px;
            line-height: 1.5;
            border-color: #ced4da;
        }

        /* 模态框自定义样式 (AdminLTE风格) */
        .custom-modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: rgba(0,0,0,0.5);
            outline: 0;
        }

        .custom-modal-dialog {
            position: relative;
            width: auto;
            margin: 1.75rem auto;
            max-width: 500px;
            pointer-events: none;
        }

        .custom-modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0,0,0,.2);
            border-radius: .3rem;
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.5);
            outline: 0;
        }

        .custom-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 1rem 1rem;
            border-bottom: 1px solid #dee2e6;
            border-top-left-radius: .3rem;
            border-top-right-radius: .3rem;
        }

        .custom-modal-body {
            position: relative;
            flex: 1 1 auto;
            padding: 1rem;
        }

        .custom-modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 1rem;
            border-top: 1px solid #dee2e6;
            border-bottom-right-radius: .3rem;
            border-bottom-left-radius: .3rem;
        }

        .custom-modal-title {
            margin-bottom: 0;
            line-height: 1.5;
            font-size: 1.25rem;
            font-weight: 500;
        }

        .btn-close {
            padding: 1rem;
            margin: -1rem -1rem -1rem auto;
            background: transparent;
            border: 0;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            color: #000;
            text-shadow: 0 1px 0 #fff;
            opacity: .5;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>批量删除包裹 - 库存修正</h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-default btn-sm">
                    <i class="fa fa-arrow-left"></i> 返回库存
                </a>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- 警告提示 -->
            <div class="callout-warning">
                <h5><i class="icon fa fa-warning"></i> 重要提示</h5>
                <ul style="margin-bottom: 0; padding-left: 20px;">
                    <li>此功能用于修正错误的入库记录，只能删除<strong>未出库</strong>（状态为in_stock）的包裹。</li>
                    <li>删除后包裹及其产品明细将<strong>永久移除</strong>，无法恢复。</li>
                    <li>已出库的包裹无法删除。</li>
                </ul>
            </div>

            <!-- 输入区域 -->
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">步骤1: 输入快递单号</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>请输入快递单号（支持批量，每行一个）：</label>
                        <textarea
                            id="trackingInput"
                            class="form-control tracking-input"
                            rows="5"
                            placeholder="例如：&#10;1234567890123&#10;9876543210987"
                        ></textarea>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" onclick="checkPackages()">
                            <i class="fa fa-search"></i> 检查包裹状态
                        </button>
                        <button type="button" class="btn btn-default" onclick="clearAll()">
                            <i class="fa fa-refresh"></i> 清空重置
                        </button>
                    </div>
                </div>
            </div>

            <!-- 加载提示 -->
            <div id="loadingMessage" style="display: none; text-align: center; padding: 40px;">
                <i class="fa fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top: 10px;">正在处理，请稍候...</p>
            </div>

            <!-- 结果区域 -->
            <div id="resultSection" style="display: none;">

                <!-- 统计概览 (美化版) -->
                <div class="stats-container" id="summaryStats">
                    <!-- JS将填充内容 -->
                </div>

                <!-- 可删除区域 -->
                <div id="deletableSection" class="card card-success card-outline" style="display: none;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title text-success"><i class="fa fa-check-circle"></i> 可删除的包裹</h3>
                        <div class="card-tools">
                             <button type="button" class="btn btn-danger btn-sm" onclick="openConfirmModal()">
                                <i class="fa fa-trash"></i> 确认删除这些包裹
                            </button>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-striped table-hover table-bordered m-0">
                            <thead>
                                <tr>
                                    <th>快递单号</th>
                                    <th>批次</th>
                                    <th>箱号</th>
                                    <th>货架位置</th>
                                    <th>产品明细</th>
                                    <th>入库时间</th>
                                </tr>
                            </thead>
                            <tbody id="deletableTableBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- 不可删除区域 -->
                <div id="nonDeletableSection" class="card card-danger card-outline" style="display: none; margin-top: 20px;">
                    <div class="card-header">
                        <h3 class="card-title text-danger"><i class="fa fa-times-circle"></i> 不可删除的包裹</h3>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-striped table-hover table-bordered m-0">
                            <thead>
                                <tr>
                                    <th>快递单号</th>
                                    <th>批次</th>
                                    <th>箱号</th>
                                    <th>产品明细</th>
                                    <th>状态</th>
                                    <th>原因</th>
                                </tr>
                            </thead>
                            <tbody id="nonDeletableTableBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- 未找到区域 -->
                <div id="notFoundSection" class="card card-secondary card-outline" style="display: none; margin-top: 20px;">
                    <div class="card-header">
                        <h3 class="card-title text-secondary"><i class="fa fa-question-circle"></i> 未找到的快递单号</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">以下单号在系统中不存在：</p>
                        <div id="notFoundList" style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 通用提示模态框 (Alert Modal) -->
    <div id="alertModal" class="custom-modal">
        <div class="custom-modal-dialog">
            <div class="custom-modal-content">
                <div class="custom-modal-header">
                    <h5 class="custom-modal-title" id="alertTitle">提示</h5>
                    <button type="button" class="btn-close" onclick="closeAlertModal()">×</button>
                </div>
                <div class="custom-modal-body">
                    <p id="alertMessage"></p>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn btn-primary" onclick="closeAlertModal()">确定</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 确认删除模态框 (Confirm Modal) -->
    <div id="confirmModal" class="custom-modal">
        <div class="custom-modal-dialog">
            <div class="custom-modal-content">
                <div class="custom-modal-header bg-danger text-white">
                    <h5 class="custom-modal-title" style="color: white;">⚠️ 确认删除</h5>
                    <button type="button" class="btn-close" onclick="closeConfirmModal()" style="color: white; opacity: 1;">×</button>
                </div>
                <div class="custom-modal-body">
                    <div id="confirmSummary" class="mb-3"></div>

                    <div class="form-group">
                        <label for="modalDeleteReason" class="text-danger">请输入删除原因 (必填):</label>
                        <input type="text" id="modalDeleteReason" class="form-control" placeholder="例如：入库错误、包裹破损等">
                        <small class="text-muted">此操作记录将被审计，请如实填写。</small>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn btn-default" onclick="closeConfirmModal()">取消</button>
                    <button type="button" class="btn btn-danger" onclick="executeDelete()">确认删除</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let checkResult = null;

        // 显示通用提示模态框
        function showAlert(message, title = '提示') {
            document.getElementById('alertTitle').textContent = title;
            document.getElementById('alertMessage').textContent = message;
            document.getElementById('alertModal').style.display = 'block';
        }

        function closeAlertModal() {
            document.getElementById('alertModal').style.display = 'none';
        }

        // 检查包裹
        async function checkPackages() {
            const trackingInput = document.getElementById('trackingInput').value.trim();

            if (!trackingInput) {
                showAlert('请输入快递单号', '输入错误');
                return;
            }

            document.getElementById('loadingMessage').style.display = 'block';
            document.getElementById('resultSection').style.display = 'none';

            try {
                const response = await fetch('/mrs/ap/index.php?action=backend_bulk_deletion', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'check', tracking_input: trackingInput })
                });

                const result = await response.json();

                if (!result.success) {
                    showAlert('错误: ' + result.message, '系统错误');
                    return;
                }

                checkResult = result;
                displayCheckResult(result);

            } catch (error) {
                showAlert('请求失败: ' + error.message, '网络错误');
            } finally {
                document.getElementById('loadingMessage').style.display = 'none';
            }
        }

        // 显示结果
        function displayCheckResult(result) {
            const summary = result.summary;
            document.getElementById('resultSection').style.display = 'block';

            // 1. 渲染美化后的统计栏
            const statsHtml = `
                <div class="mini-stat-box">
                    <div class="mini-stat-icon text-primary">📦</div>
                    <div class="mini-stat-info">
                        <h6>请求总数</h6>
                        <span>${summary.total_requested}</span>
                    </div>
                </div>
                <div class="mini-stat-box">
                    <div class="mini-stat-icon text-success">✅</div>
                    <div class="mini-stat-info">
                        <h6>找到包裹</h6>
                        <span>${summary.found}</span>
                    </div>
                </div>
                <div class="mini-stat-box">
                    <div class="mini-stat-icon text-info">🗑️</div>
                    <div class="mini-stat-info">
                        <h6>可删除</h6>
                        <span class="text-success">${summary.deletable}</span>
                    </div>
                </div>
                <div class="mini-stat-box">
                    <div class="mini-stat-icon text-danger">🚫</div>
                    <div class="mini-stat-info">
                        <h6>不可删除</h6>
                        <span class="text-danger">${summary.non_deletable}</span>
                    </div>
                </div>
                <div class="mini-stat-box">
                    <div class="mini-stat-icon text-secondary">❓</div>
                    <div class="mini-stat-info">
                        <h6>未找到</h6>
                        <span>${summary.not_found}</span>
                    </div>
                </div>
            `;
            document.getElementById('summaryStats').innerHTML = statsHtml;

            // 2. 渲染可删除表格
            if (result.deletable.length > 0) {
                document.getElementById('deletableSection').style.display = 'block';
                document.getElementById('deletableTableBody').innerHTML = result.deletable.map(pkg => `
                    <tr>
                        <td><code>${escapeHtml(pkg.tracking_number)}</code></td>
                        <td>${escapeHtml(pkg.batch_name)}</td>
                        <td>${escapeHtml(pkg.box_number)}</td>
                        <td>${escapeHtml(pkg.warehouse_location || '-')}</td>
                        <td><small>${escapeHtml(pkg.products || '-')}</small></td>
                        <td>${escapeHtml(pkg.inbound_time)}</td>
                    </tr>
                `).join('');
            } else {
                document.getElementById('deletableSection').style.display = 'none';
            }

            // 3. 渲染不可删除表格
            if (result.non_deletable.length > 0) {
                document.getElementById('nonDeletableSection').style.display = 'block';
                document.getElementById('nonDeletableTableBody').innerHTML = result.non_deletable.map(pkg => `
                    <tr>
                        <td><code>${escapeHtml(pkg.tracking_number)}</code></td>
                        <td>${escapeHtml(pkg.batch_name)}</td>
                        <td>${escapeHtml(pkg.box_number)}</td>
                        <td><small>${escapeHtml(pkg.products || '-')}</small></td>
                        <td><span class="badge badge-warning">${escapeHtml(pkg.status)}</span></td>
                        <td class="text-danger">${escapeHtml(pkg.reason)}</td>
                    </tr>
                `).join('');
            } else {
                document.getElementById('nonDeletableSection').style.display = 'none';
            }

            // 4. 渲染未找到列表
            if (result.not_found.length > 0) {
                document.getElementById('notFoundSection').style.display = 'block';
                document.getElementById('notFoundList').innerHTML =
                    result.not_found.map(tn => `<span>${escapeHtml(tn)}</span>`).join(', ');
            } else {
                document.getElementById('notFoundSection').style.display = 'none';
            }
        }

        // 打开删除确认模态框
        function openConfirmModal() {
            if (!checkResult || checkResult.deletable.length === 0) {
                showAlert('没有可删除的包裹');
                return;
            }

            const count = checkResult.deletable.length;
            document.getElementById('confirmSummary').innerHTML =
                `<p class="lead">您即将删除 <strong>${count}</strong> 个包裹。</p>
                 <p class="text-muted">此操作不可恢复，删除后库存将减少。</p>`;

            // 清空输入框
            document.getElementById('modalDeleteReason').value = '';

            document.getElementById('confirmModal').style.display = 'block';
            document.getElementById('modalDeleteReason').focus();
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        // 执行删除
        async function executeDelete() {
            const reason = document.getElementById('modalDeleteReason').value.trim();
            if (!reason) {
                // 使用 Alert 模态框提示，而不是 alert()
                // 但这里为了用户体验，直接高亮输入框可能更好，或者弹出一个小的警告
                // 既然用户要求全部用模态框，我们可以叠加，或者简单的在当前模态框显示错误
                // 这里选择叠加AlertModal
                showAlert('请输入删除原因！', '缺少信息');
                return;
            }

            // 准备数据
            const ledger_ids = checkResult.deletable.map(pkg => pkg.ledger_id);

            // UI 状态更新
            closeConfirmModal();
            document.getElementById('loadingMessage').style.display = 'block';

            try {
                const response = await fetch('/mrs/ap/index.php?action=backend_bulk_deletion', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        ledger_ids: ledger_ids,
                        reason: reason
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('删除成功！' + result.message, '操作成功');
                    clearAll(); // 重置界面
                } else {
                    showAlert('删除失败: ' + result.message, '操作失败');
                }
            } catch (error) {
                showAlert('请求失败: ' + error.message, '网络错误');
            } finally {
                document.getElementById('loadingMessage').style.display = 'none';
            }
        }

        function clearAll() {
            document.getElementById('trackingInput').value = '';
            document.getElementById('resultSection').style.display = 'none';
            checkResult = null;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 点击外部关闭
        window.onclick = function(event) {
            const alertModal = document.getElementById('alertModal');
            const confirmModal = document.getElementById('confirmModal');
            if (event.target === alertModal) closeAlertModal();
            if (event.target === confirmModal) closeConfirmModal();
        }
    </script>
</body>
</html>
