<?php
/**
 * SKU Toggle Status API
 * 文件路径: app/mrs/api/sku_toggle_status.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

try {
    $input = mrs_get_json_input();

    if (!$input || empty($input['sku_id']) || empty($input['status'])) {
        mrs_json_response(false, null, '无效的输入数据');
    }

    $sku_id = (int)$input['sku_id'];
    $new_status = $input['status'];

    // 验证状态值
    if (!in_array($new_status, ['active', 'inactive'])) {
        mrs_json_response(false, null, '无效的状态值');
    }

    // 更新状态
    $sql = "UPDATE mrs_sku SET status = :status, updated_at = NOW() WHERE sku_id = :sku_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $new_status,
        ':sku_id' => $sku_id
    ]);

    if ($stmt->rowCount() > 0) {
        $action = $new_status === 'active' ? '启用' : '停用';
        mrs_json_response(true, ['sku_id' => $sku_id, 'status' => $new_status], "SKU{$action}成功");
    } else {
        mrs_json_response(false, null, 'SKU不存在或状态未变更');
    }
} catch (Exception $e) {
    mrs_log('SKU状态切换失败: ' . $e->getMessage(), 'ERROR');
    mrs_json_response(false, null, '操作失败: ' . $e->getMessage());
}
