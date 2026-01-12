<?php
/**
 * SKU Management Page
 * 文件路径: app/mrs/views/sku_manage.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取排序和筛选参数
$sort_by = $_GET['sort'] ?? 'sku_name_cn';
$sort_dir = $_GET['dir'] ?? 'asc';
$search_keyword = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_status = $_GET['status'] ?? '';

// 构建查询SQL
$sql = "SELECT
    s.sku_id,
    s.sku_code,
    s.sku_name_cn,
    s.sku_name_es,
    s.product_category,
    s.barcode,
    s.brand_name,
    s.spec_info,
    s.shelf_life_months,
    s.standard_unit,
    s.case_unit_name,
    s.case_to_standard_qty,
    s.supplier_country,
    s.status,
    s.created_at,
    s.updated_at,
    c.category_name
FROM mrs_sku s
LEFT JOIN mrs_category c ON s.category_id = c.category_id
WHERE 1=1";

$params = [];

// 搜索条件
if (!empty($search_keyword)) {
    $sql .= " AND (s.sku_name_cn LIKE :search
              OR s.sku_name_es LIKE :search
              OR s.sku_code LIKE :search
              OR s.barcode LIKE :search)";
    $params[':search'] = '%' . $search_keyword . '%';
}

// 产品类别筛选
if (!empty($filter_category)) {
    $sql .= " AND s.product_category = :category";
    $params[':category'] = $filter_category;
}

// 状态筛选
if (!empty($filter_status)) {
    $sql .= " AND s.status = :status";
    $params[':status'] = $filter_status;
}

// 排序
$allowed_sort_columns = [
    'sku_name_cn', 'sku_code', 'product_category', 'supplier_country',
    'status', 'created_at', 'updated_at'
];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'sku_name_cn';
}
$sort_dir = strtoupper($sort_dir) === 'DESC' ? 'DESC' : 'ASC';
$sql .= " ORDER BY $sort_by $sort_dir";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sku_list = $stmt->fetchAll();

// 产品类别映射
$category_map = [
    'packaging' => '包材',
    'raw_material' => '原物料',
    'semi_finished' => '半成品',
    'finished_product' => '成品'
];

// 供货商国家映射
$country_map = [
    'china' => '🇨🇳 中国',
    'spain' => '🇪🇸 西班牙'
];

// 辅助函数：生成排序链接
function get_sort_url($column, $current_sort, $current_dir, $extra_params = []) {
    $new_dir = 'asc';
    if ($column === $current_sort && $current_dir === 'asc') {
        $new_dir = 'desc';
    }
    $params = array_merge(['action' => 'sku_manage', 'sort' => $column, 'dir' => $new_dir], $extra_params);
    return '/mrs/ap/index.php?' . http_build_query($params);
}

// 辅助函数：生成排序图标
function get_sort_icon($column, $current_sort, $current_dir) {
    if ($column !== $current_sort) {
        return '<span style="color: #ccc;">⇅</span>';
    }
    return $current_dir === 'asc' ? '<span style="color: #007bff;">↑</span>' : '<span style="color: #007bff;">↓</span>';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKU管理 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
    <style>
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 6px;
        }

        .filter-input, .filter-select {
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.15);
        }

        .category-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .category-packaging { background: #e3f2fd; color: #1976d2; }
        .category-raw { background: #fff3e0; color: #f57c00; }
        .category-semi { background: #f3e5f5; color: #7b1fa2; }
        .category-finished { background: #e8f5e9; color: #388e3c; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }

        .action-buttons {
            display: flex;
            gap: 6px;
        }

        .btn-icon {
            padding: 6px 10px;
            font-size: 13px;
        }

        .data-table thead th a {
            display: inline-block;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.2s;
            color: inherit;
            text-decoration: none;
        }

        .data-table thead th a:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }

        .text-muted {
            color: #6c757d;
        }

        .info-chip {
            display: inline-block;
            padding: 2px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 12px;
            margin-right: 6px;
            color: #495057;
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>📦 SKU管理</h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=sku_edit" class="btn btn-primary">
                    ➕ 新增SKU
                </a>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- 筛选栏 -->
            <form class="filter-bar" method="GET" action="/mrs/ap/index.php">
                <input type="hidden" name="action" value="sku_manage">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">搜索关键词</label>
                        <input type="text" name="search" class="filter-input"
                               placeholder="输入SKU名称、编码、条码..."
                               value="<?= htmlspecialchars($search_keyword) ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">产品类别</label>
                        <select name="category" class="filter-select">
                            <option value="">全部</option>
                            <option value="packaging" <?= $filter_category === 'packaging' ? 'selected' : '' ?>>包材</option>
                            <option value="raw_material" <?= $filter_category === 'raw_material' ? 'selected' : '' ?>>原物料</option>
                            <option value="semi_finished" <?= $filter_category === 'semi_finished' ? 'selected' : '' ?>>半成品</option>
                            <option value="finished_product" <?= $filter_category === 'finished_product' ? 'selected' : '' ?>>成品</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">状态</label>
                        <select name="status" class="filter-select">
                            <option value="">全部</option>
                            <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>使用中</option>
                            <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>已停用</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">🔍 搜索</button>
                    </div>
                </div>
            </form>

            <!-- 统计信息 -->
            <div class="info-box" style="margin-bottom: 20px;">
                <strong>共找到 <?= count($sku_list) ?> 个SKU</strong>
            </div>

            <?php if (empty($sku_list)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-text">暂无SKU数据</div>
                    <a href="/mrs/ap/index.php?action=sku_edit" class="btn btn-primary">立即新增</a>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">SKU ID</th>
                            <th>
                                <a href="<?= get_sort_url('sku_name_cn', $sort_by, $sort_dir, ['search' => $search_keyword, 'category' => $filter_category, 'status' => $filter_status]) ?>">
                                    SKU名称 <?= get_sort_icon('sku_name_cn', $sort_by, $sort_dir) ?>
                                </a>
                            </th>
                            <th style="width: 120px;">
                                <a href="<?= get_sort_url('sku_code', $sort_by, $sort_dir, ['search' => $search_keyword, 'category' => $filter_category, 'status' => $filter_status]) ?>">
                                    SKU编码 <?= get_sort_icon('sku_code', $sort_by, $sort_dir) ?>
                                </a>
                            </th>
                            <th style="width: 100px;">产品类别</th>
                            <th style="width: 120px;">条码</th>
                            <th style="width: 100px;">规格</th>
                            <th style="width: 100px;">保质期</th>
                            <th style="width: 100px;">供货国家</th>
                            <th style="width: 80px;">
                                <a href="<?= get_sort_url('status', $sort_by, $sort_dir, ['search' => $search_keyword, 'category' => $filter_category, 'status' => $filter_status]) ?>">
                                    状态 <?= get_sort_icon('status', $sort_by, $sort_dir) ?>
                                </a>
                            </th>
                            <th style="width: 140px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sku_list as $sku): ?>
                            <tr>
                                <td><?= $sku['sku_id'] ?></td>
                                <td>
                                    <div style="font-weight: 600; margin-bottom: 4px;">
                                        <?= htmlspecialchars($sku['sku_name_cn'] ?: '-') ?>
                                    </div>
                                    <?php if (!empty($sku['sku_name_es'])): ?>
                                        <div style="font-size: 12px; color: #666;">
                                            🇪🇸 <?= htmlspecialchars($sku['sku_name_es']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="font-size: 12px; background: #f8f9fa; padding: 2px 6px; border-radius: 3px;">
                                        <?= htmlspecialchars($sku['sku_code'] ?: '-') ?>
                                    </code>
                                </td>
                                <td>
                                    <?php if (!empty($sku['product_category'])): ?>
                                        <?php
                                        $cat_class = 'category-packaging';
                                        if ($sku['product_category'] === 'raw_material') $cat_class = 'category-raw';
                                        elseif ($sku['product_category'] === 'semi_finished') $cat_class = 'category-semi';
                                        elseif ($sku['product_category'] === 'finished_product') $cat_class = 'category-finished';
                                        ?>
                                        <span class="category-badge <?= $cat_class ?>">
                                            <?= $category_map[$sku['product_category']] ?? $sku['product_category'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($sku['barcode'])): ?>
                                        <code style="font-size: 11px; background: #f8f9fa; padding: 2px 6px; border-radius: 3px;">
                                            <?= htmlspecialchars($sku['barcode']) ?>
                                        </code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($sku['case_to_standard_qty'])): ?>
                                        <span class="info-chip"><?= $sku['case_to_standard_qty'] ?> <?= htmlspecialchars($sku['standard_unit'] ?? '件') ?>/<?= htmlspecialchars($sku['case_unit_name'] ?? '箱') ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($sku['shelf_life_months'])): ?>
                                        <span class="info-chip"><?= $sku['shelf_life_months'] ?> 个月</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($sku['supplier_country'])): ?>
                                        <?= $country_map[$sku['supplier_country']] ?? $sku['supplier_country'] ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span id="status-badge-<?= $sku['sku_id'] ?>" class="status-badge <?= $sku['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                        <?= $sku['status'] === 'active' ? '✓ 使用中' : '✗ 已停用' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="/mrs/ap/index.php?action=sku_edit&sku_id=<?= $sku['sku_id'] ?>"
                                           class="btn btn-sm btn-primary btn-icon" title="编辑">
                                            ✏️ 编辑
                                        </a>
                                        <button id="status-btn-<?= $sku['sku_id'] ?>"
                                                onclick="toggleSkuStatus(<?= $sku['sku_id'] ?>, '<?= $sku['status'] ?>')"
                                                class="btn btn-sm btn-<?= $sku['status'] === 'active' ? 'warning' : 'success' ?> btn-icon"
                                                title="<?= $sku['status'] === 'active' ? '停用' : '启用' ?>">
                                            <?= $sku['status'] === 'active' ? '⏸️' : '▶️' ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script src="/mrs/ap/js/modal.js"></script>
    <script>
        async function toggleSkuStatus(skuId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const actionText = newStatus === 'active' ? '启用' : '停用';

            const confirmed = await showConfirm(
                `确定要${actionText}此SKU吗?`,
                `${actionText}SKU`,
                { type: 'warning', confirmText: '确认', cancelText: '取消' }
            );

            if (!confirmed) return;

            try {
                const response = await fetch('/mrs/ap/index.php?action=sku_toggle_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sku_id: skuId, status: newStatus })
                });

                const data = await response.json();

                if (data.success) {
                    await showAlert(`${actionText}成功`, '成功', 'success');
                    location.reload();
                } else {
                    await showAlert(`${actionText}失败: ` + data.message, '错误', 'error');
                }
            } catch (error) {
                await showAlert('网络错误: ' + error.message, '错误', 'error');
            }
        }
    </script>
</body>
</html>
