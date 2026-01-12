<?php
/**
 * MRS Package Management System - Backend Router
 * 文件路径: dc_html/mrs/ap/index.php
 * 说明: 后台管理中央路由入口 (网络可访问)
 */

// 定义系统入口标识
define('MRS_ENTRY', true);

// 定义项目根目录 (dc_html的上级目录)
define('PROJECT_ROOT', dirname(dirname(dirname(__DIR__))));

// 加载bootstrap (在app目录中)
require_once PROJECT_ROOT . '/app/mrs/bootstrap.php';

// 获取action参数
$action = $_GET['action'] ?? 'inventory_list';
$action = basename($action); // 防止路径遍历

// 身份验证: 所有非登录操作必须经过会话校验
if ($action !== 'login' && $action !== 'do_login') {
    mrs_require_login();
}

// 后台允许的action列表
$allowed_actions = [
    // === 认证相关 ===
    'login',                // 登录页面
    'do_login',             // 处理登录
    'logout',               // 登出

    // === 仪表板 ===
    'dashboard',            // 后台首页仪表板
    'backend_dashboard',    // 后台管理仪表板
    'backend_manage',       // 后台管理中心

    // === 库存管理 ===
    'inventory_list',       // 库存列表
    'inventory_detail',     // 库存明细

    // === 入库管理 ===
    'inbound',              // 入库页面
    'inbound_split',        // 拆分入库页面
    'inbound_save',         // 保存入库
    'inbound_split_save',   // 拆分入库保存

    // === 出库管理 ===
    'outbound',             // 出库页面
    'outbound_list',        // 出库单列表
    'outbound_detail',      // 出库单详情
    'outbound_create',      // 创建出库单
    'outbound_save',        // 保存出库
    'partial_outbound',     // 拆零出货
    'debug_partial_outbound', // 拆零出货调试

    // === 批次管理 ===
    'batch_list',           // 批次列表
    'batch_create',         // 新建批次
    'batch_create_save',    // 保存新建批次
    'batch_detail',         // 批次详情
    'batch_edit',           // 编辑批次
    'batch_save',           // 保存批次
    'batch_print',          // 批次箱贴打印
    'get_batch_list_api',   // 批次列表API
    'get_batch_records_api', // 批次记录API

    // === SKU管理 ===
    'sku_list',             // SKU列表
    'sku_edit',             // 编辑SKU
    'sku_manage',           // 物料管理
    'sku_save',             // 保存物料
    'sku_save_api',         // 保存SKU API
    'sku_toggle_status',    // 切换SKU状态
    'sku_search_api',       // SKU搜索API

    // === 分类管理 ===
    'category_list',        // 分类列表
    'category_edit',        // 编辑分类
    'category_save',        // 保存分类

    // === 去向管理 ===
    'destination_manage',   // 去向管理
    'destination_save',     // 保存去向

    // === 统计报表 ===
    'reports',              // 统计报表
    'usage_statistics',     // 用量统计

    // === 包裹管理 ===
    'status_change',        // 状态变更
    'update_package',       // 修改包裹信息
    'get_package_items',    // 获取包裹产品明细
    'package_locations',    // 货架位置管理
    'update_package_location', // 更新单个箱子位置
    'batch_update_locations', // 批量更新箱子位置
    'bulk_package_deletion', // 批量删除包裹（库存修正）

    // === API接口 ===
    'box_search_api',       // 箱子搜索API
    'product_search_api',   // 产品搜索API
    'product_name_autocomplete', // 产品名称自动完成
    'backend_package_locations', // 箱子位置管理API
    'backend_bulk_deletion', // 批量删除包裹API
];

// 验证action是否允许
if (!in_array($action, $allowed_actions)) {
    $accepts_json = isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($accepts_json || $is_ajax) {
        mrs_json_response(false, null, 'Invalid action');
    }

    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 - Page Not Found</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:40px;}';
    echo '.card{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}';
    echo '.card h1{margin-top:0;font-size:22px;color:#c62828;} .card p{color:#444;line-height:1.6;} .card a{color:#1565c0;text-decoration:none;font-weight:600;}</style>';
    echo '</head><body><div class="card"><h1>404 - 无效的后台入口</h1><p>请求的操作未被允许或链接已失效。</p>';
    echo '<p><a href="/mrs/ap/index.php?action=inventory_list">返回后台首页</a></p></div></body></html>';
    exit;
}

// API action (返回JSON)
$api_actions = [
    'do_login',
    'logout',
    'inbound_save',
    'inbound_split_save',
    'outbound_save',
    'partial_outbound',
    'usage_statistics',
    'sku_save',
    'sku_save_api',
    'sku_toggle_status',
    'category_save',
    'batch_save',
    'batch_create_save',
    'status_change',
    'update_package',
    'get_package_items',
    'destination_save',
    'box_search_api',
    'product_search_api',
    'sku_search_api',
    'get_batch_list_api',
    'get_batch_records_api',
    'product_name_autocomplete',
    'update_package_location',
    'batch_update_locations',
    'backend_package_locations',
    'backend_bulk_deletion',
];

// 路由到对应的action或API文件 (在app目录中)
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
    $view_file = MRS_VIEW_PATH . '/' . $action . '.php';
    if (file_exists($view_file)) {
        require_once $view_file;
    } else {
        http_response_code(404);
        die('Page not found');
    }
}