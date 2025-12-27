<?php
/**
 * 保存批次编辑（批次编号不可修改，只能修改状态和备注）
 * 文件路径: app/express/api/batch_edit_save.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$batch_id = (int)($input['batch_id'] ?? 0);
$status = $input['status'] ?? 'active';
$notes = isset($input['notes']) ? trim($input['notes']) : null;

if ($batch_id <= 0) {
    express_json_response(false, null, '批次ID不能为空');
}

$result = express_update_batch($pdo, $batch_id, $status, $notes);

express_json_response($result['success'], ['batch_id' => $batch_id], $result['message']);
