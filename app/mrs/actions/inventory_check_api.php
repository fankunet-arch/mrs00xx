<?php
// Mobile inventory check unified JSON API
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mrs_json_response(false, null, '非法的请求方式');
}

mrs_require_login();

$data = mrs_get_json_input();
if (!is_array($data)) {
    $data = $_POST;
}

$op = $data['op'] ?? '';
$operator = $_SESSION['user_display_name'] ?? ($_SESSION['user_login'] ?? 'system');

if ($op === '') {
    mrs_json_response(false, null, '缺少操作类型');
}

switch ($op) {
    case 'search':
        handle_inventory_search($pdo, $data);
        break;
    case 'confirm':
        handle_inventory_confirm($pdo, $data, $operator);
        break;
    case 'update':
        handle_inventory_update($pdo, $data, $operator);
        break;
    case 'void':
        handle_inventory_void($pdo, $data, $operator);
        break;
    case 'create':
        handle_inventory_create($pdo, $data, $operator);
        break;
    default:
        mrs_json_response(false, null, '不支持的操作类型');
}

function handle_inventory_search(PDO $pdo, array $data): void
{
    $keyword = trim((string)($data['keyword'] ?? ''));
    $only_unchecked = isset($data['only_unchecked']) && (string)$data['only_unchecked'] !== ''
        ? (int)$data['only_unchecked'] === 1 || $data['only_unchecked'] === true
        : false;

    $sql = "SELECT ledger_id, box_number, tracking_number, batch_name, content_note, quantity, warehouse_location, last_counted_at, inbound_time, status
            FROM mrs_package_ledger
            WHERE status = 'in_stock'";
    $params = [];

    if ($keyword !== '') {
        $sql .= " AND (box_number LIKE :kw OR tracking_number LIKE :kw OR warehouse_location LIKE :kw OR content_note LIKE :kw OR batch_name LIKE :kw)";
        $params[':kw'] = '%' . $keyword . '%';
    }

    if ($only_unchecked) {
        $sql .= " AND (last_counted_at IS NULL OR last_counted_at < CURDATE())";
    }

    $sql .= " ORDER BY warehouse_location ASC, box_number ASC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    mrs_json_response(true, $rows);
}

function handle_inventory_confirm(PDO $pdo, array $data, string $operator): void
{
    $ledger_id = isset($data['ledger_id']) ? (int)$data['ledger_id'] : 0;
    if ($ledger_id <= 0) {
        mrs_json_response(false, null, '无效的台账ID');
    }

    $stmt = $pdo->prepare(
        "UPDATE mrs_package_ledger
         SET last_counted_at = NOW(),
             updated_by = :operator
         WHERE ledger_id = :ledger_id
           AND status = 'in_stock'"
    );

    $stmt->execute([
        ':ledger_id' => $ledger_id,
        ':operator' => $operator,
    ]);

    if ($stmt->rowCount() > 0) {
        mrs_json_response(true, null, '已确认盘点');
    }

    mrs_json_response(false, null, '未找到在库记录或状态已变更');
}

function handle_inventory_update(PDO $pdo, array $data, string $operator): void
{
    $ledger_id = isset($data['ledger_id']) ? (int)$data['ledger_id'] : 0;
    $qty = $data['qty'] ?? null;

    if ($ledger_id <= 0) {
        mrs_json_response(false, null, '无效的台账ID');
    }

    if (!is_numeric($qty)) {
        mrs_json_response(false, null, '数量必须为数字');
    }

    $stmt = $pdo->prepare(
        "UPDATE mrs_package_ledger
         SET quantity = :qty,
             last_counted_at = NOW(),
             updated_by = :operator
         WHERE ledger_id = :ledger_id
           AND status = 'in_stock'"
    );

    $stmt->execute([
        ':qty' => (int)$qty,
        ':ledger_id' => $ledger_id,
        ':operator' => $operator,
    ]);

    if ($stmt->rowCount() > 0) {
        mrs_json_response(true, null, '数量已更新');
    }

    mrs_json_response(false, null, '未找到在库记录或状态已变更');
}

function handle_inventory_void(PDO $pdo, array $data, string $operator): void
{
    $ledger_id = isset($data['ledger_id']) ? (int)$data['ledger_id'] : 0;
    $reason = $data['reason'] ?? null;

    if ($ledger_id <= 0) {
        mrs_json_response(false, null, '无效的台账ID');
    }

    $stmt = $pdo->prepare(
        "UPDATE mrs_package_ledger
         SET status = 'void',
             void_reason = COALESCE(:reason, '盘点确认丢失'),
             outbound_time = NOW(),
             last_counted_at = NOW(),
             updated_by = :operator
         WHERE ledger_id = :ledger_id
           AND status = 'in_stock'"
    );

    $stmt->execute([
        ':ledger_id' => $ledger_id,
        ':reason' => $reason !== '' ? $reason : null,
        ':operator' => $operator,
    ]);

    if ($stmt->rowCount() > 0) {
        mrs_json_response(true, null, '已标记丢失/作废');
    }

    mrs_json_response(false, null, '未找到在库记录或状态已变更');
}

function handle_inventory_create(PDO $pdo, array $data, string $operator): void
{
    $box_number = trim((string)($data['box_number'] ?? ''));
    $content_note = trim((string)($data['content_note'] ?? ''));
    $warehouse_location = trim((string)($data['warehouse_location'] ?? ''));
    $qty = $data['qty'] ?? null;

    if ($box_number === '') {
        mrs_json_response(false, null, '箱号不能为空');
    }

    if ($qty !== null && !is_numeric($qty)) {
        mrs_json_response(false, null, '数量必须为数字');
    }

    $timestamp_stmt = $pdo->query("SELECT DATE_FORMAT(NOW(), '%Y%m%d%H%i%s') AS ts");
    $timestamp = $timestamp_stmt->fetchColumn() ?: date('YmdHis');

    $batch_name = $data['batch_name'] ?? ('adhoc-' . $timestamp . '-' . random_int(100, 999));
    $tracking_number = $data['tracking_number'] ?? ('adhoc-' . bin2hex(random_bytes(16)));

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO mrs_package_ledger
            (batch_name, tracking_number, box_number, content_note, quantity, warehouse_location,
             status, inbound_time, last_counted_at, created_by, updated_by)
            VALUES
            (:batch_name, :tracking_number, :box_number, :content_note, :qty, :warehouse_location,
             'in_stock', NOW(), NOW(), :operator, :operator)"
        );

        $stmt->execute([
            ':batch_name' => $batch_name,
            ':tracking_number' => $tracking_number,
            ':box_number' => $box_number,
            ':content_note' => $content_note !== '' ? $content_note : null,
            ':qty' => $qty !== null ? (int)$qty : null,
            ':warehouse_location' => $warehouse_location !== '' ? $warehouse_location : null,
            ':operator' => $operator,
        ]);

        $new_id = (int)$pdo->lastInsertId();
        mrs_json_response(true, [
            'ledger_id' => $new_id,
            'batch_name' => $batch_name,
            'tracking_number' => $tracking_number,
        ], '已登记新包裹');
    } catch (PDOException $e) {
        mrs_json_response(false, null, '登记失败: ' . $e->getMessage());
    }
}
