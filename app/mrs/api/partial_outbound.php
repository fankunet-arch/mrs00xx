<?php
/**
 * API: Partial Outbound (拆零出库)
 * 文件路径: app/mrs/api/partial_outbound.php
 * 说明: 从包裹中拆出部分数量给门店，支持按具体产品扣减
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mrs_json_response(false, null, '非法请求方式');
}

$input = mrs_get_json_input();
if (!$input) {
    $input = $_POST;
}

// 接收参数
$ledger_id = (int)($input['ledger_id'] ?? 0);
$deduct_qty = floatval($input['deduct_qty'] ?? 0);
$destination = trim($input['destination'] ?? '');
$remark = trim($input['remark'] ?? '');
$outbound_date = trim($input['outbound_date'] ?? '');
$product_name = trim($input['product_name'] ?? '');

// 参数验证
if ($ledger_id <= 0) {
    mrs_json_response(false, null, '包裹ID无效');
}

if ($deduct_qty <= 0) {
    mrs_json_response(false, null, '出库数量必须大于0');
}

if (empty($destination)) {
    mrs_json_response(false, null, '目的地（门店）不能为空');
}

// 校验出库日期（可选，默认今天）
$outbound_datetime = new DateTime();
if (!empty($outbound_date)) {
    $parsed_date = DateTime::createFromFormat('Y-m-d', $outbound_date);
    if (!$parsed_date) {
        mrs_json_response(false, null, '出库日期格式无效，应为YYYY-MM-DD');
    }
    // 使用用户选择的日期，时间沿用当前时间
    $outbound_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $outbound_date . ' ' . date('H:i:s'));
}

// 获取操作员
$operator = $_SESSION['user_login'] ?? 'system';

try {
    // 1. 获取包裹信息
    $stmt = $pdo->prepare("
        SELECT ledger_id, content_note, quantity, status
        FROM mrs_package_ledger
        WHERE ledger_id = :ledger_id
    ");
    $stmt->execute(['ledger_id' => $ledger_id]);
    $package = $stmt->fetch();

    if (!$package) {
        mrs_json_response(false, null, '包裹不存在');
    }

    if ($package['status'] !== 'in_stock') {
        mrs_json_response(false, null, '只能从在库包裹中出货');
    }

    // 2. 开启事务
    $pdo->beginTransaction();

    // 3. 如果指定了产品名称，从 item 级别扣减
    if (!empty($product_name)) {
        // 查找该产品在包裹中的明细记录
        $item_stmt = $pdo->prepare("
            SELECT item_id, quantity
            FROM mrs_package_items
            WHERE ledger_id = :ledger_id AND product_name = :product_name
            LIMIT 1
        ");
        $item_stmt->execute([
            'ledger_id' => $ledger_id,
            'product_name' => $product_name
        ]);
        $item = $item_stmt->fetch();

        if (!$item) {
            $pdo->rollBack();
            mrs_json_response(false, null, "包裹中未找到产品「{$product_name}」");
        }

        // 清洗 item 级别数量
        $item_qty_str = $item['quantity'] ?? '';
        if ($item_qty_str === null || $item_qty_str === '') {
            $item_current_qty = 0.0;
        } else {
            $cleaned = preg_replace('/[^0-9.]/', '', trim((string)$item_qty_str));
            $item_current_qty = $cleaned !== '' ? floatval($cleaned) : 0.0;
        }

        if ($deduct_qty > $item_current_qty) {
            $pdo->rollBack();
            mrs_json_response(false, null, "「{$product_name}」库存不足。当前库存：{$item_current_qty} 件，需要出库：{$deduct_qty} 件");
        }

        $new_item_qty = $item_current_qty - $deduct_qty;

        // 更新 item 级别数量
        $update_item_stmt = $pdo->prepare("
            UPDATE mrs_package_items
            SET quantity = :new_qty
            WHERE item_id = :item_id
        ");
        $update_item_stmt->execute([
            'new_qty' => $new_item_qty,
            'item_id' => $item['item_id']
        ]);

        // 重新计算 ledger 级别总数量（所有 item 数量之和）
        $sum_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(CAST(quantity AS DECIMAL(10,2))), 0) as total_qty
            FROM mrs_package_items
            WHERE ledger_id = :ledger_id
        ");
        $sum_stmt->execute(['ledger_id' => $ledger_id]);
        $new_ledger_qty = floatval($sum_stmt->fetchColumn());

        $remaining_qty = $new_item_qty;
        $log_product_name = $product_name;
    } else {
        // 未指定产品名称时（兼容旧调用），从 ledger 级别扣减
        $quantity_str = $package['quantity'] ?? '';
        if ($quantity_str === null || $quantity_str === '') {
            $current_qty = 0.0;
        } else {
            $cleaned = preg_replace('/[^0-9.]/', '', trim((string)$quantity_str));
            $current_qty = $cleaned !== '' ? floatval($cleaned) : 0.0;
        }

        if ($deduct_qty > $current_qty) {
            $pdo->rollBack();
            mrs_json_response(false, null, "库存不足。当前库存：{$current_qty} 件，需要出库：{$deduct_qty} 件");
        }

        $new_ledger_qty = $current_qty - $deduct_qty;
        $remaining_qty = $new_ledger_qty;
        $log_product_name = $package['content_note'];
    }

    // 4. 更新 ledger 级别数量
    $update_stmt = $pdo->prepare("
        UPDATE mrs_package_ledger
        SET quantity = :new_qty,
            updated_at = NOW()
        WHERE ledger_id = :ledger_id
    ");
    $update_stmt->execute([
        'new_qty' => $new_ledger_qty,
        'ledger_id' => $ledger_id
    ]);

    // 5. 记录出库到统计表
    $insert_stmt = $pdo->prepare("
        INSERT INTO mrs_usage_log (
            ledger_id,
            product_name,
            outbound_type,
            deduct_qty,
            destination,
            operator,
            created_at,
            remark
        ) VALUES (
            :ledger_id,
            :product_name,
            'partial',
            :deduct_qty,
            :destination,
            :operator,
            :created_at,
            :remark
        )
    ");
    $insert_stmt->execute([
        'ledger_id' => $ledger_id,
        'product_name' => $log_product_name,
        'deduct_qty' => $deduct_qty,
        'destination' => $destination,
        'operator' => $operator,
        'created_at' => $outbound_datetime->format('Y-m-d H:i:s'),
        'remark' => $remark ?: null
    ]);

    // 6. 提交事务
    $pdo->commit();

    // 7. 返回成功
    mrs_json_response(true, [
        'ledger_id' => $ledger_id,
        'product_name' => $log_product_name,
        'deduct_qty' => $deduct_qty,
        'remaining_qty' => $remaining_qty,
        'destination' => $destination
    ], "拆零出库成功！已从「{$log_product_name}」扣减 {$deduct_qty} 件，剩余 {$remaining_qty} 件");

} catch (PDOException $e) {
    // 回滚事务
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    mrs_log('Partial outbound error: ' . $e->getMessage(), 'ERROR', [
        'ledger_id' => $ledger_id,
        'deduct_qty' => $deduct_qty,
        'destination' => $destination
    ]);

    mrs_json_response(false, null, '拆零出库失败：' . $e->getMessage());
} catch (Exception $e) {
    // 回滚事务
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    mrs_json_response(false, null, '拆零出库失败：' . $e->getMessage());
}
