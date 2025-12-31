<?php
/**
 * MRS 物料收发管理系统 - 后台API: 批量删除包裹（库存修正）
 * 文件路径: app/mrs/api/bulk_package_deletion.php
 * 说明: 批量输入快递单号，删除未出库的包裹
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 需要登录
mrs_require_login();

// 获取当前用户信息
$current_user = $_SESSION['username'] ?? 'unknown';

try {
    // 获取请求方法
    $request_method = $_SERVER['REQUEST_METHOD'];

    if ($request_method === 'POST') {
        // POST请求：检查或执行删除
        $json_input = file_get_contents('php://input');
        $data = json_decode($json_input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON input: ' . json_last_error_msg()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $action = $data['action'] ?? '';

        if ($action === 'check') {
            // 检查模式：分析快递单号
            $tracking_input = $data['tracking_input'] ?? '';

            if (empty($tracking_input)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '请输入快递单号'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 解析快递单号（支持换行、逗号、空格分隔）
            $tracking_numbers = preg_split('/[\s,，\r\n]+/', $tracking_input, -1, PREG_SPLIT_NO_EMPTY);

            if (empty($tracking_numbers)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '没有有效的快递单号'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 获取数据库连接
            $pdo = get_db_connection();

            // 调用检查函数
            $result = mrs_check_packages_for_deletion($pdo, $tracking_numbers);

            if (!$result['success']) {
                http_response_code(400);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 返回检查结果
            http_response_code(200);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } elseif ($action === 'delete') {
            // 删除模式：执行批量删除
            $ledger_ids = $data['ledger_ids'] ?? [];
            $reason = $data['reason'] ?? '';

            if (empty($ledger_ids)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '没有要删除的包裹'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (empty($reason)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '请输入删除原因'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 验证ledger_ids为整数数组
            $ledger_ids = array_map('intval', $ledger_ids);
            $ledger_ids = array_filter($ledger_ids, function($id) {
                return $id > 0;
            });

            if (empty($ledger_ids)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '无效的包裹ID'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 获取数据库连接
            $pdo = get_db_connection();

            // 调用删除函数
            $result = mrs_bulk_delete_packages($pdo, $ledger_ids, $current_user, $reason);

            if (!$result['success']) {
                http_response_code(400);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 返回删除结果
            http_response_code(200);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action. Use "check" or "delete"'
            ], JSON_UNESCAPED_UNICODE);
        }

    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

    // 记录错误日志
    mrs_log('Bulk package deletion API error: ' . $e->getMessage(), 'ERROR');
}
