<?php
/**
 * MRS Count API - Search Box
 * 文件路径: app/mrs/api/count_search_box.php
 * 说明: 搜索箱号API
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

header('Content-Type: application/json; charset=utf-8');

// 加载清点业务逻辑库
require_once MRS_LIB_PATH . '/count_lib.php';

// 获取搜索关键词
$box_number = $_GET['box_number'] ?? null;

if (empty($box_number)) {
    echo json_encode([
        'success' => false,
        'message' => '请输入箱号'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 搜索箱号
$results = mrs_count_search_box($pdo, $box_number);

if (empty($results)) {
    echo json_encode([
        'success' => false,
        'found' => false,
        'message' => '系统中未找到此箱号'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => true,
        'found' => true,
        'data' => $results
    ], JSON_UNESCAPED_UNICODE);
}
