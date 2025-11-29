<?php
/**
 * API: Process Login
 * 文件路径: app/express/api/do_login.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /express/exp/index.php?action=login&error=invalid');
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

    if (empty($username) || empty($password)) {
        header('Location: /express/exp/index.php?action=login&error=invalid');
        exit;
    }

    express_start_secure_session();
    $loginAttempts = $_SESSION['express_login_attempts'] ?? 0;
    $lastAttemptTime = $_SESSION['express_last_attempt_time'] ?? 0;

    if ($loginAttempts >= 5 && (time() - $lastAttemptTime) < 300) {
        express_log("登录失败: 尝试次数过多 - {$username}", 'WARNING', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        header('Location: /express/exp/index.php?action=login&error=too_many_attempts');
        exit;
    }

    $user = express_authenticate_user($pdo, $username, $password);

    if ($user === false) {
        $_SESSION['express_login_attempts'] = $loginAttempts + 1;
        $_SESSION['express_last_attempt_time'] = time();

        express_log("登录失败: 用户名或密码错误 - {$username}", 'WARNING', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'attempts' => $_SESSION['express_login_attempts']
        ]);

        header('Location: /express/exp/index.php?action=login&error=invalid');
        exit;
    }

    express_create_user_session($user);
    unset($_SESSION['express_login_attempts']);
    unset($_SESSION['express_last_attempt_time']);

    if ($remember) {
        $rememberToken = bin2hex(random_bytes(32));
        setcookie('express_remember_me', $rememberToken, time() + (86400 * 30), '/');
    }

    express_log("登录成功: {$username}", 'INFO', [
        'user_id' => $user['user_id'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    header('Location: /express/exp/index.php?action=batch_list');
    exit;

} catch (Exception $e) {
    express_log('登录处理异常: ' . $e->getMessage(), 'ERROR');
    header('Location: /express/exp/index.php?action=login&error=system');
    exit;
}
