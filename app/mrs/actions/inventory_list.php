<?php
// Action: inventory_list.php - 库存列表页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

// 获取筛选参数
$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';

try {
    $pdo = get_db_connection();

    // 获取品类列表供筛选
    $categories = $pdo->query("SELECT * FROM mrs_category ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

    // 构建库存查询（使用子查询优化）
    $where = ['1=1'];
    $params = [];

    if (!empty($search)) {
        $where[] = "(s.sku_name LIKE ? OR s.brand_name LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($category_id)) {
        $where[] = "s.category_id = ?";
        $params[] = $category_id;
    }

    $sql = "
        SELECT
            s.sku_id,
            s.sku_name,
            s.brand_name,
            s.standard_unit,
            s.case_unit_name,
            s.case_to_standard_qty,
            c.category_name,
            COALESCE(inbound.total_inbound, 0) as total_inbound,
            COALESCE(outbound.total_outbound, 0) as total_outbound,
            COALESCE(adjustment.total_adjustment, 0) as total_adjustment,
            (COALESCE(inbound.total_inbound, 0) - COALESCE(outbound.total_outbound, 0) + COALESCE(adjustment.total_adjustment, 0)) as current_inventory
        FROM mrs_sku s
        LEFT JOIN mrs_category c ON s.category_id = c.category_id
        LEFT JOIN (
            SELECT sku_id, SUM(total_standard_qty) as total_inbound
            FROM mrs_batch_confirmed_item
            GROUP BY sku_id
        ) inbound ON s.sku_id = inbound.sku_id
        LEFT JOIN (
            SELECT i.sku_id, SUM(i.total_standard_qty) as total_outbound
            FROM mrs_outbound_order_item i
            INNER JOIN mrs_outbound_order o ON i.outbound_order_id = o.outbound_order_id
            WHERE o.status = 'confirmed'
            GROUP BY i.sku_id
        ) outbound ON s.sku_id = outbound.sku_id
        LEFT JOIN (
            SELECT sku_id, SUM(delta_qty) as total_adjustment
            FROM mrs_inventory_adjustment
            GROUP BY sku_id
        ) adjustment ON s.sku_id = adjustment.sku_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.sku_name
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    mrs_log("Failed to load inventory: " . $e->getMessage(), 'ERROR');
    $inventory = [];
    $categories = [];
}

$page_title = "库存管理";
$action = 'inventory_list';

require_once MRS_VIEW_PATH . '/inventory_list.php';
