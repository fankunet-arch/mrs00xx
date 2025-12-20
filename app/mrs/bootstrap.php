<?php
/**
 * MRS Package Management System - Bootstrap
 * 文件路径: app/mrs/bootstrap.php
 * 说明: 系统初始化,加载配置和核心库
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 定义PROJECT_ROOT常量(如果未定义)
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}

// 定义MRS_ACTION_PATH常量(如果未定义)
if (!defined('MRS_ACTION_PATH')) {
    define('MRS_ACTION_PATH', __DIR__ . '/actions');
}

// 1. 加载配置文件
require_once __DIR__ . '/config_mrs/env_mrs.php';

// 2. 获取数据库连接
// [IMPORTANT] $pdo 变量通过 require 作用域继承到 actions 和 views
// 推荐做法：在需要时调用 get_mrs_db_connection() 而非依赖全局变量
// 当前保留此方式以保持向后兼容性
try {
    $pdo = get_mrs_db_connection();
} catch (PDOException $e) {
    http_response_code(503);
    error_log('Critical: MRS Database connection failed - ' . $e->getMessage());
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>系统错误</title></head><body><h1>系统维护中</h1><p>数据库连接失败,请稍后再试。</p></body></html>');
}

// 3. 加载核心业务库
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 4. 启动会话
mrs_start_secure_session();
