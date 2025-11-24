<?php
/**
 * MRS 物料收发管理系统 - 配置文件
 * 文件路径: app/mrs/config_mrs/env_mrs.php
 * 说明: 数据库连接、路径常量、系统配置
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// ============================================
// 数据库配置
// ============================================

define('DB_HOST', 'mhdlmskp2kpxguj.mysql.db');
define('DB_NAME', 'mhdlmskp2kpxguj');
define('DB_USER', 'mhdlmskp2kpxguj');
define('DB_PASS', ''); // TODO: 填入实际密码
define('DB_CHARSET', 'utf8mb4');

// ============================================
// 路径常量
// ============================================

// 应用根目录
define('MRS_APP_PATH', dirname(dirname(__FILE__)));

// 配置目录
define('MRS_CONFIG_PATH', MRS_APP_PATH . '/config_mrs');

// 业务库目录
define('MRS_LIB_PATH', MRS_APP_PATH . '/lib');

// 控制器目录
define('MRS_ACTION_PATH', MRS_APP_PATH . '/actions');

// API目录
define('MRS_API_PATH', MRS_APP_PATH . '/api');

// 日志目录
define('MRS_LOG_PATH', dirname(dirname(MRS_APP_PATH)) . '/logs/mrs');

// Web根目录 (dc_html/mrs)
define('MRS_WEB_ROOT', dirname(dirname(dirname(MRS_APP_PATH))) . '/dc_html/mrs');

// ============================================
// 系统配置
// ============================================

// 时区设置
date_default_timezone_set('UTC');

// 错误报告级别
error_reporting(E_ALL);
ini_set('display_errors', '0'); // 生产环境设为0
ini_set('log_errors', '1');
ini_set('error_log', MRS_LOG_PATH . '/error.log');

// 数据库连接配置
$db_config = [
    'host' => DB_HOST,
    'dbname' => DB_NAME,
    'user' => DB_USER,
    'pass' => DB_PASS,
    'charset' => DB_CHARSET,
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

// ============================================
// 数据库连接函数
// ============================================

/**
 * 获取数据库PDO连接
 * @return PDO
 * @throws PDOException
 */
function get_db_connection() {
    global $db_config;

    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $db_config['host'],
                $db_config['dbname'],
                $db_config['charset']
            );

            $pdo = new PDO(
                $dsn,
                $db_config['user'],
                $db_config['pass'],
                $db_config['options']
            );
        } catch (PDOException $e) {
            // 记录错误日志
            error_log('Database connection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    return $pdo;
}

// ============================================
// 日志函数
// ============================================

/**
 * 写入日志
 * @param string $message 日志消息
 * @param string $level 日志级别 (INFO, WARNING, ERROR)
 * @param array $context 上下文数据
 */
function mrs_log($message, $level = 'INFO', $context = []) {
    $log_file = MRS_LOG_PATH . '/debug.log';

    // 确保日志目录存在
    if (!is_dir(MRS_LOG_PATH)) {
        mkdir(MRS_LOG_PATH, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $log_line = sprintf("[%s] [%s] %s%s\n", $timestamp, $level, $message, $context_str);

    file_put_contents($log_file, $log_line, FILE_APPEND);
}

// ============================================
// 辅助函数
// ============================================

/**
 * 输出JSON响应
 * @param bool $success 成功标志
 * @param mixed $data 响应数据
 * @param string $message 消息
 */
function json_response($success, $data = null, $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 获取POST JSON数据
 * @return array|null
 */
function get_json_input() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}
