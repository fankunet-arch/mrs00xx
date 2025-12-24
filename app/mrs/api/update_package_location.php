<?php
/**
 * MRS 物料收发管理系统 - 后台API: 更新单个箱子位置
 * 文件路径: app/mrs/api/update_package_location.php
 * 说明: 更新单个箱子的货架位置
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

    $ledger_id = $input['ledger_id'] ?? null;
    $new_location = trim($input['new_location'] ?? '');

    // 验证参数
    if (!$ledger_id || !is_numeric($ledger_id)) {
        json_response(false, null, '无效的箱子ID');
    }

    if ($new_location === '') {
        json_response(false, null, '新位置不能为空');
    }

    // 验证格式 (XX-XX-XX)
    if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $new_location)) {
        json_response(false, null, '位置格式错误，应为：XX-XX-XX (如 01-02-03)');
    }

    // 获取数据库连接
    $pdo = get_mrs_db_connection();

    // 更新位置
    $stmt = $pdo->prepare("
        UPDATE mrs_package_ledger
        SET warehouse_location = :location,
            updated_at = NOW()
        WHERE ledger_id = :ledger_id
    ");

    $stmt->bindValue(':location', $new_location);
    $stmt->bindValue(':ledger_id', (int)$ledger_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        mrs_log('箱子位置已更新', 'INFO', [
            'ledger_id' => $ledger_id,
            'new_location' => $new_location
        ]);
        json_response(true, null, '位置更新成功');
    } else {
        json_response(false, null, '更新失败或箱子不存在');
    }

} catch (Exception $e) {
    mrs_log('箱子位置更新API错误: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '服务器错误');
}
