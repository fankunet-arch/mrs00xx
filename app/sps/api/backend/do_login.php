<?php
/**
 * SPS 后台登录处理（仅admin可进后台）
 */
if (!defined('SPS_ENTRY')) die('Access denied');

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['sps_login_error'] = '请输入用户名和密码';
    header('Location: /sps/ap/index.php?action=login');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM sps_users WHERE username = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $_SESSION['sps_login_error'] = '用户名或密码错误';
        header('Location: /sps/ap/index.php?action=login');
        exit;
    }

    if ($user['role'] !== 'admin') {
        $_SESSION['sps_login_error'] = '无后台访问权限';
        header('Location: /sps/ap/index.php?action=login');
        exit;
    }

    $_SESSION['sps_user_id']   = $user['user_id'];
    $_SESSION['sps_username']  = $user['username'];
    $_SESSION['sps_display']   = $user['display_name'];
    $_SESSION['sps_role']      = $user['role'];

    header('Location: /sps/ap/index.php?action=dashboard');
    exit;

} catch (PDOException $e) {
    sps_log('Login error: ' . $e->getMessage(), 'ERROR');
    $_SESSION['sps_login_error'] = '系统错误，请稍后重试';
    header('Location: /sps/ap/index.php?action=login');
    exit;
}
