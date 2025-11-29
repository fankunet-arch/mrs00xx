<?php
/**
 * API: Logout
 * 文件路径: app/express/api/logout.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$username = $_SESSION['express_user_login'] ?? ($_SESSION['express_admin_username'] ?? 'unknown');

express_destroy_user_session();

express_log('Admin logged out: ' . $username, 'INFO');

header('Location: /express/exp/index.php?action=login&error=logout');
exit;
