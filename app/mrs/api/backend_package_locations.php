<?php
/**
 * MRS 物料收发管理系统 - 后台API: 箱子位置管理
 * 文件路径: app/mrs/api/backend_package_locations.php
 * 说明: 箱子位置查询、修改、批量修改
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置和库文件
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

try {
    // 获取数据库连接
    $pdo = get_mrs_db_connection();

    // 获取请求方法和操作类型
    $method = $_SERVER['REQUEST_METHOD'];
    $operation = $_GET['operation'] ?? $_POST['operation'] ?? 'list';

    switch ($operation) {
        case 'list':
            // 查询箱子位置列表
            handleListLocations($pdo);
            break;

        case 'update':
            // 更新单个箱子位置
            handleUpdateLocation($pdo);
            break;

        case 'batch_update':
            // 批量更新箱子位置
            handleBatchUpdateLocation($pdo);
            break;

        default:
            json_response(false, null, '无效的操作类型');
            break;
    }

} catch (Exception $e) {
    mrs_log('箱子位置管理API错误: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '服务器错误');
}

/**
 * 查询箱子位置列表
 */
function handleListLocations($pdo) {
    // 获取查询参数
    $box_number = trim($_GET['box_number'] ?? '');
    $location = trim($_GET['location'] ?? '');
    $batch_name = trim($_GET['batch_name'] ?? '');
    $status = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // 构建查询条件
    $where = [];
    $params = [];

    if ($box_number !== '') {
        $where[] = "box_number LIKE :box_number";
        $params[':box_number'] = '%' . $box_number . '%';
    }

    if ($location !== '') {
        $where[] = "warehouse_location LIKE :location";
        $params[':location'] = '%' . $location . '%';
    }

    if ($batch_name !== '') {
        $where[] = "batch_name LIKE :batch_name";
        $params[':batch_name'] = '%' . $batch_name . '%';
    }

    if ($status !== '') {
        $where[] = "status = :status";
        $params[':status'] = $status;
    }

    $where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // 查询总数
    $count_sql = "SELECT COUNT(*) FROM mrs_package_ledger $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn();

    // 查询数据
    $sql = "SELECT
                ledger_id,
                box_number,
                batch_name,
                tracking_number,
                warehouse_location,
                content_note,
                quantity,
                status,
                inbound_time,
                updated_at
            FROM mrs_package_ledger
            $where_clause
            ORDER BY inbound_time DESC, ledger_id DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 返回结果
    json_response(true, [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ], '查询成功');
}

/**
 * 更新单个箱子位置
 */
function handleUpdateLocation($pdo) {
    // 获取输入数据
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        json_response(false, null, '无效的请求数据');
    }

    $ledger_id = $input['ledger_id'] ?? null;
    $new_location = trim($input['new_location'] ?? '');

    // 验证参数
    if (!$ledger_id || !is_numeric($ledger_id)) {
        json_response(false, null, '无效的箱子ID');
    }

    if ($new_location === '') {
        json_response(false, null, '新位置不能为空');
    }

    // 验证格式 (XX-XX-XX)
    if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $new_location)) {
        json_response(false, null, '位置格式错误，应为：XX-XX-XX (如 01-02-03)');
    }

    // 更新位置
    $stmt = $pdo->prepare("
        UPDATE mrs_package_ledger
        SET warehouse_location = :location,
            updated_at = NOW()
        WHERE ledger_id = :ledger_id
    ");

    $stmt->bindValue(':location', $new_location);
    $stmt->bindValue(':ledger_id', (int)$ledger_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        mrs_log('箱子位置已更新', 'INFO', [
            'ledger_id' => $ledger_id,
            'new_location' => $new_location
        ]);
        json_response(true, null, '位置更新成功');
    } else {
        json_response(false, null, '更新失败或箱子不存在');
    }
}

/**
 * 批量更新箱子位置
 */
function handleBatchUpdateLocation($pdo) {
    // 获取输入数据
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        json_response(false, null, '无效的请求数据');
    }

    $ledger_ids = $input['ledger_ids'] ?? [];
    $new_location = trim($input['new_location'] ?? '');

    // 验证参数
    if (empty($ledger_ids) || !is_array($ledger_ids)) {
        json_response(false, null, '请选择要更新的箱子');
    }

    if ($new_location === '') {
        json_response(false, null, '新位置不能为空');
    }

    // 验证格式 (XX-XX-XX)
    if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $new_location)) {
        json_response(false, null, '位置格式错误，应为：XX-XX-XX (如 01-02-03)');
    }

    // 过滤和验证ID
    $ledger_ids = array_filter($ledger_ids, function($id) {
        return is_numeric($id) && $id > 0;
    });

    if (empty($ledger_ids)) {
        json_response(false, null, '没有有效的箱子ID');
    }

    // 构建批量更新SQL
    $placeholders = implode(',', array_fill(0, count($ledger_ids), '?'));
    $stmt = $pdo->prepare("
        UPDATE mrs_package_ledger
        SET warehouse_location = ?,
            updated_at = NOW()
        WHERE ledger_id IN ($placeholders)
    ");

    // 绑定参数
    $params = array_merge([$new_location], array_values($ledger_ids));
    $stmt->execute($params);

    $affected = $stmt->rowCount();

    if ($affected > 0) {
        mrs_log('批量更新箱子位置', 'INFO', [
            'count' => $affected,
            'new_location' => $new_location,
            'ledger_ids' => $ledger_ids
        ]);
        json_response(true, ['affected' => $affected], "成功更新 {$affected} 个箱子的位置");
    } else {
        json_response(false, null, '更新失败，请检查箱子是否存在');
    }
}
