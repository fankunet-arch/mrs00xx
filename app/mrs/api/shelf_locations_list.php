<?php
/**
 * MRS API - 货架位置列表查询
 * 文件路径: app/mrs/api/shelf_locations_list.php
 * 功能: 查询货架位置配置列表,支持筛选和搜索
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

// 获取查询参数
$keyword = trim($_GET['keyword'] ?? '');
$zone = trim($_GET['zone'] ?? '');
$is_active = isset($_GET['is_active']) ? intval($_GET['is_active']) : null;
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

try {
    // 构建查询条件
    $where_conditions = ['1=1'];
    $params = [];

    // 关键词搜索
    if (!empty($keyword)) {
        $where_conditions[] = "(location_full_name LIKE :keyword OR shelf_code LIKE :keyword2)";
        $params['keyword'] = '%' . $keyword . '%';
        $params['keyword2'] = '%' . $keyword . '%';
    }

    // 区域筛选
    if (!empty($zone)) {
        $where_conditions[] = "zone = :zone";
        $params['zone'] = $zone;
    }

    // 状态筛选
    if ($is_active !== null) {
        $where_conditions[] = "is_active = :is_active";
        $params['is_active'] = $is_active;
    }

    $where_sql = implode(' AND ', $where_conditions);

    // 查询总数
    $count_sql = "SELECT COUNT(*) FROM mrs_shelf_locations WHERE $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetchColumn();

    // 查询列表(使用统计视图)
    $sql = "SELECT
                sl.location_id,
                sl.shelf_code,
                sl.shelf_name,
                sl.level_number,
                sl.location_full_name,
                sl.capacity,
                sl.current_usage,
                sl.zone,
                sl.is_active,
                sl.sort_order,
                sl.remark,
                sl.created_at,
                sl.updated_at,
                CASE
                    WHEN sl.capacity IS NOT NULL AND sl.capacity > 0
                    THEN ROUND((sl.current_usage / sl.capacity) * 100, 2)
                    ELSE NULL
                END as usage_rate
            FROM mrs_shelf_locations sl
            WHERE $where_sql
            ORDER BY sl.sort_order ASC, sl.shelf_code ASC, sl.level_number ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 返回结果
    json_response(true, [
        'locations' => $locations,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ], 'Success');

} catch (PDOException $e) {
    if (function_exists('mrs_log')) {
        mrs_log('Shelf locations list error: ' . $e->getMessage(), 'ERROR');
    }
    json_response(false, null, 'Database error');
}
