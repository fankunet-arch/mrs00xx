<?php
/**
 * MRS Package Management System - Core Library
 * 文件路径: app/mrs/lib/mrs_lib.php
 * 说明: 核心业务逻辑函数
 *
 * 函数命名规范:
 * - 所有MRS业务函数使用 mrs_ 前缀（如 mrs_authenticate_user, mrs_get_pagination_params）
 * - 为了向后兼容，部分核心函数提供不带前缀的别名（定义在 env_mrs.php 中）
 * - 别名函数列表: get_db_connection, json_response, get_json_input, start_secure_session 等
 * - 新增函数建议统一使用 mrs_ 前缀以避免命名空间污染
 */

// ============================================
// 认证相关函数 (共享用户数据库)
// ============================================

/**
 * 验证用户登录
 * @param PDO $pdo
 * @param string $username
 * @param string $password
 * @return array|false
 */
function mrs_authenticate_user($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, user_login, user_secret_hash, user_email, user_display_name, user_status FROM sys_users WHERE user_login = :username LIMIT 1");
        $stmt->bindValue(':username', $username);
        $stmt->execute();

        $user = $stmt->fetch();

        if (!$user) {
            mrs_log("登录失败: 用户不存在 - {$username}", 'WARNING');
            return false;
        }

        if ($user['user_status'] !== 'active') {
            mrs_log("登录失败: 账户未激活 - {$username}", 'WARNING');
            return false;
        }

        if (password_verify($password, $user['user_secret_hash'])) {
            $update = $pdo->prepare("UPDATE sys_users SET user_last_login_at = NOW(6) WHERE user_id = :user_id");
            $update->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
            $update->execute();

            unset($user['user_secret_hash']);
            mrs_log("登录成功: {$username}", 'INFO');
            return $user;
        }

        mrs_log("登录失败: 密码错误 - {$username}", 'WARNING');
        return false;
    } catch (PDOException $e) {
        mrs_log('用户认证失败: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 创建用户会话
 * @param array $user
 * @return void
 */
function mrs_create_user_session($user): void {
    mrs_start_secure_session();

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_login'] = $user['user_login'];
    $_SESSION['user_display_name'] = $user['user_display_name'];
    $_SESSION['user_email'] = $user['user_email'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * 检查用户是否登录
 * @return bool
 */
function mrs_is_user_logged_in(): bool {
    mrs_start_secure_session();

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    $timeout = MRS_SESSION_TIMEOUT;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        mrs_destroy_user_session();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * 别名函数: 检查用户登录状态 (向后兼容)
 * @return bool
 */
function is_user_logged_in(): bool {
    return mrs_is_user_logged_in();
}

// ============================================
// 批次管理别名函数 (向后兼容)
// ============================================

/**
 * 获取批次列表
 * @param int $limit 返回数量限制
 * @param string|null $status 批次状态筛选
 * @return array
 */
function get_batch_list($limit = 20, $status = null) {
    try {
        $pdo = get_mrs_db_connection();

        $sql = "SELECT
                    batch_id,
                    batch_code,
                    batch_name,
                    batch_date,
                    batch_status,
                    location_name,
                    supplier_name,
                    remark,
                    created_by,
                    created_at,
                    updated_at
                FROM mrs_batch";

        $params = [];

        if ($status !== null) {
            $sql .= " WHERE batch_status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        mrs_log('获取批次列表失败: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 根据ID获取批次信息
 * @param int $batch_id 批次ID
 * @return array|false
 */
function get_batch_by_id($batch_id) {
    try {
        $pdo = get_mrs_db_connection();

        $stmt = $pdo->prepare("
            SELECT
                batch_id,
                batch_code,
                batch_name,
                batch_date,
                batch_status,
                location_name,
                supplier_name,
                remark,
                created_by,
                created_at,
                updated_at
            FROM mrs_batch
            WHERE batch_id = :batch_id
        ");

        $stmt->bindValue(':batch_id', (int)$batch_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        mrs_log('获取批次信息失败: ' . $e->getMessage(), 'ERROR', ['batch_id' => $batch_id]);
        return false;
    }
}

/**
 * 获取批次的原始收货记录
 * @param int $batch_id 批次ID
 * @return array
 */
function get_batch_raw_records($batch_id) {
    try {
        $pdo = get_mrs_db_connection();

        $stmt = $pdo->prepare("
            SELECT
                r.raw_record_id,
                r.batch_id,
                r.input_sku_name,
                r.input_case_qty,
                r.input_single_qty,
                r.physical_box_count,
                r.status,
                r.matched_sku_id,
                r.created_at,
                r.updated_at,
                s.sku_name,
                s.brand_name,
                s.standard_unit,
                s.case_unit_name,
                s.case_to_standard_qty
            FROM mrs_batch_raw_record r
            LEFT JOIN mrs_sku s ON r.matched_sku_id = s.sku_id
            WHERE r.batch_id = :batch_id
            ORDER BY r.created_at ASC
        ");

        $stmt->bindValue(':batch_id', (int)$batch_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        mrs_log('获取批次原始记录失败: ' . $e->getMessage(), 'ERROR', ['batch_id' => $batch_id]);
        return [];
    }
}

/**
 * 保存原始收货记录
 * @param array $data 记录数据
 * @return int|false 返回新记录ID或false
 */
function save_raw_record($data) {
    try {
        $pdo = get_mrs_db_connection();

        // 准备数据
        $batch_id = $data['batch_id'] ?? null;
        $input_sku_name = $data['input_sku_name'] ?? ($data['sku_name'] ?? '');
        $input_case_qty = $data['input_case_qty'] ?? ($data['case_qty'] ?? 0);
        $input_single_qty = $data['input_single_qty'] ?? ($data['single_qty'] ?? ($data['qty'] ?? 0));
        $physical_box_count = $data['physical_box_count'] ?? 1;
        $matched_sku_id = $data['matched_sku_id'] ?? ($data['sku_id'] ?? null);

        // 验证必填字段
        if (!$batch_id) {
            mrs_log('保存原始记录失败: 缺少batch_id', 'ERROR');
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT INTO mrs_batch_raw_record (
                batch_id,
                input_sku_name,
                input_case_qty,
                input_single_qty,
                physical_box_count,
                matched_sku_id,
                status
            ) VALUES (
                :batch_id,
                :input_sku_name,
                :input_case_qty,
                :input_single_qty,
                :physical_box_count,
                :matched_sku_id,
                'pending'
            )
        ");

        $stmt->bindValue(':batch_id', (int)$batch_id, PDO::PARAM_INT);
        $stmt->bindValue(':input_sku_name', $input_sku_name);
        $stmt->bindValue(':input_case_qty', (float)$input_case_qty);
        $stmt->bindValue(':input_single_qty', (float)$input_single_qty);
        $stmt->bindValue(':physical_box_count', (int)$physical_box_count, PDO::PARAM_INT);
        $stmt->bindValue(':matched_sku_id', $matched_sku_id ? (int)$matched_sku_id : null, PDO::PARAM_INT);

        $stmt->execute();

        $record_id = (int)$pdo->lastInsertId();
        mrs_log('原始记录保存成功', 'INFO', ['record_id' => $record_id, 'batch_id' => $batch_id]);

        return $record_id;
    } catch (Exception $e) {
        mrs_log('保存原始记录失败: ' . $e->getMessage(), 'ERROR', ['data' => $data]);
        return false;
    }
}

/**
 * 销毁会话
 * @return void
 */
function mrs_destroy_user_session(): void {
    mrs_start_secure_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * 登录保护
 * @return void
 */
function mrs_require_login(): void {
    if (!mrs_is_user_logged_in()) {
        header('Location: /mrs/ap/index.php?action=login');
        exit;
    }
}

// ============================================
// Express 数据查询函数（只读，松耦合）
// ============================================

/**
 * 获取 Express 数据库连接（与 MRS 共享同一数据库）
 * @return PDO
 * @throws PDOException
 */
function get_express_db_connection() {
    // Express 和 MRS 表在同一个数据库中，直接返回 MRS 的连接
    return get_mrs_db_connection();
}

/**
 * 获取 Express 批次列表（只读查询）
 * @return array
 */
function mrs_get_express_batches() {
    try {
        $express_pdo = get_express_db_connection();

        // 暂时显示所有批次，不过滤状态
        // TODO: 根据实际 Express 批次状态调整过滤条件
        $stmt = $express_pdo->prepare("
            SELECT
                batch_id,
                batch_name,
                status,
                total_count,
                counted_count,
                created_at
            FROM express_batch
            ORDER BY created_at DESC
            LIMIT 100
        ");

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get Express batches: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取 Express 批次中已清点的包裹（排除已入库的）
 * @param PDO $mrs_pdo MRS 数据库连接
 * @param string $batch_name 批次名称
 * @return array
 */
function mrs_get_express_counted_packages($mrs_pdo, $batch_name) {
    try {
        $express_pdo = get_express_db_connection();

        // 查询 Express 中已清点的包裹（排除标记为不入库的）
        $stmt = $express_pdo->prepare("
            SELECT
                b.batch_name,
                p.package_id,
                p.tracking_number,
                p.content_note,
                p.package_status,
                p.counted_at,
                p.expiry_date,
                p.quantity
            FROM express_package p
            INNER JOIN express_batch b ON p.batch_id = b.batch_id
            WHERE b.batch_name = :batch_name
              AND p.package_status IN ('counted', 'adjusted')
              AND (p.skip_inbound = 0 OR p.skip_inbound IS NULL)
            ORDER BY p.tracking_number ASC
        ");

        $stmt->execute(['batch_name' => $batch_name]);
        $express_packages = $stmt->fetchAll();

        // 过滤掉已入库的包裹，并加载产品明细
        $available_packages = [];

        foreach ($express_packages as $pkg) {
            // 检查是否已入库
            $check_stmt = $mrs_pdo->prepare("
                SELECT 1 FROM mrs_package_ledger
                WHERE batch_name = :batch_name
                  AND tracking_number = :tracking_number
                LIMIT 1
            ");

            $check_stmt->execute([
                'batch_name' => $pkg['batch_name'],
                'tracking_number' => $pkg['tracking_number']
            ]);

            // 如果不存在，则可入库
            if (!$check_stmt->fetch()) {
                // 加载产品明细
                $items_stmt = $express_pdo->prepare("
                    SELECT product_name, quantity, expiry_date, sort_order
                    FROM express_package_items
                    WHERE package_id = :package_id
                    ORDER BY sort_order ASC
                ");
                $items_stmt->execute(['package_id' => $pkg['package_id']]);
                $pkg['items'] = $items_stmt->fetchAll();

                $available_packages[] = $pkg;
            }
        }

        return $available_packages;
    } catch (PDOException $e) {
        mrs_log('Failed to get Express counted packages: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

// ============================================
// 包裹台账管理函数
// ============================================

/**
 * 获取批次中下一个可用的箱号
 * @param PDO $pdo
 * @param string $batch_name
 * @return string 4位箱号，如 '0001'
 */
function mrs_get_next_box_number($pdo, $batch_name) {
    try {
        $stmt = $pdo->prepare("
            SELECT box_number
            FROM mrs_package_ledger
            WHERE batch_name = :batch_name
            ORDER BY box_number DESC
            LIMIT 1
        ");

        $stmt->execute(['batch_name' => $batch_name]);
        $last_box = $stmt->fetch();

        if (!$last_box) {
            return '0001';
        }

        $last_number = intval($last_box['box_number']);
        $next_number = $last_number + 1;

        return str_pad($next_number, 4, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        mrs_log('Failed to get next box number: ' . $e->getMessage(), 'ERROR');
        return '0001';
    }
}

/**
 * 创建入库记录（批量，从 Express 包裹）
 * @param PDO $pdo
 * @param array $packages 包裹数组，每个元素包含: batch_name, tracking_number, content_note, expiry_date, quantity
 * @param string $spec_info 规格信息（可选）
 * @param string $operator 操作员
 * @return array ['success' => bool, 'created' => int, 'errors' => array]
 */
function mrs_inbound_packages($pdo, $packages, $spec_info = '', $operator = '', $shelf_location = '') {
    $created = 0;
    $errors = [];

    try {
        $pdo->beginTransaction();

        foreach ($packages as $pkg) {
            try {
                $batch_name = $pkg['batch_name'];
                $tracking_number = $pkg['tracking_number'];
                $content_note = trim((string)($pkg['content_note'] ?? ''));
                if ($content_note === '') {
                    $content_note = '未填写';
                }

                // 获取有效期和数量（可选字段，向后兼容）
                $expiry_date = $pkg['expiry_date'] ?? null;
                $quantity = $pkg['quantity'] ?? null;

                // 获取产品明细数组（新增）
                $items = $pkg['items'] ?? [];

                // 获取货架位置（优先使用包裹自己的位置，其次使用批量设置的位置）
                $warehouse_location = trim($pkg['shelf_location'] ?? $shelf_location);

                // 自动生成箱号
                $box_number = mrs_get_next_box_number($pdo, $batch_name);

                $stmt = $pdo->prepare("
                    INSERT INTO mrs_package_ledger
                    (batch_name, tracking_number, content_note, box_number, spec_info,
                     expiry_date, quantity, warehouse_location, status, inbound_time, created_by)
                    VALUES (:batch_name, :tracking_number, :content_note, :box_number, :spec_info,
                            :expiry_date, :quantity, :warehouse_location, 'in_stock', NOW(), :operator)
                ");

                $stmt->execute([
                    'batch_name' => trim($batch_name),
                    'tracking_number' => trim($tracking_number),
                    'content_note' => trim($content_note),
                    'box_number' => $box_number,
                    'spec_info' => trim($spec_info),
                    'expiry_date' => $expiry_date,
                    'quantity' => $quantity,
                    'warehouse_location' => $warehouse_location ?: null,
                    'operator' => $operator
                ]);

                $ledger_id = $pdo->lastInsertId();

                // 保存产品明细数据（如果有）
                if (is_array($items) && count($items) > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO mrs_package_items
                        (ledger_id, product_name, quantity, expiry_date, sort_order)
                        VALUES (:ledger_id, :product_name, :quantity, :expiry_date, :sort_order)
                    ");

                    foreach ($items as $item) {
                        $stmt->execute([
                            'ledger_id' => $ledger_id,
                            'product_name' => $item['product_name'] ?? null,
                            'quantity' => $item['quantity'] ?? null,
                            'expiry_date' => $item['expiry_date'] ?? null,
                            'sort_order' => $item['sort_order'] ?? 0
                        ]);
                    }
                }

                $created++;

                mrs_log("Package inbound: batch={$batch_name}, tracking={$tracking_number}, box={$box_number}", 'INFO');

            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errors[] = "快递单号 {$tracking_number} 已入库";
                } else {
                    $errors[] = "快递单号 {$tracking_number} 入库失败: " . $e->getMessage();
                }
            }
        }

        $pdo->commit();

        mrs_log("Inbound batch completed: created=$created, errors=" . count($errors), 'INFO');

        return [
            'success' => true,
            'created' => $created,
            'errors' => $errors
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        mrs_log('Failed to inbound packages: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'created' => 0,
            'message' => '入库失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 获取已入库的批次列表（在库箱数）
 * @param PDO $pdo
 * @return array
 */
function mrs_get_instock_batches($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT
                batch_name,
                COUNT(*) AS in_stock_boxes,
                MAX(inbound_time) AS last_inbound_time
            FROM mrs_package_ledger
            WHERE status = 'in_stock'
            GROUP BY batch_name
            ORDER BY last_inbound_time DESC, batch_name ASC");

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get instock batches: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取批次下的包裹（可选状态过滤）
 * @param PDO $pdo
 * @param string $batch_name
 * @param string $status
 * @return array
 */
function mrs_get_packages_by_batch($pdo, $batch_name, $status = 'in_stock') {
    try {
        $sql = "SELECT
                    ledger_id,
                    batch_name,
                    tracking_number,
                    content_note,
                    box_number,
                    spec_info,
                    status,
                    inbound_time
                FROM mrs_package_ledger
                WHERE batch_name = :batch_name";

        $params = ['batch_name' => $batch_name];

        if (!empty($status)) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY box_number ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get packages by batch: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取可用库存 (按物料分组)
 * @param PDO $pdo
 * @param string $content_note 可选,筛选特定物料
 * @return array
 */
function mrs_get_inventory_summary($pdo, $content_note = '') {
    try {
        $sql = "
            SELECT
                content_note AS sku_name,
                COUNT(*) as total_boxes,
                SUM(
                    CASE
                        WHEN quantity IS NOT NULL
                        AND quantity != ''
                        AND quantity REGEXP '^[0-9]+$'
                        THEN CAST(quantity AS UNSIGNED)
                        ELSE 0
                    END
                ) as total_quantity,
                MIN(
                    CASE
                        WHEN expiry_date IS NOT NULL
                        THEN expiry_date
                        ELSE NULL
                    END
                ) as nearest_expiry_date
            FROM mrs_package_ledger
            WHERE status = 'in_stock'
        ";

        if (!empty($content_note)) {
            $sql .= " AND content_note = :content_note";
        }

        $sql .= " GROUP BY content_note ORDER BY content_note ASC";

        $stmt = $pdo->prepare($sql);

        if (!empty($content_note)) {
            $stmt->bindValue(':content_note', $content_note, PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get inventory summary: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取库存明细 (某个物料的所有在库包裹)
 * @param PDO $pdo
 * @param string $content_note 物料名称（content_note）
 * @param string $order_by 排序方式:
 *   - 'fifo' (先进先出，按入库时间升序)
 *   - 'batch' (按批次)
 *   - 'expiry_date_asc' (有效期升序，最早到期在前)
 *   - 'expiry_date_desc' (有效期降序，最晚到期在前)
 *   - 'inbound_time_asc' (入库时间升序)
 *   - 'inbound_time_desc' (入库时间降序)
 *   - 'days_in_stock_asc' (库存天数升序，库龄最短在前)
 *   - 'days_in_stock_desc' (库存天数降序，库龄最长在前)
 * @return array
 */
function mrs_get_inventory_detail($pdo, $content_note, $order_by = 'fifo') {
    try {
        $sql = "
            SELECT
                ledger_id,
                batch_name,
                tracking_number,
                content_note,
                box_number,
                spec_info,
                expiry_date,
                quantity,
                warehouse_location,
                status,
                inbound_time,
                DATEDIFF(NOW(), inbound_time) as days_in_stock
            FROM mrs_package_ledger
            WHERE content_note = :content_note AND status = 'in_stock'
        ";

        // 根据排序方式选择 ORDER BY 子句
        switch ($order_by) {
            case 'expiry_date_asc':
                // 有效期升序，NULL值排在最后
                $sql .= " ORDER BY CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date ASC, inbound_time ASC";
                break;
            case 'expiry_date_desc':
                // 有效期降序，NULL值排在最后
                $sql .= " ORDER BY CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date DESC, inbound_time ASC";
                break;
            case 'inbound_time_asc':
            case 'fifo':
                // 入库时间升序（先进先出）
                $sql .= " ORDER BY inbound_time ASC, batch_name ASC, box_number ASC";
                break;
            case 'inbound_time_desc':
                // 入库时间降序（后进先出）
                $sql .= " ORDER BY inbound_time DESC, batch_name ASC, box_number ASC";
                break;
            case 'days_in_stock_asc':
                // 库存天数升序（库龄最短）
                $sql .= " ORDER BY days_in_stock ASC, inbound_time ASC";
                break;
            case 'days_in_stock_desc':
                // 库存天数降序（库龄最长）
                $sql .= " ORDER BY days_in_stock DESC, inbound_time ASC";
                break;
            case 'batch':
            default:
                // 按批次排序
                $sql .= " ORDER BY batch_name ASC, box_number ASC";
                break;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':content_note', $content_note, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get inventory detail: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 出库操作 (批量)
 * @param PDO $pdo
 * @param array $ledger_ids 要出库的台账ID数组
 * @param string $operator 操作员
 * @param int $destination_id 去向ID（可选）
 * @param string $destination_note 去向备注（可选）
 * @return array ['success' => bool, 'shipped' => int, 'message' => string]
 */
function mrs_outbound_packages($pdo, $ledger_ids, $operator = '', $destination_id = null, $destination_note = '') {
    try {
        $pdo->beginTransaction();

        // 1. 先获取包裹信息（用于记录到 usage_log）
        $placeholders = implode(',', array_fill(0, count($ledger_ids), '?'));
        $fetch_stmt = $pdo->prepare("
            SELECT ledger_id, content_note, quantity
            FROM mrs_package_ledger
            WHERE ledger_id IN ($placeholders)
              AND status = 'in_stock'
        ");
        $fetch_stmt->execute($ledger_ids);
        $packages = $fetch_stmt->fetchAll();

        if (empty($packages)) {
            $pdo->rollBack();
            return [
                'success' => false,
                'shipped' => 0,
                'message' => '没有可出库的包裹'
            ];
        }

        // 2. 更新包裹状态
        $stmt = $pdo->prepare("
            UPDATE mrs_package_ledger
            SET status = 'shipped',
                outbound_time = NOW(),
                destination_id = ?,
                destination_note = ?,
                updated_by = ?
            WHERE ledger_id IN ($placeholders)
              AND status = 'in_stock'
        ");

        $params = array_merge([$destination_id, $destination_note, $operator], $ledger_ids);
        $stmt->execute($params);

        $shipped = $stmt->rowCount();

        // 3. 记录到统计表 mrs_usage_log（整箱出库）
        $usage_stmt = $pdo->prepare("
            INSERT INTO mrs_usage_log (
                ledger_id,
                product_name,
                outbound_type,
                deduct_qty,
                destination,
                operator,
                created_at
            ) VALUES (
                :ledger_id,
                :product_name,
                'whole',
                :deduct_qty,
                :destination,
                :operator,
                NOW()
            )
        ");

        foreach ($packages as $pkg) {
            // 清洗数量字段（处理不规范数据）
            $quantity_str = $pkg['quantity'] ?? '';
            if ($quantity_str === null || $quantity_str === '') {
                $qty = 0.0;
            } else {
                $cleaned = preg_replace('/[^0-9.]/', '', trim((string)$quantity_str));
                $qty = $cleaned !== '' ? floatval($cleaned) : 0.0;
            }

            $usage_stmt->execute([
                'ledger_id' => $pkg['ledger_id'],
                'product_name' => $pkg['content_note'],
                'deduct_qty' => $qty,
                'destination' => $destination_note ?: "门店ID:{$destination_id}",
                'operator' => $operator
            ]);
        }

        $pdo->commit();

        mrs_log("Outbound completed: shipped=$shipped, destination_id=$destination_id", 'INFO', ['operator' => $operator]);

        return [
            'success' => true,
            'shipped' => $shipped,
            'message' => "成功出库 {$shipped} 个包裹"
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        mrs_log('Failed to outbound packages: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'shipped' => 0,
            'message' => '出库失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 状态变更 (损耗/作废)
 * @param PDO $pdo
 * @param int $ledger_id 台账ID
 * @param string $new_status 'void' (损耗)
 * @param string $reason 原因
 * @param string $operator
 * @return array
 */
function mrs_change_status($pdo, $ledger_id, $new_status, $reason = '', $operator = '') {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE mrs_package_ledger
            SET status = :new_status,
                void_reason = :reason,
                updated_by = :operator,
                outbound_time = NOW()
            WHERE ledger_id = :ledger_id
        ");

        $stmt->execute([
            'new_status' => $new_status,
            'reason' => $reason,
            'operator' => $operator,
            'ledger_id' => $ledger_id
        ]);

        $pdo->commit();

        mrs_log("Status changed: ledger_id=$ledger_id, new_status=$new_status", 'INFO');

        return [
            'success' => true,
            'message' => '状态已更新'
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        mrs_log('Failed to change status: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '状态更新失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 更新包裹信息（支持多产品）
 * @param PDO $pdo
 * @param int $ledger_id 台账ID
 * @param string $spec_info 规格信息
 * @param string|null $expiry_date 有效期（向后兼容）
 * @param string|null $quantity 数量（向后兼容）
 * @param string $operator 操作员
 * @param array|null $items 产品明细数组（新增）
 * @return array
 */
function mrs_update_package_info($pdo, $ledger_id, $spec_info, $expiry_date, $quantity, $operator = '', $items = null) {
    try {
        // 检查包裹是否存在且在库
        $stmt = $pdo->prepare("SELECT status, content_note FROM mrs_package_ledger WHERE ledger_id = :ledger_id");
        $stmt->execute(['ledger_id' => $ledger_id]);
        $package = $stmt->fetch();

        if (!$package) {
            return [
                'success' => false,
                'message' => '包裹不存在'
            ];
        }

        if ($package['status'] !== 'in_stock') {
            return [
                'success' => false,
                'message' => '只能修改在库状态的包裹'
            ];
        }

        $pdo->beginTransaction();

        // 如果有多产品数据，生成content_note（只包含产品名称，不包含数量）
        $content_note = $package['content_note'];
        if ($items && is_array($items) && count($items) > 0) {
            $product_names = [];
            foreach ($items as $item) {
                if (!empty($item['product_name'])) {
                    // 只使用产品名称，不加数量
                    $product_names[] = trim($item['product_name']);
                }
            }
            if (count($product_names) > 0) {
                // 如果只有一个产品，直接使用产品名
                // 如果有多个产品，用逗号分隔（如："番茄酱, 辣椒酱"）
                $content_note = implode(', ', $product_names);
            }

            // 使用第一个产品的有效期和数量（向后兼容）
            $expiry_date = $items[0]['expiry_date'] ?? null;
            $quantity = $items[0]['quantity'] ?? null;
        }

        $stmt = $pdo->prepare("
            UPDATE mrs_package_ledger
            SET spec_info = :spec_info,
                content_note = :content_note,
                expiry_date = :expiry_date,
                quantity = :quantity,
                updated_by = :operator,
                updated_at = NOW()
            WHERE ledger_id = :ledger_id
        ");

        $stmt->execute([
            'spec_info' => $spec_info,
            'content_note' => $content_note,
            'expiry_date' => $expiry_date,
            'quantity' => $quantity,
            'operator' => $operator,
            'ledger_id' => $ledger_id
        ]);

        // 更新产品明细数据（如果有）
        if ($items && is_array($items) && count($items) > 0) {
            // 先删除旧的产品明细
            $stmt = $pdo->prepare("DELETE FROM mrs_package_items WHERE ledger_id = :ledger_id");
            $stmt->execute(['ledger_id' => $ledger_id]);

            // 插入新的产品明细
            $stmt = $pdo->prepare("
                INSERT INTO mrs_package_items
                (ledger_id, product_name, quantity, expiry_date, sort_order)
                VALUES (:ledger_id, :product_name, :quantity, :expiry_date, :sort_order)
            ");

            foreach ($items as $item) {
                // 只插入有产品名称的项
                if (!empty($item['product_name'])) {
                    $stmt->execute([
                        'ledger_id' => $ledger_id,
                        'product_name' => trim($item['product_name']),
                        'quantity' => $item['quantity'] ?? null,
                        'expiry_date' => $item['expiry_date'] ?? null,
                        'sort_order' => $item['sort_order'] ?? 0
                    ]);
                }
            }
        }

        $pdo->commit();

        mrs_log("Package info updated: ledger_id=$ledger_id", 'INFO', ['operator' => $operator]);

        return [
            'success' => true,
            'message' => '包裹信息已更新'
        ];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        mrs_log('Failed to update package info: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '更新失败: ' . $e->getMessage()
        ];
    }
}

// ============================================
// 统计报表函数
// ============================================

/**
 * 月度入库统计
 * @param PDO $pdo
 * @param string $month 格式: '2025-11'
 * @return array
 */
function mrs_get_monthly_inbound($pdo, $month) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                content_note AS sku_name,
                COUNT(*) as package_count,
                COUNT(DISTINCT batch_name) as batch_count
            FROM mrs_package_ledger
            WHERE DATE_FORMAT(inbound_time, '%Y-%m') = :month
            GROUP BY content_note
            ORDER BY package_count DESC
        ");

        $stmt->bindValue(':month', $month, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get monthly inbound: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 月度出库统计
 * @param PDO $pdo
 * @param string $month 格式: '2025-11'
 * @return array
 */
function mrs_get_monthly_outbound($pdo, $month) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                content_note AS sku_name,
                COUNT(*) as package_count
            FROM mrs_package_ledger
            WHERE DATE_FORMAT(outbound_time, '%Y-%m') = :month
              AND status = 'shipped'
            GROUP BY content_note
            ORDER BY package_count DESC
        ");

        $stmt->bindValue(':month', $month, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get monthly outbound: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 月度汇总统计
 * @param PDO $pdo
 * @param string $month
 * @return array
 */
function mrs_get_monthly_summary($pdo, $month) {
    try {
        // 入库总数
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM mrs_package_ledger
            WHERE DATE_FORMAT(inbound_time, '%Y-%m') = :month
        ");
        $stmt->execute(['month' => $month]);
        $inbound_total = $stmt->fetch()['total'] ?? 0;

        // 出库总数
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM mrs_package_ledger
            WHERE DATE_FORMAT(outbound_time, '%Y-%m') = :month
              AND status = 'shipped'
        ");
        $stmt->execute(['month' => $month]);
        $outbound_total = $stmt->fetch()['total'] ?? 0;

        return [
            'month' => $month,
            'inbound_total' => $inbound_total,
            'outbound_total' => $outbound_total
        ];
    } catch (PDOException $e) {
        mrs_log('Failed to get monthly summary: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取包裹详情
 * @param PDO $pdo
 * @param int $ledger_id 台账ID
 * @return array|null
 */
function mrs_get_package_by_id($pdo, $ledger_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM mrs_package_ledger WHERE ledger_id = :ledger_id");
        $stmt->execute(['ledger_id' => $ledger_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        mrs_log('Failed to get package: ' . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * 搜索包裹
 * @param PDO $pdo
 * @param string $content_note 物料名称
 * @param string $batch_name 批次名称
 * @param string $box_number 箱号
 * @param string $tracking_number 快递单号
 * @return array
 */
function mrs_search_packages($pdo, $content_note = '', $batch_name = '', $box_number = '', $tracking_number = '') {
    try {
        $sql = "SELECT * FROM mrs_package_ledger WHERE 1=1";
        $params = [];

        if (!empty($content_note)) {
            $sql .= " AND content_note = :content_note";
            $params['content_note'] = $content_note;
        }

        if (!empty($batch_name)) {
            $sql .= " AND batch_name LIKE :batch_name";
            $params['batch_name'] = '%' . $batch_name . '%';
        }

        if (!empty($box_number)) {
            $sql .= " AND box_number LIKE :box_number";
            $params['box_number'] = '%' . $box_number . '%';
        }

        if (!empty($tracking_number)) {
            $sql .= " AND tracking_number LIKE :tracking_number";
            $params['tracking_number'] = '%' . $tracking_number . '%';
        }

        $sql .= " ORDER BY inbound_time DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to search packages: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 搜索在库包裹（用于出库页面）
 * @param PDO $pdo
 * @param string $search_type 搜索类型: content_note|box_number|tracking_tail|batch_name
 * @param string $search_value 搜索值
 * @param string $order_by 排序方式: fifo|expiry_date_asc|expiry_date_desc|inbound_time_asc|inbound_time_desc|days_in_stock_asc|days_in_stock_desc
 * @return array
 */
function mrs_search_instock_packages($pdo, $search_type, $search_value, $order_by = 'fifo') {
    try {
        $sql = "SELECT
                    ledger_id,
                    batch_name,
                    tracking_number,
                    content_note,
                    box_number,
                    spec_info,
                    expiry_date,
                    quantity,
                    warehouse_location,
                    status,
                    inbound_time,
                    DATEDIFF(NOW(), inbound_time) as days_in_stock
                FROM mrs_package_ledger
                WHERE status = 'in_stock'";
        $params = [];

        if (!empty($search_value)) {
            switch ($search_type) {
                case 'content_note':
                    $sql .= " AND content_note LIKE :search_value";
                    $params['search_value'] = '%' . $search_value . '%';
                    break;
                case 'box_number':
                    $sql .= " AND box_number LIKE :search_value";
                    $params['search_value'] = '%' . $search_value . '%';
                    break;
                case 'tracking_tail':
                    // 搜索快递单号尾号（后4位或更多）
                    $sql .= " AND tracking_number LIKE :search_value";
                    $params['search_value'] = '%' . $search_value;
                    break;
                case 'batch_name':
                    $sql .= " AND batch_name LIKE :search_value";
                    $params['search_value'] = '%' . $search_value . '%';
                    break;
            }
        }

        // 根据排序方式选择 ORDER BY 子句
        switch ($order_by) {
            case 'expiry_date_asc':
                $sql .= " ORDER BY CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date ASC, inbound_time ASC";
                break;
            case 'expiry_date_desc':
                $sql .= " ORDER BY CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date DESC, inbound_time ASC";
                break;
            case 'inbound_time_asc':
            case 'fifo':
                $sql .= " ORDER BY inbound_time ASC, batch_name ASC, box_number ASC";
                break;
            case 'inbound_time_desc':
                $sql .= " ORDER BY inbound_time DESC, batch_name ASC, box_number ASC";
                break;
            case 'days_in_stock_asc':
                $sql .= " ORDER BY days_in_stock ASC, inbound_time ASC";
                break;
            case 'days_in_stock_desc':
                $sql .= " ORDER BY days_in_stock DESC, inbound_time ASC";
                break;
            default:
                $sql .= " ORDER BY inbound_time ASC";
                break;
        }

        $sql .= " LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to search instock packages: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

// ============================================
// 去向管理函数
// ============================================

/**
 * 获取所有去向类型
 * @param PDO $pdo
 * @return array
 */
function mrs_get_destination_types($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT * FROM mrs_destination_types
            WHERE is_enabled = 1
            ORDER BY sort_order ASC, type_id ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get destination types: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取所有有效去向
 * @param PDO $pdo
 * @param string $type_code 可选：按类型筛选
 * @return array
 */
function mrs_get_destinations($pdo, $type_code = '') {
    try {
        $sql = "
            SELECT
                d.*,
                dt.type_name
            FROM mrs_destinations d
            LEFT JOIN mrs_destination_types dt ON d.type_code = dt.type_code
            WHERE d.is_active = 1
        ";
        $params = [];

        if (!empty($type_code)) {
            $sql .= " AND d.type_code = :type_code";
            $params['type_code'] = $type_code;
        }

        $sql .= " ORDER BY d.sort_order ASC, d.destination_id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get destinations: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取去向详情
 * @param PDO $pdo
 * @param int $destination_id
 * @return array|null
 */
function mrs_get_destination_by_id($pdo, $destination_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                d.*,
                dt.type_name
            FROM mrs_destinations d
            LEFT JOIN mrs_destination_types dt ON d.type_code = dt.type_code
            WHERE d.destination_id = :destination_id
        ");
        $stmt->execute(['destination_id' => $destination_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        mrs_log('Failed to get destination: ' . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * 创建去向
 * @param PDO $pdo
 * @param array $data
 * @return array
 */
function mrs_create_destination($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mrs_destinations
            (type_code, destination_name, destination_code, contact_person,
             contact_phone, address, remark, sort_order, created_by)
            VALUES
            (:type_code, :destination_name, :destination_code, :contact_person,
             :contact_phone, :address, :remark, :sort_order, :created_by)
        ");

        $stmt->execute([
            'type_code' => $data['type_code'],
            'destination_name' => $data['destination_name'],
            'destination_code' => $data['destination_code'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'remark' => $data['remark'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $data['created_by'] ?? 'system'
        ]);

        $destination_id = $pdo->lastInsertId();

        mrs_log("Destination created: id=$destination_id", 'INFO');

        return [
            'success' => true,
            'destination_id' => $destination_id,
            'message' => '去向创建成功'
        ];
    } catch (PDOException $e) {
        mrs_log('Failed to create destination: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '创建失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 更新去向
 * @param PDO $pdo
 * @param int $destination_id
 * @param array $data
 * @return array
 */
function mrs_update_destination($pdo, $destination_id, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE mrs_destinations
            SET type_code = :type_code,
                destination_name = :destination_name,
                destination_code = :destination_code,
                contact_person = :contact_person,
                contact_phone = :contact_phone,
                address = :address,
                remark = :remark,
                sort_order = :sort_order
            WHERE destination_id = :destination_id
        ");

        $stmt->execute([
            'type_code' => $data['type_code'],
            'destination_name' => $data['destination_name'],
            'destination_code' => $data['destination_code'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'remark' => $data['remark'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'destination_id' => $destination_id
        ]);

        mrs_log("Destination updated: id=$destination_id", 'INFO');

        return [
            'success' => true,
            'message' => '去向更新成功'
        ];
    } catch (PDOException $e) {
        mrs_log('Failed to update destination: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '更新失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 删除去向（软删除）
 * @param PDO $pdo
 * @param int $destination_id
 * @return array
 */
function mrs_delete_destination($pdo, $destination_id) {
    try {
        // 检查是否有关联的出库记录
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM mrs_package_ledger
            WHERE destination_id = :destination_id
        ");
        $stmt->execute(['destination_id' => $destination_id]);
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            return [
                'success' => false,
                'message' => "该去向已被使用 {$count} 次，不能删除"
            ];
        }

        // 软删除
        $stmt = $pdo->prepare("
            UPDATE mrs_destinations
            SET is_active = 0
            WHERE destination_id = :destination_id
        ");
        $stmt->execute(['destination_id' => $destination_id]);

        mrs_log("Destination deleted: id=$destination_id", 'INFO');

        return [
            'success' => true,
            'message' => '去向已删除'
        ];
    } catch (PDOException $e) {
        mrs_log('Failed to delete destination: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '删除失败: ' . $e->getMessage()
        ];
    }
}

// ============================================
// 真正的多产品库存统计函数（基于mrs_package_items表）
// ============================================

/**
 * 获取真正的库存汇总（按单个产品聚合，不是按组合）
 *
 * 重要说明：
 * - 旧函数mrs_get_inventory_summary()按content_note分组，导致"AA,BB"和"AA,CC"被当作不同SKU
 * - 本函数按product_name分组，正确统计每个独立产品的库存
 * - 这是仓储系统应有的正确行为
 *
 * @param PDO $pdo
 * @param string $product_name 产品名称（可选，用于筛选）
 * @return array
 */
function mrs_get_true_inventory_summary($pdo, $product_name = '', $sort_by = 'sku_name', $sort_dir = 'asc') {
    try {
        // [FIX] 使用映射表，更安全地处理排序参数，避免SQL注入风险
        $sort_column_map = [
            'sku_name' => 'i.product_name',
            'total_boxes' => 'total_boxes',
            'total_quantity' => 'total_quantity',
            'nearest_expiry_date' => 'nearest_expiry_date'
        ];

        // 验证并获取安全的排序列名
        $order_column = $sort_column_map[$sort_by] ?? 'i.product_name';

        // 验证排序方向（只允许ASC或DESC）
        $sort_direction_map = ['asc' => 'ASC', 'desc' => 'DESC'];
        $order_direction = $sort_direction_map[strtolower($sort_dir)] ?? 'ASC';

        $sql = "
            SELECT
                i.product_name AS sku_name,
                COUNT(DISTINCT l.ledger_id) as total_boxes,
                SUM(
                    CASE
                        WHEN i.quantity IS NOT NULL
                        AND i.quantity != ''
                        AND i.quantity REGEXP '^[0-9]+$'
                        THEN CAST(i.quantity AS UNSIGNED)
                        ELSE 0
                    END
                ) as total_quantity,
                MIN(
                    CASE
                        WHEN i.expiry_date IS NOT NULL
                        THEN i.expiry_date
                        ELSE NULL
                    END
                ) as nearest_expiry_date
            FROM mrs_package_items i
            INNER JOIN mrs_package_ledger l ON i.ledger_id = l.ledger_id
            WHERE l.status = 'in_stock'
        ";

        if (!empty($product_name)) {
            $sql .= " AND i.product_name = :product_name";
        }

        // [FIX] 使用已验证的安全变量，不直接拼接用户输入
        $sql .= " GROUP BY i.product_name ORDER BY {$order_column} {$order_direction}";

        // 对于到期日期排序，NULL值放在最后
        if ($sort_by === 'nearest_expiry_date') {
            $sql .= ", i.product_name ASC";
        }

        $stmt = $pdo->prepare($sql);

        if (!empty($product_name)) {
            $stmt->bindValue(':product_name', $product_name, PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get true inventory summary: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取某个产品的真正库存明细（所有包含该产品的包裹）
 *
 * @param PDO $pdo
 * @param string $product_name 产品名称
 * @param string $order_by 排序方式（同旧函数）
 * @return array 返回包含该产品的所有包裹，每个包裹包含items数组
 */
function mrs_get_true_inventory_detail($pdo, $product_name, $order_by = 'fifo') {
    try {
        // [FIX] 根据排序参数构建ORDER BY子句 (使用 switch 以兼容 PHP 7.x)
        switch($order_by) {
            case 'batch':
                $order_clause = 'l.batch_name ASC, l.inbound_time ASC';
                break;
            case 'expiry_date_asc':
                $order_clause = 'i.expiry_date ASC, l.inbound_time ASC';
                break;
            case 'expiry_date_desc':
                $order_clause = 'i.expiry_date DESC, l.inbound_time ASC';
                break;
            case 'inbound_time_desc':
                $order_clause = 'l.inbound_time DESC';
                break;
            case 'days_in_stock_asc':
                $order_clause = 'l.inbound_time DESC';
                break;
            case 'days_in_stock_desc':
                $order_clause = 'l.inbound_time ASC';
                break;
            case 'fifo':
            case 'inbound_time_asc':
            default:
                $order_clause = 'l.inbound_time ASC'; // fifo 或 inbound_time_asc
                break;
        }

        // 首先获取包含该产品的所有包裹
        $sql = "
            SELECT DISTINCT
                l.ledger_id,
                l.batch_name,
                l.tracking_number,
                l.content_note,
                l.box_number,
                l.spec_info,
                l.expiry_date AS ledger_expiry_date,
                l.quantity AS ledger_quantity,
                l.warehouse_location,
                l.status,
                l.inbound_time,
                DATEDIFF(NOW(), l.inbound_time) as days_in_stock
            FROM mrs_package_ledger l
            INNER JOIN mrs_package_items i ON l.ledger_id = i.ledger_id
            WHERE i.product_name = :product_name AND l.status = 'in_stock'
            ORDER BY {$order_clause}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':product_name', $product_name, PDO::PARAM_STR);
        $stmt->execute();
        $packages = $stmt->fetchAll();

        // 为每个包裹加载其产品明细
        foreach ($packages as &$package) {
            $items_stmt = $pdo->prepare("
                SELECT product_name, quantity, expiry_date, sort_order
                FROM mrs_package_items
                WHERE ledger_id = :ledger_id
                ORDER BY sort_order ASC
            ");
            $items_stmt->execute(['ledger_id' => $package['ledger_id']]);
            $package['items'] = $items_stmt->fetchAll();
        }

        return $packages;
    } catch (PDOException $e) {
        mrs_log('Failed to get true inventory detail: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取包裹的产品明细
 *
 * @param PDO $pdo
 * @param int $ledger_id 台账ID
 * @return array
 */
function mrs_get_package_items($pdo, $ledger_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT item_id, product_name, quantity, expiry_date, sort_order
            FROM mrs_package_items
            WHERE ledger_id = :ledger_id
            ORDER BY sort_order ASC
        ");
        $stmt->execute(['ledger_id' => $ledger_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get package items: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 更新包裹的产品明细（删除旧的，插入新的）
 *
 * @param PDO $pdo
 * @param int $ledger_id 台账ID
 * @param array $items 产品数组，每个元素包含 product_name, quantity, expiry_date
 * @return bool
 */
function mrs_update_package_items($pdo, $ledger_id, $items) {
    try {
        $pdo->beginTransaction();

        // 删除旧的产品明细
        $delete_stmt = $pdo->prepare("DELETE FROM mrs_package_items WHERE ledger_id = :ledger_id");
        $delete_stmt->execute(['ledger_id' => $ledger_id]);

        // 插入新的产品明细
        if (!empty($items) && is_array($items)) {
            $insert_stmt = $pdo->prepare("
                INSERT INTO mrs_package_items (ledger_id, product_name, quantity, expiry_date, sort_order)
                VALUES (:ledger_id, :product_name, :quantity, :expiry_date, :sort_order)
            ");

            foreach ($items as $index => $item) {
                if (empty($item['product_name'])) continue;

                $insert_stmt->execute([
                    'ledger_id' => $ledger_id,
                    'product_name' => trim($item['product_name']),
                    'quantity' => !empty($item['quantity']) ? (int)$item['quantity'] : null,
                    'expiry_date' => !empty($item['expiry_date']) ? $item['expiry_date'] : null,
                    'sort_order' => $index
                ]);
            }

            // 同时更新主表的content_note（仅用于显示）
            $product_names = array_map(function($item) {
                return trim($item['product_name']);
            }, array_filter($items, function($item) {
                return !empty($item['product_name']);
            }));

            $content_note = implode(', ', $product_names);

            $update_main = $pdo->prepare("
                UPDATE mrs_package_ledger
                SET content_note = :content_note
                WHERE ledger_id = :ledger_id
            ");
            $update_main->execute([
                'content_note' => $content_note,
                'ledger_id' => $ledger_id
            ]);
        }

        $pdo->commit();
        mrs_log("Package items updated: ledger_id=$ledger_id", 'INFO');
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        mrs_log('Failed to update package items: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

// ============================================
// 包裹拆分入库功能函数
// ============================================

/**
 * 获取有产品明细的包裹（适合拆分入库）
 * @param PDO $pdo
 * @param string $batch_name Express批次名称
 * @return array
 */
function mrs_get_splittable_packages($pdo, $batch_name) {
    try {
        $express_pdo = get_express_db_connection();

        // 查询有产品明细的包裹（排除已入库的）
        $stmt = $express_pdo->prepare("
            SELECT
                b.batch_name,
                p.package_id,
                p.tracking_number,
                p.content_note,
                p.package_status,
                p.counted_at,
                p.expiry_date,
                p.quantity,
                (SELECT COUNT(*) FROM express_package_items WHERE package_id = p.package_id) AS item_count
            FROM express_package p
            INNER JOIN express_batch b ON p.batch_id = b.batch_id
            WHERE b.batch_name = :batch_name
              AND p.package_status IN ('counted', 'adjusted')
              AND (p.skip_inbound = 0 OR p.skip_inbound IS NULL)
            HAVING item_count > 0
            ORDER BY p.tracking_number ASC
        ");

        $stmt->execute(['batch_name' => $batch_name]);
        $express_packages = $stmt->fetchAll();

        // 过滤掉已入库的包裹
        $available_packages = [];

        foreach ($express_packages as $pkg) {
            // 检查是否已创建SKU收货记录（通过remark字段追溯）
            $check_stmt = $pdo->prepare("
                SELECT 1 FROM mrs_batch_raw_record r
                INNER JOIN mrs_batch b ON r.batch_id = b.batch_id
                WHERE b.batch_name = :batch_name
                  AND b.remark LIKE :tracking_ref
                LIMIT 1
            ");

            $check_stmt->execute([
                'batch_name' => $batch_name,
                'tracking_ref' => '%' . $pkg['tracking_number'] . '%'
            ]);

            // 如果不存在，则可拆分入库
            if (!$check_stmt->fetch()) {
                // 加载产品明细
                $items_stmt = $express_pdo->prepare("
                    SELECT product_name, quantity, expiry_date, sort_order
                    FROM express_package_items
                    WHERE package_id = :package_id
                    ORDER BY sort_order ASC
                ");
                $items_stmt->execute(['package_id' => $pkg['package_id']]);
                $pkg['items'] = $items_stmt->fetchAll();

                $available_packages[] = $pkg;
            }
        }

        return $available_packages;
    } catch (PDOException $e) {
        mrs_log('Failed to get splittable packages: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取或创建MRS批次（用于拆分入库）
 * @param PDO $pdo
 * @param string $batch_name 批次名称
 * @param string $operator 操作员
 * @return int|false 批次ID，失败返回false
 */
function mrs_get_or_create_batch($pdo, $batch_name, $operator = '') {
    try {
        // 检查批次是否存在
        $stmt = $pdo->prepare("
            SELECT batch_id FROM mrs_batch
            WHERE batch_name = :batch_name
            LIMIT 1
        ");
        $stmt->execute(['batch_name' => $batch_name]);
        $batch = $stmt->fetch();

        if ($batch) {
            return $batch['batch_id'];
        }

        // 不存在则创建
        $batch_code = 'EXPSPLIT-' . date('YmdHis');

        $create_stmt = $pdo->prepare("
            INSERT INTO mrs_batch (
                batch_code, batch_name, batch_date, batch_status,
                remark, created_by, created_at, updated_at
            ) VALUES (
                :batch_code, :batch_name, :batch_date, 'receiving',
                :remark, :created_by, NOW(6), NOW(6)
            )
        ");

        $create_stmt->execute([
            'batch_code' => $batch_code,
            'batch_name' => $batch_name,
            'batch_date' => date('Y-m-d'),
            'remark' => "来源：Express批次拆分入库",
            'created_by' => $operator
        ]);

        $batch_id = $pdo->lastInsertId();
        mrs_log("Created MRS batch for split inbound: batch_id=$batch_id, batch_name=$batch_name", 'INFO');

        return $batch_id;
    } catch (PDOException $e) {
        mrs_log('Failed to get or create batch: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 拆分入库 - 将Express包裹转换为SKU收货记录
 * @param PDO $pdo
 * @param array $packages 包裹数组
 * @param string $operator 操作员
 * @return array ['success' => bool, 'batch_id' => int, 'records_created' => int, 'errors' => array]
 */
function mrs_split_inbound_packages($pdo, $packages, $operator = '') {
    $records_created = 0;
    $errors = [];
    $batch_id = null;

    try {
        $pdo->beginTransaction();

        if (empty($packages) || !is_array($packages)) {
            throw new Exception('包裹数据为空');
        }

        // 获取批次名称（所有包裹应该来自同一批次）
        $batch_name = $packages[0]['batch_name'] ?? '';
        if (empty($batch_name)) {
            throw new Exception('批次名称为空');
        }

        // 获取或创建MRS批次
        $batch_id = mrs_get_or_create_batch($pdo, $batch_name, $operator);
        if (!$batch_id) {
            throw new Exception('创建批次失败');
        }

        $express_pdo = get_express_db_connection();

        // 处理每个包裹
        foreach ($packages as $pkg) {
            try {
                $tracking_number = $pkg['tracking_number'] ?? '';
                $package_id = $pkg['package_id'] ?? 0;

                if (empty($tracking_number) || $package_id <= 0) {
                    $errors[] = "包裹数据无效";
                    continue;
                }

                // 读取产品明细
                $items_stmt = $express_pdo->prepare("
                    SELECT product_name, quantity, expiry_date
                    FROM express_package_items
                    WHERE package_id = :package_id
                    ORDER BY sort_order ASC
                ");
                $items_stmt->execute(['package_id' => $package_id]);
                $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($items)) {
                    $errors[] = "包裹 {$tracking_number} 没有产品明细";
                    continue;
                }

                // 转换为收货记录
                $record_stmt = $pdo->prepare("
                    INSERT INTO mrs_batch_raw_record (
                        batch_id, input_sku_name, input_case_qty, input_single_qty,
                        physical_box_count, status, created_at, updated_at
                    ) VALUES (
                        :batch_id, :input_sku_name, 0, :input_single_qty,
                        1, 'pending', NOW(6), NOW(6)
                    )
                ");

                foreach ($items as $item) {
                    $product_name = trim($item['product_name'] ?? '');
                    $quantity = floatval($item['quantity'] ?? 0);

                    if (empty($product_name) || $quantity <= 0) {
                        continue;
                    }

                    $record_stmt->execute([
                        'batch_id' => $batch_id,
                        'input_sku_name' => $product_name,
                        'input_single_qty' => $quantity
                    ]);

                    $records_created++;
                }

                // 更新批次的remark，记录已处理的快递单号
                $update_remark = $pdo->prepare("
                    UPDATE mrs_batch
                    SET remark = CONCAT(COALESCE(remark, ''), ' [已拆分: ', :tracking_number, ']')
                    WHERE batch_id = :batch_id
                ");
                $update_remark->execute([
                    'tracking_number' => $tracking_number,
                    'batch_id' => $batch_id
                ]);

                mrs_log("Split inbound package: tracking=$tracking_number, items=" . count($items), 'INFO');

            } catch (PDOException $e) {
                $errors[] = "包裹 {$tracking_number} 处理失败: " . $e->getMessage();
            }
        }

        $pdo->commit();

        mrs_log("Split inbound completed: batch_id=$batch_id, records=$records_created, errors=" . count($errors), 'INFO');

        return [
            'success' => true,
            'batch_id' => $batch_id,
            'records_created' => $records_created,
            'errors' => $errors
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        mrs_log('Failed to split inbound packages: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '拆分入库失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 获取统一出库报表数据（整合SKU和包裹台账系统）
 * @param PDO $pdo
 * @param string $start_date 开始日期
 * @param string $end_date 结束日期
 * @return array
 */
function mrs_get_unified_outbound_report($pdo, $start_date, $end_date) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                product_name,
                SUM(case_qty) AS total_case_qty,
                SUM(single_qty) AS total_single_qty,
                CASE
                    WHEN SUM(case_qty) > 0 AND SUM(single_qty) > 0
                        THEN CONCAT(SUM(case_qty), '箱+', SUM(single_qty), '件')
                    WHEN SUM(case_qty) > 0
                        THEN CONCAT(SUM(case_qty), '箱')
                    WHEN SUM(single_qty) > 0
                        THEN CONCAT(SUM(single_qty), '件')
                    ELSE '0'
                END AS display_qty,
                GROUP_CONCAT(DISTINCT source_type) AS sources
            FROM vw_unified_outbound_report
            WHERE outbound_date BETWEEN :start_date AND :end_date
            GROUP BY product_name
            ORDER BY product_name ASC
        ");

        $stmt->execute([
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get unified outbound report: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 获取统一入库报表数据（整合SKU和包裹台账系统）
 * @param PDO $pdo
 * @param string $start_date 开始日期
 * @param string $end_date 结束日期
 * @return array
 */
function mrs_get_unified_inbound_report($pdo, $start_date, $end_date) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                product_name,
                SUM(case_qty) AS total_case_qty,
                SUM(single_qty) AS total_single_qty,
                CASE
                    WHEN SUM(case_qty) > 0 AND SUM(single_qty) > 0
                        THEN CONCAT(SUM(case_qty), '箱+', SUM(single_qty), '件')
                    WHEN SUM(case_qty) > 0
                        THEN CONCAT(SUM(case_qty), '箱')
                    WHEN SUM(single_qty) > 0
                        THEN CONCAT(SUM(single_qty), '件')
                    ELSE '0'
                END AS display_qty,
                GROUP_CONCAT(DISTINCT source_type) AS sources
            FROM vw_unified_inbound_report
            WHERE inbound_date BETWEEN :start_date AND :end_date
            GROUP BY product_name
            ORDER BY product_name ASC
        ");

        $stmt->execute([
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get unified inbound report: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

// ============================================
// 工具函数 (Critical Fixes)
// ============================================

/**
 * 根据SKU ID获取SKU详情
 * @param int $sku_id SKU ID
 * @return array|null SKU详情或null
 */
function get_sku_by_id($sku_id) {
    try {
        $pdo = get_mrs_db_connection();
        $stmt = $pdo->prepare("
            SELECT sku_id, sku_name, brand_name, category_id,
                   standard_unit, case_unit_name, case_to_standard_qty,
                   status, remark
            FROM mrs_sku
            WHERE sku_id = :sku_id
            LIMIT 1
        ");
        $stmt->execute(['sku_id' => $sku_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        mrs_log('Failed to get SKU by ID: ' . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * 归一化数量到标准单位
 * @param float $case_qty 箱数
 * @param float $single_qty 散装数
 * @param float $case_spec 箱规格(每箱含多少个标准单位)
 * @return int 标准单位总数(取整)
 */
function normalize_quantity_to_storage($case_qty, $single_qty, $case_spec) {
    $raw_total = ($case_qty * $case_spec) + $single_qty;
    return (int)round($raw_total, 0);  // 强制取整，防止浮点精度问题
}

/**
 * 生成出库单号
 * @param string $date 日期 (Y-m-d)
 * @return string 出库单号 (格式: OUT20251220XXXX)
 */
function generate_outbound_code($date) {
    try {
        $pdo = get_mrs_db_connection();
        $datePrefix = date('Ymd', strtotime($date));

        // 查询当天最大序号
        $stmt = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(outbound_code, -4) AS UNSIGNED)) as max_seq
            FROM mrs_outbound_order
            WHERE outbound_code LIKE :prefix
        ");
        $stmt->execute(['prefix' => "OUT{$datePrefix}%"]);
        $result = $stmt->fetch();

        $nextSeq = ($result['max_seq'] ?? 0) + 1;
        $code = sprintf("OUT%s%04d", $datePrefix, $nextSeq);

        mrs_log("Generated outbound code: {$code}", 'INFO');
        return $code;
    } catch (PDOException $e) {
        mrs_log('Failed to generate outbound code: ' . $e->getMessage(), 'ERROR');
        // 降级方案：使用随机数
        return 'OUT' . date('Ymd', strtotime($date)) . rand(1000, 9999);
    }
}

/**
 * 生成批次编号
 * @param string $date 日期 (Y-m-d)
 * @return string 批次编号 (格式: BATCH20251220XXXX)
 */
function generate_batch_code($date) {
    try {
        $pdo = get_mrs_db_connection();
        $datePrefix = date('Ymd', strtotime($date));

        // 查询当天最大序号
        $stmt = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(batch_code, -4) AS UNSIGNED)) as max_seq
            FROM mrs_batch
            WHERE batch_code LIKE :prefix
        ");
        $stmt->execute(['prefix' => "BATCH{$datePrefix}%"]);
        $result = $stmt->fetch();

        $nextSeq = ($result['max_seq'] ?? 0) + 1;
        $code = sprintf("BATCH%s%04d", $datePrefix, $nextSeq);

        mrs_log("Generated batch code: {$code}", 'INFO');
        return $code;
    } catch (PDOException $e) {
        mrs_log('Failed to generate batch code: ' . $e->getMessage(), 'ERROR');
        // 降级方案：使用随机数
        return 'BATCH' . date('Ymd', strtotime($date)) . rand(1000, 9999);
    }
}

/**
 * 验证和净化文本输入（增强版）
 * @param string $input 用户输入
 * @param int $max_length 最大长度限制（默认使用配置常量）
 * @return string 净化后的文本
 */
function mrs_sanitize_input($input, $max_length = null): string {
    $max_length = $max_length ?? MRS_MAX_INPUT_LENGTH;
    if (!is_string($input)) {
        return '';
    }

    // 移除首尾空格
    $input = trim($input);

    // 限制长度
    if ($max_length > 0 && mb_strlen($input, 'UTF-8') > $max_length) {
        $input = mb_substr($input, 0, $max_length, 'UTF-8');
    }

    // 移除控制字符（保留换行和制表符）
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);

    return $input;
}

/**
 * 验证枚举值
 * @param mixed $value 待验证的值
 * @param array $allowed_values 允许的值列表
 * @param mixed $default 默认值
 * @return mixed 验证后的值
 */
function mrs_validate_enum($value, array $allowed_values, $default = null) {
    return in_array($value, $allowed_values, true) ? $value : $default;
}

/**
 * 验证和净化整数输入
 * @param mixed $input 用户输入
 * @param int $min 最小值
 * @param int $max 最大值
 * @param int $default 默认值
 * @return int 验证后的整数
 */
function mrs_sanitize_int($input, $min = 0, $max = PHP_INT_MAX, $default = 0): int {
    $value = filter_var($input, FILTER_VALIDATE_INT);

    if ($value === false) {
        return $default;
    }

    return max($min, min($max, $value));
}

/**
 * 验证日期格式
 * @param string $date 日期字符串
 * @param string $format 期望的日期格式
 * @return string|null 验证后的日期或null
 */
function mrs_validate_date($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return null;
    }

    $d = DateTime::createFromFormat($format, $date);
    return ($d && $d->format($format) === $date) ? $date : null;
}

/**
 * 获取并验证分页参数
 * @param int $default_limit 默认每页记录数（默认使用配置常量）
 * @param int $max_limit 最大每页记录数（默认使用配置常量）
 * @param string $limit_param 限制参数名（支持'limit'或'page_size'）
 * @return array ['page', 'limit', 'offset'] 验证后的分页参数
 */
function mrs_get_pagination_params($default_limit = null, $max_limit = null, $limit_param = 'limit'): array {
    // 使用配置常量作为默认值
    $default_limit = $default_limit ?? MRS_DEFAULT_PAGE_SIZE;
    $max_limit = $max_limit ?? MRS_MAX_PAGE_SIZE;

    // 验证页码
    $page = mrs_sanitize_int($_GET['page'] ?? 1, 1, MRS_MAX_PAGE_NUMBER, 1);

    // 验证每页记录数（支持'limit'或'page_size'参数名）
    $limit_value = $_GET[$limit_param] ?? $_GET['limit'] ?? $_GET['page_size'] ?? $default_limit;
    $limit = mrs_sanitize_int($limit_value, 1, $max_limit, $default_limit);

    // 计算偏移量
    $offset = ($page - 1) * $limit;

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset
    ];
}

// ============================================
// SKU管理函数
// ============================================

/**
 * 获取所有SKU列表
 * @param PDO $pdo 数据库连接
 * @param string $status SKU状态过滤 ('active', 'inactive', 'all')
 * @return array SKU列表
 */
function mrs_get_all_skus($pdo, $status = 'active') {
    try {
        $sql = "SELECT
                    sku_id,
                    sku_name,
                    sku_code,
                    brand_name,
                    category_id,
                    standard_unit,
                    case_unit_name,
                    case_to_standard_qty,
                    status,
                    remark,
                    created_at,
                    updated_at
                FROM mrs_sku";

        if ($status !== 'all') {
            $sql .= " WHERE status = :status";
        }

        $sql .= " ORDER BY sku_name ASC";

        $stmt = $pdo->prepare($sql);

        if ($status !== 'all') {
            $stmt->execute(['status' => $status]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        mrs_log('Failed to get all SKUs: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 创建新的SKU（如果已存在则返回现有的ID）
 * @param PDO $pdo 数据库连接
 * @param string $sku_name SKU名称
 * @return int|false SKU ID 或 false（失败）
 */
function mrs_create_sku($pdo, $sku_name) {
    try {
        $sku_name = trim($sku_name);

        if (empty($sku_name)) {
            mrs_log('SKU name is empty', 'WARNING');
            return false;
        }

        // 先检查是否已存在同名SKU
        $stmt = $pdo->prepare("
            SELECT sku_id
            FROM mrs_sku
            WHERE sku_name = :sku_name
            AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute(['sku_name' => $sku_name]);
        $existing = $stmt->fetch();

        if ($existing) {
            // 已存在，返回现有的ID
            mrs_log("SKU already exists: {$sku_name} (ID: {$existing['sku_id']})", 'INFO');
            return (int)$existing['sku_id'];
        }

        // 不存在，创建新SKU
        $stmt = $pdo->prepare("
            INSERT INTO mrs_sku (sku_name, status, created_at, updated_at)
            VALUES (:sku_name, 'active', NOW(6), NOW(6))
        ");
        $stmt->execute(['sku_name' => $sku_name]);

        $sku_id = $pdo->lastInsertId();
        mrs_log("Created new SKU: {$sku_name} (ID: {$sku_id})", 'INFO');

        return (int)$sku_id;
    } catch (PDOException $e) {
        mrs_log('Failed to create SKU: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 检查包裹是否可以删除（批量）
 * @param PDO $pdo 数据库连接
 * @param array $tracking_numbers 快递单号数组
 * @return array 返回结果数组，包含可删除和不可删除的包裹信息
 */
function mrs_check_packages_for_deletion($pdo, $tracking_numbers) {
    try {
        if (empty($tracking_numbers)) {
            return [
                'success' => false,
                'message' => '快递单号列表为空'
            ];
        }

        // 去重和清理快递单号
        $tracking_numbers = array_unique(array_map('trim', $tracking_numbers));
        $tracking_numbers = array_filter($tracking_numbers, function($tn) {
            return !empty($tn);
        });

        if (empty($tracking_numbers)) {
            return [
                'success' => false,
                'message' => '没有有效的快递单号'
            ];
        }

        // 构建IN查询的占位符
        $placeholders = str_repeat('?,', count($tracking_numbers) - 1) . '?';

        // 查询所有包裹及其出库状态
        $sql = "
            SELECT
                pl.ledger_id,
                pl.tracking_number,
                pl.batch_name,
                pl.box_number,
                pl.warehouse_location,
                pl.status,
                pl.inbound_time,
                pl.outbound_time,
                pl.destination_id,
                pl.content_note,
                GROUP_CONCAT(
                    CONCAT(pi.product_name, '(', pi.quantity, ')')
                    ORDER BY pi.sort_order
                    SEPARATOR ', '
                ) as products,
                COUNT(ul.log_id) as outbound_count
            FROM mrs_package_ledger pl
            LEFT JOIN mrs_package_items pi ON pl.ledger_id = pi.ledger_id
            LEFT JOIN mrs_usage_log ul ON pl.ledger_id = ul.ledger_id
            WHERE pl.tracking_number IN ($placeholders)
            GROUP BY pl.ledger_id
            ORDER BY pl.tracking_number
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($tracking_numbers);
        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $deletable = [];
        $non_deletable = [];
        $not_found = [];

        // 找出未找到的快递单号
        $found_tracking_numbers = array_column($packages, 'tracking_number');
        $not_found = array_diff($tracking_numbers, $found_tracking_numbers);

        // 检查每个包裹
        foreach ($packages as $package) {
            $can_delete = true;
            $reason = '';

            // 检查是否已出库
            if ($package['status'] === 'shipped' || !empty($package['outbound_time'])) {
                $can_delete = false;
                $reason = '已有出库记录';
            } elseif ($package['outbound_count'] > 0) {
                $can_delete = false;
                $reason = '存在出库使用记录';
            }

            $package_info = [
                'ledger_id' => $package['ledger_id'],
                'tracking_number' => $package['tracking_number'],
                'batch_name' => $package['batch_name'],
                'box_number' => $package['box_number'],
                'warehouse_location' => $package['warehouse_location'],
                'status' => $package['status'],
                'products' => $package['products'] ?? '',
                'inbound_time' => $package['inbound_time'],
                'reason' => $reason
            ];

            if ($can_delete) {
                $deletable[] = $package_info;
            } else {
                $non_deletable[] = $package_info;
            }
        }

        return [
            'success' => true,
            'deletable' => $deletable,
            'non_deletable' => $non_deletable,
            'not_found' => array_values($not_found),
            'summary' => [
                'total_requested' => count($tracking_numbers),
                'found' => count($packages),
                'deletable' => count($deletable),
                'non_deletable' => count($non_deletable),
                'not_found' => count($not_found)
            ]
        ];

    } catch (PDOException $e) {
        mrs_log('Failed to check packages for deletion: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '数据库查询失败: ' . $e->getMessage()
        ];
    }
}

/**
 * 批量删除包裹（硬删除）
 * @param PDO $pdo 数据库连接
 * @param array $ledger_ids 要删除的包裹ledger_id数组
 * @param string $operator 操作员
 * @param string $reason 删除原因
 * @return array 返回删除结果
 */
function mrs_bulk_delete_packages($pdo, $ledger_ids, $operator = '', $reason = '') {
    try {
        if (empty($ledger_ids)) {
            return [
                'success' => false,
                'message' => '没有要删除的包裹'
            ];
        }

        $pdo->beginTransaction();

        // 再次检查这些包裹是否可以删除
        $placeholders = str_repeat('?,', count($ledger_ids) - 1) . '?';
        $check_sql = "
            SELECT ledger_id, tracking_number, status, outbound_time
            FROM mrs_package_ledger
            WHERE ledger_id IN ($placeholders)
            AND (status = 'shipped' OR outbound_time IS NOT NULL)
        ";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute($ledger_ids);
        $invalid_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($invalid_packages)) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => '存在不可删除的包裹',
                'invalid_packages' => $invalid_packages
            ];
        }

        // 记录删除操作日志（在删除前记录包裹信息）
        $log_sql = "
            INSERT INTO mrs_operation_log
            (operation_type, operation_detail, operator, created_at)
            SELECT
                'package_deletion' as operation_type,
                CONCAT(
                    'Deleted package - Tracking: ', tracking_number,
                    ', Batch: ', batch_name,
                    ', Box: ', box_number,
                    ', Location: ', IFNULL(warehouse_location, 'N/A'),
                    ', Reason: ', ?
                ) as operation_detail,
                ? as operator,
                NOW(6) as created_at
            FROM mrs_package_ledger
            WHERE ledger_id IN ($placeholders)
        ";

        $log_params = array_merge([$reason, $operator], $ledger_ids);
        $stmt = $pdo->prepare($log_sql);
        $stmt->execute($log_params);

        // 删除包裹项（虽然有外键级联，但显式删除更清晰）
        $delete_items_sql = "
            DELETE FROM mrs_package_items
            WHERE ledger_id IN ($placeholders)
        ";
        $stmt = $pdo->prepare($delete_items_sql);
        $stmt->execute($ledger_ids);
        $items_deleted = $stmt->rowCount();

        // 删除包裹台账
        $delete_ledger_sql = "
            DELETE FROM mrs_package_ledger
            WHERE ledger_id IN ($placeholders)
        ";
        $stmt = $pdo->prepare($delete_ledger_sql);
        $stmt->execute($ledger_ids);
        $packages_deleted = $stmt->rowCount();

        $pdo->commit();

        mrs_log(
            "Bulk deleted {$packages_deleted} packages by {$operator}. Reason: {$reason}",
            'INFO'
        );

        return [
            'success' => true,
            'packages_deleted' => $packages_deleted,
            'items_deleted' => $items_deleted,
            'message' => "成功删除 {$packages_deleted} 个包裹"
        ];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        mrs_log('Failed to bulk delete packages: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => '删除失败: ' . $e->getMessage()
        ];
    }
}

