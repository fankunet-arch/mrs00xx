<?php
/**
 * Inventory List Page
 * 文件路径: app/mrs/views/inventory_list.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取排序参数
$sort_by = $_GET['sort'] ?? 'sku_name';
$sort_dir = $_GET['dir'] ?? 'asc';

// 获取库存汇总（使用真正的多产品统计）
$inventory = mrs_get_true_inventory_summary($pdo, '', $sort_by, $sort_dir);
$total_boxes = array_sum(array_column($inventory, 'total_boxes'));

// 辅助函数：生成排序链接
function get_sort_url($column, $current_sort, $current_dir) {
    $new_dir = 'asc';
    if ($column === $current_sort && $current_dir === 'asc') {
        $new_dir = 'desc';
    }
    return "/mrs/ap/index.php?action=inventory_list&sort={$column}&dir={$new_dir}";
}

// 辅助函数：生成排序图标
function get_sort_icon($column, $current_sort, $current_dir) {
    if ($column !== $current_sort) {
        return '<span style="color: #ccc;">⇅</span>';
    }
    return $current_dir === 'asc' ? '<span style="color: #007bff;">↑</span>' : '<span style="color: #007bff;">↓</span>';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>库存总览 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
    <style>
        .data-table thead th a {
            display: inline-block;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .data-table thead th a:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }

        /* 箱子搜索模态框样式 */
        .search-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            animation: fadeIn 0.2s;
        }

        .search-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow: hidden;
            animation: slideDown 0.3s;
        }

        .search-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .search-modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }

        .search-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .search-modal-close:hover {
            background: #f1f3f5;
        }

        .search-modal-body {
            padding: 24px;
        }

        .search-input-wrapper {
            position: relative;
            margin-bottom: 16px;
        }

        .search-input {
            width: 100%;
            padding: 14px 48px 14px 16px;
            font-size: 16px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.15);
        }

        .search-input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #adb5bd;
        }

        .search-results-container {
            max-height: 400px;
            overflow-y: auto;
        }

        .search-result-item {
            padding: 14px 16px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .search-result-item:hover {
            background: #f8f9fa;
            border-color: #007bff;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .search-result-main {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .search-result-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
        }

        .badge-box {
            background: #e7f3ff;
            color: #0066cc;
        }

        .badge-tracking {
            background: #f0f0f0;
            color: #495057;
        }

        .search-result-details {
            font-size: 13px;
            color: #6c757d;
            line-height: 1.6;
        }

        .search-empty {
            padding: 40px 20px;
            text-align: center;
            color: #adb5bd;
        }

        .search-empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .search-hint {
            font-size: 13px;
            color: #adb5bd;
            margin-top: 8px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>库存总览</h1>
            <div class="header-actions">
                <button onclick="openSearchModal()" class="btn btn-info" style="margin-right: 10px;">
                    🔍 搜索箱子
                </button>
                <a href="/mrs/ap/index.php?action=batch_print" class="btn btn-secondary">箱贴打印</a>
                <a href="/mrs/ap/index.php?action=inbound" class="btn btn-primary">入库录入</a>
                <a href="/mrs/ap/index.php?action=outbound" class="btn btn-success">出库核销</a>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_boxes ?></div>
                    <div class="stat-label">在库包裹总数</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-number"><?= count($inventory) ?></div>
                    <div class="stat-label">物料种类</div>
                </div>
            </div>

            <h2 style="margin-bottom: 15px;">库存汇总</h2>

            <?php if (empty($inventory)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-text">暂无库存数据</div>
                    <a href="/mrs/ap/index.php?action=inbound" class="btn btn-primary">立即入库</a>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?= get_sort_url('sku_name', $sort_by, $sort_dir) ?>" style="color: inherit; text-decoration: none;">
                                    物料名称 <?= get_sort_icon('sku_name', $sort_by, $sort_dir) ?>
                                </a>
                            </th>
                            <th class="text-center">
                                <a href="<?= get_sort_url('total_boxes', $sort_by, $sort_dir) ?>" style="color: inherit; text-decoration: none;">
                                    在库数量 <?= get_sort_icon('total_boxes', $sort_by, $sort_dir) ?>
                                </a>
                            </th>
                            <th class="text-center">
                                <a href="<?= get_sort_url('total_quantity', $sort_by, $sort_dir) ?>" style="color: inherit; text-decoration: none;">
                                    数量 <?= get_sort_icon('total_quantity', $sort_by, $sort_dir) ?>
                                </a>
                            </th>
                            <th class="text-center">
                                <a href="<?= get_sort_url('nearest_expiry_date', $sort_by, $sort_dir) ?>" style="color: inherit; text-decoration: none;">
                                    最近到期 <?= get_sort_icon('nearest_expiry_date', $sort_by, $sort_dir) ?>
                                </a>
                            </th>
                            <th class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['sku_name']) ?></td>
                                <td class="text-center"><strong><?= $item['total_boxes'] ?></strong> 箱</td>
                                <td class="text-center">
                                    <?php if ($item['total_quantity'] > 0): ?>
                                        约:<strong><?= number_format($item['total_quantity']) ?></strong>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($item['nearest_expiry_date'])): ?>
                                        <?php
                                        $expiry = new DateTime($item['nearest_expiry_date']);
                                        $today = new DateTime();
                                        $diff = $today->diff($expiry);
                                        $days_to_expiry = (int)$today->diff($expiry)->format('%r%a');

                                        // 根据到期天数显示不同颜色
                                        $color_class = '';
                                        if ($days_to_expiry < 0) {
                                            $color_class = 'style="color: #999; text-decoration: line-through;"'; // 已过期：灰色删除线
                                        } elseif ($days_to_expiry <= 7) {
                                            $color_class = 'style="color: #dc3545; font-weight: bold;"'; // 7天内：红色加粗
                                        } elseif ($days_to_expiry <= 30) {
                                            $color_class = 'style="color: #ff9800; font-weight: bold;"'; // 30天内：橙色加粗
                                        } elseif ($days_to_expiry <= 90) {
                                            $color_class = 'style="color: #ffc107;"'; // 90天内：黄色
                                        }
                                        ?>
                                        <span <?= $color_class ?>>
                                            <?= $expiry->format('Y-m-d') ?>
                                            <?php if ($days_to_expiry >= 0): ?>
                                                <small>(<?= $days_to_expiry ?>天)</small>
                                            <?php else: ?>
                                                <small>(已过期)</small>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-success"
                                            onclick="openPartialOutbound('<?= htmlspecialchars($item['sku_name'], ENT_QUOTES) ?>')">拆零出货</button>
                                    <a href="/mrs/ap/index.php?action=inventory_detail&sku=<?= urlencode($item['sku_name']) ?>"
                                       class="btn btn-sm btn-secondary">查看明细</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- 箱子搜索模态框 -->
    <div id="search-modal-overlay" class="search-modal-overlay" onclick="closeSearchModal(event)">
        <div class="search-modal" onclick="event.stopPropagation()">
            <div class="search-modal-header">
                <h3>🔍 快速定位箱子</h3>
                <button class="search-modal-close" onclick="closeSearchModal()">&times;</button>
            </div>
            <div class="search-modal-body">
                <div class="search-input-wrapper">
                    <input type="text"
                           id="box-search-input"
                           class="search-input"
                           placeholder="输入箱号、快递单号或物品名称..."
                           autocomplete="off">
                    <span class="search-input-icon">🔍</span>
                </div>
                <div class="search-hint">
                    支持箱号、快递单号和物品名称的模糊搜索
                </div>
                <div id="box-search-results" class="search-results-container"></div>
            </div>
        </div>
    </div>

    <script src="/mrs/ap/js/modal.js"></script>
    <script>
    function cleanQty(rawQty) {
        if (!rawQty) return 0;
        const cleaned = String(rawQty).replace(/[^0-9.]/g, '');
        return cleaned ? parseFloat(cleaned) : 0;
    }

    async function openPartialOutbound(skuName) {
        // 兜底：当 modal.js 未加载时给出提示，避免按钮点击无反应
        if (typeof window.showModal !== 'function' || typeof window.showAlert !== 'function') {
            alert('页面脚本未完全加载，请刷新后重试（缺少 modal.js）');
            return;
        }

        try {
            const response = await fetch(`/mrs/ap/index.php?action=outbound&sku=${encodeURIComponent(skuName)}&order_by=fifo&format=json`);
            const data = await response.json();

            if (!data.success || !Array.isArray(data.data.packages) || data.data.packages.length === 0) {
                await showAlert('该物料暂无可出库的在库包裹', '提示', 'warning');
                return;
            }

            const packages = data.data.packages;
            const today = new Date().toISOString().split('T')[0];
            const firstQty = cleanQty(packages[0]?.ledger_quantity ?? packages[0]?.quantity ?? '');

            const optionsHtml = packages.map(pkg => {
                const qty = cleanQty(pkg.ledger_quantity ?? pkg.quantity ?? '');
                const label = `${pkg.batch_name || '-'} / 箱号：${pkg.box_number || '-'} / 库存：${qty}件`;
                return `<option value="${pkg.ledger_id}" data-qty="${qty}">${label}</option>`;
            }).join('');

            const content = `
                <div class="modal-section">
                    <div class="form-group">
                        <label for="package-select">选择出库包裹 <span style="color: red;">*</span></label>
                        <select id="package-select" class="form-control">${optionsHtml}</select>
                    </div>

                    <div class="form-group">
                        <label for="outbound-date">出库日期 <span style="color: red;">*</span></label>
                        <input type="date" id="outbound-date" class="form-control" value="${today}" required>
                    </div>

                    <div class="form-group">
                        <label for="outbound-qty">出货数量 <span style="color: red;">*</span></label>
                        <input type="number" id="outbound-qty" class="form-control" min="0.01" step="0.01" max="${firstQty}" required>
                        <small id="available-tip" style="color: #666;">可出货数量：${firstQty} 件</small>
                    </div>

                    <div class="form-group">
                        <label for="destination">目的地（门店） <span style="color: red;">*</span></label>
                        <input type="text" id="destination" class="form-control" placeholder="请输入门店名称" required>
                    </div>

                    <div class="form-group">
                        <label for="remark">备注</label>
                        <textarea id="remark" class="form-control" rows="2" placeholder="选填"></textarea>
                    </div>
                </div>
            `;

            // 使用自定义模态框
            const confirmed = await window.showModal({
                title: '拆零出货',
                content,
                width: '560px',
                footer: `
                    <div class="modal-footer">
                        <button class="modal-btn modal-btn-secondary" data-action="cancel">取消</button>
                        <button class="modal-btn modal-btn-primary" data-action="confirm">确认出货</button>
                    </div>
                `
            });

            if (!confirmed) return;

            const packageSelect = document.getElementById('package-select');
            if (!packageSelect || !packageSelect.selectedOptions || packageSelect.selectedOptions.length === 0) {
                await showAlert('请选择要出库的包裹', '错误', 'error');
                return;
            }
            const selectedOption = packageSelect.selectedOptions[0];
            const ledgerId = parseInt(selectedOption.value, 10);
            const availableQty = parseFloat(selectedOption.dataset.qty || '0');

            const outboundQty = parseFloat(document.getElementById('outbound-qty').value);
            const outboundDate = document.getElementById('outbound-date').value;
            const destination = document.getElementById('destination').value.trim();
            const remark = document.getElementById('remark').value.trim();

            if (!ledgerId) {
                await showAlert('请选择要出库的包裹', '提示', 'warning');
                return;
            }

            if (!outboundQty || outboundQty <= 0) {
                await showAlert('请输入有效的出货数量', '错误', 'error');
                return;
            }

            if (outboundQty > availableQty) {
                await showAlert(`出货数量（${outboundQty}）超过库存（${availableQty}）`, '错误', 'error');
                return;
            }

            if (!destination) {
                await showAlert('请输入目的地（门店）', '错误', 'error');
                return;
            }

            if (!outboundDate) {
                await showAlert('请选择出库日期', '错误', 'error');
                return;
            }

            const responseSave = await fetch('/mrs/ap/index.php?action=partial_outbound', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ledger_id: ledgerId,
                    deduct_qty: outboundQty,
                    destination: destination,
                    remark: remark,
                    outbound_date: outboundDate
                })
            });

            const result = await responseSave.json();

            if (result.success) {
                await showAlert(`拆零出货成功！\n\n已扣减 ${outboundQty} 件\n剩余 ${result.data.remaining_qty} 件`, '成功', 'success');
                window.location.reload();
            } else {
                await showAlert('操作失败: ' + result.message, '错误', 'error');
            }
        } catch (error) {
            await showAlert('网络错误: ' + error.message, '错误', 'error');
        }
    }

    // 根据选择的包裹更新提示
    document.addEventListener('change', function(event) {
        if (event.target && event.target.id === 'package-select') {
            const option = (event.target.selectedOptions && event.target.selectedOptions.length > 0)
                ? event.target.selectedOptions[0]
                : null;
            const qty = option ? parseFloat(option.dataset.qty || '0') : 0;
            const tip = document.getElementById('available-tip');
            if (tip) {
                tip.textContent = `可出货数量：${qty} 件`;
            }
            const qtyInput = document.getElementById('outbound-qty');
            if (qtyInput) {
                qtyInput.max = qty;
                qtyInput.value = '';
            }
        }
    });

    // ====== 箱子搜索模态框功能 ======
    const boxSearchInput = document.getElementById('box-search-input');
    const boxSearchResults = document.getElementById('box-search-results');
    const searchModalOverlay = document.getElementById('search-modal-overlay');
    let searchTimeout = null;

    // 打开搜索模态框
    function openSearchModal() {
        searchModalOverlay.style.display = 'block';
        setTimeout(() => {
            boxSearchInput.focus();
        }, 100);
    }

    // 关闭搜索模态框
    function closeSearchModal(event) {
        if (event && event.target !== searchModalOverlay) return;
        searchModalOverlay.style.display = 'none';
        boxSearchInput.value = '';
        boxSearchResults.innerHTML = '';
    }

    // ESC键关闭模态框
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && searchModalOverlay.style.display === 'block') {
            closeSearchModal();
        }
    });

    if (boxSearchInput) {
        // 输入事件 - 实时搜索
        boxSearchInput.addEventListener('input', function(e) {
            const keyword = e.target.value.trim();

            // 清除之前的延时
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            // 如果输入为空，清空结果
            if (!keyword) {
                boxSearchResults.innerHTML = '';
                return;
            }

            // 延时搜索（防抖）
            searchTimeout = setTimeout(() => {
                performBoxSearch(keyword);
            }, 300);
        });
    }

    async function performBoxSearch(keyword) {
        try {
            const response = await fetch(`/mrs/ap/index.php?action=box_search_api&keyword=${encodeURIComponent(keyword)}`);
            const data = await response.json();

            if (data.success && data.data && data.data.length > 0) {
                displaySearchResults(data.data);
            } else {
                displayEmptyResults();
            }
        } catch (error) {
            console.error('Box search error:', error);
            displayEmptyResults();
        }
    }

    function displaySearchResults(results) {
        let html = '';

        results.forEach(item => {
            // 格式化日期
            const inboundDate = item.inbound_time ? new Date(item.inbound_time).toLocaleDateString('zh-CN') : '-';
            const expiryDate = item.expiry_date ? new Date(item.expiry_date).toLocaleDateString('zh-CN') : '-';

            html += `
                <div class="search-result-item" data-ledger-id="${item.ledger_id}" data-content-note="${escapeHtml(item.content_note || '')}">
                    <div class="search-result-main">
                        <span class="search-result-badge badge-box">箱号: ${escapeHtml(item.box_number || '-')}</span>
                        <span class="search-result-badge badge-tracking">单号: ${escapeHtml(item.tracking_number || '-')}</span>
                    </div>
                    <div class="search-result-details">
                        <strong>${escapeHtml(item.content_note || '无品名')}</strong><br>
                        批次: ${escapeHtml(item.batch_name || '-')} |
                        数量: ${item.quantity || 0} |
                        入库: ${inboundDate}
                        ${item.warehouse_location ? ' | 位置: ' + escapeHtml(item.warehouse_location) : ''}
                    </div>
                </div>
            `;
        });

        boxSearchResults.innerHTML = html;

        // 绑定点击事件
        boxSearchResults.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', function() {
                const contentNote = this.dataset.contentNote;
                if (contentNote) {
                    // 跳转到库存明细页面
                    window.location.href = `/mrs/ap/index.php?action=inventory_detail&sku=${encodeURIComponent(contentNote)}`;
                }
            });
        });
    }

    function displayEmptyResults() {
        boxSearchResults.innerHTML = `
            <div class="search-empty">
                <div class="search-empty-icon">📦</div>
                <div>未找到匹配的箱子</div>
            </div>
        `;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    </script>
</body>
</html>
