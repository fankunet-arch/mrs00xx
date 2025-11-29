<?php
/**
 * API: Update package content note
 * 文件路径: app/express/api/update_content_note.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = express_get_json_input();
$package_id = $input['package_id'] ?? 0;
$content_note = trim($input['content_note'] ?? '');
$operator = $_SESSION['user_display_name'] ?? $_SESSION['user_login'] ?? 'system';

if (empty($package_id)) {
    express_json_response(false, null, '缺少包裹ID');
}

if ($content_note === '') {
    express_json_response(false, null, '内容备注不能为空');
}

$result = express_update_content_note($pdo, $package_id, $operator, $content_note);

if ($result['success']) {
    express_json_response(true, $result['package'], $result['message']);
}

express_json_response(false, null, $result['message'] ?? '更新失败');
