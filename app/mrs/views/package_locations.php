<?php
/**
 * Package Locations Management Page
 * 文件路径: app/mrs/views/package_locations.php
 * 说明: 货架位置管理页面
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>货架位置管理 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
    <link rel="stylesheet" href="/mrs/ap/css/package_locations.css">
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>📦 货架位置管理</h1>
            <p>管理箱子的货架位置信息</p>
        </div>

        <div class="card">
            <!-- 搜索过滤 -->
            <div class="filters">
                <input type="text" id="filter-box-number" placeholder="箱号" />
                <input type="text" id="filter-location" placeholder="货架位置 (如: 01-02-03)" />
                <input type="text" id="filter-batch" placeholder="批次名称" />
                <select id="filter-status">
                    <option value="">全部状态</option>
                    <option value="in_stock">在库</option>
                    <option value="shipped">已出库</option>
                </select>
                <button onclick="loadPackageLocations(1)">🔍 搜索</button>
                <button class="secondary" onclick="resetFilters()">重置</button>
            </div>

            <!-- 操作按钮 -->
            <div class="action-buttons">
                <button class="primary" onclick="batchUpdateLocations()">📝 批量修改位置</button>
                <button class="secondary" onclick="exportData()">📊 导出数据</button>
            </div>

            <!-- 数据表格 -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-checkbox">
                                <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)" />
                            </th>
                            <th>箱号</th>
                            <th>批次名称</th>
                            <th>快递单号</th>
                            <th>货架位置</th>
                            <th>内容备注</th>
                            <th>数量</th>
                            <th>状态</th>
                            <th>入库时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="locations-tbody">
                        <tr>
                            <td colspan="10" class="empty-state">
                                加载中...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 分页 -->
            <div class="pagination" id="pagination-container">
                <!-- 动态生成分页 -->
            </div>
        </div>
    </div>

    <!-- 单个修改位置模态框 -->
    <div class="modal-overlay" id="modal-update-single">
        <div class="modal">
            <div class="modal-header">
                <h3>修改箱子位置</h3>
                <button class="modal-close" onclick="closeModal('modal-update-single')">&times;</button>
            </div>
            <form id="form-update-single" onsubmit="submitSingleUpdate(event)">
                <input type="hidden" id="update-ledger-id" />

                <div class="form-group">
                    <label>箱号</label>
                    <input type="text" id="update-box-number" disabled />
                </div>

                <div class="form-group">
                    <label>快递尾号</label>
                    <input type="text" id="update-tracking-tail" disabled class="disabled-muted" />
                </div>

                <div class="form-group">
                    <label>物品内容</label>
                    <input type="text" id="update-content-note" disabled class="disabled-muted" />
                </div>

                <div class="form-group">
                    <label>新位置</label>
                    <small>格式: 排号-架号-层号 (每段2位数字，全部留空即清除位置)</small>
                    <div class="shelf-inputs">
                        <input type="text" id="update-row" class="shelf-segment" placeholder="排" maxlength="2" autocomplete="off" />
                        <span>-</span>
                        <input type="text" id="update-rack" class="shelf-segment" placeholder="架" maxlength="2" autocomplete="off" />
                        <span>-</span>
                        <input type="text" id="update-level" class="shelf-segment" placeholder="层" maxlength="2" autocomplete="off" />
                        <button type="button" onclick="clearLocationInputs('update')" class="btn-clear-location">清空</button>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="closeModal('modal-update-single')">取消</button>
                    <button type="submit">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 批量修改位置模态框 -->
    <div class="modal-overlay" id="modal-batch-update">
        <div class="modal">
            <div class="modal-header">
                <h3>批量修改位置</h3>
                <button class="modal-close" onclick="closeModal('modal-batch-update')">&times;</button>
            </div>
            <form id="form-batch-update" onsubmit="submitBatchUpdate(event)">
                <div class="form-group">
                    <label>已选择 <span id="selected-count">0</span> 个箱子</label>
                </div>

                <div class="form-group">
                    <label>新位置</label>
                    <small>格式: 排号-架号-层号 (每段2位数字，全部留空即清除位置)</small>
                    <div class="shelf-inputs">
                        <input type="text" id="batch-row" class="shelf-segment" placeholder="排" maxlength="2" autocomplete="off" />
                        <span>-</span>
                        <input type="text" id="batch-rack" class="shelf-segment" placeholder="架" maxlength="2" autocomplete="off" />
                        <span>-</span>
                        <input type="text" id="batch-level" class="shelf-segment" placeholder="层" maxlength="2" autocomplete="off" />
                        <button type="button" onclick="clearLocationInputs('batch')" class="btn-clear-location">清空</button>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="closeModal('modal-batch-update')">取消</button>
                    <button type="submit">批量更新</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/mrs/ap/js/package_locations.js"></script>
</body>
</html>
