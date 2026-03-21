<?php
/**
 * SPS API: 完成采购轮次 → 自动创建下一轮
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$input    = sps_json_input();
$round_id = (int)($input['round_id'] ?? 0);

if (!$round_id) sps_json(false, null, '参数错误');

try {
    $round = $pdo->prepare("SELECT * FROM sps_rounds WHERE round_id = ? AND status = 'open'");
    $round->execute([$round_id]);
    $round = $round->fetch();

    if (!$round) sps_json(false, null, '轮次不存在或已完成');

    $pdo->beginTransaction();

    // 1. 标记当前轮次为completed
    $pdo->prepare("UPDATE sps_rounds SET status='completed', completed_at=NOW() WHERE round_id=?")
        ->execute([$round_id]);

    // 2. 计算下一轮标签
    $now_year  = (int)date('Y');
    $now_month = (int)date('n');
    $cur_year  = (int)$round['round_year'];
    $cur_month = (int)$round['round_month'];

    if ($now_year === $cur_year && $now_month === $cur_month) {
        // 还在同月
        $new_year  = $cur_year;
        $new_month = $cur_month;
        $new_order = (int)$round['order_in_month'] + 1;
    } else {
        // 已到新月
        $new_year  = $now_year;
        $new_month = $now_month;
        $new_order = 1;
    }

    $new_label = $new_month . '月 第' . $new_order . '次';

    // 3. 创建下一轮
    $stmt = $pdo->prepare("
        INSERT INTO sps_rounds (round_year, round_month, order_in_month, label, status)
        VALUES (?, ?, ?, ?, 'open')
    ");
    $stmt->execute([$new_year, $new_month, $new_order, $new_label]);
    $new_round_id = $pdo->lastInsertId();

    $pdo->commit();

    sps_json(true, [
        'new_round_id' => $new_round_id,
        'new_label'    => $new_label,
    ], "「{$round['label']}」已完成，新轮次「{$new_label}」已开启");

} catch (PDOException $e) {
    $pdo->rollBack();
    sps_log('round_complete error: ' . $e->getMessage(), 'ERROR');
    sps_json(false, null, '操作失败：' . $e->getMessage());
}
