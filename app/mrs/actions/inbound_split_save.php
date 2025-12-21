<?php
/**
 * MRS Action: inbound_split_save.php
 * 拆分入库保存处理
 * 文件路径: app/mrs/actions/inbound_split_save.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 需要登录
mrs_require_login();

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, '无效的请求方法');
    exit;
}

// 读取 JSON 数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    json_response(false, null, '无效的 JSON 数据');
    exit;
}

// 验证必填字段
if (empty($data['batch_name']) || empty($data['packages']) || !is_array($data['packages'])) {
    json_response(false, null, '缺少必填字段或数据格式不正确');
    exit;
}

// 获取操作员信息
$operator = $_SESSION['user_display_name'] ?? $_SESSION['user_login'] ?? 'system';

// 执行拆分入库
$result = mrs_split_inbound_packages($pdo, $data['packages'], $operator);

if ($result['success']) {
    json_response(true, [
        'batch_id' => $result['batch_id'],
        'records_created' => $result['records_created'],
        'errors' => $result['errors'] ?? []
    ], '拆分入库成功');
} else {
    json_response(false, null, $result['message'] ?? '拆分入库失败');
}
