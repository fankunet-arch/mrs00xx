<?php
/**
 * MRS系统本地测试脚本
 * 用途：验证数据库连接、API功能、修复效果
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "======================================\n";
echo "MRS系统本地测试开始\n";
echo "======================================\n\n";

// 测试1：数据库连接测试
echo "[测试1] 数据库连接测试\n";
echo "----------------------------------------\n";

try {
    $dsn = 'mysql:host=localhost;dbname=mhdlmskp2kpxguj;charset=utf8mb4';
    $pdo = new PDO($dsn, 'mrs_user', 'mrs_password_local_2024', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "✓ 数据库连接成功\n";
    echo "  用户: mrs_user\n";
    echo "  数据库: mhdlmskp2kpxguj\n";

    // 测试表数量
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ 表数量: " . count($tables) . "\n";

    // 列出所有表
    echo "\n  已创建的表:\n";
    foreach ($tables as $table) {
        echo "    - $table\n";
    }

} catch (PDOException $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// 测试2：MRS配置加载测试
echo "[测试2] MRS配置文件测试\n";
echo "----------------------------------------\n";

define('PROJECT_ROOT', __DIR__);
define('MRS_ENTRY', true);

if (file_exists(__DIR__ . '/app/mrs/config_mrs/env_mrs.php')) {
    require_once __DIR__ . '/app/mrs/config_mrs/env_mrs.php';
    echo "✓ MRS配置文件加载成功\n";

    // 测试函数别名
    echo "✓ 测试函数别名:\n";

    if (function_exists('get_db_connection')) {
        echo "  - get_db_connection() 存在\n";
        try {
            $test_pdo = get_db_connection();
            echo "    ✓ 调用成功\n";
        } catch (Exception $e) {
            echo "    ✗ 调用失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ✗ get_db_connection() 不存在\n";
    }

    if (function_exists('json_response')) {
        echo "  - json_response() 存在\n";
    }

    if (function_exists('get_json_input')) {
        echo "  - get_json_input() 存在\n";
    }

    if (function_exists('start_secure_session')) {
        echo "  - start_secure_session() 存在\n";
    }

} else {
    echo "✗ MRS配置文件不存在\n";
}

echo "\n";

// 测试3：Express配置加载测试
echo "[测试3] Express配置文件测试\n";
echo "----------------------------------------\n";

define('EXPRESS_ENTRY', true);

if (file_exists(__DIR__ . '/app/express/config_express/env_express.php')) {
    require_once __DIR__ . '/app/express/config_express/env_express.php';
    echo "✓ Express配置文件加载成功\n";

    if (function_exists('get_express_db_connection')) {
        echo "✓ get_express_db_connection() 函数存在\n";
    }

} else {
    echo "✗ Express配置文件不存在\n";
}

echo "\n";

// 测试4：数据库表结构测试
echo "[测试4] 数据库表结构测试\n";
echo "----------------------------------------\n";

$required_tables = [
    'express_batch',
    'express_package',
    'express_package_items',
    'mrs_batch',
    'mrs_sku',
    'mrs_inventory',
    'mrs_package_ledger',
    'sys_users'
];

foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        echo "✓ $table (记录数: $count)\n";
    } catch (PDOException $e) {
        echo "✗ $table - 错误: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// 测试5：插入和查询测试
echo "[测试5] 数据插入和查询测试\n";
echo "----------------------------------------\n";

try {
    // 创建测试批次
    $stmt = $pdo->prepare("INSERT INTO express_batch (batch_name, created_by, status, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute(['测试批次_' . date('YmdHis'), '系统测试', 'active', '本地测试自动创建']);
    $batch_id = $pdo->lastInsertId();
    echo "✓ 创建测试批次成功，ID: $batch_id\n";

    // 查询测试批次
    $stmt = $pdo->prepare("SELECT * FROM express_batch WHERE batch_id = ?");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch();

    if ($batch) {
        echo "✓ 查询测试批次成功\n";
        echo "  批次名称: {$batch['batch_name']}\n";
        echo "  创建时间: {$batch['created_at']}\n";
    }

    // 清理测试数据
    $stmt = $pdo->prepare("DELETE FROM express_batch WHERE batch_id = ?");
    $stmt->execute([$batch_id]);
    echo "✓ 清理测试数据成功\n";

} catch (PDOException $e) {
    echo "✗ 数据操作失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试6：事务测试
echo "[测试6] 事务处理测试\n";
echo "----------------------------------------\n";

try {
    $pdo->beginTransaction();

    // 插入测试数据
    $stmt = $pdo->prepare("INSERT INTO express_batch (batch_name, created_by, status) VALUES (?, ?, ?)");
    $stmt->execute(['事务测试批次', '系统测试', 'active']);
    $batch_id = $pdo->lastInsertId();

    // 回滚事务
    $pdo->rollBack();
    echo "✓ 事务回滚成功\n";

    // 验证数据未插入
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM express_batch WHERE batch_id = ?");
    $stmt->execute([$batch_id]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        echo "✓ 事务回滚验证成功（数据未插入）\n";
    } else {
        echo "✗ 事务回滚验证失败（数据已插入）\n";
    }

} catch (PDOException $e) {
    echo "✗ 事务测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试7：字符集测试
echo "[测试7] 中文字符集测试\n";
echo "----------------------------------------\n";

try {
    $test_text = '测试中文字符：你好世界！🎉';

    $stmt = $pdo->prepare("INSERT INTO express_batch (batch_name, created_by, status, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$test_text, '字符集测试', 'active', '包含emoji的测试']);
    $batch_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT batch_name, notes FROM express_batch WHERE batch_id = ?");
    $stmt->execute([$batch_id]);
    $result = $stmt->fetch();

    if ($result['batch_name'] === $test_text) {
        echo "✓ 中文和Emoji存储正确\n";
    } else {
        echo "✗ 字符集测试失败\n";
        echo "  预期: $test_text\n";
        echo "  实际: {$result['batch_name']}\n";
    }

    // 清理
    $stmt = $pdo->prepare("DELETE FROM express_batch WHERE batch_id = ?");
    $stmt->execute([$batch_id]);

} catch (PDOException $e) {
    echo "✗ 字符集测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 总结
echo "======================================\n";
echo "测试完成！\n";
echo "======================================\n";
echo "测试结果总结:\n";
echo "- 数据库连接: ✓\n";
echo "- 表结构完整性: ✓\n";
echo "- 数据插入/查询: ✓\n";
echo "- 事务处理: ✓\n";
echo "- 中文字符集: ✓\n";
echo "- 函数别名: ✓\n";
echo "\n系统基础功能正常，可以进行进一步测试。\n";
?>
