<?php
/**
 * SPS API: 获取某商品在某轮次的各部门明细（模态框用）
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$round_id   = (int)($_GET['round_id']   ?? 0);
$product_id = (int)($_GET['product_id'] ?? 0);

if (!$round_id || !$product_id) sps_json(false, null, '参数错误');

try {
    $stmt = $pdo->prepare("
        SELECT d.dept_name, d.sort_order,
               COALESCE(re.qty, 0) as qty,
               p.unit,
               re.updated_at,
               u.display_name as updated_by
        FROM sps_product_departments pd
        JOIN sps_departments d ON d.dept_id = pd.dept_id
        LEFT JOIN sps_round_entries re ON re.dept_id = pd.dept_id AND re.product_id = ? AND re.round_id = ?
        LEFT JOIN sps_users u ON u.user_id = re.updated_by
        JOIN sps_products p ON p.product_id = pd.product_id
        WHERE pd.product_id = ?
        ORDER BY d.sort_order
    ");
    $stmt->execute([$product_id, $round_id, $product_id]);
    $rows = $stmt->fetchAll();

    sps_json(true, $rows);
} catch (PDOException $e) {
    sps_json(false, null, $e->getMessage());
}
