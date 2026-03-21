<?php
/**
 * SPS System Configuration
 * 文件路径: app/sps/config_sps/env_sps.php
 */

if (!defined('SPS_ENTRY')) {
    die('Access denied');
}

// 数据库配置（与MRS共用同一数据库）
define('SPS_DB_HOST',    'mhdlmskp2kpxguj.mysql.db');
define('SPS_DB_PORT',    '3306');
define('SPS_DB_NAME',    'mhdlmskp2kpxguj');
define('SPS_DB_USER',    'mhdlmskp2kpxguj');
define('SPS_DB_PASS',    'BWNrmksqMEqgbX37r3QNDJLGRrUka');
define('SPS_DB_CHARSET', 'utf8mb4');

// 路径常量
define('SPS_APP_PATH',    PROJECT_ROOT . '/app/sps');
define('SPS_VIEW_PATH',   SPS_APP_PATH . '/views');
define('SPS_API_PATH',    SPS_APP_PATH . '/api');

// 会话（独立命名，与MRS隔离）
define('SPS_SESSION_NAME',    'SPS_SESSION');
define('SPS_SESSION_TIMEOUT', 7200); // 2小时

/**
 * 获取数据库连接（静态单例）
 */
function sps_get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        SPS_DB_HOST, SPS_DB_PORT, SPS_DB_NAME, SPS_DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, SPS_DB_USER, SPS_DB_PASS, $options);
    return $pdo;
}

/**
 * 返回JSON响应并退出
 */
function sps_json(bool $success, $data = null, string $message = ''): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 读取JSON请求体
 */
function sps_json_input(): ?array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return null;
    return json_decode($raw, true);
}

/**
 * 启动SPS独立会话
 */
function sps_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(SPS_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * 检查是否已登录
 */
function sps_is_logged_in(): bool {
    return !empty($_SESSION['sps_user_id']);
}

/**
 * 检查是否为管理员
 */
function sps_is_admin(): bool {
    return ($_SESSION['sps_role'] ?? '') === 'admin';
}

/**
 * 要求登录（否则跳转）
 */
function sps_require_login(string $redirect = '/sps/index.php?action=login'): void {
    if (!sps_is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * 要求管理员（否则跳转）
 */
function sps_require_admin(): void {
    if (!sps_is_logged_in() || !sps_is_admin()) {
        header('Location: /sps/ap/index.php?action=login');
        exit;
    }
}

/**
 * 记录日志
 */
function sps_log(string $message, string $level = 'INFO'): void {
    error_log("[SPS][$level] $message");
}
