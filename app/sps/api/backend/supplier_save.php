<?php
/**
 * SPS API: 保存供货商
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$input         = sps_json_input();
$supplier_id   = (int)($input['supplier_id'] ?? 0);
$supplier_name = trim($input['supplier_name'] ?? '');
$contact_name  = trim($input['contact_name']  ?? '') ?: null;
$contact_phone = trim($input['contact_phone'] ?? '') ?: null;
$sort_order    = (int)($input['sort_order'] ?? 0);
$status        = in_array($input['status'] ?? '', ['active','inactive']) ? $input['status'] : 'active';
$remark        = trim($input['remark'] ?? '') ?: null;

if (empty($supplier_name)) sps_json(false, null, '供货商名称不能为空');

try {
    if ($supplier_id) {
        $pdo->prepare("UPDATE sps_suppliers SET supplier_name=?, contact_name=?, contact_phone=?, sort_order=?, status=?, remark=? WHERE supplier_id=?")
            ->execute([$supplier_name, $contact_name, $contact_phone, $sort_order, $status, $remark, $supplier_id]);
    } else {
        $pdo->prepare("INSERT INTO sps_suppliers (supplier_name, contact_name, contact_phone, sort_order, status, remark) VALUES (?,?,?,?,?,?)")
            ->execute([$supplier_name, $contact_name, $contact_phone, $sort_order, $status, $remark]);
        $supplier_id = $pdo->lastInsertId();
    }
    sps_json(true, ['supplier_id' => $supplier_id], '供货商已保存');
} catch (PDOException $e) {
    sps_log('supplier_save error: ' . $e->getMessage(), 'ERROR');
    sps_json(false, null, '保存失败：' . $e->getMessage());
}
