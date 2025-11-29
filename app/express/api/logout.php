<?php
/**
 * API: Logout
 * 文件路径: app/express/api/logout.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$username = $_SESSION['express_admin_username'] ?? 'unknown';

session_destroy();

express_log('Admin logged out: ' . $username, 'INFO');

header('Location: /app/express/exp/index.php?action=login');
exit;
