<?php
// MRS bootstrap.php (v3 - Corrected)

// 1. Core Settings
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

// 2. Path Constants
define('MRS_APP_ROOT', dirname(__FILE__));
define('MRS_PROJECT_ROOT', dirname(MRS_APP_ROOT));
define('MRS_PUBLIC_ROOT', dirname(MRS_PROJECT_ROOT) . '/dc_html');
define('MRS_ACTION_PATH', MRS_APP_ROOT . '/actions');
define('MRS_VIEW_PATH', MRS_APP_ROOT . '/views');
define('MRS_LIB_PATH', MRS_APP_ROOT . '/lib');
define('MRS_LOG_PATH', dirname(MRS_PROJECT_ROOT) . '/logs/mrs');
ini_set('error_log', MRS_LOG_PATH . '/error.log');

// 3. Database Connection
// Support runtime switch to the SQLite test configuration via MRS_ENV=test
$config_env = getenv('MRS_ENV') ?: 'prod';
$config_file = $config_env === 'test'
    ? MRS_APP_ROOT . '/config_mrs/env_mrs_test.php'
    : MRS_APP_ROOT . '/config_mrs/env_mrs.php';

require_once $config_file;

// Global PDO instance with error handling
try {
    $pdo = get_db_connection();
} catch (PDOException $e) {
    http_response_code(503);
    error_log('Critical: Database connection failed - ' . $e->getMessage());
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>系统错误</title></head><body><h1>系统维护中</h1><p>数据库连接失败,请稍后再试。</p></body></html>');
}

// 4. Core Library & Session Start
require_once MRS_LIB_PATH . '/mrs_lib.php';
start_secure_session();
