<?php
/**
 * SPS API: 更新商品采购状态（pending/purchased/out_of_stock）
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$input      = sps_json_input();
$round_id   = (int)($input['round_id']   ?? 0);
$product_id = (int)($input['product_id'] ?? 0);
$status     = $input['status'] ?? '';
$remark     = trim($input['remark'] ?? '');

$allowed = ['pending', 'purchased', 'out_of_stock'];
if (!$round_id || !$product_id || !in_array($status, $allowed)) {
    sps_json(false, null, '参数错误');
}

try {
    // 检查轮次状态
    $round = $pdo->prepare("SELECT status FROM sps_rounds WHERE round_id=?");
    $round->execute([$round_id]);
    $round = $round->fetch();
    if (!$round || $round['status'] !== 'open') {
        sps_json(false, null, '轮次已关闭，无法修改');
    }

    $stmt = $pdo->prepare("
        INSERT INTO sps_round_purchase (round_id, product_id, purchase_status, remark)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE purchase_status=VALUES(purchase_status), remark=VALUES(remark), updated_at=NOW()
    ");
    $stmt->execute([$round_id, $product_id, $status, $remark ?: null]);

    sps_json(true, ['status' => $status], '状态已更新');

} catch (PDOException $e) {
    sps_log('purchase_status error: ' . $e->getMessage(), 'ERROR');
    sps_json(false, null, '操作失败：' . $e->getMessage());
}
