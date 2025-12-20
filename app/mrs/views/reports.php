<?php
/**
 * Reports Page
 * 文件路径: app/mrs/views/reports.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 默认查询当前月份
$month = $_GET['month'] ?? date('Y-m');

// 计算月份的开始和结束日期
$start_date = $month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// 获取月度统计（旧版本，用于汇总数字）
$summary = mrs_get_monthly_summary($pdo, $month);

// 使用统一视图获取入库和出库数据（整合两套系统）
$inbound_data = mrs_get_unified_inbound_report($pdo, $start_date, $end_date);
$outbound_data = mrs_get_unified_outbound_report($pdo, $start_date, $end_date);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>统计报表 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>统计报表</h1>
        </div>

        <div class="content-wrapper">
            <!-- 月份选择 -->
            <div class="form-group" style="max-width: 300px;">
                <label for="month_select">选择月份</label>
                <input type="month" id="month_select" class="form-control"
                       value="<?= htmlspecialchars($month) ?>"
                       onchange="window.location.href='/mrs/ap/index.php?action=reports&month=' + this.value">
            </div>

            <!-- 汇总统计 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $summary['inbound_total'] ?? 0 ?></div>
                    <div class="stat-label">入库总数 (箱)</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-number"><?= $summary['outbound_total'] ?? 0 ?></div>
                    <div class="stat-label">出库总数 (箱)</div>
                </div>
            </div>

            <!-- 入库明细 -->
            <h2 style="margin-top: 30px; margin-bottom: 15px;">入库明细（整合SKU系统+包裹台账）</h2>

            <?php if (empty($inbound_data)): ?>
                <div class="empty-state">
                    <div class="empty-state-text">本月暂无入库记录</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>物料名称</th>
                            <th class="text-center">入库数量</th>
                            <th class="text-center">来源</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inbound_data as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td class="text-center"><strong><?= $item['display_qty'] ?></strong></td>
                                <td class="text-center">
                                    <?php
                                    $sources = explode(',', $item['sources'] ?? '');
                                    $source_labels = [];
                                    if (in_array('sku_system', $sources)) $source_labels[] = 'SKU';
                                    if (in_array('package_system', $sources)) $source_labels[] = '台账';
                                    echo htmlspecialchars(implode('+', $source_labels));
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- 出库明细 -->
            <h2 style="margin-top: 40px; margin-bottom: 15px;">出库明细（整合SKU系统+包裹台账）</h2>

            <?php if (empty($outbound_data)): ?>
                <div class="empty-state">
                    <div class="empty-state-text">本月暂无出库记录</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>物料名称</th>
                            <th class="text-center">出库数量</th>
                            <th class="text-center">来源</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($outbound_data as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td class="text-center"><strong><?= $item['display_qty'] ?></strong></td>
                                <td class="text-center">
                                    <?php
                                    $sources = explode(',', $item['sources'] ?? '');
                                    $source_labels = [];
                                    if (in_array('sku_system', $sources)) $source_labels[] = 'SKU';
                                    if (in_array('package_system', $sources)) $source_labels[] = '台账';
                                    echo htmlspecialchars(implode('+', $source_labels));
                                    ?>
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
