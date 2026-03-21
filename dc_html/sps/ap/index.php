<?php
/**
 * SPS 后台入口
 * URL: /sps/ap/index.php
 */

define('SPS_ENTRY', true);
define('PROJECT_ROOT', dirname(dirname(dirname(dirname(__DIR__)))));

require_once PROJECT_ROOT . '/app/sps/bootstrap.php';

$action = basename($_GET['action'] ?? 'dashboard');

// 登录相关无需鉴权
if (!in_array($action, ['login', 'do_login'])) {
    sps_require_admin();
}

// 页面路由（GET → views）
$page_actions = [
    'login', 'dashboard',
    'rounds', 'round_detail',
    'products', 'product_edit',
    'suppliers', 'supplier_edit',
    'users', 'user_edit',
    'departments',
];

// API路由（POST → api）
$api_actions = [
    'do_login', 'do_logout',
    'round_create', 'round_complete',
    'purchase_status',
    'product_save', 'product_delete',
    'supplier_save',
    'user_save',
    'dept_save',
    'dept_breakdown',
];

if (!in_array($action, array_merge($page_actions, $api_actions))) {
    http_response_code(404);
    die('无效的操作');
}

if (in_array($action, $api_actions)) {
    $file = SPS_API_PATH . '/backend/' . $action . '.php';
    file_exists($file) ? require_once $file : sps_json(false, null, 'API not found');
} else {
    $is_backend = true;
    $file = SPS_VIEW_PATH . '/backend/' . $action . '.php';
    file_exists($file) ? require_once $file : die('Page not found');
}
