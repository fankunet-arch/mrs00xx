<?php
/**
 * MRS Count API - Finish Session
 * 文件路径: app/mrs/api/count_finish_session.php
 * 说明: 完成清点任务并生成报告API
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

header('Content-Type: application/json; charset=utf-8');

// 加载清点业务逻辑库
require_once MRS_LIB_PATH . '/count_lib.php';

// 获取参数
$session_id = $_POST['session_id'] ?? null;

if (empty($session_id)) {
    echo json_encode([
        'success' => false,
        'message' => '缺少必填参数'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 完成清点任务
$result = mrs_count_finish_session($pdo, $session_id);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'report' => $result['report'],
        'message' => '清点任务已完成'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['error'] ?? '完成失败'
    ], JSON_UNESCAPED_UNICODE);
}
