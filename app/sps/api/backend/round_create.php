<?php
/**
 * SPS API: 创建新采购轮次
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$input = sps_json_input();
$remark = trim($input['remark'] ?? '');

try {
    // 检查是否有进行中的轮次
    $open = $pdo->query("SELECT round_id FROM sps_rounds WHERE status='open' LIMIT 1")->fetch();
    if ($open) {
        sps_json(false, null, '当前有进行中的轮次，请先完成后再创建');
    }

    // 获取最新轮次，计算下一个标签
    $latest = $pdo->query("SELECT * FROM sps_rounds ORDER BY round_id DESC LIMIT 1")->fetch();

    $now_year  = (int)date('Y');
    $now_month = (int)date('n');

    if ($latest) {
        $ly = (int)$latest['round_year'];
        $lm = (int)$latest['round_month'];
        if ($ly === $now_year && $lm === $now_month) {
            $new_year  = $now_year;
            $new_month = $now_month;
            $new_order = (int)$latest['order_in_month'] + 1;
        } else {
            $new_year  = $now_year;
            $new_month = $now_month;
            $new_order = 1;
        }
    } else {
        $new_year  = $now_year;
        $new_month = $now_month;
        $new_order = 1;
    }

    $label = $new_month . '月 第' . $new_order . '次';

    $stmt = $pdo->prepare("
        INSERT INTO sps_rounds (round_year, round_month, order_in_month, label, status, remark)
        VALUES (?, ?, ?, ?, 'open', ?)
    ");
    $stmt->execute([$new_year, $new_month, $new_order, $label, $remark ?: null]);
    $round_id = $pdo->lastInsertId();

    sps_json(true, ['round_id' => $round_id, 'label' => $label], "轮次「{$label}」已创建");

} catch (PDOException $e) {
    sps_log('round_create error: ' . $e->getMessage(), 'ERROR');
    sps_json(false, null, '创建失败：' . $e->getMessage());
}
