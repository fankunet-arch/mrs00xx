<?php
/**
 * 执行数据库迁移脚本
 * 文件路径: docs/migrations/run_migration.php
 */

// 设置项目根目录
define('PROJECT_ROOT', dirname(dirname(__DIR__)));
define('MRS_ENTRY', true);

// 加载配置
require_once PROJECT_ROOT . '/app/mrs/config_mrs/env_mrs.php';

try {
    $pdo = get_mrs_db_connection();

    // 读取SQL文件
    $sql_file = __DIR__ . '/add_sku_fields_20260112.sql';

    if (!file_exists($sql_file)) {
        die("SQL文件不存在: $sql_file\n");
    }

    $sql = file_get_contents($sql_file);

    // 分割SQL语句（按分号分割，但排除注释和空行）
    $statements = array_filter(
        array_map('trim', preg_split('/;[\r\n]+/', $sql)),
        function($stmt) {
            return !empty($stmt) &&
                   !preg_match('/^--/', $stmt) &&
                   !preg_match('/^USE /', $stmt);
        }
    );

    echo "开始执行迁移...\n";
    echo "共有 " . count($statements) . " 条SQL语句\n\n";

    $pdo->beginTransaction();

    $success_count = 0;
    foreach ($statements as $index => $statement) {
        try {
            if (trim($statement)) {
                echo "执行语句 " . ($index + 1) . "...\n";
                $pdo->exec($statement);
                $success_count++;
                echo "✓ 成功\n\n";
            }
        } catch (PDOException $e) {
            echo "✗ 失败: " . $e->getMessage() . "\n\n";
            // 如果是字段已存在的错误，继续执行
            if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
                strpos($e->getMessage(), 'Multiple primary key') !== false) {
                echo "  (字段已存在，跳过)\n\n";
                continue;
            }
            throw $e;
        }
    }

    $pdo->commit();

    echo "\n迁移完成!\n";
    echo "成功执行: $success_count 条语句\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}
