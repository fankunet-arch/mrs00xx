<?php
/**
 * SPS API: Staff 保存填报数量
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_login('/sps/index.php?action=login');

$input      = sps_json_input();
$round_id   = (int)($input['round_id']   ?? 0);
$product_id = (int)($input['product_id'] ?? 0);
$dept_id    = (int)($input['dept_id']    ?? 0);
$qty        = max(0, (float)($input['qty'] ?? 0));
$user_id    = $_SESSION['sps_user_id'];

if (!$round_id || !$product_id || !$dept_id) sps_json(false, null, '参数错误');

try {
    // 验证轮次是否开放
    $round = $pdo->prepare("SELECT status FROM sps_rounds WHERE round_id=?");
    $round->execute([$round_id]);
    $round = $round->fetch();
    if (!$round || $round['status'] !== 'open') {
        sps_json(false, null, '当前轮次已关闭，无法修改');
    }

    // 验证用户有该部门权限
    $check = $pdo->prepare("SELECT 1 FROM sps_user_departments WHERE user_id=? AND dept_id=?");
    $check->execute([$user_id, $dept_id]);
    if (!$check->fetch()) {
        sps_json(false, null, '无权操作该部门');
    }

    // 验证商品属于该部门
    $checkPD = $pdo->prepare("SELECT 1 FROM sps_product_departments WHERE product_id=? AND dept_id=?");
    $checkPD->execute([$product_id, $dept_id]);
    if (!$checkPD->fetch()) {
        sps_json(false, null, '商品与部门不匹配');
    }

    // 获取商品单位
    $unitRow = $pdo->prepare("SELECT unit FROM sps_products WHERE product_id=?");
    $unitRow->execute([$product_id]);
    $unit = $unitRow->fetchColumn() ?: '件';

    // 插入或更新
    $stmt = $pdo->prepare("
        INSERT INTO sps_round_entries (round_id, product_id, dept_id, qty, unit, updated_by)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE qty=VALUES(qty), unit=VALUES(unit), updated_by=VALUES(updated_by), updated_at=NOW()
    ");
    $stmt->execute([$round_id, $product_id, $dept_id, $qty, $unit, $user_id]);

    sps_json(true, ['qty' => $qty, 'unit' => $unit], '已保存');

} catch (PDOException $e) {
    sps_log('entry_save error: ' . $e->getMessage(), 'ERROR');
    sps_json(false, null, '保存失败：' . $e->getMessage());
}
