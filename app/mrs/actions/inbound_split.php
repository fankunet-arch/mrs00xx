<?php
/**
 * MRS Action: inbound_split.php
 * 拆分入库页面（从 Express 批次拆分入库到 SKU 系统）
 * 文件路径: app/mrs/actions/inbound_split.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 需要登录
mrs_mrs_require_login();

$page_title = "拆分入库";
$action = 'inbound_split';

// 加载视图
require_once MRS_VIEW_PATH . '/inbound_split.php';
