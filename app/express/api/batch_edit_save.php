<?php
/**
 * API: Save Batch Edit
 * 文件路径: app/express/api/batch_edit_save.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = express_get_json_input();

$batch_id = isset($input['batch_id']) ? (int)$input['batch_id'] : 0;
$batch_name = trim($input['batch_name'] ?? '');
$status = $input['status'] ?? 'active';
$notes = $input['notes'] ?? null;

if ($batch_id <= 0) {
    express_json_response(false, null, '批次ID不能为空');
}

if (empty($batch_name)) {
    express_json_response(false, null, '批次名称不能为空');
}

$result = express_update_batch($pdo, $batch_id, $batch_name, $status, $notes);

if ($result['success']) {
    express_json_response(true, ['batch_id' => $batch_id], $result['message']);
}

express_json_response(false, null, $result['message'] ?? '批次更新失败');
