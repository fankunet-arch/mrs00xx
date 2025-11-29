<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> - MRS</title>
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
                <h2>库存管理</h2>

                <!-- 筛选区 -->
                <form action="/mrs/be/index.php" method="get" class="mb-3">
                    <input type="hidden" name="action" value="inventory_list">
                    <div class="flex-between">
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="search" placeholder="搜索物料名称/品牌..." value="<?php echo htmlspecialchars($search); ?>" style="width: 250px;">

                            <select name="category_id">
                                <option value="">全部品类</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" class="secondary">搜索</button>
                            <a href="/mrs/be/index.php?action=inventory_list"><button type="button" class="text">重置</button></a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>物料名称</th>
                                <th>品类</th>
                                <th>品牌</th>
                                <th>单位</th>
                                <th>当前库存</th>
                                <th>入库总量</th>
                                <th>出库总量</th>
                                <th>调整总量</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($inventory)): ?>
                                <?php foreach ($inventory as $item): ?>
                                    <?php
                                    // 计算库存显示格式
                                    $current_qty = $item['current_inventory'];
                                    $case_spec = $item['case_to_standard_qty'] ?? 0;

                                    if ($case_spec > 1 && $current_qty > 0) {
                                        $cases = floor($current_qty / $case_spec);
                                        $singles = $current_qty % $case_spec;
                                        $inventory_display = '';
                                        if ($cases > 0) $inventory_display .= format_number($cases) . $item['case_unit_name'] . ' ';
                                        if ($singles > 0) $inventory_display .= format_number($singles) . $item['standard_unit'];
                                        $inventory_display = trim($inventory_display);
                                    } else {
                                        $inventory_display = format_number($current_qty) . $item['standard_unit'];
                                    }

                                    // 库存颜色提示
                                    if ($current_qty <= 0) {
                                        $inventory_class = 'text-danger';
                                    } elseif ($current_qty < 10) {
                                        $inventory_class = 'text-warning';
                                    } else {
                                        $inventory_class = 'text-success';
                                    }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['sku_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['brand_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['standard_unit']); ?></td>
                                        <td class="<?php echo $inventory_class; ?>"><strong><?php echo $inventory_display; ?></strong></td>
                                        <td><?php echo format_number($item['total_inbound']); ?></td>
                                        <td><?php echo format_number($item['total_outbound']); ?></td>
                                        <td><?php echo format_number($item['total_adjustment']); ?></td>
                                        <td>
                                            <a href="/mrs/be/index.php?action=outbound_create&sku_id=<?php echo $item['sku_id']; ?>"><button class="primary small">出库</button></a>
                                            <button class="secondary small" onclick="viewHistory(<?php echo $item['sku_id']; ?>, '<?php echo htmlspecialchars(addslashes($item['sku_name'])); ?>')">历史</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center muted">暂无库存数据</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- 历史记录模态框 -->
    <div id="history-modal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3 id="history-title">物料历史记录</h3>
                <span class="close" onclick="closeHistoryModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="history-content">加载中...</div>
            </div>
        </div>
    </div>

    <script>
    async function viewHistory(skuId, skuName) {
        document.getElementById('history-title').textContent = skuName + ' - 历史记录';
        document.getElementById('history-modal').style.display = 'block';
        document.getElementById('history-content').innerHTML = '加载中...';

        try {
            const response = await fetch(`/mrs/be/index.php?action=backend_inventory_history&sku_id=${skuId}`);
            const result = await response.json();

            if (result.success) {
                renderHistory(result.data);
            } else {
                document.getElementById('history-content').innerHTML = '<div class="alert error">加载失败：' + (result.message || '未知错误') + '</div>';
            }
        } catch (error) {
            document.getElementById('history-content').innerHTML = '<div class="alert error">网络错误：' + error.message + '</div>';
        }
    }

    // Format number: remove trailing zeros and unnecessary decimal point
    function formatNumber(num) {
        if (num === null || num === undefined || num === '') return '-';
        const parsed = parseFloat(num);
        if (isNaN(parsed)) return '-';
        // Convert to string and remove trailing zeros
        return parsed.toString().replace(/\.0+$/, '').replace(/(\.\d*[1-9])0+$/, '$1');
    }

    function renderHistory(history) {
        if (!history || history.length === 0) {
            document.getElementById('history-content').innerHTML = '<p class="muted text-center">暂无历史记录</p>';
            return;
        }

        // Check if we have qty_after field (new transaction table format)
        const hasQtyAfter = history.length > 0 && history[0].qty_after !== undefined && history[0].qty_after !== null;
        const hasOperator = history.length > 0 && history[0].operator_name !== undefined && history[0].operator_name !== null;

        let html = '<table><thead><tr><th>日期</th><th>类型</th><th>单号</th><th>数量变化</th>';
        if (hasQtyAfter) html += '<th>交易后余额</th>';
        html += '<th>地点</th>';
        if (hasOperator) html += '<th>操作员</th>';
        html += '<th>备注</th></tr></thead><tbody>';

        history.forEach(record => {
            let qtyClass = record.qty > 0 ? 'text-success' : 'text-danger';
            let qtyFormatted = formatNumber(record.qty);
            let qtyText = record.qty > 0 ? '+' + qtyFormatted : qtyFormatted;

            html += `<tr>
                <td>${record.date}</td>
                <td>${record.type}</td>
                <td>${record.code || '-'}</td>
                <td class="${qtyClass}"><strong>${qtyText}</strong></td>`;

            if (hasQtyAfter) {
                let qtyAfterFormatted = formatNumber(record.qty_after);
                html += `<td class="text-info"><strong>${qtyAfterFormatted}</strong></td>`;
            }

            html += `<td>${record.location || '-'}</td>`;

            if (hasOperator) {
                html += `<td>${record.operator_name || '-'}</td>`;
            }

            html += `<td>${record.remark || '-'}</td>
            </tr>`;
        });

        html += '</tbody></table>';
        document.getElementById('history-content').innerHTML = html;
    }

    function closeHistoryModal() {
        document.getElementById('history-modal').style.display = 'none';
    }

    // 点击模态框外部关闭
    window.onclick = function(event) {
        const modal = document.getElementById('history-modal');
        if (event.target == modal) {
            closeHistoryModal();
        }
    }
    </script>

    <style>
    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.4);
    }
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #888;
        max-width: 900px;
        border-radius: 5px;
    }
    .modal-header {
        padding: 15px 20px;
        background-color: #f1f1f1;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-body {
        padding: 20px;
        max-height: 600px;
        overflow-y: auto;
    }
    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    .close:hover {
        color: #000;
    }
    </style>
</body>
</html>
