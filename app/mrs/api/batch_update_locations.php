<?php
/**
 * MRS 物料收发管理系统 - 后台API: 批量更新箱子位置
 * 文件路径: app/mrs/api/batch_update_locations.php
 * 说明: 批量更新多个箱子的货架位置
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置和库文件
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

try {
    // 获取输入数据
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        json_response(false, null, '无效的请求数据');
    }

    $ledger_ids = $input['ledger_ids'] ?? [];
    $new_location = trim($input['new_location'] ?? '');

    // 验证参数
    if (empty($ledger_ids) || !is_array($ledger_ids)) {
        json_response(false, null, '请选择要更新的箱子');
    }

    if ($new_location === '') {
        json_response(false, null, '新位置不能为空');
    }

    // 验证格式 (XX-XX-XX)
    if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $new_location)) {
        json_response(false, null, '位置格式错误，应为：XX-XX-XX (如 01-02-03)');
    }

    // 过滤和验证ID
    $ledger_ids = array_filter($ledger_ids, function($id) {
        return is_numeric($id) && $id > 0;
    });

    if (empty($ledger_ids)) {
        json_response(false, null, '没有有效的箱子ID');
    }

    // 获取数据库连接
    $pdo = get_mrs_db_connection();

    // 构建批量更新SQL
    $placeholders = implode(',', array_fill(0, count($ledger_ids), '?'));
    $stmt = $pdo->prepare("
        UPDATE mrs_package_ledger
        SET warehouse_location = ?,
            updated_at = NOW()
        WHERE ledger_id IN ($placeholders)
    ");

    // 绑定参数
    $params = array_merge([$new_location], array_values($ledger_ids));
    $stmt->execute($params);

    $affected = $stmt->rowCount();

    if ($affected > 0) {
        mrs_log('批量更新箱子位置', 'INFO', [
            'count' => $affected,
            'new_location' => $new_location,
            'ledger_ids' => $ledger_ids
        ]);
        json_response(true, ['affected' => $affected], "成功更新 {$affected} 个箱子的位置");
    } else {
        json_response(false, null, '更新失败，请检查箱子是否存在');
    }

} catch (Exception $e) {
    mrs_log('批量更新箱子位置API错误: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '服务器错误');
}
