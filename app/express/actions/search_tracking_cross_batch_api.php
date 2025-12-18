<?php
/**
 * API: Cross-Batch Search Tracking Number
 * 文件路径: app/express/actions/search_tracking_cross_batch_api.php
 * 说明: 跨批次模糊搜索快递单号
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$keyword = $_GET['keyword'] ?? '';

if (empty($keyword)) {
    express_json_response(false, null, '请输入快递单号');
}

$results = express_search_tracking_cross_batch($pdo, $keyword, 20);
express_json_response(true, $results);
