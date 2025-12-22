<?php
/**
 * MRS Count API - Create Session
 * 文件路径: app/mrs/api/count_create_session.php
 * 说明: 创建清点任务API
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

header('Content-Type: application/json; charset=utf-8');

// 加载清点业务逻辑库
require_once MRS_LIB_PATH . '/count_lib.php';

// 获取POST数据
$session_name = $_POST['session_name'] ?? null;
$created_by = $_POST['created_by'] ?? null;
$remark = $_POST['remark'] ?? null;

// 验证必填字段
if (empty($session_name)) {
    echo json_encode([
        'success' => false,
        'message' => '请输入清点任务名称'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 创建清点任务
$result = mrs_count_create_session($pdo, $session_name, $created_by, $remark);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'session_id' => $result['session_id'],
        'message' => '清点任务创建成功'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['error'] ?? '创建失败'
    ], JSON_UNESCAPED_UNICODE);
}
