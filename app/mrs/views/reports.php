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
                <h2>数据报表</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label>报表类型</label>
                        <select id="report-type">
                            <option value="sku">SKU收货汇总</option>
                            <option value="category">品类收货汇总</option>
                            <option value="daily">每日收货统计</option>
                            <option value="sku_shipment">SKU出库汇总</option>
                            <option value="category_shipment">品类出库汇总</option>
                            <option value="daily_shipment">每日出库统计</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>开始日期</label>
                        <input type="date" id="date-start" value="<?php echo date('Y-m-01'); ?>">
                    </div>

                    <div class="form-group">
                        <label>结束日期</label>
                        <input type="date" id="date-end" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button class="primary" onclick="loadReport()">生成报表</button>
                    </div>
                </div>
            </div>

            <div class="card" id="report-container" style="display: none;">
                <div class="flex-between">
                    <h3 id="report-title">报表结果</h3>
                </div>

                <div id="report-content" class="mt-3">
                    <!-- 报表内容动态加载 -->
                </div>
            </div>
        </main>
    </div>

    <script>
    async function loadReport() {
        const type = document.getElementById('report-type').value;
        const dateStart = document.getElementById('date-start').value;
        const dateEnd = document.getElementById('date-end').value;

        if (!dateStart || !dateEnd) {
            alert('请选择日期范围');
            return;
        }

        try {
            const response = await fetch(`/mrs/be/index.php?action=backend_reports&type=${type}&date_start=${dateStart}&date_end=${dateEnd}`);
            const result = await response.json();

            if (result.success) {
                renderReport(result.data);
            } else {
                alert('加载失败：' + (result.message || '未知错误'));
            }
        } catch (error) {
            alert('网络错误：' + error.message);
        }
    }

    // 格式化数字，去除不必要的小数点
    function formatNumber(num) {
        if (num === null || num === undefined) return 0;
        const number = parseFloat(num);
        // 如果是整数，不显示小数点
        if (number === Math.floor(number)) {
            return number.toString();
        }
        // 如果有小数，去除尾部的0
        return number.toString().replace(/\.?0+$/, '');
    }

    function renderReport(data) {
        const container = document.getElementById('report-container');
        const content = document.getElementById('report-content');
        const title = document.getElementById('report-title');

        const typeNames = {
            'sku': 'SKU收货汇总',
            'category': '品类收货汇总',
            'daily': '每日收货统计',
            'sku_shipment': 'SKU出库汇总',
            'category_shipment': '品类出库汇总',
            'daily_shipment': '每日出库统计'
        };

        title.textContent = `${typeNames[data.type] || '报表'} (${data.date_start} 至 ${data.date_end})`;

        let html = '<div class="table-responsive"><table><thead><tr>';

        // 根据报表类型渲染不同的表头
        if (data.type === 'sku' || data.type === 'sku_shipment') {
            html += '<th>物料名称</th><th>品牌</th><th>品类</th><th>总数量</th><th>单位</th><th>单据数</th>';
        } else if (data.type === 'category' || data.type === 'category_shipment') {
            html += '<th>品类</th><th>SKU数量</th><th>总数量</th><th>单据数</th>';
        } else if (data.type === 'daily' || data.type === 'daily_shipment') {
            html += '<th>日期</th><th>单据数</th><th>状态</th><th>地点</th>';
        }

        html += '</tr></thead><tbody>';

        if (data.data && data.data.length > 0) {
            data.data.forEach(row => {
                html += '<tr>';
                if (data.type === 'sku' || data.type === 'sku_shipment') {
                    html += `<td><strong>${row.sku_name || '-'}</strong></td>`;
                    html += `<td>${row.brand_name || '-'}</td>`;
                    html += `<td>${row.category_name || '-'}</td>`;
                    html += `<td><strong>${formatNumber(row.total_qty)}</strong></td>`;
                    html += `<td>${row.standard_unit || '-'}</td>`;
                    html += `<td>${row.order_count || row.batch_count || 0}</td>`;
                } else if (data.type === 'category' || data.type === 'category_shipment') {
                    html += `<td><strong>${row.category_name || '-'}</strong></td>`;
                    html += `<td>${row.sku_count || 0}</td>`;
                    html += `<td><strong>${formatNumber(row.total_qty)}</strong></td>`;
                    html += `<td>${row.order_count || row.batch_count || 0}</td>`;
                } else if (data.type === 'daily' || data.type === 'daily_shipment') {
                    html += `<td>${row.date || '-'}</td>`;
                    html += `<td>${row.order_count || row.batch_count || 0}</td>`;
                    html += `<td>${row.status || row.batch_status || '-'}</td>`;
                    html += `<td>${row.location_name || '-'}</td>`;
                }
                html += '</tr>';
            });
        } else {
            const colspan = (data.type === 'sku' || data.type === 'sku_shipment') ? 6 : 4;
            html += `<tr><td colspan="${colspan}" class="text-center muted">暂无数据</td></tr>`;
        }

        html += '</tbody></table></div>';

        content.innerHTML = html;
        container.style.display = 'block';
    }
    </script>
</body>
</html>
