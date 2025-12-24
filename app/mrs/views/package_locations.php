<?php
/**
 * Package Locations Management Page
 * 文件路径: app/mrs/views/package_locations.php
 * 说明: 货架位置管理页面
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
    <title>货架位置管理 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
    <style>
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters input,
        .filters select,
        .filters button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .filters input {
            width: 150px;
        }

        .filters button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 500;
        }

        .filters button:hover {
            background: #0056b3;
        }

        .filters button.secondary {
            background: #6c757d;
        }

        .filters button.secondary:hover {
            background: #545b62;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .action-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
        }

        .action-buttons button.primary {
            background: #28a745;
            color: white;
        }

        .action-buttons button.primary:hover {
            background: #218838;
        }

        .action-buttons button.secondary {
            background: #6c757d;
            color: white;
        }

        .action-buttons button.secondary:hover {
            background: #545b62;
        }

        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .data-table td button {
            padding: 5px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .data-table td button.edit {
            background: #007bff;
            color: white;
        }

        .data-table td button.edit:hover {
            background: #0056b3;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 4px;
        }

        .pagination button:hover:not(:disabled) {
            background: #007bff;
            color: white;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination span {
            padding: 8px 12px;
            color: #666;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.in-stock {
            background: #d4edda;
            color: #155724;
        }

        .badge.shipped {
            background: #f8d7da;
            color: #721c24;
        }

        /* Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
        }

        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal {
            background: white;
            border-radius: 8px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
        }

        .modal-close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group small {
            display: block;
            margin-top: 4px;
            color: #666;
            font-size: 13px;
        }

        .form-group input[type="text"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .shelf-inputs {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .shelf-inputs input {
            width: 60px;
            text-align: center;
            font-size: 16px;
            padding: 10px;
        }

        .shelf-inputs span {
            font-weight: bold;
            color: #666;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
        }

        .modal-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .modal-actions button[type="submit"] {
            background: #007bff;
            color: white;
        }

        .modal-actions button[type="submit"]:hover {
            background: #0056b3;
        }

        .modal-actions button[type="button"] {
            background: #6c757d;
            color: white;
        }

        .modal-actions button[type="button"]:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>📦 货架位置管理</h1>
            <p>管理箱子的货架位置信息</p>
        </div>

        <div class="card">
            <!-- 搜索过滤 -->
            <div class="filters">
                <input type="text" id="filter-box-number" placeholder="箱号" />
                <input type="text" id="filter-location" placeholder="货架位置 (如: 01-02-03)" />
                <input type="text" id="filter-batch" placeholder="批次名称" />
                <select id="filter-status">
                    <option value="">全部状态</option>
                    <option value="in_stock">在库</option>
                    <option value="shipped">已出库</option>
                </select>
                <button onclick="loadPackageLocations(1)">🔍 搜索</button>
                <button class="secondary" onclick="resetFilters()">重置</button>
            </div>

            <!-- 操作按钮 -->
            <div class="action-buttons">
                <button class="primary" onclick="batchUpdateLocations()">📝 批量修改位置</button>
                <button class="secondary" onclick="exportData()">📊 导出数据</button>
            </div>

            <!-- 数据表格 -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)" />
                            </th>
                            <th>箱号</th>
                            <th>批次名称</th>
                            <th>快递单号</th>
                            <th>货架位置</th>
                            <th>内容备注</th>
                            <th>数量</th>
                            <th>状态</th>
                            <th>入库时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="locations-tbody">
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px; color: #999;">
                                加载中...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 分页 -->
            <div class="pagination" id="pagination-container">
                <!-- 动态生成分页 -->
            </div>
        </div>
    </div>

    <!-- 单个修改位置模态框 -->
    <div class="modal-overlay" id="modal-update-single">
        <div class="modal">
            <div class="modal-header">
                <h3>修改箱子位置</h3>
                <button class="modal-close" onclick="closeModal('modal-update-single')">&times;</button>
            </div>
            <form id="form-update-single" onsubmit="submitSingleUpdate(event)">
                <input type="hidden" id="update-ledger-id" />

                <div class="form-group">
                    <label>箱号</label>
                    <input type="text" id="update-box-number" disabled />
                </div>

                <div class="form-group">
                    <label>新位置 *</label>
                    <small>格式: 排号-架号-层号 (每段2位数字)</small>
                    <div class="shelf-inputs">
                        <input type="text" id="update-row" class="shelf-segment" placeholder="排" maxlength="2" autocomplete="off" />
                        <span>-</span>
                        <input type="text" id="update-rack" class="shelf-segment" placeholder="架" maxlength="2" autocomplete="off" />
                        <span>-</span>
                        <input type="text" id="update-level" class="shelf-segment" placeholder="层" maxlength="2" autocomplete="off" />
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="closeModal('modal-update-single')">取消</button>
                    <button type="submit">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 批量修改位置模态框 -->
    <div class="modal-overlay" id="modal-batch-update">
        <div class="modal">
            <div class="modal-header">
                <h3>批量修改位置</h3>
                <button class="modal-close" onclick="closeModal('modal-batch-update')">&times;</button>
            </div>
            <form id="form-batch-update" onsubmit="submitBatchUpdate(event)">
                <div class="form-group">
                    <label>已选择 <span id="selected-count">0</span> 个箱子</label>
                </div>

                <div class="form-group">
                    <label>新位置 *</label>
                    <small>格式: 排号-架号-层号 (每段2位数字)</small>
                    <div class="shelf-inputs">
                        <input type="text" id="batch-row" class="shelf-segment" placeholder="排" maxlength="2" autocomplete="off" />
                        <span>-</span>
                        <input type="text" id="batch-rack" class="shelf-segment" placeholder="架" maxlength="2" autocomplete="off" />
                        <span>-</span>
                        <input type="text" id="batch-level" class="shelf-segment" placeholder="层" maxlength="2" autocomplete="off" />
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="closeModal('modal-batch-update')">取消</button>
                    <button type="submit">批量更新</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let totalPages = 1;

        // 页面加载时获取数据
        document.addEventListener('DOMContentLoaded', function() {
            loadPackageLocations(1);
            initSegmentedInputs();
        });

        // 初始化三段式输入
        function initSegmentedInputs() {
            const segments = document.querySelectorAll('.shelf-segment');

            segments.forEach((input, index) => {
                const allSegments = Array.from(input.closest('.shelf-inputs').querySelectorAll('.shelf-segment'));
                const currentIndex = allSegments.indexOf(input);

                // 只允许输入数字
                input.addEventListener('input', function(e) {
                    this.value = this.value.replace(/\D/g, '');

                    if (this.value.length > 2) {
                        this.value = this.value.substring(0, 2);
                    }

                    // 输入满2位后自动跳转
                    if (this.value.length === 2 && currentIndex < allSegments.length - 1) {
                        setTimeout(() => {
                            allSegments[currentIndex + 1].focus();
                            allSegments[currentIndex + 1].select();
                        }, 0);
                    }
                });

                // 同时监听keyup事件
                input.addEventListener('keyup', function(e) {
                    if (this.value.length === 2 && currentIndex < allSegments.length - 1) {
                        const navKeys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Tab', 'Backspace', 'Delete'];
                        if (!navKeys.includes(e.key)) {
                            setTimeout(() => {
                                allSegments[currentIndex + 1].focus();
                                allSegments[currentIndex + 1].select();
                            }, 0);
                        }
                    }
                });

                // 支持键盘导航
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && this.value === '' && currentIndex > 0) {
                        e.preventDefault();
                        allSegments[currentIndex - 1].focus();
                        allSegments[currentIndex - 1].select();
                    }

                    if (e.key === 'ArrowLeft' && currentIndex > 0) {
                        e.preventDefault();
                        allSegments[currentIndex - 1].focus();
                    }

                    if (e.key === 'ArrowRight' && currentIndex < allSegments.length - 1) {
                        e.preventDefault();
                        allSegments[currentIndex + 1].focus();
                    }
                });
            });
        }

        // 加载箱子位置数据
        function loadPackageLocations(page) {
            currentPage = page;
            const params = new URLSearchParams({
                operation: 'list',
                page: page,
                limit: 20
            });

            const boxNumber = document.getElementById('filter-box-number').value.trim();
            const location = document.getElementById('filter-location').value.trim();
            const batch = document.getElementById('filter-batch').value.trim();
            const status = document.getElementById('filter-status').value;

            if (boxNumber) params.append('box_number', boxNumber);
            if (location) params.append('location', location);
            if (batch) params.append('batch_name', batch);
            if (status) params.append('status', status);

            fetch(`/mrs/api/backend_package_locations.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayLocations(data.data.items);
                        updatePagination(data.data.pagination);
                    } else {
                        alert('加载失败: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('加载失败，请稍后重试');
                });
        }

        // 显示数据
        function displayLocations(items) {
            const tbody = document.getElementById('locations-tbody');

            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 40px; color: #999;">暂无数据</td></tr>';
                return;
            }

            tbody.innerHTML = items.map(item => `
                <tr>
                    <td>
                        <input type="checkbox" class="item-checkbox" value="${item.ledger_id}" />
                    </td>
                    <td>${escapeHtml(item.box_number || '-')}</td>
                    <td>${escapeHtml(item.batch_name || '-')}</td>
                    <td>${escapeHtml(item.tracking_number || '-')}</td>
                    <td>${escapeHtml(item.warehouse_location || '-')}</td>
                    <td>${escapeHtml(item.content_note || '-')}</td>
                    <td>${item.quantity || 0}</td>
                    <td>
                        <span class="badge ${item.status === 'in_stock' ? 'in-stock' : 'shipped'}">
                            ${item.status === 'in_stock' ? '在库' : '已出库'}
                        </span>
                    </td>
                    <td>${item.inbound_time || '-'}</td>
                    <td>
                        <button class="edit" onclick="showUpdateModal(${item.ledger_id}, '${escapeHtml(item.box_number)}', '${escapeHtml(item.warehouse_location || '')}')">
                            修改位置
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // 更新分页
        function updatePagination(pagination) {
            totalPages = pagination.total_pages;
            const container = document.getElementById('pagination-container');

            container.innerHTML = `
                <button onclick="loadPackageLocations(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}>
                    上一页
                </button>
                <span>第 ${currentPage} / ${totalPages} 页 (共 ${pagination.total} 条)</span>
                <button onclick="loadPackageLocations(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}>
                    下一页
                </button>
            `;
        }

        // 显示单个修改模态框
        function showUpdateModal(ledgerId, boxNumber, currentLocation) {
            document.getElementById('update-ledger-id').value = ledgerId;
            document.getElementById('update-box-number').value = boxNumber;

            // 解析现有位置
            const parts = currentLocation.split('-');
            document.getElementById('update-row').value = parts[0] || '';
            document.getElementById('update-rack').value = parts[1] || '';
            document.getElementById('update-level').value = parts[2] || '';

            openModal('modal-update-single');
        }

        // 提交单个修改
        function submitSingleUpdate(event) {
            event.preventDefault();

            const ledgerId = document.getElementById('update-ledger-id').value;
            const row = document.getElementById('update-row').value.trim().padStart(2, '0');
            const rack = document.getElementById('update-rack').value.trim().padStart(2, '0');
            const level = document.getElementById('update-level').value.trim().padStart(2, '0');

            if (!row || !rack || !level) {
                alert('请填写完整的位置信息（排号-架号-层号）');
                return;
            }

            const newLocation = `${row}-${rack}-${level}`;

            fetch('/mrs/ap/index.php?action=update_package_location', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ledger_id: ledgerId,
                    new_location: newLocation
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('位置更新成功');
                    closeModal('modal-update-single');
                    loadPackageLocations(currentPage);
                } else {
                    alert('更新失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('更新失败，请稍后重试');
            });
        }

        // 批量修改位置
        function batchUpdateLocations() {
            const checked = document.querySelectorAll('.item-checkbox:checked');

            if (checked.length === 0) {
                alert('请先选择要修改的箱子');
                return;
            }

            document.getElementById('selected-count').textContent = checked.length;
            document.getElementById('batch-row').value = '';
            document.getElementById('batch-rack').value = '';
            document.getElementById('batch-level').value = '';

            openModal('modal-batch-update');
        }

        // 提交批量修改
        function submitBatchUpdate(event) {
            event.preventDefault();

            const checked = document.querySelectorAll('.item-checkbox:checked');
            const ledgerIds = Array.from(checked).map(cb => parseInt(cb.value));

            const row = document.getElementById('batch-row').value.trim().padStart(2, '0');
            const rack = document.getElementById('batch-rack').value.trim().padStart(2, '0');
            const level = document.getElementById('batch-level').value.trim().padStart(2, '0');

            if (!row || !rack || !level) {
                alert('请填写完整的位置信息（排号-架号-层号）');
                return;
            }

            const newLocation = `${row}-${rack}-${level}`;

            if (!confirm(`确定要将 ${ledgerIds.length} 个箱子的位置更新为 ${newLocation} 吗？`)) {
                return;
            }

            fetch('/mrs/ap/index.php?action=batch_update_locations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ledger_ids: ledgerIds,
                    new_location: newLocation
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`成功更新 ${data.data.affected} 个箱子的位置`);
                    closeModal('modal-batch-update');
                    loadPackageLocations(currentPage);
                    document.getElementById('select-all').checked = false;
                } else {
                    alert('更新失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('更新失败，请稍后重试');
            });
        }

        // 全选/取消全选
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }

        // 重置过滤
        function resetFilters() {
            document.getElementById('filter-box-number').value = '';
            document.getElementById('filter-location').value = '';
            document.getElementById('filter-batch').value = '';
            document.getElementById('filter-status').value = '';
            loadPackageLocations(1);
        }

        // 导出数据
        function exportData() {
            alert('导出功能开发中...');
        }

        // 打开模态框
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        // 关闭模态框
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // HTML转义
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 点击模态框背景关闭
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    </script>
</body>
</html>
