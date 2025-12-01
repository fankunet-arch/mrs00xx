<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>MRS 管理系统 - <?php echo htmlspecialchars($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            <div class="card dashboard-hero">
                <div class="hero-text">
                    <div class="eyebrow">仪表盘</div>
                    <h2>欢迎回来，<?php echo htmlspecialchars($_SESSION['user_display_name'] ?? '同事'); ?></h2>
                    <p class="muted">快速了解库存、入库和出库的整体趋势，并跳转到常用操作。</p>
                    <div class="hero-tags">
                        <span class="pill">今日：<?php echo date('Y-m-d'); ?></span>
                        <span class="pill info">近7天入库：<?php echo format_number($recent_movements['inbound_7d'] ?? 0); ?></span>
                        <span class="pill secondary">近7天出库：<?php echo format_number(abs($recent_movements['outbound_7d'] ?? 0)); ?></span>
                        <span class="pill muted">调整：<?php echo format_number($recent_movements['adjustment_7d'] ?? 0); ?></span>
                    </div>
                </div>
                <div class="hero-actions">
                    <a class="primary" href="/mrs/be/index.php?action=batch_list">📦 入库批次</a>
                    <a class="secondary" href="/mrs/be/index.php?action=outbound_create">🚚 新建出库</a>
                    <a class="text" href="/mrs/be/index.php?action=inventory_list">📊 查看库存</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($metrics['sku_count'] ?? 0); ?></div>
                    <div class="stat-label">物料档案</div>
                    <p class="stat-desc">覆盖 <?php echo number_format($metrics['category_count'] ?? 0); ?> 个品类</p>
                </div>
                <div class="stat-card stat-verified">
                    <div class="stat-number"><?php echo number_format($metrics['open_batches'] ?? 0); ?></div>
                    <div class="stat-label">待处理入库批次</div>
                    <p class="stat-desc">批次状态非已过账</p>
                </div>
                <div class="stat-card stat-counted">
                    <div class="stat-number"><?php echo number_format($metrics['outbound_week'] ?? 0); ?></div>
                    <div class="stat-label">近7天出库单</div>
                    <p class="stat-desc">按出库日期统计</p>
                </div>
                <div class="stat-card stat-adjusted">
                    <div class="stat-number"><?php echo number_format($metrics['low_stock'] ?? 0); ?></div>
                    <div class="stat-label">低库存SKU</div>
                    <p class="stat-desc">库存低于安全库存的SKU数量</p>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header card-header-clean">
                        <div>
                            <h3>最近入库批次</h3>
                            <div class="card-subtitle">按日期倒序展示最新 5 个批次</div>
                        </div>
                        <a class="text" href="/mrs/be/index.php?action=batch_list">查看全部</a>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>批次编号</th>
                                    <th>日期</th>
                                    <th>地点/门店</th>
                                    <th>状态</th>
                                    <th>备注</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_batches)): ?>
                                    <?php foreach ($recent_batches as $batch): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($batch['batch_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($batch['batch_date']); ?></td>
                                            <td><?php echo htmlspecialchars($batch['location_name'] ?? '-'); ?></td>
                                            <td><span class="status-badge status-<?php echo htmlspecialchars($batch['batch_status']); ?>"><?php echo htmlspecialchars($batch['batch_status'] ?? ''); ?></span></td>
                                            <td class="muted"><?php echo htmlspecialchars($batch['remark'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center muted">暂无批次记录</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-header-clean">
                        <div>
                            <h3>库存提醒</h3>
                            <div class="card-subtitle">展示库存最低的 5 个 SKU</div>
                        </div>
                        <a class="text" href="/mrs/be/index.php?action=inventory_list">库存列表</a>
                    </div>
                    <?php if (!empty($inventory_alerts)): ?>
                        <ul class="list">
                            <?php foreach ($inventory_alerts as $item): ?>
                                <li class="list-item">
                                    <div>
                                        <div class="list-title"><?php echo htmlspecialchars($item['sku_name']); ?></div>
                                        <div class="muted">品类：<?php echo htmlspecialchars($item['category_name'] ?? '未分类'); ?></div>
                                    </div>
                                    <div class="list-meta <?php echo ($item['current_qty'] ?? 0) <= 0 ? 'danger' : 'warning'; ?>">
                                        <?php echo format_number($item['current_qty'] ?? 0) . ($item['standard_unit'] ? ' ' . htmlspecialchars($item['standard_unit']) : ''); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted">暂无库存预警。</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header card-header-clean">
                        <div>
                            <h3>最近出库</h3>
                            <div class="card-subtitle">最新 5 条出库记录</div>
                        </div>
                        <a class="text" href="/mrs/be/index.php?action=outbound_list">管理出库</a>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>出库单号</th>
                                    <th>出库日期</th>
                                    <th>目的地/门店</th>
                                    <th>状态</th>
                                    <th>备注</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_outbounds)): ?>
                                    <?php foreach ($recent_outbounds as $outbound): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($outbound['outbound_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($outbound['outbound_date']); ?></td>
                                            <td><?php echo htmlspecialchars($outbound['location_name'] ?? '-'); ?></td>
                                            <td><span class="status-badge status-<?php echo htmlspecialchars($outbound['status'] ?? ''); ?>"><?php echo htmlspecialchars($outbound['status'] ?? ''); ?></span></td>
                                            <td class="muted"><?php echo htmlspecialchars($outbound['remark'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center muted">暂无出库记录</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-header-clean">
                        <div>
                            <h3>快捷入口</h3>
                            <div class="card-subtitle">常用操作快速导航</div>
                        </div>
                    </div>
                    <div class="quick-links">
                        <a href="/mrs/be/index.php?action=batch_create" class="quick-link">
                            <div class="quick-icon">➕</div>
                            <div>
                                <div class="quick-title">新建入库批次</div>
                                <div class="muted">录入新的到货批次信息</div>
                            </div>
                        </a>
                        <a href="/mrs/be/index.php?action=sku_list" class="quick-link">
                            <div class="quick-icon">📚</div>
                            <div>
                                <div class="quick-title">物料档案</div>
                                <div class="muted">维护 SKU 信息与规格</div>
                            </div>
                        </a>
                        <a href="/mrs/be/index.php?action=reports" class="quick-link">
                            <div class="quick-icon">📈</div>
                            <div>
                                <div class="quick-title">数据报表</div>
                                <div class="muted">查看收发汇总与趋势</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
