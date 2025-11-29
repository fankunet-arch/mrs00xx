<?php
/**
 * API: 更新批次
 * 文件路径: app/express/api/batch_update.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = express_get_json_input();

$batch_id = isset($input['batch_id']) ? intval($input['batch_id']) : 0;
$batch_name = trim($input['batch_name'] ?? '');
$status = $input['status'] ?? 'active';
$notes = $input['notes'] ?? null;

if ($batch_id <= 0 || empty($batch_name)) {
    express_json_response(false, null, '批次ID或名称不能为空');
}

if (!in_array($status, ['active', 'closed'], true)) {
    express_json_response(false, null, '状态值不合法');
}

$result = express_update_batch($pdo, $batch_id, $batch_name, $status, $notes);

express_json_response($result['success'], null, $result['message']);
