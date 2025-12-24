<?php
/**
 * MRS API Endpoint Router
 * 文件路径: app/mrs/actions/api.php
 * 说明: 处理 action=api&endpoint=xxx 格式的API调用
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取endpoint参数
$endpoint = $_GET['endpoint'] ?? '';
$endpoint = basename($endpoint); // 防止路径遍历

if (empty($endpoint)) {
    mrs_json_response(false, null, 'Missing endpoint parameter');
    exit;
}

// 构建API文件路径
$api_file = MRS_API_PATH . '/' . $endpoint . '.php';

// 检查API文件是否存在
if (file_exists($api_file)) {
    require_once $api_file;
} else {
    mrs_log("API endpoint not found: {$endpoint}", 'WARNING');
    mrs_json_response(false, null, "API endpoint '{$endpoint}' not found");
}
