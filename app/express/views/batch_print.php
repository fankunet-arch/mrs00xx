<?php
/**
 * Backend Batch Print Preview Page
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
$printable_packages = array_values(array_filter($packages, function ($package) {
    $note = trim($package['content_note'] ?? '');
    return $note !== '空';
}));

function express_tracking_tail($tracking_number)
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
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批次打印预览 - <?= htmlspecialchars($batch['batch_name']) ?></title>
    <link rel="stylesheet" href="../css/batch_print.css">
</head>
<body>
<div class="page-wrapper">
    <div class="print-header">
        <div class="print-title">批次：<?= htmlspecialchars($batch['batch_name']) ?>（共 <?= count($printable_packages) ?> 件）</div>
        <div class="print-actions">
            <button class="btn" onclick="window.print()">打印</button>
            <button class="btn" onclick="window.close()">关闭</button>
        </div>
    </div>

    <div class="label-grid">
        <?php foreach ($printable_packages as $package): ?>
            <?php
            $content = trim($package['content_note'] ?? '');
            $content = $content !== '' ? $content : '未填写内容备注';
            $tail = express_tracking_tail($package['tracking_number'] ?? '');
            ?>
            <div class="label-card">
                <div class="label-title"><?= htmlspecialchars($content) ?></div>
                <div class="label-meta">
                    <span class="tracking-tail"><?= htmlspecialchars($batch['batch_name']) ?>-<?= htmlspecialchars($tail) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    (function adjustTitleSizes() {
        const titles = document.querySelectorAll('.label-title');
        titles.forEach(title => {
            let size = 40;
            const minSize = 18;
            title.style.fontSize = `${size}pt`;

            while (title.scrollWidth > title.clientWidth && size > minSize) {
                size -= 1;
                title.style.fontSize = `${size}pt`;
            }
        });
    })();
</script>
</body>
</html>
