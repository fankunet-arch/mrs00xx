<?php
/**
 * Express Package Management System - Bootstrap (Mock for Testing)
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

// Load mock configuration
require_once __DIR__ . '/config_express/env_express_mock.php';

// Get database connection (mock)
try {
    $pdo = get_express_db_connection();
} catch (Exception $e) {
    http_response_code(503);
    error_log('Critical: Express Database connection failed - ' . $e->getMessage());
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>系统错误</title></head><body><h1>系统维护中</h1><p>数据库连接失败，请稍后再试。</p></body></html>');
}

// Load core library
require_once EXPRESS_LIB_PATH . '/express_lib.php';

// Start session
express_start_secure_session();
