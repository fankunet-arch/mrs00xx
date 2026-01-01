<?php
/**
 * Batch Label Print Page
 * 文件路径: app/mrs/views/batch_print.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取在库批次及可打印包裹
$batches = mrs_get_instock_batches($pdo);
$selected_batch = $_GET['batch'] ?? '';
$packages = [];

if (!empty($selected_batch)) {
    $packages = mrs_get_packages_by_batch($pdo, $selected_batch, 'in_stock');
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
    <title>批次箱贴打印 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/batch_print.css">
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>批次箱贴打印</h1>
            <div class="print-actions">
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-secondary">返回库存</a>
                <?php if (!empty($packages)): ?>
                    <button class="btn btn-primary print-only" onclick="window.print()">打印当前批次</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="info-box">
                选择一个已经入库的批次，生成该批次所有在库箱子的箱贴打印页。打印时系统会自动隐藏导航栏和操作按钮。
            </div>

            <?php if (empty($batches)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-text">暂无可打印的批次</div>
                    <p class="text-muted">请先完成入库，再回到此处打印箱贴。</p>
                </div>
            <?php else: ?>
                <div class="batch-form">
                    <label for="batch_select">选择批次</label>
                    <select id="batch_select" class="form-control" onchange="onBatchChange(this.value)">
                        <option value="">-- 请选择需要打印的批次 --</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?= htmlspecialchars($batch['batch_name']) ?>"
                                <?= $selected_batch === $batch['batch_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($batch['batch_name']) ?> （在库: <?= $batch['in_stock_boxes'] ?> 箱）
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($selected_batch)): ?>
                    <div class="batch-summary">
                        当前批次：<strong><?= htmlspecialchars($selected_batch) ?></strong>，在库箱数：<strong><?= count($packages) ?></strong>
                    </div>

                    <?php if (empty($packages)): ?>
                        <div class="empty-state">
                            <div class="empty-state-text">该批次暂无在库箱子可打印</div>
                        </div>
                    <?php else: ?>
                        <div class="print-canvas">
                            <div class="label-grid">
                                <?php foreach ($packages as $package): ?>
                                    <?php
                                    $content = trim($package['content_note'] ?? '');
                                    $content = $content !== '' ? $content : '未填写物料';
                                    $spec = trim($package['spec_info'] ?? '');
                                    $tail = mrs_tracking_tail($package['tracking_number'] ?? '');
                                    ?>
                                    <div class="label-card">
                                        <div class="label-title"><?= htmlspecialchars($content) ?></div>
                                        <div class="label-meta">
                                            <span><?= htmlspecialchars($selected_batch) ?>-<?= htmlspecialchars($package['box_number']) ?>-<?= htmlspecialchars($tail) ?></span>
                                        </div>
                                        <?php if (!empty($spec)): ?>
                                            <div class="label-spec">规格：<?= htmlspecialchars($spec) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function onBatchChange(batch) {
            const url = new URL(window.location.href);
            if (batch) {
                url.searchParams.set('batch', batch);
            } else {
                url.searchParams.delete('batch');
            }
            window.location.href = url.toString();
        }

        document.addEventListener('DOMContentLoaded', () => {
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
        });
    </script>
</body>
</html>
