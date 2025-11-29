<?php
/**
 * API: Process Login
 * 文件路径: app/express/api/do_login.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = express_get_json_input();

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

// 简单的硬编码认证（仅用于测试）
// 生产环境应该使用数据库验证和密码哈希
if ($username === 'admin' && $password === 'admin123') {
    $_SESSION['express_admin_logged_in'] = true;
    $_SESSION['express_admin_username'] = $username;

    express_log('Admin logged in: ' . $username, 'INFO');
    express_json_response(true, ['username' => $username], '登录成功');
} else {
    express_log('Failed login attempt: ' . $username, 'WARNING');
    express_json_response(false, null, '用户名或密码错误');
}
