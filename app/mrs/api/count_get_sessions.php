<?php
/**
 * MRS Count API - Get Sessions
 * 文件路径: app/mrs/api/count_get_sessions.php
 * 说明: 获取清点任务列表API
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

header('Content-Type: application/json; charset=utf-8');

// 加载清点业务逻辑库
require_once MRS_LIB_PATH . '/count_lib.php';

// 获取参数
$status = $_GET['status'] ?? null;
$limit = (int)($_GET['limit'] ?? 20);

// 获取清点任务列表
$sessions = mrs_count_get_sessions($pdo, $status, $limit);

echo json_encode([
    'success' => true,
    'data' => $sessions
], JSON_UNESCAPED_UNICODE);
