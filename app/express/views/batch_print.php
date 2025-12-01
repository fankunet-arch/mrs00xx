<?php
/**
 * Batch Label Print Page
 * 文件路径: app/express/views/batch_print.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batch_id = $_GET['batch_id'] ?? 0;

if (empty($batch_id)) {
    die('批次ID不能为空');
}

$batch = express_get_batch_by_id($pdo, $batch_id);

if (!$batch) {
    die('批次不存在');
}

$packages = express_get_packages_by_batch($pdo, $batch_id, 'all');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批次打印标签 - <?= htmlspecialchars($batch['batch_name']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            color: #2c3e50;
        }

        .page {
            max-width: 210mm;
            margin: 0 auto;
            padding: 12mm;
            background: white;
            min-height: 100vh;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .toolbar h1 {
            margin: 0;
            font-size: 20px;
        }

        .toolbar a,
        .toolbar button {
            padding: 8px 14px;
            border: 1px solid #d0d7de;
            background: #fff;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            color: #1f2933;
            transition: all 0.2s;
        }

        .toolbar a:hover,
        .toolbar button:hover {
            background: #f3f4f6;
        }

        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 24px;
            margin-bottom: 18px;
            font-size: 14px;
            color: #4a5568;
        }

        .summary-item {
            min-width: 120px;
        }

        .label-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }

        .label-card {
            border: 1px dashed #b0bec5;
            border-radius: 8px;
            padding: 12px 14px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.02);
        }

        .label-title {
            font-size: 40pt;
            font-weight: 700;
            line-height: 1.1;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
        }

        .label-subline {
            margin-top: 10px;
            font-size: 16pt;
            line-height: 1.2;
            white-space: nowrap;
        }

        .label-subline .tail {
            font-weight: 700;
            margin-right: 8px;
        }

        .empty-hint {
            padding: 24px;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            text-align: center;
            color: #6b7280;
            background: #f9fafb;
        }

        @media print {
            body {
                background: white;
            }

            .page {
                box-shadow: none;
                padding: 0;
                min-height: auto;
            }

            .toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="toolbar">
            <h1>批次打印标签</h1>
            <div style="display: flex; gap: 10px;">
                <a href="/express/exp/index.php?action=batch_detail&batch_id=<?= $batch_id ?>">返回批次详情</a>
                <button type="button" onclick="window.print()">打印</button>
            </div>
        </div>

        <div class="summary">
            <div class="summary-item"><strong>批次:</strong> <?= htmlspecialchars($batch['batch_name']) ?> (#<?= $batch_id ?>)</div>
            <div class="summary-item"><strong>包裹数:</strong> <?= count($packages) ?></div>
            <div class="summary-item"><strong>创建时间:</strong> <?= $batch['created_at'] ?></div>
            <?php if (!empty($batch['notes'])): ?>
                <div class="summary-item"><strong>备注:</strong> <?= htmlspecialchars($batch['notes']) ?></div>
            <?php endif; ?>
        </div>

        <?php if (empty($packages)): ?>
            <div class="empty-hint">当前批次暂无包裹，无法生成打印标签。</div>
        <?php else: ?>
            <div class="label-grid" id="label-grid">
                <?php foreach ($packages as $package): ?>
                    <?php
                        $content = trim($package['content_note'] ?? '');
                        $labelTitle = $content !== '' ? $content : '未填写内容';
                        $tracking = $package['tracking_number'] ?? '';
                        $tail = $tracking !== '' ? substr($tracking, -4) : '----';
                    ?>
                    <div class="label-card">
                        <div class="label-title" data-max="40" data-min="18"><?= htmlspecialchars($labelTitle) ?></div>
                        <div class="label-subline"><span class="tail"><?= htmlspecialchars($tail) ?></span>系数：</div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function adjustTitleSizes() {
            const titles = document.querySelectorAll('.label-title');
            titles.forEach((el) => {
                const maxSize = parseInt(el.dataset.max, 10) || 40;
                const minSize = parseInt(el.dataset.min, 10) || 18;
                let size = maxSize;
                el.style.fontSize = `${size}pt`;

                while (el.scrollWidth > el.clientWidth && size > minSize) {
                    size -= 1;
                    el.style.fontSize = `${size}pt`;
                }
            });
        }

        window.addEventListener('load', adjustTitleSizes);
        window.addEventListener('resize', adjustTitleSizes);
    </script>
</body>
</html>
