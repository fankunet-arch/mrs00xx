<?php
/**
 * API: Save New Batch (自动生成编号并录入快递单号)
 * 文件路径: app/express/api/batch_create_save.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = express_get_json_input();

$tracking_numbers = $input['tracking_numbers'] ?? [];
$notes = $input['notes'] ?? null;
$created_by = $input['created_by'] ?? $_SESSION['user_login'] ?? 'admin';

// 快递单号可以为空，允许创建空批次后再添加
if (!is_array($tracking_numbers)) {
    express_json_response(false, null, '快递单号格式错误');
}

try {
    $pdo->beginTransaction();

    // 创建批次（自动生成编号）
    $batch_id = express_create_batch($pdo, $created_by, $notes);

    if (!$batch_id) {
        $pdo->rollBack();
        express_json_response(false, null, '批次创建失败');
    }

    // 如果有快递单号，批量导入
    $imported_count = 0;
    $duplicates = 0;
    $errors = [];

    foreach ($tracking_numbers as $tracking_info) {
        $tracking_number = is_array($tracking_info) ? ($tracking_info['tracking_number'] ?? '') : trim($tracking_info);
        $expiry_date = is_array($tracking_info) ? ($tracking_info['expiry_date'] ?? null) : null;
        $quantity = is_array($tracking_info) ? ($tracking_info['quantity'] ?? null) : null;

        if (empty($tracking_number)) {
            continue;
        }

        $result = express_create_package($pdo, $batch_id, $tracking_number, $expiry_date, $quantity);

        if ($result) {
            $imported_count++;
        } else {
            // 检查是否是重复单号
            $check_stmt = $pdo->prepare("
                SELECT package_id FROM express_package
                WHERE batch_id = :batch_id AND tracking_number = :tracking_number
            ");
            $check_stmt->execute([
                'batch_id' => $batch_id,
                'tracking_number' => $tracking_number
            ]);

            if ($check_stmt->fetch()) {
                $duplicates++;
            } else {
                $errors[] = $tracking_number;
            }
        }
    }

    // 更新批次统计
    express_update_batch_stats($pdo, $batch_id);

    $pdo->commit();

    // 获取批次信息
    $batch = express_get_batch_by_id($pdo, $batch_id);

    express_json_response(true, [
        'batch_id' => $batch_id,
        'batch_name' => $batch['batch_name'],
        'imported_count' => $imported_count,
        'duplicates' => $duplicates,
        'errors' => $errors
    ], '批次创建成功');

} catch (Exception $e) {
    $pdo->rollBack();
    express_log('Batch creation failed: ' . $e->getMessage(), 'ERROR');
    express_json_response(false, null, '批次创建失败: ' . $e->getMessage());
}
