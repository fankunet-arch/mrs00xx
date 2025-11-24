<?php
/**
 * MRS 物料收发管理系统 - 后台管理入口
 * 文件路径: dc_html/mrs/backend.php
 * 说明: 后台管理系统入口、路由分发
 */

// 定义入口常量
define('MRS_ENTRY', true);
define('MRS_BACKEND', true);

// 加载配置文件
require_once __DIR__ . '/../../app/mrs/config_mrs/env_mrs.php';

// 加载业务库
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 获取 action 参数
$action = $_GET['action'] ?? 'dashboard';

// 路由分发
switch ($action) {
    case 'dashboard':
        // 后台主控制台
        require_once MRS_ACTION_PATH . '/backend_dashboard.php';
        break;

    default:
        // 默认跳转到控制台
        header('Location: backend.php?action=dashboard');
        exit;
}
