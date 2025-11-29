<?php
/**
 * API: Get Recent Operations with Type Filter and Dedup
 * 文件路径: app/express/actions/get_recent_operations_api.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batch_id = $_GET['batch_id'] ?? 0;
$operation_type = $_GET['operation_type'] ?? null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if (empty($batch_id)) {
    express_json_response(false, null, '批次ID不能为空');
}

if (!empty($operation_type) && !in_array($operation_type, ['verify', 'count', 'adjust'])) {
    express_json_response(false, null, '无效的操作类型');
}

$records = express_get_recent_operations($pdo, $batch_id, $operation_type, $limit);
express_json_response(true, $records);
