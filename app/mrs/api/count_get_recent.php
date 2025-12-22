<?php
/**
 * MRS Count API - Get Recent Records
 * 文件路径: app/mrs/api/count_get_recent.php
 * 说明: 获取最近清点记录API
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

header('Content-Type: application/json; charset=utf-8');

// 加载清点业务逻辑库
require_once MRS_LIB_PATH . '/count_lib.php';

// 获取参数
$session_id = $_GET['session_id'] ?? null;
$limit = (int)($_GET['limit'] ?? 20);

if (empty($session_id)) {
    echo json_encode([
        'success' => false,
        'message' => '缺少必填参数'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取最近清点记录
$records = mrs_count_get_recent_records($pdo, $session_id, $limit);

echo json_encode([
    'success' => true,
    'data' => $records
], JSON_UNESCAPED_UNICODE);
