<?php
/**
 * SPS Bootstrap
 * 文件路径: app/sps/bootstrap.php
 */

if (!defined('SPS_ENTRY')) {
    die('Access denied');
}

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}

require_once __DIR__ . '/config_sps/env_sps.php';

try {
    $pdo = sps_get_db();
} catch (PDOException $e) {
    http_response_code(503);
    error_log('[SPS] DB connection failed: ' . $e->getMessage());
    die('系统维护中，请稍后再试。');
}

sps_session_start();
