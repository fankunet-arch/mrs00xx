<?php
/**
 * MRS 物料收发管理系统 - 后台API: 库存调整/盘点
 * 文件路径: app/mrs/api/backend_adjust_inventory.php
 * 说明: 管理员手动调整库存数量，用于盘点修正
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 需要登录
require_login();

try {
    // 获取POST数据
    $input = get_json_input();

    if (!$input || empty($input['sku_id']) || !isset($input['current_qty'])) {
        json_response(false, null, '缺少必要参数');
    }

    $skuId = intval($input['sku_id']);
    $currentQty = floatval($input['current_qty']);
    $reason = $input['reason'] ?? '手动盘点调整';
    $operatorName = $_SESSION['username'] ?? '管理员';

    // 验证数量必须 >= 0
    if ($currentQty < 0) {
        json_response(false, null, '库存数量不能为负数');
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    // 开启事务
    $pdo->beginTransaction();

    try {
        // 1. 查询SKU是否存在
        $sku = get_sku_by_id($skuId);
        if (!$sku) {
            $pdo->rollBack();
            json_response(false, null, 'SKU不存在');
        }

        // 2. 计算系统当前库存 (Inbound - Outbound + Adjustment)
        // 入库总量
        $inboundSql = "SELECT COALESCE(SUM(total_standard_qty), 0) as total
                       FROM mrs_batch_confirmed_item
                       WHERE sku_id = :sku_id";
        $inStmt = $pdo->prepare($inboundSql);
        $inStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $inStmt->execute();
        $totalInbound = (int)$inStmt->fetchColumn();

        // 出库总量
        $outboundSql = "SELECT COALESCE(SUM(i.total_standard_qty), 0) as total
                        FROM mrs_outbound_order_item i
                        JOIN mrs_outbound_order o ON i.outbound_order_id = o.outbound_order_id
                        WHERE i.sku_id = :sku_id AND o.status = 'confirmed'";
        $outStmt = $pdo->prepare($outboundSql);
        $outStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $outStmt->execute();
        $totalOutbound = (int)$outStmt->fetchColumn();

        // 调整总量
        $adjustmentSql = "SELECT COALESCE(SUM(delta_qty), 0) as total
                          FROM mrs_inventory_adjustment
                          WHERE sku_id = :sku_id";
        $adjStmt = $pdo->prepare($adjustmentSql);
        $adjStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $adjStmt->execute();
        $totalAdjustment = floatval($adjStmt->fetchColumn());

        // 系统库存 = 入库 - 出库 + 调整
        $sysQty = $totalInbound - $totalOutbound + $totalAdjustment;

        // 3. 计算差值
        $delta = $currentQty - $sysQty;

        // 4. 如果差值为0，无需调整
        if (abs($delta) < 0.01) { // 使用浮点数比较，避免精度问题
            $pdo->rollBack();
            json_response(true, [
                'system_qty' => $sysQty,
                'current_qty' => $currentQty,
                'delta' => 0,
                'message' => '库存数量一致，无需调整'
            ], '库存数量一致，无需调整');
        }

        // 5. 插入调整记录
        $insertSql = "INSERT INTO mrs_inventory_adjustment (
                        sku_id,
                        delta_qty,
                        reason,
                        operator_name,
                        created_at
                    ) VALUES (
                        :sku_id,
                        :delta_qty,
                        :reason,
                        :operator_name,
                        NOW(6)
                    )";

        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $insertStmt->bindValue(':delta_qty', $delta);
        $insertStmt->bindValue(':reason', $reason);
        $insertStmt->bindValue(':operator_name', $operatorName);
        $insertStmt->execute();

        $adjustmentId = $pdo->lastInsertId();

        // 提交事务
        $pdo->commit();

        mrs_log("库存调整成功: sku_id={$skuId}, delta={$delta}, adjustment_id={$adjustmentId}", 'INFO');

        json_response(true, [
            'adjustment_id' => $adjustmentId,
            'system_qty' => $sysQty,
            'current_qty' => $currentQty,
            'delta' => $delta
        ], '库存调整成功');

    } catch (Exception $e) {
        // 回滚事务
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

} catch (PDOException $e) {
    mrs_log('库存调整失败: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('库存调整异常: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}
