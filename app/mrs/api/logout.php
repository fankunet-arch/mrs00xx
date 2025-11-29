<?php
/**
 * MRS 物料收发管理系统 - 注销登录
 * 文件路径: app/mrs/api/logout.php
 * 说明: 处理用户注销请求
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    $user = mrs_get_current_user();

    if ($user) {
        mrs_log("用户注销: {$user['user_login']}", 'INFO', [
            'user_id' => $user['user_id']
        ]);
    }

    // 销毁会话
    destroy_user_session();

    // 删除"记住我"cookie
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
    }

    // 跳转到登录页面
    header('Location: login.php?error=logout');
    exit;

} catch (Exception $e) {
    mrs_log('注销处理异常: ' . $e->getMessage(), 'ERROR');
    header('Location: login.php');
    exit;
}
