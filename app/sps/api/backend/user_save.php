<?php
/**
 * SPS API: 保存用户
 */
if (!defined('SPS_ENTRY')) die('Access denied');
sps_require_admin();

$input        = sps_json_input();
$user_id      = (int)($input['user_id'] ?? 0);
$display_name = trim($input['display_name'] ?? '');
$username     = trim($input['username'] ?? '');
$password     = $input['password'] ?? '';
$role         = in_array($input['role'] ?? '', ['admin','staff']) ? $input['role'] : 'staff';
$status       = in_array($input['status'] ?? '', ['active','inactive']) ? $input['status'] : 'active';
$dept_ids     = array_map('intval', $input['dept_ids'] ?? []);

if (empty($display_name)) sps_json(false, null, '显示名称不能为空');
if (empty($username))     sps_json(false, null, '用户名不能为空');
if (!$user_id && empty($password)) sps_json(false, null, '新用户密码不能为空');

try {
    $pdo->beginTransaction();

    if ($user_id) {
        // 编辑
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE sps_users SET display_name=?, username=?, password_hash=?, role=?, status=? WHERE user_id=?")
                ->execute([$display_name, $username, $hash, $role, $status, $user_id]);
        } else {
            $pdo->prepare("UPDATE sps_users SET display_name=?, username=?, role=?, status=? WHERE user_id=?")
                ->execute([$display_name, $username, $role, $status, $user_id]);
        }
    } else {
        // 新增
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO sps_users (username, password_hash, display_name, role, status) VALUES (?,?,?,?,?)")
            ->execute([$username, $hash, $display_name, $role, $status]);
        $user_id = $pdo->lastInsertId();
    }

    // 重置部门关联
    $pdo->prepare("DELETE FROM sps_user_departments WHERE user_id=?")->execute([$user_id]);
    if ($role === 'staff' && !empty($dept_ids)) {
        $ins = $pdo->prepare("INSERT IGNORE INTO sps_user_departments (user_id, dept_id) VALUES (?,?)");
        foreach ($dept_ids as $did) {
            $ins->execute([$user_id, $did]);
        }
    }

    $pdo->commit();
    sps_json(true, ['user_id' => $user_id], '用户已保存');

} catch (PDOException $e) {
    $pdo->rollBack();
    sps_log('user_save error: ' . $e->getMessage(), 'ERROR');
    $msg = str_contains($e->getMessage(), 'Duplicate entry') ? '用户名已存在' : $e->getMessage();
    sps_json(false, null, '保存失败：' . $msg);
}
