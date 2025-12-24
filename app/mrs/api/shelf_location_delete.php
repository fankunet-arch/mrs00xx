<?php
/**
 * MRS API - 删除货架位置
 * 文件路径: app/mrs/api/shelf_location_delete.php
 * 功能: 删除货架位置配置
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, '非法请求方式');
    exit;
}

// 获取POST数据
$input = mrs_get_json_input();
if (!$input) {
    $input = $_POST;
}

$location_id = intval($input['location_id'] ?? 0);

// 验证必填字段
if ($location_id <= 0) {
    json_response(false, null, '无效的位置ID');
    exit;
}

try {
    $pdo->beginTransaction();

    // 检查位置是否存在
    $check_sql = "SELECT location_full_name, current_usage
                  FROM mrs_shelf_locations
                  WHERE location_id = :location_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute(['location_id' => $location_id]);
    $location = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        $pdo->rollBack();
        json_response(false, null, '货架位置不存在');
        exit;
    }

    // 检查是否有包裹正在使用该位置
    $usage_sql = "SELECT COUNT(*) FROM mrs_package_ledger
                  WHERE warehouse_location = :location_name
                  AND status = 'in_stock'";
    $usage_stmt = $pdo->prepare($usage_sql);
    $usage_stmt->execute(['location_name' => $location['location_full_name']]);
    $usage_count = $usage_stmt->fetchColumn();

    if ($usage_count > 0) {
        $pdo->rollBack();
        json_response(false, null, "该货架位置正在使用中,有 {$usage_count} 个包裹,无法删除");
        exit;
    }

    // 执行删除
    $delete_sql = "DELETE FROM mrs_shelf_locations WHERE location_id = :location_id";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute(['location_id' => $location_id]);

    $pdo->commit();
    json_response(true, null, '货架位置删除成功');

} catch (PDOException $e) {
    $pdo->rollBack();
    if (function_exists('mrs_log')) {
        mrs_log('Shelf location delete error: ' . $e->getMessage(), 'ERROR');
    }
    json_response(false, null, '删除失败: ' . $e->getMessage());
}
