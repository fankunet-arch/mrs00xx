<?php
/**
 * MRS 物料收发管理系统 - API: 产品名称搜索
 * 文件路径: app/mrs/api/product_search_api.php
 * 说明: 按产品明细（mrs_package_items）搜索，每个独立产品名称单独展示
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
    // 搜索产品明细表中的独立产品名称
    $search_pattern = '%' . $keyword . '%';

    $sql = "SELECT DISTINCT
                i.product_name,
                COUNT(DISTINCT l.ledger_id) as box_count,
                COALESCE(SUM(i.quantity), 0) as total_quantity
            FROM mrs_package_items i
            INNER JOIN mrs_package_ledger l ON i.ledger_id = l.ledger_id
            WHERE l.status = 'in_stock'
            AND i.product_name LIKE :keyword
            AND i.product_name IS NOT NULL
            AND i.product_name != ''
            GROUP BY i.product_name
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
