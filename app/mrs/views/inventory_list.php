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

                <?php
                $query_params = [
                    'action' => 'inventory_list',
                    'search' => $search,
                    'category_id' => $category_id
                ];

                function mrs_inventory_sort_link($field, $label, $currentSort, $currentDir, $params) {
                    $nextDir = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';
                    $params['sort'] = $field;
                    $params['dir'] = $nextDir;
                    $url = '/mrs/be/index.php?' . http_build_query($params);
                    $arrow = '';
                    if ($currentSort === $field) {
                        $arrow = $currentDir === 'asc' ? ' ▲' : ' ▼';
                    }

                    return '<a class="sort-link" href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . $arrow . '</a>';
                }
                ?>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo mrs_inventory_sort_link('sku_name', '物料名称', $current_sort_key, $current_sort_dir, $query_params); ?></th>
                                <th><?php echo mrs_inventory_sort_link('category', '品类', $current_sort_key, $current_sort_dir, $query_params); ?></th>
                                <th><?php echo mrs_inventory_sort_link('brand', '品牌', $current_sort_key, $current_sort_dir, $query_params); ?></th>
                                <th>单位</th>
                                <th><?php echo mrs_inventory_sort_link('current_inventory', '当前库存', $current_sort_key, $current_sort_dir, $query_params); ?></th>
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

                                    $box_threshold = ($case_spec > 0) ? 5 * $case_spec : null;
                                    $is_zero_inventory = $current_qty <= 0;
                                    $is_low_box_stock = !$is_zero_inventory && $box_threshold !== null && $current_qty < $box_threshold;

                                    $name_class = $is_low_box_stock ? 'low-inventory' : '';
                                    if ($is_zero_inventory) {
                                        $inventory_class = 'zero-inventory';
                                    } elseif ($is_low_box_stock) {
                                        $inventory_class = 'low-inventory';
                                    } else {
                                        $inventory_class = 'text-success';
                                    }
                                    ?>
                                    <tr>
                                        <td class="<?php echo $name_class; ?>"><strong><?php echo htmlspecialchars($item['sku_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['brand_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['standard_unit']); ?></td>
                                        <td class="<?php echo $inventory_class; ?>"><strong><?php echo $inventory_display; ?></strong></td>
                                        <td><?php echo format_number($item['total_inbound']); ?></td>
                                        <td><?php echo format_number($item['total_outbound']); ?></td>
                                        <td><?php echo format_number($item['total_adjustment']); ?></td>
                                        <td>
                                            <button class="info small" onclick="openStocktakeModal(
                                                <?php echo $item['sku_id']; ?>,
                                                '<?php echo htmlspecialchars(addslashes($item['sku_name'])); ?>',
                                                <?php echo $case_spec > 0 ? $case_spec : 0; ?>,
                                                '<?php echo htmlspecialchars(addslashes($item['case_unit_name'] ?? '')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($item['standard_unit'])); ?>',
                                                <?php echo $current_qty; ?>
                                            )">盘点</button>
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

    <!-- 盘点调整模态框 -->
    <div id="stocktake-modal" class="modal" style="display: none;">
        <div class="modal-content stocktake-card" style="max-width: 620px;">
            <div class="modal-header stocktake-header">
                <div>
                    <p class="eyebrow">库存盘点</p>
                    <h3 id="stocktake-title">盘点调整</h3>
                </div>
                <span class="close" onclick="closeStocktakeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="stocktake-summary">
                    <div class="pill muted">现有库存</div>
                    <div id="stocktake-current" class="stocktake-current">-</div>
                    <div class="divider"></div>
                    <div class="pill info-light">调整后</div>
                    <div id="stocktake-target" class="stocktake-target">-</div>
                </div>

                <div class="form-grid">
                    <div class="form-group" id="stocktake-case-group" style="display: none;">
                        <label>调整为（箱）</label>
                        <div class="input-row">
                            <input type="number" id="stocktake-case" min="0" value="0" oninput="updateStocktakePreview()">
                            <span class="unit" id="stocktake-case-unit">箱</span>
                        </div>
                        <small class="muted" id="stocktake-case-hint"></small>
                    </div>
                    <div class="form-group">
                        <label id="stocktake-single-label">调整为</label>
                        <div class="input-row">
                            <input type="number" id="stocktake-single" min="0" value="0" oninput="updateStocktakePreview()">
                            <span class="unit" id="stocktake-single-unit"></span>
                        </div>
                    </div>
                    <div class="form-group full">
                        <label>备注</label>
                        <input type="text" id="stocktake-reason" placeholder="可填写盘点备注，默认：手动盘点调整">
                    </div>
                </div>

                <div id="stocktake-error" class="alert error" style="display:none;"></div>

                <div class="form-actions" style="justify-content: flex-end;">
                    <button class="secondary" onclick="closeStocktakeModal()">取消</button>
                    <button class="primary" onclick="submitStocktake()">确认调整</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const stocktakeState = {
        skuId: null,
        caseSpec: 0,
        caseUnit: '',
        standardUnit: '',
        currentQty: 0
    };

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

    function openStocktakeModal(skuId, skuName, caseSpec, caseUnit, standardUnit, currentQty) {
        stocktakeState.skuId = skuId;
        stocktakeState.caseSpec = caseSpec;
        stocktakeState.caseUnit = caseUnit || '';
        stocktakeState.standardUnit = standardUnit;
        stocktakeState.currentQty = currentQty;

        document.getElementById('stocktake-title').textContent = skuName + ' - 库存盘点';
        document.getElementById('stocktake-current').textContent = formatNumber(currentQty) + standardUnit;
        document.getElementById('stocktake-reason').value = '';
        document.getElementById('stocktake-error').style.display = 'none';

        if (caseSpec > 1) {
            document.getElementById('stocktake-case-group').style.display = 'block';
            document.getElementById('stocktake-case-hint').textContent = '1 箱 = ' + formatNumber(caseSpec) + ' ' + standardUnit + (caseUnit ? ' / ' + caseUnit : '');
            document.getElementById('stocktake-case-unit').textContent = caseUnit || '箱';
            const cases = Math.floor(currentQty / caseSpec);
            const singles = currentQty % caseSpec;
            document.getElementById('stocktake-case').value = cases;
            document.getElementById('stocktake-single').value = singles;
            document.getElementById('stocktake-single-label').textContent = '调整为（散件）';
            document.getElementById('stocktake-single-unit').textContent = standardUnit;
        } else {
            document.getElementById('stocktake-case-group').style.display = 'none';
            document.getElementById('stocktake-single-label').textContent = '调整为（' + standardUnit + '）';
            document.getElementById('stocktake-single').value = currentQty;
            document.getElementById('stocktake-single-unit').textContent = standardUnit;
        }

        updateStocktakePreview();
        document.getElementById('stocktake-modal').style.display = 'block';
    }

    function closeStocktakeModal() {
        document.getElementById('stocktake-modal').style.display = 'none';
    }

    async function submitStocktake() {
        const errorBox = document.getElementById('stocktake-error');
        errorBox.style.display = 'none';

        const caseQty = parseFloat(document.getElementById('stocktake-case').value) || 0;
        const singleQty = parseFloat(document.getElementById('stocktake-single').value) || 0;
        const reason = document.getElementById('stocktake-reason').value || '手动盘点调整';

        if (caseQty < 0 || singleQty < 0) {
            errorBox.textContent = '数量不能为负数';
            errorBox.style.display = 'block';
            return;
        }

        let targetQty = singleQty;
        if (stocktakeState.caseSpec > 1) {
            targetQty = (caseQty * stocktakeState.caseSpec) + singleQty;
        }

        updateStocktakePreview(targetQty);

        try {
            const response = await fetch('/mrs/be/index.php?action=backend_adjust_inventory', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sku_id: stocktakeState.skuId,
                    current_qty: targetQty,
                    reason: reason
                })
            });

            const result = await response.json();
            if (result.success) {
                closeStocktakeModal();
                alert('盘点成功，库存已更新');
                window.location.reload();
            } else {
                throw new Error(result.message || '调整失败');
            }
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.style.display = 'block';
        }
    }

    function updateStocktakePreview(providedQty) {
        const caseQty = parseFloat(document.getElementById('stocktake-case').value) || 0;
        const singleQty = parseFloat(document.getElementById('stocktake-single').value) || 0;

        let totalQty = providedQty !== undefined ? providedQty : singleQty;
        if (stocktakeState.caseSpec > 1) {
            totalQty = (caseQty * stocktakeState.caseSpec) + singleQty;
        }

        const breakdown = stocktakeState.caseSpec > 1
            ? `${formatNumber(caseQty)}${stocktakeState.caseUnit || '箱'} + ${formatNumber(singleQty)}${stocktakeState.standardUnit}`
            : `${formatNumber(totalQty)}${stocktakeState.standardUnit}`;

        document.getElementById('stocktake-target').textContent = breakdown + ' （合计 ' + formatNumber(totalQty) + stocktakeState.standardUnit + '）';
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
        const historyModal = document.getElementById('history-modal');
        const stocktakeModal = document.getElementById('stocktake-modal');
        if (event.target === historyModal) {
            closeHistoryModal();
        }
        if (event.target === stocktakeModal) {
            closeStocktakeModal();
        }
    }
    </script>

    <style>
    .sort-link {
        color: inherit;
        text-decoration: none;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .sort-link:hover {
        color: #2563eb;
    }
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
    .modal-content.stocktake-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(0,0,0,0.18);
        background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
    }
    .modal-header {
        padding: 15px 20px;
        background-color: #f1f1f1;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .stocktake-header {
        background: linear-gradient(120deg, #0ea5e9 0%, #2563eb 100%);
        color: #fff;
        border-bottom: none;
    }
    .stocktake-header h3 { margin: 2px 0 0; }
    .stocktake-header .eyebrow {
        text-transform: uppercase;
        letter-spacing: .08em;
        font-size: 12px;
        opacity: .85;
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
    .stocktake-summary {
        display: grid;
        grid-template-columns: auto 1fr auto 1fr;
        align-items: center;
        gap: 10px;
        padding: 14px 16px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
        margin-bottom: 16px;
    }
    .pill {
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .pill.muted { background: #f3f4f6; color: #4b5563; }
    .pill.info-light { background: #e0f2fe; color: #0ea5e9; }
    .stocktake-current { font-size: 18px; font-weight: 700; color: #0f172a; }
    .stocktake-target { font-size: 16px; font-weight: 600; color: #0f172a; }
    .divider { height: 24px; width: 1px; background: #e5e7eb; }
    .input-row {
        display: flex;
        align-items: center;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
        box-shadow: inset 0 1px 2px rgba(17,24,39,0.05);
    }
    .input-row input[type="number"] {
        border: none;
        padding: 12px 14px;
        flex: 1;
        font-size: 15px;
        outline: none;
    }
    .input-row input[type="number"]:focus { box-shadow: none; }
    .input-row .unit {
        padding: 0 14px;
        background: #f9fafb;
        color: #6b7280;
        font-weight: 700;
        border-left: 1px solid #e5e7eb;
    }
    .form-grid .form-group.full input[type="text"] {
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid #d1d5db;
    }
    .form-grid .form-group.full input[type="text"]:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .form-group label { color: #111827; }
    .form-grid .form-group small { margin-top: 6px; display: block; }
    .low-inventory {
        color: #1d4ed8;
        font-weight: 700;
    }
    .zero-inventory {
        color: #dc2626;
        font-weight: 700;
    }
    </style>
</body>
</html>
