<?php
/**
 * MRS Count API - Save Record
 * 文件路径: app/mrs/api/count_save_record.php
 * 说明: 保存清点记录API
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
$ledger_id = $_POST['ledger_id'] ?? null;
$check_mode = $_POST['check_mode'] ?? 'box_only';
$items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
$remark = $_POST['remark'] ?? null;
$counted_by = $_POST['counted_by'] ?? null;
$new_shelf_location = trim($_POST['shelf_location'] ?? '');

// 验证必填字段
if (empty($session_id) || empty($box_number)) {
    echo json_encode([
        'success' => false,
        'message' => '缺少必填参数'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 判断匹配状态
$match_status = 'found';
$has_multiple_items = 0;
$system_content = null;
$system_total_qty = null;

if ($ledger_id) {
    // 获取系统数据
    $stmt = $pdo->prepare("SELECT content_note, quantity FROM mrs_package_ledger WHERE ledger_id = :ledger_id");
    $stmt->execute([':ledger_id' => $ledger_id]);
    $ledger = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ledger) {
        $system_content = $ledger['content_note'];
        $system_total_qty = $ledger['quantity'];
    }

    if ($check_mode === 'with_qty') {
        // 核对数量模式
        if (!empty($items)) {
            $has_multiple_items = 1;
            // 计算总差异
            $total_diff = 0;
            foreach ($items as $item) {
                $diff = ($item['actual_qty'] ?? 0) - ($item['system_qty'] ?? 0);
                $total_diff += $diff;
            }
            $match_status = ($total_diff == 0) ? 'matched' : 'diff';
        }
    }
} else {
    // 系统中不存在
    $match_status = 'not_found';
}

// 准备清点记录数据
$record_data = [
    'session_id' => $session_id,
    'box_number' => $box_number,
    'ledger_id' => $ledger_id,
    'system_content' => $system_content,
    'system_total_qty' => $system_total_qty,
    'check_mode' => $check_mode,
    'has_multiple_items' => $has_multiple_items,
    'match_status' => $match_status,
    'is_new_box' => 0,
    'remark' => $remark,
    'counted_by' => $counted_by
];

try {
    $pdo->beginTransaction();

    // 保存清点记录
    $result = mrs_count_save_record($pdo, $record_data);

    if (!$result['success']) {
        throw new Exception($result['error'] ?? '保存失败');
    }

    $record_id = $result['record_id'];

    // 如果有多件物品，保存明细
    if ($has_multiple_items && !empty($items)) {
        $items_result = mrs_count_save_record_items($pdo, $record_id, $items);
        if (!$items_result['success']) {
            throw new Exception($items_result['error'] ?? '保存明细失败');
        }
    }

    // 如果提供了新货架位置，更新台账
    if ($ledger_id && $new_shelf_location !== '') {
        $update_stmt = $pdo->prepare("
            UPDATE mrs_package_ledger
            SET warehouse_location = :shelf_location,
                updated_by = :updated_by
            WHERE ledger_id = :ledger_id
        ");
        $update_stmt->execute([
            'shelf_location' => $new_shelf_location,
            'updated_by' => $counted_by,
            'ledger_id' => $ledger_id
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'record_id' => $record_id,
        'message' => '清点记录保存成功'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
