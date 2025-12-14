<?php
/**
 * Inventory List Page
 * 文件路径: app/mrs/views/inventory_list.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取库存汇总
$inventory = mrs_get_inventory_summary($pdo);
$total_boxes = array_sum(array_column($inventory, 'total_boxes'));
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>库存总览 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>库存总览</h1>
            <div class="header-actions">
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
                            <th>物料名称</th>
                            <th class="text-center">在库数量</th>
                            <th class="text-center">数量</th>
                            <th class="text-center">最近到期</th>
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
                                    <a href="/mrs/ap/index.php?action=inventory_detail&sku=<?= urlencode($item['sku_name']) ?>"
                                       class="btn btn-sm btn-primary">查看明细</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
