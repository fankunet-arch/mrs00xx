<?php
/**
 * MRS Package Management System - Frontend Router
 * 文件路径: dc_html/mrs/index.php
 * 说明: 前台中央路由入口（网络可访问）
 */

// 定义系统入口标识
define('MRS_ENTRY', true);

// 定义项目根目录（dc_html的上级目录）
define('PROJECT_ROOT', dirname(dirname(__DIR__)));

// 加载bootstrap（在app目录中）
require_once PROJECT_ROOT . '/app/mrs/bootstrap.php';

// 获取action参数
$action = $_GET['action'] ?? 'count_home';
$action = basename($action); // 防止路径遍历

// 前台允许的action列表
$allowed_actions = [
    'count_home',              // 清点首页
    'count_ops',               // 清点操作页面
    'count_create_session',    // 创建清点任务API
    'count_get_sessions',      // 获取任务列表API
    'count_search_box',        // 搜索箱号API
    'count_autocomplete_box',  // 自动完成搜索箱号API
    'count_save_record',       // 保存清点记录API
    'count_finish_session',    // 完成清点任务API
    'count_get_recent',        // 获取最近记录API
    'count_quick_add_box',     // 快速录入新箱API
];

// 验证action是否允许
if (!in_array($action, $allowed_actions)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 - Page Not Found</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:40px;}';
    echo '.card{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}';
    echo '.card h1{margin-top:0;font-size:22px;color:#c62828;} .card p{color:#444;line-height:1.6;} .card a{color:#1565c0;text-decoration:none;font-weight:600;}</style>';
    echo '</head><body><div class="card"><h1>404 - 无效的前台入口</h1><p>请求的操作未被允许或链接已失效。</p>';
    echo '<p><a href="/mrs/index.php">返回清点首页</a></p></div></body></html>';
    exit;
}

// API action（返回JSON）
$api_actions = [
    'count_create_session',
    'count_get_sessions',
    'count_search_box',
    'count_autocomplete_box',
    'count_save_record',
    'count_finish_session',
    'count_get_recent',
    'count_quick_add_box',
];

// 路由到对应的action或API文件（在app目录中）
if (in_array($action, $api_actions)) {
    // API路由
    $api_file = MRS_API_PATH . '/' . $action . '.php';
    if (file_exists($api_file)) {
        require_once $api_file;
    } else {
        mrs_json_response(false, null, 'API not found');
    }
} else {
    // 页面路由
    $action_file = MRS_ACTION_PATH . '/' . $action . '.php';
    if (file_exists($action_file)) {
        require_once $action_file;
    } else {
        http_response_code(404);
        die('Action not found');
    }
}
