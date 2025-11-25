<?php
/**
 * MRS 物料收发管理系统 - 后台API: 库存列表查询
 * 文件路径: app/mrs/api/backend_inventory_list.php
 * 说明: 获取所有有库存记录的SKU列表（包括库存为0的）
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 需要登录
require_login();

try {
    // 获取筛选参数
    $search = $_GET['search'] ?? '';
    $categoryId = $_GET['category_id'] ?? '';

    // 获取数据库连接
    $pdo = get_db_connection();

    // 构建查询 - 获取所有有入库记录的SKU（排除下架状态的SKU）
    $sql = "SELECT DISTINCT
                s.sku_id,
                s.sku_name,
                s.brand_name,
                s.category_id,
                c.category_name,
                s.standard_unit,
                s.case_unit_name,
                s.case_to_standard_qty,
                s.status
            FROM mrs_sku s
            LEFT JOIN mrs_category c ON s.category_id = c.category_id
            INNER JOIN mrs_batch_confirmed_item ci ON s.sku_id = ci.sku_id
            WHERE s.status = 'active'";

    $params = [];

    // 添加搜索条件
    if (!empty($search)) {
        $sql .= " AND (s.sku_name LIKE :search OR s.brand_name LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    // 添加品类筛选
    if (!empty($categoryId)) {
        $sql .= " AND s.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    $sql .= " ORDER BY s.sku_name ASC";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $skus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 为每个SKU计算库存
    $inventoryList = [];
    foreach ($skus as $sku) {
        $skuId = $sku['sku_id'];

        // 1. 入库总量
        $inboundSql = "SELECT COALESCE(SUM(total_standard_qty), 0) as total
                       FROM mrs_batch_confirmed_item
                       WHERE sku_id = :sku_id";
        $inStmt = $pdo->prepare($inboundSql);
        $inStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $inStmt->execute();
        $totalInbound = (int)$inStmt->fetchColumn();

        // 2. 出库总量
        $outboundSql = "SELECT COALESCE(SUM(i.total_standard_qty), 0) as total
                        FROM mrs_outbound_order_item i
                        JOIN mrs_outbound_order o ON i.outbound_order_id = o.outbound_order_id
                        WHERE i.sku_id = :sku_id AND o.status = 'confirmed'";
        $outStmt = $pdo->prepare($outboundSql);
        $outStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $outStmt->execute();
        $totalOutbound = (int)$outStmt->fetchColumn();

        // 3. 调整总量
        $adjustmentSql = "SELECT COALESCE(SUM(delta_qty), 0) as total
                          FROM mrs_inventory_adjustment
                          WHERE sku_id = :sku_id";
        $adjStmt = $pdo->prepare($adjustmentSql);
        $adjStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $adjStmt->execute();
        $totalAdjustment = floatval($adjStmt->fetchColumn());

        // 4. 当前库存
        $currentInventory = $totalInbound - $totalOutbound + $totalAdjustment;

        // 5. 格式化显示
        $caseSpec = floatval($sku['case_to_standard_qty'] ?? 1);
        $unit = $sku['standard_unit'];
        $caseUnit = $sku['case_unit_name'] ?? 'Box';

        if ($caseSpec > 1 && $currentInventory > 0) {
            $cases = floor($currentInventory / $caseSpec);
            $singles = $currentInventory % $caseSpec;
            $display = "{$cases}{$caseUnit} {$singles}{$unit}";
            if ($cases == 0) $display = "{$singles}{$unit}";
            if ($singles == 0) $display = "{$cases}{$caseUnit}";
        } else {
            $display = "{$currentInventory}{$unit}";
        }

        $inventoryList[] = [
            'sku_id' => $skuId,
            'sku_name' => $sku['sku_name'],
            'brand_name' => $sku['brand_name'],
            'category_id' => $sku['category_id'],
            'category_name' => $sku['category_name'] ?? '-',
            'standard_unit' => $unit,
            'case_unit_name' => $caseUnit,
            'case_to_standard_qty' => $caseSpec,
            'total_inbound' => $totalInbound,
            'total_outbound' => $totalOutbound,
            'total_adjustment' => $totalAdjustment,
            'current_inventory' => $currentInventory,
            'display_text' => $display,
            'status' => $sku['status']
        ];
    }

    mrs_log("查询库存列表成功, 记录数: " . count($inventoryList), 'INFO');

    json_response(true, ['inventory' => $inventoryList]);

} catch (PDOException $e) {
    mrs_log('查询库存列表失败: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('查询库存列表异常: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误: ' . $e->getMessage());
}
