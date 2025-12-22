<?php
// Unified API for mobile inventory check

mrs_require_login();

header('Content-Type: application/json; charset=utf-8');

$input = mrs_get_json_input();
if ($input === null) {
    $input = $_POST;
}

$op = $input['op'] ?? null;
if ($op === null) {
    mrs_json_response(false, null, 'Missing operation');
}

$pdo = get_db_connection();
$operator = $_SESSION['user_login'] ?? 'system';

try {
    switch ($op) {
        case 'search':
            handle_search($pdo, $input);
            break;
        case 'confirm':
            handle_confirm($pdo, $input, $operator);
            break;
        case 'update':
            handle_update($pdo, $input, $operator);
            break;
        case 'void':
            handle_void($pdo, $input, $operator);
            break;
        case 'create':
            handle_create($pdo, $input, $operator);
            break;
        default:
            mrs_json_response(false, null, 'Unsupported op');
    }
} catch (PDOException $e) {
    mrs_json_response(false, null, 'Database error: ' . $e->getMessage());
}

function handle_search(PDO $pdo, array $input): void {
    $keyword = trim($input['keyword'] ?? '');
    $onlyUnchecked = !empty($input['only_unchecked']);

    $where = ["status = 'in_stock'"];
    $params = [];

    if ($onlyUnchecked) {
        $where[] = '(last_counted_at IS NULL OR last_counted_at < CURDATE())';
    }

    if ($keyword !== '') {
        $where[] = '(
            box_number LIKE :kw OR
            tracking_number LIKE :kw OR
            warehouse_location LIKE :kw OR
            content_note LIKE :kw OR
            batch_name LIKE :kw
        )';
        $params[':kw'] = '%' . $keyword . '%';
    }

    $sql = "SELECT
                ledger_id,
                box_number,
                tracking_number,
                batch_name,
                content_note,
                quantity,
                warehouse_location,
                last_counted_at,
                inbound_time,
                status
            FROM mrs_package_ledger
            WHERE " . implode(' AND ', $where) . "
            ORDER BY warehouse_location ASC, box_number ASC
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mrs_json_response(true, ['items' => $items], 'ok');
}

function handle_confirm(PDO $pdo, array $input, string $operator): void {
    $ledgerId = (int)($input['ledger_id'] ?? 0);
    if ($ledgerId <= 0) {
        mrs_json_response(false, null, '缺少 ledger_id');
    }

    $stmt = $pdo->prepare("UPDATE mrs_package_ledger
        SET last_counted_at = NOW(),
            updated_by = :operator
        WHERE ledger_id = :ledger_id
          AND status = 'in_stock'");
    $stmt->bindValue(':operator', $operator);
    $stmt->bindValue(':ledger_id', $ledgerId, PDO::PARAM_INT);
    $stmt->execute();

    mrs_json_response(true, ['affected' => $stmt->rowCount()], '确认成功');
}

function handle_update(PDO $pdo, array $input, string $operator): void {
    $ledgerId = (int)($input['ledger_id'] ?? 0);
    $qty = $input['qty'] ?? null;
    if ($ledgerId <= 0 || $qty === null || $qty === '') {
        mrs_json_response(false, null, '缺少参数');
    }

    $stmt = $pdo->prepare("UPDATE mrs_package_ledger
        SET quantity = :qty,
            last_counted_at = NOW(),
            updated_by = :operator
        WHERE ledger_id = :ledger_id
          AND status = 'in_stock'");
    $stmt->bindValue(':qty', $qty);
    $stmt->bindValue(':operator', $operator);
    $stmt->bindValue(':ledger_id', $ledgerId, PDO::PARAM_INT);
    $stmt->execute();

    mrs_json_response(true, ['affected' => $stmt->rowCount()], '修改成功');
}

function handle_void(PDO $pdo, array $input, string $operator): void {
    $ledgerId = (int)($input['ledger_id'] ?? 0);
    if ($ledgerId <= 0) {
        mrs_json_response(false, null, '缺少 ledger_id');
    }
    $reason = $input['reason'] ?? null;

    $stmt = $pdo->prepare("UPDATE mrs_package_ledger
        SET status = 'void',
            void_reason = COALESCE(:reason, '盘点确认丢失'),
            outbound_time = NOW(),
            last_counted_at = NOW(),
            updated_by = :operator
        WHERE ledger_id = :ledger_id
          AND status = 'in_stock'");
    $stmt->bindValue(':reason', $reason);
    $stmt->bindValue(':operator', $operator);
    $stmt->bindValue(':ledger_id', $ledgerId, PDO::PARAM_INT);
    $stmt->execute();

    mrs_json_response(true, ['affected' => $stmt->rowCount()], '标记成功');
}

function handle_create(PDO $pdo, array $input, string $operator): void {
    $boxNumber = trim($input['box_num'] ?? '');
    $contentNote = trim($input['content_note'] ?? '');
    $qty = $input['qty'] ?? null;
    $warehouseLocation = trim($input['warehouse_location'] ?? '');
    $batchName = isset($input['batch_name']) && $input['batch_name'] !== '' ? trim($input['batch_name']) : null;
    $trackingNumber = isset($input['tracking_number']) && $input['tracking_number'] !== '' ? trim($input['tracking_number']) : null;

    if ($boxNumber === '') {
        mrs_json_response(false, null, '箱号必填');
    }

    if ($batchName === null) {
        $batchName = generate_batch_name();
    }
    if ($trackingNumber === null) {
        $trackingNumber = generate_tracking_number();
    }

    $stmt = $pdo->prepare("INSERT INTO mrs_package_ledger
        (batch_name, tracking_number, box_number, content_note, quantity, warehouse_location,
         status, inbound_time, last_counted_at, created_by, updated_by)
        VALUES
        (:batch_name, :tracking_number, :box_number, :content_note, :qty, :warehouse_location,
         'in_stock', NOW(), NOW(), :operator, :operator)");
    $stmt->bindValue(':batch_name', $batchName);
    $stmt->bindValue(':tracking_number', $trackingNumber);
    $stmt->bindValue(':box_number', $boxNumber);
    $stmt->bindValue(':content_note', $contentNote);
    $stmt->bindValue(':qty', $qty);
    $stmt->bindValue(':warehouse_location', $warehouseLocation);
    $stmt->bindValue(':operator', $operator);
    $stmt->execute();

    mrs_json_response(true, ['ledger_id' => $pdo->lastInsertId()], '创建成功');
}

function generate_batch_name(): string {
    return 'adhoc-' . date('YmdHis') . '-' . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
}

function generate_tracking_number(): string {
    return 'adhoc-' . bin2hex(random_bytes(16));
}
