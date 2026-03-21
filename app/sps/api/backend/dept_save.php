<?php
/**
 * SPS API: 保存部门
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$input      = sps_json_input();
$dept_id    = (int)($input['dept_id'] ?? 0);
$dept_name  = trim($input['dept_name'] ?? '');
$sort_order = (int)($input['sort_order'] ?? 0);
$status     = in_array($input['status'] ?? '', ['active','inactive']) ? $input['status'] : 'active';

if (empty($dept_name)) sps_json(false, null, '部门名称不能为空');

try {
    if ($dept_id) {
        $pdo->prepare("UPDATE sps_departments SET dept_name=?, sort_order=?, status=? WHERE dept_id=?")
            ->execute([$dept_name, $sort_order, $status, $dept_id]);
    } else {
        $pdo->prepare("INSERT INTO sps_departments (dept_name, sort_order, status) VALUES (?,?,?)")
            ->execute([$dept_name, $sort_order, $status]);
        $dept_id = $pdo->lastInsertId();
    }
    sps_json(true, ['dept_id' => $dept_id], '部门已保存');
} catch (PDOException $e) {
    $msg = str_contains($e->getMessage(), 'Duplicate') ? '部门名称已存在' : $e->getMessage();
    sps_json(false, null, '保存失败：' . $msg);
}
