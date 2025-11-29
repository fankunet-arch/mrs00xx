<?php
/**
 * Express Package Management System - Frontend Router
 * 文件路径: dc_html/express/index.php
 * 说明: 前台中央路由入口（网络可访问）
 */

// 定义系统入口标识
define('EXPRESS_ENTRY', true);

// 定义项目根目录（dc_html的上级目录）
define('PROJECT_ROOT', dirname(dirname(__DIR__)));

// 加载bootstrap（在app目录中）
// 使用mock bootstrap进行测试
if (file_exists(PROJECT_ROOT . '/app/express/bootstrap_mock.php')) {
    require_once PROJECT_ROOT . '/app/express/bootstrap_mock.php';
} else {
    require_once PROJECT_ROOT . '/app/express/bootstrap.php';
}

// 获取action参数
$action = $_GET['action'] ?? 'quick_ops';
$action = basename($action); // 防止路径遍历

// 前台允许的action列表
$allowed_actions = [
    'quick_ops',                // 前台操作页面
    'get_batches_api',          // 获取批次列表API
    'search_tracking_api',      // 搜索快递单号API
    'save_record_api',          // 保存操作记录API
    'get_packages_api',         // 获取包裹列表API
    'get_batch_detail_api'      // 获取批次详情API
];

// 验证action是否允许
if (!in_array($action, $allowed_actions)) {
    $accepts_json = isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($accepts_json || $is_ajax) {
        express_json_response(false, null, 'Invalid action');
    }

    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 - Page Not Found</title>';
    echo '<style>body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:40px;}';
    echo '.card{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}';
    echo '.card h1{margin-top:0;font-size:22px;color:#c62828;} .card p{color:#444;line-height:1.6;} .card a{color:#1565c0;text-decoration:none;font-weight:600;}</style>';
    echo '</head><body><div class="card"><h1>404 - 无效的前台入口</h1><p>请求的操作未被允许或链接已失效。</p>';
    echo '<p><a href="/express/index.php?action=quick_ops">返回前台首页</a></p></div></body></html>';
    exit;
}

// 路由到对应的action文件（在app目录中）
$action_file = EXPRESS_ACTION_PATH . '/' . $action . '.php';

if (file_exists($action_file)) {
    require_once $action_file;
} else {
    http_response_code(404);
    die('Action not found');
}
