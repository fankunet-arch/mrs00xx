<?php
/**
 * MRS 物料收发管理系统 - API: 产品名称搜索
 * 文件路径: app/mrs/api/product_search_api.php
 * 说明: 支持产品名称的模糊搜索，自动去重
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 获取搜索关键词
$keyword = trim($_GET['keyword'] ?? '');

if (empty($keyword)) {
    json_response(false, [], '请输入搜索关键词');
    exit;
}

try {
    // 搜索产品名称（去重，只返回不同的产品名称）
    $search_pattern = '%' . $keyword . '%';

    $sql = "SELECT DISTINCT
                content_note as product_name,
                COUNT(*) as box_count,
                SUM(quantity) as total_quantity
            FROM mrs_package_ledger
            WHERE status = 'in_stock'
            AND content_note LIKE :keyword
            GROUP BY content_note
            ORDER BY box_count DESC
            LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['keyword' => $search_pattern]);
    $results = $stmt->fetchAll();

    json_response(true, $results, '搜索成功');
} catch (PDOException $e) {
    mrs_log('Product search API error: ' . $e->getMessage(), 'ERROR');
    json_response(false, [], '搜索失败');
}
