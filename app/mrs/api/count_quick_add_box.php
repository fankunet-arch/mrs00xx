<?php
/**
 * MRS Count API - Quick Add Box
 * 文件路径: app/mrs/api/count_quick_add_box.php
 * 说明: 快速录入新箱API
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

header('Content-Type: application/json; charset=utf-8');

// 加载清点业务逻辑库
require_once MRS_LIB_PATH . '/count_lib.php';

// 获取POST数据
$session_id = $_POST['session_id'] ?? null;
$box_number = $_POST['box_number'] ?? null;
$sku_id = $_POST['sku_id'] ?? null;
$sku_name = $_POST['sku_name'] ?? null;
$quantity = $_POST['quantity'] ?? null;
$content_note = $_POST['content_note'] ?? null;
$created_by = $_POST['created_by'] ?? null;

// 验证必填字段
if (empty($box_number)) {
    echo json_encode([
        'success' => false,
        'message' => '请输入箱号'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 如果没有sku_id但有sku_name，尝试查找或创建SKU
if (!$sku_id && $sku_name) {
    // 先尝试查找同名SKU
    $stmt = $pdo->prepare("SELECT sku_id FROM mrs_sku WHERE sku_name = :sku_name AND status = 'active' LIMIT 1");
    $stmt->execute([':sku_name' => $sku_name]);
    $existing_sku = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_sku) {
        $sku_id = $existing_sku['sku_id'];
    } else {
        // 创建新SKU
        $stmt = $pdo->prepare("
            INSERT INTO mrs_sku (sku_name, status, created_at, updated_at)
            VALUES (:sku_name, 'active', NOW(6), NOW(6))
        ");
        $stmt->execute([':sku_name' => $sku_name]);
        $sku_id = $pdo->lastInsertId();
    }
}

try {
    $pdo->beginTransaction();

    // 快速录入新箱
    $box_data = [
        'box_number' => $box_number,
        'sku_id' => $sku_id,
        'content_note' => $content_note ?? $sku_name,
        'quantity' => $quantity,
        'created_by' => $created_by
    ];

    $add_result = mrs_count_quick_add_box($pdo, $box_data);

    if (!$add_result['success']) {
        throw new Exception($add_result['error'] ?? '录入失败');
    }

    $ledger_id = $add_result['ledger_id'];

    // 同时创建清点记录
    $record_data = [
        'session_id' => $session_id,
        'box_number' => $box_number,
        'ledger_id' => $ledger_id,
        'system_content' => $content_note ?? $sku_name,
        'system_total_qty' => $quantity,
        'check_mode' => 'box_only',
        'has_multiple_items' => 0,
        'match_status' => 'found',
        'is_new_box' => 1, // 标记为新录入的箱子
        'remark' => '现场新录入',
        'counted_by' => $created_by
    ];

    $record_result = mrs_count_save_record($pdo, $record_data);

    if (!$record_result['success']) {
        throw new Exception($record_result['error'] ?? '保存清点记录失败');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'ledger_id' => $ledger_id,
        'record_id' => $record_result['record_id'],
        'message' => '新箱录入成功并已清点'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
