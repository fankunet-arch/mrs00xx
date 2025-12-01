<?php
/**
 * Batch labels print page
 * 文件路径: app/express/views/batch_print_labels.php
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

$labels = array_map(function ($package) {
    $tracking_number = trim((string)($package['tracking_number'] ?? ''));
    $label_name = trim((string)($package['content_note'] ?? ''));

    if ($label_name === '') {
        $label_name = '未填写';
    }

    $tail = $tracking_number !== '' ? substr($tracking_number, -4) : '----';

    return [
        'name' => $label_name,
        'tail' => $tail,
    ];
}, $packages);

usort($labels, function ($a, $b) {
    $nameCompare = strcmp($a['name'], $b['name']);
    if ($nameCompare !== 0) {
        return $nameCompare;
    }

    return strcmp($a['tail'], $b['tail']);
});
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
            margin: 10mm;
        }

        :root {
            --border-color: #cfd8dc;
        }

        body {
            margin: 0;
            padding: 0;
            background: #f4f6f8;
            font-family: "Helvetica Neue", Arial, sans-serif;
        }

        .print-actions {
            position: sticky;
            top: 0;
            background: rgba(244, 246, 248, 0.9);
            backdrop-filter: blur(3px);
            padding: 12px 16px;
            display: flex;
            gap: 10px;
            border-bottom: 1px solid var(--border-color);
            z-index: 5;
        }

        .print-actions button,
        .print-actions a {
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: white;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            color: #263238;
            transition: all 0.2s ease;
        }

        .print-actions button:hover,
        .print-actions a:hover {
            background: #eceff1;
        }

        .page {
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 12mm;
            background: white;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }

        .page-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12mm;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 6mm;
        }

        .page-title {
            font-size: 20px;
            color: #263238;
            margin: 0;
            font-weight: 700;
        }

        .page-subtitle {
            font-size: 14px;
            color: #546e7a;
            margin: 0;
        }

        .label-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(62mm, 1fr));
            gap: 10mm 8mm;
        }

        .label-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8mm;
            break-inside: avoid;
        }

        .label-name {
            font-size: 40pt;
            font-weight: 700;
            line-height: 1.05;
            white-space: nowrap;
            overflow: hidden;
            width: 100%;
        }

        .label-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 16pt;
            font-weight: 600;
            color: #37474f;
            letter-spacing: 0.02em;
        }

        .coefficient {
            margin-left: 6mm;
        }

        .empty-hint {
            text-align: center;
            color: #90a4ae;
            font-size: 16px;
            margin-top: 40px;
        }

        @media print {
            body {
                background: white;
            }

            .page {
                box-shadow: none;
                max-width: none;
                min-height: auto;
                margin: 0;
                padding: 10mm;
            }

            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="print-actions">
    <button type="button" onclick="window.print()">打印当前页面</button>
    <a href="/express/exp/index.php?action=batch_detail&batch_id=<?= $batch_id ?>">返回批次详情</a>
</div>

<div class="page">
    <div class="page-header">
        <h1 class="page-title">批次打印标签 - <?= htmlspecialchars($batch['batch_name']) ?></h1>
        <p class="page-subtitle">共 <?= count($labels) ?> 条</p>
    </div>

    <?php if (empty($labels)): ?>
        <div class="empty-hint">暂无包裹数据</div>
    <?php else: ?>
        <div class="label-grid">
            <?php foreach ($labels as $label): ?>
                <div class="label-card">
                    <div class="label-name"><?= htmlspecialchars($label['name']) ?></div>
                    <div class="label-meta">
                        <span class="tracking-tail"><?= htmlspecialchars($label['tail']) ?></span>
                        <span class="coefficient">系数：</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    const adjustLabelFonts = () => {
        const maxPt = 40;
        const minPt = 18;

        document.querySelectorAll('.label-name').forEach(label => {
            let fontSize = maxPt;
            label.style.fontSize = `${fontSize}pt`;

            const availableWidth = label.clientWidth || label.parentElement.clientWidth;

            while (fontSize > minPt && label.scrollWidth > availableWidth) {
                fontSize -= 1;
                label.style.fontSize = `${fontSize}pt`;
            }
        });
    };

    window.addEventListener('load', adjustLabelFonts);
    window.addEventListener('resize', () => {
        window.requestAnimationFrame(adjustLabelFonts);
    });
</script>
</body>
</html>
