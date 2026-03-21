<?php
/**
 * SPS 前台入口
 * URL: /sps/index.php
 */

define('SPS_ENTRY', true);
define('PROJECT_ROOT', dirname(dirname(dirname(__DIR__))));

require_once PROJECT_ROOT . '/app/sps/bootstrap.php';

$action = basename($_GET['action'] ?? 'entry');

if (!in_array($action, ['login', 'do_login'])) {
    sps_require_login('/sps/index.php?action=login');
}

$page_actions = ['login', 'entry', 'history'];
$api_actions  = ['do_login', 'do_logout', 'entry_save', 'dept_breakdown'];

if (!in_array($action, array_merge($page_actions, $api_actions))) {
    http_response_code(404);
    die('无效的操作');
}

if (in_array($action, $api_actions)) {
    $file = SPS_API_PATH . '/frontend/' . $action . '.php';
    file_exists($file) ? require_once $file : sps_json(false, null, 'API not found');
} else {
    $is_backend = false;
    $file = SPS_VIEW_PATH . '/frontend/' . $action . '.php';
    file_exists($file) ? require_once $file : die('Page not found');
}
