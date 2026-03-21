<?php
/**
 * SPS API: 删除商品（有历史数据时拒绝）
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$input      = sps_json_input();
$product_id = (int)($input['product_id'] ?? 0);
if (!$product_id) sps_json(false, null, '参数错误');

try {
    // 有填报记录则拒绝删除
    $count = $pdo->prepare("SELECT COUNT(*) FROM sps_round_entries WHERE product_id=?");
    $count->execute([$product_id]);
    if ($count->fetchColumn() > 0) {
        sps_json(false, null, '该商品已有填报记录，无法删除。可将其设为"停用"状态。');
    }

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM sps_product_departments WHERE product_id=?")->execute([$product_id]);
    $pdo->prepare("DELETE FROM sps_products WHERE product_id=?")->execute([$product_id]);
    $pdo->commit();

    sps_json(true, null, '商品已删除');

} catch (PDOException $e) {
    $pdo->rollBack();
    sps_json(false, null, '删除失败：' . $e->getMessage());
}
