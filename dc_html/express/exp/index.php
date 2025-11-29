<?php
/**
 * Express Package Management System - Backend Router
 * 文件路径: dc_html/express/exp/index.php
 * 说明: 后台管理中央路由入口（网络可访问）
 */

// 定义系统入口标识
define('EXPRESS_ENTRY', true);

// 定义项目根目录（dc_html的上级的上级目录）
define('PROJECT_ROOT', dirname(dirname(dirname(__DIR__))));

// 加载bootstrap（在app目录中）
// 使用mock bootstrap进行测试
if (file_exists(PROJECT_ROOT . '/app/express/bootstrap_mock.php')) {
    require_once PROJECT_ROOT . '/app/express/bootstrap_mock.php';
} else {
    require_once PROJECT_ROOT . '/app/express/bootstrap.php';
}

// 获取action参数
$action = $_GET['action'] ?? 'batch_list';
$action = basename($action); // 防止路径遍历

// 身份验证：所有非登录操作必须经过MRS一致的会话校验
if ($action !== 'login' && $action !== 'do_login') {
    express_require_login();
}

// 后台允许的action列表
$allowed_actions = [
    'login',                    // 登录页面
    'do_login',                 // 处理登录
    'logout',                   // 登出
    'batch_list',               // 批次列表
    'batch_detail',             // 批次详情
    'batch_create',             // 创建批次页面
    'batch_create_save',        // 保存新批次
    'bulk_import',              // 批量导入页面
    'bulk_import_save',         // 保存批量导入
    'content_search',           // 内容备注搜索页面
    'content_search_api',       // 内容备注搜索API
    'update_content_note'       // 更新内容备注API
];

// 验证action是否允许
if (!in_array($action, $allowed_actions)) {
    http_response_code(404);
    die('Invalid action');
}

// API action（返回JSON）
$api_actions = [
    'do_login',
    'batch_create_save',
    'bulk_import_save',
    'logout',
    'content_search_api',
    'update_content_note'
];

// 路由到对应的action或API文件（在app目录中）
if (in_array($action, $api_actions)) {
    // API路由
    $api_file = EXPRESS_API_PATH . '/' . $action . '.php';
    if (file_exists($api_file)) {
        require_once $api_file;
    } else {
        express_json_response(false, null, 'API not found');
    }
} else {
    // 页面路由
    $view_file = EXPRESS_VIEW_PATH . '/' . $action . '.php';
    if (file_exists($view_file)) {
        require_once $view_file;
    } else {
        http_response_code(404);
        die('Page not found');
    }
}
