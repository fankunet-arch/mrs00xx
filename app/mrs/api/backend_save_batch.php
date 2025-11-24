<?php
/**
 * MRS 物料收发管理系统 - 后台API: 保存批次
 * 文件路径: app/mrs/api/backend_save_batch.php
 * 说明: 创建或更新收货批次
 */

// 定义API入口
define('MRS_ENTRY', true);

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取POST数据
    $input = get_json_input();

    if (!$input) {
        json_response(false, null, '无效的请求数据');
    }

    // 验证必填字段
    $required = ['batch_code', 'batch_date', 'location_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            json_response(false, null, "缺少必填字段: {$field}");
        }
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    // 判断是新建还是更新
    $batchId = $input['batch_id'] ?? null;

    if ($batchId) {
        // 更新现有批次
        $sql = "UPDATE mrs_batch SET
                    batch_code = :batch_code,
                    batch_date = :batch_date,
                    location_name = :location_name,
                    remark = :remark,
                    batch_status = :batch_status,
                    updated_at = NOW(6)
                WHERE batch_id = :batch_id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':batch_code', $input['batch_code']);
        $stmt->bindValue(':batch_date', $input['batch_date']);
        $stmt->bindValue(':location_name', $input['location_name']);
        $stmt->bindValue(':remark', $input['remark'] ?? '');
        $stmt->bindValue(':batch_status', $input['batch_status'] ?? 'draft');
        $stmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $stmt->execute();

        mrs_log("批次更新成功: batch_id={$batchId}", 'INFO', $input);

        json_response(true, ['batch_id' => $batchId], '批次更新成功');

    } else {
        // 检查批次编号是否已存在
        $checkSql = "SELECT batch_id FROM mrs_batch WHERE batch_code = :batch_code";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':batch_code', $input['batch_code']);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            json_response(false, null, '批次编号已存在');
        }

        // 创建新批次
        $sql = "INSERT INTO mrs_batch (
                    batch_code,
                    batch_date,
                    location_name,
                    remark,
                    batch_status,
                    created_at,
                    updated_at
                ) VALUES (
                    :batch_code,
                    :batch_date,
                    :location_name,
                    :remark,
                    :batch_status,
                    NOW(6),
                    NOW(6)
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':batch_code', $input['batch_code']);
        $stmt->bindValue(':batch_date', $input['batch_date']);
        $stmt->bindValue(':location_name', $input['location_name']);
        $stmt->bindValue(':remark', $input['remark'] ?? '');
        $stmt->bindValue(':batch_status', $input['batch_status'] ?? 'draft');
        $stmt->execute();

        $newBatchId = $pdo->lastInsertId();

        mrs_log("新批次创建成功: batch_id={$newBatchId}", 'INFO', $input);

        json_response(true, ['batch_id' => $newBatchId], '批次创建成功');
    }

} catch (PDOException $e) {
    mrs_log('保存批次失败: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('保存批次异常: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}
