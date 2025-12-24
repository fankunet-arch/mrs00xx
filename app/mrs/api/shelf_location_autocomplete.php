<?php
/**
 * MRS API - 货架位置自动补全
 * 文件路径: app/mrs/api/shelf_location_autocomplete.php
 * 功能: 为货架位置输入提供自动完成建议
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

// 获取并处理关键词
$keyword = trim($_GET['keyword'] ?? '');

// 关键词为空时返回常用位置
if ($keyword === '') {
    try {
        // 返回最常用的10个位置
        $sql = "SELECT DISTINCT location_full_name
                FROM mrs_shelf_locations
                WHERE is_active = 1
                ORDER BY sort_order ASC, current_usage DESC
                LIMIT 10";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

        json_response(true, $results, 'Success');
    } catch (PDOException $e) {
        if (function_exists('mrs_log')) {
            mrs_log('Shelf location autocomplete error: ' . $e->getMessage(), 'ERROR');
        }
        json_response(false, [], 'Database error');
    }
    exit;
}

try {
    // 查询逻辑：
    // 1. 从 mrs_shelf_locations 表中查找匹配的位置
    // 2. 同时从实际使用的位置中查找(mrs_package_ledger)
    // 3. 合并去重,按使用频率排序

    // 从配置表查找
    $sql1 = "SELECT location_full_name, current_usage
             FROM mrs_shelf_locations
             WHERE is_active = 1
             AND location_full_name LIKE :keyword
             ORDER BY sort_order ASC, current_usage DESC
             LIMIT 10";

    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute(['keyword' => '%' . $keyword . '%']);
    $config_results = $stmt1->fetchAll(PDO::FETCH_COLUMN);

    // 从实际使用中查找(补充配置表中没有的位置)
    $sql2 = "SELECT DISTINCT warehouse_location, COUNT(*) as usage_count
             FROM mrs_package_ledger
             WHERE warehouse_location LIKE :keyword
             AND warehouse_location IS NOT NULL
             AND warehouse_location != ''
             AND status = 'in_stock'
             GROUP BY warehouse_location
             ORDER BY usage_count DESC
             LIMIT 10";

    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute(['keyword' => '%' . $keyword . '%']);
    $actual_results = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    // 合并结果并去重(配置表优先)
    $merged = array_unique(array_merge($config_results, $actual_results));

    // 限制返回数量
    $results = array_slice($merged, 0, 10);

    // 返回标准 JSON 响应
    json_response(true, array_values($results), 'Success');

} catch (PDOException $e) {
    // 记录错误日志
    if (function_exists('mrs_log')) {
        mrs_log('Shelf location autocomplete error: ' . $e->getMessage(), 'ERROR');
    }
    json_response(false, [], 'Database error');
}
