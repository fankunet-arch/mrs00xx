<?php
/**
 * MRS 物料收发管理系统 - API: 箱子搜索
 * 文件路径: app/mrs/api/box_search_api.php
 * 说明: 支持箱号、快递单号和物品名称的快速搜索
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
    // 搜索箱号、快递单号或物品名称
    $search_pattern = '%' . $keyword . '%';

    $sql = "SELECT
                ledger_id,
                batch_name,
                tracking_number,
                box_number,
                content_note,
                spec_info,
                quantity,
                expiry_date,
                warehouse_location,
                inbound_time
            FROM mrs_package_ledger
            WHERE status = 'in_stock'
            AND (
                box_number LIKE :keyword1
                OR tracking_number LIKE :keyword2
                OR content_note LIKE :keyword3
            )
            ORDER BY inbound_time DESC
            LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'keyword1' => $search_pattern,
        'keyword2' => $search_pattern,
        'keyword3' => $search_pattern
    ]);
    $results = $stmt->fetchAll();

    json_response(true, $results, '搜索成功');
} catch (PDOException $e) {
    mrs_log('Box search API error: ' . $e->getMessage(), 'ERROR');
    json_response(false, [], '搜索失败');
}
