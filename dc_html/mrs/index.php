<?php
/**
 * MRS 物料收发管理系统 - 前端入口
 * 文件路径: dc_html/mrs/index.php
 * 说明: 系统入口、路由分发
 */

// 定义入口常量
define('MRS_ENTRY', true);

// 加载配置文件
require_once __DIR__ . '/../../app/mrs/config_mrs/env_mrs.php';

// 加载业务库
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 获取 action 参数
$action = $_GET['action'] ?? 'inbound_quick';

// 路由分发
switch ($action) {
    case 'inbound_quick':
        // 极速入库页面
        require_once MRS_ACTION_PATH . '/inbound_quick.php';
        break;

    default:
        // 默认跳转到极速入库
        header('Location: index.php?action=inbound_quick');
        exit;
}
