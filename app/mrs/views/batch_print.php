<?php
/**
 * Batch Label Print Page (Flexible Version)
 * 文件路径: app/mrs/views/batch_print.php
 * 支持：1. 同批次部分打印  2. 不同批次混合打印
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取在库批次及可打印包裹
$batches = mrs_get_instock_batches($pdo);

// 为每个批次获取包裹列表
$batch_packages = [];
foreach ($batches as $batch) {
    $batch_name = $batch['batch_name'];
    $packages = mrs_get_packages_by_batch($pdo, $batch_name, 'in_stock');
    if (!empty($packages)) {
        $batch_packages[$batch_name] = $packages;
    }
}

function mrs_tracking_tail($tracking_number)
{
    if (!$tracking_number) {
        return '----';
    }

    $tracking_number = trim((string) $tracking_number);

    if ($tracking_number === '') {
        return '----';
    }

    return substr($tracking_number, -4);
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>灵活箱贴打印 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <style>
        body {
            background: #f5f5f5;
        }

        .print-actions {
            display: flex;
            gap: 10px;
        }

        .print-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .selection-panel {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .batch-selector {
            margin-bottom: 16px;
        }

        .batch-group {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .batch-header {
            background: #f8f9fa;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            user-select: none;
        }

        .batch-header:hover {
            background: #e9ecef;
        }

        .batch-header input[type="checkbox"] {
            cursor: pointer;
        }

        .batch-header-title {
            flex: 1;
            font-weight: 600;
            font-size: 14px;
        }

        .batch-header-count {
            color: #666;
            font-size: 13px;
        }

        .batch-toggle {
            color: #666;
            font-size: 12px;
        }

        .package-list {
            padding: 12px 16px;
            display: none;
            background: #fafbfc;
        }

        .package-list.expanded {
            display: block;
        }

        .package-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 6px;
        }

        .package-item:hover {
            background: #f0f0f0;
        }

        .package-item input[type="checkbox"] {
            cursor: pointer;
        }

        .package-info {
            flex: 1;
            font-size: 13px;
        }

        .package-info-primary {
            font-weight: 500;
            color: #333;
        }

        .package-info-secondary {
            color: #666;
            font-size: 12px;
            margin-top: 2px;
        }

        .selection-summary {
            margin: 16px 0;
            padding: 12px;
            border-radius: 6px;
            background: #e3f2fd;
            color: #0d47a1;
            font-size: 14px;
        }

        .control-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .control-buttons .btn {
            font-size: 13px;
        }

        .print-canvas {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            display: none;
        }

        .print-canvas.active {
            display: block;
        }

        .label-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(60mm, 1fr));
            gap: 8mm 6mm;
        }

        .label-card {
            border: 1.6px solid #111;
            border-radius: 6px;
            padding: 6mm 5mm;
            min-height: 45mm;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        .label-title {
            font-size: 42pt;
            font-weight: 800;
            text-align: center;
            line-height: 1.1;
            word-break: break-all;
            white-space: nowrap;
        }

        .label-meta {
            margin-top: 4mm;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
            gap: 1.5mm 3mm;
            font-size: 24pt;
            font-weight: 800;
            line-height: 1.05;
            white-space: nowrap;
        }

        .label-meta span {
            white-space: nowrap;
        }

        .label-spec {
            margin-top: 2mm;
            font-size: 14pt;
            text-align: right;
            color: #333;
        }

        @media print {
            body {
                background: white;
            }

            .sidebar,
            .page-header,
            .info-box,
            .selection-panel,
            .message,
            .print-actions button:not(.print-only) {
                display: none !important;
            }

            .main-content {
                margin: 0;
                padding: 0;
                width: auto;
            }

            .content-wrapper {
                box-shadow: none;
                border: none;
                padding: 0;
            }

            .print-canvas {
                border: none;
                box-shadow: none;
                padding: 0;
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>灵活箱贴打印</h1>
            <div class="print-actions">
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-secondary">返回库存</a>
                <button id="print-btn" class="btn btn-primary" onclick="window.print()" style="display: none;">打印选中箱贴</button>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="info-box">
                <strong>灵活打印模式：</strong>支持同批次部分打印 + 不同批次混合打印。勾选需要的包裹，点击"生成打印预览"按钮。
            </div>

            <?php if (empty($batches)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-text">暂无可打印的批次</div>
                    <p style="color: #666;">请先完成入库，再回到此处打印箱贴。</p>
                </div>
            <?php else: ?>
                <div class="selection-panel">
                    <div class="control-buttons">
                        <button class="btn btn-secondary btn-sm" onclick="selectAllBatches()">全选批次</button>
                        <button class="btn btn-secondary btn-sm" onclick="deselectAllBatches()">取消全选</button>
                        <button class="btn btn-secondary btn-sm" onclick="expandAllBatches()">展开全部</button>
                        <button class="btn btn-secondary btn-sm" onclick="collapseAllBatches()">收起全部</button>
                        <button class="btn btn-highlight btn-sm" onclick="generatePreview()">生成打印预览</button>
                    </div>

                    <div id="selection-summary" class="selection-summary" style="display: none;">
                        已选择 <strong id="selected-count">0</strong> 个包裹，来自 <strong id="selected-batches-count">0</strong> 个批次
                    </div>

                    <div class="batch-selector">
                        <?php foreach ($batch_packages as $batch_name => $packages): ?>
                            <div class="batch-group" data-batch="<?= htmlspecialchars($batch_name) ?>">
                                <div class="batch-header" onclick="toggleBatch(this)">
                                    <input type="checkbox" class="batch-checkbox" data-batch="<?= htmlspecialchars($batch_name) ?>"
                                           onchange="onBatchCheckboxChange(this)" onclick="event.stopPropagation()">
                                    <div class="batch-header-title"><?= htmlspecialchars($batch_name) ?></div>
                                    <div class="batch-header-count"><?= count($packages) ?> 箱</div>
                                    <div class="batch-toggle">▼</div>
                                </div>
                                <div class="package-list">
                                    <?php foreach ($packages as $package): ?>
                                        <?php
                                        $content = trim($package['content_note'] ?? '');
                                        $content = $content !== '' ? $content : '未填写物料';
                                        $spec = trim($package['spec_info'] ?? '');
                                        $tail = mrs_tracking_tail($package['tracking_number'] ?? '');
                                        $box_number = $package['box_number'] ?? '';
                                        ?>
                                        <div class="package-item">
                                            <input type="checkbox" class="package-checkbox"
                                                   data-batch="<?= htmlspecialchars($batch_name) ?>"
                                                   data-ledger-id="<?= htmlspecialchars($package['ledger_id']) ?>"
                                                   data-content="<?= htmlspecialchars($content) ?>"
                                                   data-box-number="<?= htmlspecialchars($box_number) ?>"
                                                   data-tail="<?= htmlspecialchars($tail) ?>"
                                                   data-spec="<?= htmlspecialchars($spec) ?>"
                                                   onchange="onPackageCheckboxChange()">
                                            <div class="package-info">
                                                <div class="package-info-primary">
                                                    <?= htmlspecialchars($content) ?>
                                                </div>
                                                <div class="package-info-secondary">
                                                    箱号：<?= htmlspecialchars($box_number) ?> | 快递尾号：<?= htmlspecialchars($tail) ?><?= !empty($spec) ? ' | 规格：' . htmlspecialchars($spec) : '' ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="print-canvas" class="print-canvas">
                    <div class="label-grid" id="label-grid">
                        <!-- 箱贴将通过JavaScript动态生成 -->
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 切换批次展开/收起
        function toggleBatch(header) {
            const batchGroup = header.closest('.batch-group');
            const packageList = batchGroup.querySelector('.package-list');
            const toggle = header.querySelector('.batch-toggle');

            packageList.classList.toggle('expanded');
            toggle.textContent = packageList.classList.contains('expanded') ? '▲' : '▼';
        }

        // 展开所有批次
        function expandAllBatches() {
            document.querySelectorAll('.package-list').forEach(list => {
                list.classList.add('expanded');
            });
            document.querySelectorAll('.batch-toggle').forEach(toggle => {
                toggle.textContent = '▲';
            });
        }

        // 收起所有批次
        function collapseAllBatches() {
            document.querySelectorAll('.package-list').forEach(list => {
                list.classList.remove('expanded');
            });
            document.querySelectorAll('.batch-toggle').forEach(toggle => {
                toggle.textContent = '▼';
            });
        }

        // 全选批次
        function selectAllBatches() {
            document.querySelectorAll('.batch-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                selectBatchPackages(checkbox.dataset.batch, true);
            });
            updateSelectionSummary();
        }

        // 取消全选
        function deselectAllBatches() {
            document.querySelectorAll('.batch-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.querySelectorAll('.package-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectionSummary();
        }

        // 批次复选框变化事件
        function onBatchCheckboxChange(checkbox) {
            const batchName = checkbox.dataset.batch;
            const isChecked = checkbox.checked;
            selectBatchPackages(batchName, isChecked);
            updateSelectionSummary();
        }

        // 选择/取消批次下的所有包裹
        function selectBatchPackages(batchName, isChecked) {
            document.querySelectorAll(`.package-checkbox[data-batch="${batchName}"]`).forEach(packageCheckbox => {
                packageCheckbox.checked = isChecked;
            });
        }

        // 包裹复选框变化事件
        function onPackageCheckboxChange() {
            // 更新批次复选框状态
            document.querySelectorAll('.batch-checkbox').forEach(batchCheckbox => {
                const batchName = batchCheckbox.dataset.batch;
                const packageCheckboxes = document.querySelectorAll(`.package-checkbox[data-batch="${batchName}"]`);
                const checkedCount = Array.from(packageCheckboxes).filter(cb => cb.checked).length;

                batchCheckbox.checked = checkedCount > 0;
                batchCheckbox.indeterminate = checkedCount > 0 && checkedCount < packageCheckboxes.length;
            });

            updateSelectionSummary();
        }

        // 更新选择摘要
        function updateSelectionSummary() {
            const selectedPackages = document.querySelectorAll('.package-checkbox:checked');
            const selectedBatches = new Set();

            selectedPackages.forEach(checkbox => {
                selectedBatches.add(checkbox.dataset.batch);
            });

            const summary = document.getElementById('selection-summary');
            const countEl = document.getElementById('selected-count');
            const batchesCountEl = document.getElementById('selected-batches-count');

            if (selectedPackages.length > 0) {
                summary.style.display = 'block';
                countEl.textContent = selectedPackages.length;
                batchesCountEl.textContent = selectedBatches.size;
            } else {
                summary.style.display = 'none';
            }
        }

        // 生成打印预览
        function generatePreview() {
            const selectedPackages = document.querySelectorAll('.package-checkbox:checked');

            if (selectedPackages.length === 0) {
                alert('请至少选择一个包裹进行打印');
                return;
            }

            const labelGrid = document.getElementById('label-grid');
            labelGrid.innerHTML = '';

            selectedPackages.forEach(checkbox => {
                const batchName = checkbox.dataset.batch;
                const content = checkbox.dataset.content;
                const boxNumber = checkbox.dataset.boxNumber;
                const tail = checkbox.dataset.tail;
                const spec = checkbox.dataset.spec;

                const labelCard = document.createElement('div');
                labelCard.className = 'label-card';

                let html = `
                    <div class="label-title">${escapeHtml(content)}</div>
                    <div class="label-meta">
                        <span>${escapeHtml(batchName)}-${escapeHtml(boxNumber)}-${escapeHtml(tail)}</span>
                    </div>
                `;

                if (spec) {
                    html += `<div class="label-spec">规格：${escapeHtml(spec)}</div>`;
                }

                labelCard.innerHTML = html;
                labelGrid.appendChild(labelCard);
            });

            // 显示打印画布和打印按钮
            document.getElementById('print-canvas').classList.add('active');
            document.getElementById('print-btn').style.display = 'inline-flex';

            // 调整文字大小
            setTimeout(() => {
                adjustLabelTextSize();
                // 滚动到打印预览区域
                document.getElementById('print-canvas').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }

        // 调整标签文字大小
        function adjustLabelTextSize() {
            const fitText = (el, { max = 42, min = 16, step = 0.5 } = {}) => {
                let size = max;
                el.style.fontSize = `${size}pt`;

                while (el.scrollWidth > el.clientWidth && size > min) {
                    size -= step;
                    el.style.fontSize = `${size}pt`;
                }
            };

            document.querySelectorAll('.label-title').forEach((title) => {
                fitText(title, { max: 42, min: 18, step: 0.5 });
            });

            document.querySelectorAll('.label-meta').forEach((meta) => {
                fitText(meta, { max: 24, min: 16, step: 0.5 });
            });
        }

        // HTML转义
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', () => {
            updateSelectionSummary();
        });
    </script>
</body>
</html>
