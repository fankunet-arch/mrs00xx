<?php
/**
 * SPS API: 保存商品（新增/编辑）
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$input      = sps_json_input();
$product_id = (int)($input['product_id'] ?? 0);
$name_cn    = trim($input['name_cn'] ?? '');
$name_es    = trim($input['name_es'] ?? '') ?: null;
$supplier_id = !empty($input['supplier_id']) ? (int)$input['supplier_id'] : null;
$unit       = trim($input['unit'] ?? '件') ?: '件';
$sort_order = (int)($input['sort_order'] ?? 0);
$status     = in_array($input['status'] ?? '', ['active','inactive']) ? $input['status'] : 'active';
$dept_ids   = array_map('intval', $input['dept_ids'] ?? []);

if (empty($name_cn)) sps_json(false, null, '中文名称不能为空');
if (empty($dept_ids)) sps_json(false, null, '请至少选择一个归属部门');

try {
    $pdo->beginTransaction();

    if ($product_id) {
        $pdo->prepare("UPDATE sps_products SET name_cn=?, name_es=?, supplier_id=?, unit=?, sort_order=?, status=?, updated_at=NOW() WHERE product_id=?")
            ->execute([$name_cn, $name_es, $supplier_id, $unit, $sort_order, $status, $product_id]);
    } else {
        $pdo->prepare("INSERT INTO sps_products (name_cn, name_es, supplier_id, unit, sort_order, status) VALUES (?,?,?,?,?,?)")
            ->execute([$name_cn, $name_es, $supplier_id, $unit, $sort_order, $status]);
        $product_id = $pdo->lastInsertId();
    }

    // 重置部门关联
    $pdo->prepare("DELETE FROM sps_product_departments WHERE product_id=?")->execute([$product_id]);
    $ins = $pdo->prepare("INSERT IGNORE INTO sps_product_departments (product_id, dept_id) VALUES (?,?)");
    foreach ($dept_ids as $did) {
        $ins->execute([$product_id, $did]);
    }

    $pdo->commit();
    sps_json(true, ['product_id' => $product_id], '商品已保存');

} catch (PDOException $e) {
    $pdo->rollBack();
    sps_log('product_save error: ' . $e->getMessage(), 'ERROR');
    sps_json(false, null, '保存失败：' . $e->getMessage());
}
