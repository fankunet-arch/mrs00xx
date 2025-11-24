<?php
/**
 * MRS 物料收发管理系统 - 业务库函数
 * 文件路径: app/mrs/lib/mrs_lib.php
 * 说明: 数据库操作、业务逻辑封装
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// ============================================
// 批次相关函数
// ============================================

/**
 * 获取批次列表
 * @param int $limit 限制数量
 * @param string $status 状态筛选
 * @return array
 */
function get_batch_list($limit = 20, $status = null) {
    try {
        $pdo = get_db_connection();

        $sql = "SELECT
                    batch_id,
                    batch_code,
                    batch_date,
                    location_name,
                    remark,
                    batch_status,
                    created_at,
                    updated_at
                FROM mrs_batch
                WHERE 1=1";

        $params = [];

        if ($status !== null) {
            $sql .= " AND batch_status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY batch_date DESC, created_at DESC LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        mrs_log('获取批次列表失败: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * 根据ID获取批次信息
 * @param int $batch_id
 * @return array|null
 */
function get_batch_by_id($batch_id) {
    try {
        $pdo = get_db_connection();

        $sql = "SELECT
                    batch_id,
                    batch_code,
                    batch_date,
                    location_name,
                    remark,
                    batch_status,
                    created_at,
                    updated_at
                FROM mrs_batch
                WHERE batch_id = :batch_id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':batch_id', $batch_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();

    } catch (PDOException $e) {
        mrs_log('获取批次信息失败: ' . $e->getMessage(), 'ERROR');
        return null;
    }
}

// ============================================
// SKU相关函数
// ============================================

/**
 * 搜索SKU
 * @param string $keyword 关键词
 * @param int $limit 限制数量
 * @return array
 */
function search_sku($keyword, $limit = 20) {
    try {
        $pdo = get_db_connection();

        $sql = "SELECT
                    s.sku_id,
                    s.sku_name,
                    s.sku_code,
                    s.brand_name,
                    s.standard_unit,
                    s.case_unit_name,
                    s.case_to_standard_qty,
                    s.pack_unit_name,
                    s.pack_to_standard_qty,
                    s.is_precise_item,
                    c.category_name
                FROM mrs_sku s
                LEFT JOIN mrs_category c ON s.category_id = c.category_id
                WHERE (
                    s.sku_name LIKE :keyword1
                    OR s.sku_code LIKE :keyword2
                    OR s.brand_name LIKE :keyword3
                )
                ORDER BY s.is_precise_item DESC, s.sku_name ASC
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $searchTerm = '%' . $keyword . '%';
        $stmt->bindValue(':keyword1', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':keyword2', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':keyword3', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();

    } catch (PDOException $e) {
        mrs_log('搜索SKU失败: ' . $e->getMessage(), 'ERROR', ['keyword' => $keyword]);
        return [];
    }
}

/**
 * 根据ID获取SKU信息
 * @param int $sku_id
 * @return array|null
 */
function get_sku_by_id($sku_id) {
    try {
        $pdo = get_db_connection();

        $sql = "SELECT
                    s.sku_id,
                    s.category_id,
                    s.sku_name,
                    s.sku_code,
                    s.brand_name,
                    s.standard_unit,
                    s.case_unit_name,
                    s.case_to_standard_qty,
                    s.pack_unit_name,
                    s.pack_to_standard_qty,
                    s.is_precise_item,
                    s.note,
                    c.category_name
                FROM mrs_sku s
                LEFT JOIN mrs_category c ON s.category_id = c.category_id
                WHERE s.sku_id = :sku_id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':sku_id', $sku_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();

    } catch (PDOException $e) {
        mrs_log('获取SKU信息失败: ' . $e->getMessage(), 'ERROR');
        return null;
    }
}

// ============================================
// 原始记录相关函数
// ============================================

/**
 * 保存原始收货记录
 * @param array $data 记录数据
 * @return int|false 返回插入的ID或false
 */
function save_raw_record($data) {
    try {
        $pdo = get_db_connection();

        // 验证必填字段
        $required_fields = ['batch_id', 'qty', 'unit_name', 'operator_name'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                mrs_log('保存原始记录失败: 缺少必填字段 ' . $field, 'ERROR', $data);
                return false;
            }
        }

        $sql = "INSERT INTO mrs_batch_raw_record (
                    batch_id,
                    sku_id,
                    input_sku_name,
                    qty,
                    unit_name,
                    operator_name,
                    recorded_at,
                    note,
                    created_at,
                    updated_at
                ) VALUES (
                    :batch_id,
                    :sku_id,
                    :input_sku_name,
                    :qty,
                    :unit_name,
                    :operator_name,
                    :recorded_at,
                    :note,
                    NOW(6),
                    NOW(6)
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':batch_id', $data['batch_id'], PDO::PARAM_INT);
        $stmt->bindValue(':sku_id', $data['sku_id'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':input_sku_name', $data['input_sku_name'] ?? null, PDO::PARAM_STR); // [FIX] 保存手动输入的物料名
        $stmt->bindValue(':qty', $data['qty'], PDO::PARAM_STR);
        $stmt->bindValue(':unit_name', $data['unit_name'], PDO::PARAM_STR);
        $stmt->bindValue(':operator_name', $data['operator_name'], PDO::PARAM_STR);
        $stmt->bindValue(':recorded_at', $data['recorded_at'] ?? date('Y-m-d H:i:s.u'), PDO::PARAM_STR);
        $stmt->bindValue(':note', $data['note'] ?? '', PDO::PARAM_STR);

        $stmt->execute();

        return $pdo->lastInsertId();

    } catch (PDOException $e) {
        mrs_log('保存原始记录失败: ' . $e->getMessage(), 'ERROR', $data);
        return false;
    }
}

/**
 * 获取批次的原始记录列表
 * @param int $batch_id
 * @return array
 */
function get_batch_raw_records($batch_id) {
    try {
        $pdo = get_db_connection();

        $sql = "SELECT
                    r.raw_record_id,
                    r.batch_id,
                    r.sku_id,
                    r.input_sku_name,
                    r.qty,
                    r.unit_name,
                    r.operator_name,
                    r.recorded_at,
                    r.note,
                    COALESCE(r.input_sku_name, s.sku_name) AS sku_name,
                    s.brand_name
                FROM mrs_batch_raw_record r
                LEFT JOIN mrs_sku s ON r.sku_id = s.sku_id
                WHERE r.batch_id = :batch_id
                ORDER BY r.recorded_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':batch_id', $batch_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();

    } catch (PDOException $e) {
        mrs_log('获取批次原始记录失败: ' . $e->getMessage(), 'ERROR');
        return [];
    }
}

// ============================================
// 辅助函数
// ============================================

/**
 * 生成批次编号
 * @param string $date 日期 (Y-m-d)
 * @return string
 */
function generate_batch_code($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }

    try {
        $pdo = get_db_connection();

        // 查找当天已有的最大序号
        $sql = "SELECT batch_code
                FROM mrs_batch
                WHERE batch_date = :batch_date
                ORDER BY batch_code DESC
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':batch_date', $date, PDO::PARAM_STR);
        $stmt->execute();

        $last_code = $stmt->fetchColumn();

        if ($last_code) {
            // 提取最后的序号
            preg_match('/-(\d+)$/', $last_code, $matches);
            $next_seq = isset($matches[1]) ? (int)$matches[1] + 1 : 1;
        } else {
            $next_seq = 1;
        }

        // 生成新编号: IN-2025-11-24-001
        return sprintf('IN-%s-%03d', $date, $next_seq);

    } catch (PDOException $e) {
        mrs_log('生成批次编号失败: ' . $e->getMessage(), 'ERROR');
        // 降级方案: 使用时间戳
        return 'IN-' . $date . '-' . time();
    }
}

/**
 * 验证批次状态是否允许添加记录
 * @param string $status
 * @return bool
 */
function is_batch_editable($status) {
    $editable_statuses = ['draft', 'receiving'];
    return in_array($status, $editable_statuses);
}

// ============================================
// 用户认证和会话管理函数
// ============================================

/**
 * 启动安全会话
 */
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // 设置安全的会话配置
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');

        session_start();

        // 防止会话固定攻击:为新会话重新生成ID
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

/**
 * 验证用户登录
 * @param string $username 用户名
 * @param string $password 密码
 * @return array|false 成功返回用户信息,失败返回false
 */
function authenticate_user($username, $password) {
    try {
        $pdo = get_db_connection();

        $sql = "SELECT
                    user_id,
                    user_login,
                    user_secret_hash,
                    user_email,
                    user_display_name,
                    user_status
                FROM sys_users
                WHERE user_login = :username
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':username', $username);
        $stmt->execute();

        $user = $stmt->fetch();

        if (!$user) {
            mrs_log("登录失败: 用户不存在 - {$username}", 'WARNING');
            return false;
        }

        // 检查账户状态
        if ($user['user_status'] !== 'active') {
            mrs_log("登录失败: 账户未激活 - {$username}", 'WARNING');
            return false;
        }

        // 验证密码
        if (password_verify($password, $user['user_secret_hash'])) {
            // 更新最后登录时间
            $updateSql = "UPDATE sys_users SET user_last_login_at = NOW(6) WHERE user_id = :user_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
            $updateStmt->execute();

            mrs_log("登录成功: {$username}", 'INFO');

            // 移除敏感信息
            unset($user['user_secret_hash']);

            return $user;
        } else {
            mrs_log("登录失败: 密码错误 - {$username}", 'WARNING');
            return false;
        }

    } catch (PDOException $e) {
        mrs_log('用户认证失败: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 创建用户会话
 * @param array $user 用户信息
 */
function create_user_session($user) {
    start_secure_session();

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_login'] = $user['user_login'];
    $_SESSION['user_display_name'] = $user['user_display_name'];
    $_SESSION['user_email'] = $user['user_email'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * 检查用户是否已登录
 * @return bool
 */
function is_user_logged_in() {
    start_secure_session();

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    // 检查会话超时(30分钟无活动)
    $timeout = 1800; // 30分钟
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        destroy_user_session();
        return false;
    }

    // 更新最后活动时间
    $_SESSION['last_activity'] = time();

    return true;
}

/**
 * 获取当前登录用户信息
 * @return array|null
 */
function mrs_get_current_user() {
    if (!is_user_logged_in()) {
        return null;
    }

    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_login' => $_SESSION['user_login'] ?? null,
        'user_display_name' => $_SESSION['user_display_name'] ?? null,
        'user_email' => $_SESSION['user_email'] ?? null,
    ];
}

/**
 * 销毁用户会话
 */
function destroy_user_session() {
    start_secure_session();

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
 * 创建新用户(仅供管理员使用)
 * @param string $username 用户名
 * @param string $password 密码
 * @param string $email 邮箱
 * @param string $display_name 显示名称
 * @return int|false 成功返回用户ID,失败返回false
 */
function create_user($username, $password, $email, $display_name) {
    try {
        $pdo = get_db_connection();

        // 检查用户名是否已存在
        $checkSql = "SELECT user_id FROM sys_users WHERE user_login = :username";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':username', $username);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            mrs_log("创建用户失败: 用户名已存在 - {$username}", 'ERROR');
            return false;
        }

        // 哈希密码
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // 插入新用户
        $sql = "INSERT INTO sys_users (
                    user_login,
                    user_secret_hash,
                    user_email,
                    user_display_name,
                    user_status,
                    user_registered_at
                ) VALUES (
                    :username,
                    :password_hash,
                    :email,
                    :display_name,
                    'active',
                    NOW(6)
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':password_hash', $passwordHash);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':display_name', $display_name);
        $stmt->execute();

        $userId = $pdo->lastInsertId();

        mrs_log("新用户创建成功: {$username} (ID: {$userId})", 'INFO');

        return $userId;

    } catch (PDOException $e) {
        mrs_log('创建用户失败: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 要求用户登录,未登录则跳转到登录页
 */
function require_login() {
    if (!is_user_logged_in()) {
        // 跳转到相对于入口的 login.php
        header('Location: login.php');
        exit;
    }
}

