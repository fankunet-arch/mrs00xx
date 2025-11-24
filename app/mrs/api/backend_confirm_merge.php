<?php
/**
 * MRS 物料收发管理系统 - 后台API: 确认批次合并
 * 文件路径: app/mrs/api/backend_confirm_merge.php
 * 说明: 确认批次合并并生成确认入库记录
 */

// 定义API入口
define('MRS_ENTRY', true);

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取POST数据
    $input = get_json_input();

    if (!$input || empty($input['batch_id']) || empty($input['items'])) {
        json_response(false, null, '缺少必要参数');
    }

    $batchId = intval($input['batch_id']);
    $items = $input['items'];

    // 获取数据库连接
    $pdo = get_db_connection();

    // 开启事务
    $pdo->beginTransaction();

    try {
        // 检查批次状态
        $checkSql = "SELECT batch_status FROM mrs_batch WHERE batch_id = :batch_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $checkStmt->execute();
        $batch = $checkStmt->fetch();

        if (!$batch) {
            $pdo->rollBack();
            json_response(false, null, '批次不存在');
        }

        // [FIX] 检查状态，防止对已完成的批次进行操作
        if ($batch['batch_status'] === 'confirmed' || $batch['batch_status'] === 'posted') {
            $pdo->rollBack();
            json_response(false, null, '该批次已确认或过账，不可再次合并');
        }

        // 删除旧的确认记录(如果有)
        $deleteOldSql = "DELETE FROM mrs_batch_confirmed_item WHERE batch_id = :batch_id";
        $deleteOldStmt = $pdo->prepare($deleteOldSql);
        $deleteOldStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $deleteOldStmt->execute();

        // 插入新的确认记录
        $insertSql = "INSERT INTO mrs_batch_confirmed_item (
                        batch_id,
                        sku_id,
                        total_standard_qty,
                        confirmed_case_qty,
                        confirmed_single_qty,
                        diff_against_expected,
                        is_over_received,
                        is_under_received,
                        created_at,
                        updated_at
                    ) VALUES (
                        :batch_id,
                        :sku_id,
                        :total_standard_qty,
                        :confirmed_case_qty,
                        :confirmed_single_qty,
                        :diff_against_expected,
                        :is_over_received,
                        :is_under_received,
                        NOW(6),
                        NOW(6)
                    )";

        $insertStmt = $pdo->prepare($insertSql);

        foreach ($items as $item) {
            // 计算总标准数量
            $caseQty = floatval($item['case_qty'] ?? 0);
            $singleQty = floatval($item['single_qty'] ?? 0);
            $caseToStandard = floatval($item['case_to_standard'] ?? 0);

            // [FIX] 强制整数规则：计算结果必须为标准单位的整数
            // 1. 计算理论浮点值
            $rawTotal = ($caseQty * $caseToStandard) + $singleQty;
            // 2. 四舍五入取整，防止浮点精度问题导致的小数（如 29.99999 -> 30）
            // 需求文档明确：系统不允许以“6.5 箱”这种形式直接作为最终库存记账单位
            $totalStandard = round($rawTotal, 0);

            // [PATCH] 归一化逻辑：仅当箱规为整数并且 >0 时做归一化
            if ($caseToStandard > 0 && fmod($caseToStandard, 1.0) == 0.0) {
                $caseSize = (int)$caseToStandard;
                $total    = (int)$totalStandard;

                $normalizedCaseQty   = intdiv($total, $caseSize);
                $normalizedSingleQty = $total % $caseSize;

                $caseQty   = $normalizedCaseQty; // Update for binding
                $singleQty = $normalizedSingleQty; // Update for binding
            }

            // 计算差异
            $expectedQty = floatval($item['expected_qty'] ?? 0);
            $diff = $totalStandard - $expectedQty;

            // 判断超收/少收
            $isOver = ($diff > 0) ? 1 : 0;
            $isUnder = ($diff < 0) ? 1 : 0;

            // 插入记录
            $insertStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
            $insertStmt->bindValue(':sku_id', intval($item['sku_id']), PDO::PARAM_INT);
            $insertStmt->bindValue(':total_standard_qty', $totalStandard); // 存入取整后的值
            $insertStmt->bindValue(':confirmed_case_qty', $caseQty);
            $insertStmt->bindValue(':confirmed_single_qty', $singleQty);
            $insertStmt->bindValue(':diff_against_expected', $diff);
            $insertStmt->bindValue(':is_over_received', $isOver, PDO::PARAM_INT);
            $insertStmt->bindValue(':is_under_received', $isUnder, PDO::PARAM_INT);
            $insertStmt->execute();
        }

        // 更新批次状态为已确认
        $updateBatchSql = "UPDATE mrs_batch SET
                            batch_status = 'confirmed',
                            updated_at = NOW(6)
                        WHERE batch_id = :batch_id";
        $updateBatchStmt = $pdo->prepare($updateBatchSql);
        $updateBatchStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $updateBatchStmt->execute();

        // 提交事务
        $pdo->commit();

        mrs_log("批次合并确认成功: batch_id={$batchId}, items_count=" . count($items), 'INFO');

        json_response(true, null, '批次合并确认成功');

    } catch (Exception $e) {
        // 回滚事务
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

} catch (PDOException $e) {
    mrs_log('确认批次合并失败: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('确认批次合并异常: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}
