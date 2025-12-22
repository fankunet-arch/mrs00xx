<?php
/**
 * MRS Count API - Autocomplete Box Search
 * 文件路径: app/mrs/api/count_autocomplete_box.php
 * 说明: 自动完成搜索箱号API（支持箱号、内容、SKU名称模糊搜索）
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

header('Content-Type: application/json; charset=utf-8');

// 加载清点业务逻辑库
require_once MRS_LIB_PATH . '/count_lib.php';

// 获取搜索关键词
$keyword = $_GET['keyword'] ?? '';

// 如果关键词少于1个字符，返回空结果
if (mb_strlen(trim($keyword)) < 1) {
    echo json_encode([
        'success' => true,
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 搜索箱号
$results = mrs_count_autocomplete_box($pdo, $keyword);

echo json_encode([
    'success' => true,
    'data' => $results
], JSON_UNESCAPED_UNICODE);
