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
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .error-box {
            background: #f8d7da;
            border: 1px solid #dc3545;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #28a745;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .input-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .result-section {
            margin-top: 30px;
        }
        .result-table {
            margin-top: 15px;
        }
        .result-table th {
            background: #e9ecef;
        }
        .deletable-row {
            background: #d4edda;
        }
        .non-deletable-row {
            background: #f8d7da;
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        .tracking-input {
            width: 100%;
            min-height: 150px;
            font-family: monospace;
            font-size: 14px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>批量删除包裹 - 库存修正</h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-secondary">返回库存</a>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- 警告提示 -->
            <div class="warning-box">
                <h3>⚠️ 重要提示</h3>
                <ul>
                    <li>此功能用于修正错误的入库记录，请谨慎操作</li>
                    <li>只能删除<strong>未出库</strong>的包裹（状态为in_stock且无出库记录）</li>
                    <li>已出库的包裹将<strong>不可删除</strong>，系统会给出提示</li>
                    <li>删除后包裹及其产品明细将从系统中移除，<strong>无法恢复</strong></li>
                    <li>批次中其他包裹的箱号不会受影响</li>
                </ul>
            </div>

            <!-- 输入区域 -->
            <div class="input-section">
                <h3>步骤1: 输入快递单号</h3>
                <p class="info-text">支持批量输入，每行一个快递单号，或使用逗号/空格分隔</p>
                <textarea
                    id="trackingInput"
                    class="tracking-input form-control"
                    placeholder="请输入快递单号，例如：&#10;1234567890123&#10;9876543210987&#10;或&#10;1234567890123, 9876543210987"
                ></textarea>
                <div class="action-buttons">
                    <button type="button" class="btn btn-primary" onclick="checkPackages()">
                        🔍 检查包裹状态
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearAll()">
                        🔄 清空重置
                    </button>
                </div>
            </div>

            <!-- 加载提示 -->
            <div id="loadingMessage" style="display: none; text-align: center; padding: 20px;">
                <p>正在检查包裹状态，请稍候...</p>
            </div>

            <!-- 结果区域 -->
            <div id="resultSection" class="result-section" style="display: none;">
                <!-- 汇总统计 -->
                <div id="summaryStats" class="summary-stats"></div>

                <!-- 可删除的包裹 -->
                <div id="deletableSection" style="display: none;">
                    <h3 style="color: #28a745;">✓ 可删除的包裹</h3>
                    <div class="result-table">
                        <table class="table">
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

                    <div style="margin-top: 20px;">
                        <label for="deleteReason">删除原因（必填）:</label>
                        <input type="text" id="deleteReason" class="form-control"
                               placeholder="请输入删除原因，例如：错误入库、重复录入等"
                               style="margin-top: 10px;">
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()"
                                style="margin-top: 15px;">
                            🗑️ 确认删除这些包裹
                        </button>
                    </div>
                </div>

                <!-- 不可删除的包裹 -->
                <div id="nonDeletableSection" style="display: none; margin-top: 30px;">
                    <h3 style="color: #dc3545;">✗ 不可删除的包裹</h3>
                    <div class="result-table">
                        <table class="table">
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

                <!-- 未找到的快递单号 -->
                <div id="notFoundSection" style="display: none; margin-top: 30px;">
                    <h3 style="color: #6c757d;">? 未找到的快递单号</h3>
                    <div class="info-box">
                        <p>以下快递单号在系统中未找到对应的包裹记录：</p>
                        <div id="notFoundList" style="margin-top: 10px; font-family: monospace;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 确认删除模态框 -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>⚠️ 确认删除操作</h3>
            <p id="confirmMessage"></p>
            <div class="modal-buttons">
                <button type="button" class="btn btn-danger" onclick="executeDelete()">确认删除</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
            </div>
        </div>
    </div>

    <script>
        let checkResult = null;

        // 检查包裹状态
        async function checkPackages() {
            const trackingInput = document.getElementById('trackingInput').value.trim();

            if (!trackingInput) {
                alert('请输入快递单号');
                return;
            }

            // 显示加载提示
            document.getElementById('loadingMessage').style.display = 'block';
            document.getElementById('resultSection').style.display = 'none';

            try {
                const response = await fetch('/mrs/ap/index.php?action=backend_bulk_deletion', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'check',
                        tracking_input: trackingInput
                    })
                });

                const result = await response.json();

                if (!result.success) {
                    alert('错误: ' + result.message);
                    document.getElementById('loadingMessage').style.display = 'none';
                    return;
                }

                // 保存检查结果
                checkResult = result;

                // 显示结果
                displayCheckResult(result);

            } catch (error) {
                alert('请求失败: ' + error.message);
                console.error('Error:', error);
            } finally {
                document.getElementById('loadingMessage').style.display = 'none';
            }
        }

        // 显示检查结果
        function displayCheckResult(result) {
            const summary = result.summary;

            // 显示结果区域
            document.getElementById('resultSection').style.display = 'block';

            // 显示汇总统计
            const statsHtml = `
                <div class="stat-card">
                    <div class="stat-label">请求总数</div>
                    <div class="stat-number">${summary.total_requested}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">找到包裹</div>
                    <div class="stat-number">${summary.found}</div>
                </div>
                <div class="stat-card" style="background: #d4edda;">
                    <div class="stat-label">可删除</div>
                    <div class="stat-number" style="color: #28a745;">${summary.deletable}</div>
                </div>
                <div class="stat-card" style="background: #f8d7da;">
                    <div class="stat-label">不可删除</div>
                    <div class="stat-number" style="color: #dc3545;">${summary.non_deletable}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">未找到</div>
                    <div class="stat-number">${summary.not_found}</div>
                </div>
            `;
            document.getElementById('summaryStats').innerHTML = statsHtml;

            // 显示可删除的包裹
            if (result.deletable.length > 0) {
                document.getElementById('deletableSection').style.display = 'block';
                const tbody = document.getElementById('deletableTableBody');
                tbody.innerHTML = result.deletable.map(pkg => `
                    <tr class="deletable-row">
                        <td>${escapeHtml(pkg.tracking_number)}</td>
                        <td>${escapeHtml(pkg.batch_name)}</td>
                        <td>${escapeHtml(pkg.box_number)}</td>
                        <td>${escapeHtml(pkg.warehouse_location || '-')}</td>
                        <td>${escapeHtml(pkg.products || '-')}</td>
                        <td>${escapeHtml(pkg.inbound_time)}</td>
                    </tr>
                `).join('');
            } else {
                document.getElementById('deletableSection').style.display = 'none';
            }

            // 显示不可删除的包裹
            if (result.non_deletable.length > 0) {
                document.getElementById('nonDeletableSection').style.display = 'block';
                const tbody = document.getElementById('nonDeletableTableBody');
                tbody.innerHTML = result.non_deletable.map(pkg => `
                    <tr class="non-deletable-row">
                        <td>${escapeHtml(pkg.tracking_number)}</td>
                        <td>${escapeHtml(pkg.batch_name)}</td>
                        <td>${escapeHtml(pkg.box_number)}</td>
                        <td>${escapeHtml(pkg.products || '-')}</td>
                        <td>${escapeHtml(pkg.status)}</td>
                        <td><strong>${escapeHtml(pkg.reason)}</strong></td>
                    </tr>
                `).join('');
            } else {
                document.getElementById('nonDeletableSection').style.display = 'none';
            }

            // 显示未找到的快递单号
            if (result.not_found.length > 0) {
                document.getElementById('notFoundSection').style.display = 'block';
                document.getElementById('notFoundList').innerHTML =
                    result.not_found.map(tn => `<div>• ${escapeHtml(tn)}</div>`).join('');
            } else {
                document.getElementById('notFoundSection').style.display = 'none';
            }
        }

        // 确认删除
        function confirmDelete() {
            if (!checkResult || checkResult.deletable.length === 0) {
                alert('没有可删除的包裹');
                return;
            }

            const reason = document.getElementById('deleteReason').value.trim();
            if (!reason) {
                alert('请输入删除原因');
                return;
            }

            // 显示确认模态框
            const count = checkResult.deletable.length;
            document.getElementById('confirmMessage').innerHTML =
                `您即将删除 <strong>${count}</strong> 个包裹，此操作不可恢复。<br><br>` +
                `删除原因: <strong>${escapeHtml(reason)}</strong><br><br>` +
                `确定要继续吗？`;
            document.getElementById('confirmModal').style.display = 'block';
        }

        // 执行删除
        async function executeDelete() {
            closeModal();

            const reason = document.getElementById('deleteReason').value.trim();
            const ledger_ids = checkResult.deletable.map(pkg => pkg.ledger_id);

            // 显示加载提示
            document.getElementById('loadingMessage').style.display = 'block';

            try {
                const response = await fetch('/mrs/ap/index.php?action=backend_bulk_deletion', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        ledger_ids: ledger_ids,
                        reason: reason
                    })
                });

                const result = await response.json();

                if (!result.success) {
                    alert('删除失败: ' + result.message);
                    return;
                }

                // 显示成功消息
                alert(`✓ 删除成功！\n\n${result.message}`);

                // 清空页面，准备下一次操作
                clearAll();

            } catch (error) {
                alert('请求失败: ' + error.message);
                console.error('Error:', error);
            } finally {
                document.getElementById('loadingMessage').style.display = 'none';
            }
        }

        // 关闭模态框
        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        // 清空所有
        function clearAll() {
            document.getElementById('trackingInput').value = '';
            document.getElementById('deleteReason').value = '';
            document.getElementById('resultSection').style.display = 'none';
            checkResult = null;
        }

        // HTML转义
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
