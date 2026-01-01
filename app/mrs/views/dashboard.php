<?php
/**
 * MRS 系统 - 控制台视图
 * 文件路径: app/mrs/views/dashboard.php
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

$batch_status_labels = [
    'draft' => '草稿',
    'receiving' => '收货中',
    'pending_merge' => '待合并',
    'confirmed' => '已确认',
    'posted' => '已入库',
];

$outbound_type_labels = [
    1 => '领料',
    2 => '调拨',
    3 => '退货',
    4 => '报废',
];

$outbound_status_labels = [
    'draft' => '草稿',
    'confirmed' => '已确认',
    'cancelled' => '已取消',
];

$page_title = '控制台';
?>
<?php include MRS_VIEW_PATH . '/shared/header.php'; ?>
    <div class="layout">
        <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>
        <main class="content">
            <div class="card">
                <div class="flex-between-start">
                    <div>
                        <h3>欢迎使用 MRS</h3>
                        <p class="muted mt-8">快速查看当前库存健康度、最近业务动态与常用操作。</p>
                        <p class="muted">所有功能均已迁移至新的 MPA 模式，如有需要可从左侧导航进入对应模块。</p>
                    </div>
                    <div class="muted text-right">
                        <div>今天（西班牙时间）：<?php echo htmlspecialchars($current_local_date ?? date('Y-m-d')); ?></div>
                        <div>上次刷新（西班牙时间）：<?php echo htmlspecialchars($last_refresh_time ?? date('H:i')); ?></div>
                    </div>
                </div>

                <div class="stats-grid mt-18">
                    <div class="stat-card stat-verified">
                        <div class="stat-number"><?php echo number_format($stats['sku_count']); ?></div>
                        <div class="stat-label">物料 SKU</div>
                    </div>
                    <div class="stat-card stat-counted">
                        <div class="stat-number"><?php echo number_format($stats['category_count']); ?></div>
                        <div class="stat-label">品类数量</div>
                    </div>
                    <div class="stat-card stat-adjusted">
                        <div class="stat-number"><?php echo number_format($stats['batch_count']); ?></div>
                        <div class="stat-label">入库批次</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['outbound_count']); ?></div>
                        <div class="stat-label">出库单</div>
                    </div>
                    <div class="stat-card stat-counted">
                        <div class="stat-number"><?php echo number_format($stats['inventory_records']); ?></div>
                        <div class="stat-label">在库记录</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex-between">
                    <div>
                        <h3>快捷操作</h3>
                        <p class="muted">常用入口，方便快速创建或查看业务单据。</p>
                    </div>
                </div>
                <div class="flex-wrap-gap mt-12">
                    <a class="primary" href="/mrs/be/index.php?action=quick_receipt" target="_blank" rel="noopener noreferrer">快速收货</a>
                    <a class="primary" href="/mrs/be/index.php?action=batch_create">创建批次</a>
                    <a class="secondary" href="/mrs/be/index.php?action=batch_list">查看批次</a>
                    <a class="primary" href="/mrs/be/index.php?action=outbound_create">创建出库单</a>
                    <a class="secondary" href="/mrs/be/index.php?action=outbound_list">出库列表</a>
                    <a class="secondary" href="/mrs/be/index.php?action=inventory_list">库存概览</a>
                    <a class="text" href="/mrs/be/index.php?action=reports">数据报表</a>
                </div>
            </div>

            <div class="card">
                <div class="flex-between-start">
                    <div>
                        <h3>最新动态</h3>
                        <p class="muted">最近的入库批次与出库单，便于追踪执行情况。</p>
                    </div>
                </div>
                <div class="grid-responsive mt-12">
                    <div class="table-responsive">
                        <h4 class="mb-8">最近入库批次</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>批次号</th>
                                    <th>日期</th>
                                    <th>状态</th>
                                    <th>地点</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_batches)): ?>
                                    <?php foreach ($recent_batches as $batch): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($batch['batch_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($batch['batch_date']))); ?></td>
                                            <td><?php echo htmlspecialchars($batch_status_labels[$batch['batch_status']] ?? ($batch['batch_status'] ?? '-')); ?></td>
                                            <td><?php echo htmlspecialchars($batch['location_name'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center muted">暂无批次记录</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive">
                        <h4 class="mb-8">最近出库单</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>出库单号</th>
                                    <th>日期</th>
                                    <th>类型</th>
                                    <th>状态</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_outbounds)): ?>
                                    <?php foreach ($recent_outbounds as $outbound): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($outbound['outbound_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($outbound['outbound_date']))); ?></td>
                                            <td><?php echo htmlspecialchars($outbound_type_labels[(int) $outbound['outbound_type']] ?? ($outbound['outbound_type'] ?? '-')); ?></td>
                                            <td><?php echo htmlspecialchars($outbound_status_labels[$outbound['status']] ?? ($outbound['status'] ?? '-')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center muted">暂无出库记录</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex-between-start">
                    <div>
                        <h3>低库存提醒</h3>
                        <p class="muted">按库存数量从低到高排序的物料，便于及时补货。</p>
                    </div>
                </div>
                <div class="table-responsive mt-12">
                    <table>
                        <thead>
                            <tr>
                                <th>物料</th>
                                <th>品类</th>
                                <th>品牌</th>
                                <th>当前库存</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($low_inventory)): ?>
                                <?php foreach ($low_inventory as $item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['sku_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['brand_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars(format_number($item['current_qty'])) . ' ' . htmlspecialchars($item['standard_unit'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center muted">暂无库存数据</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
<?php include MRS_VIEW_PATH . '/shared/footer.php'; ?>
