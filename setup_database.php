<?php
/**
 * 数据库初始化脚本
 * 文件路径: setup_database.php
 * 用途: 初始化MRS系统数据库，创建所有必需的表结构
 *
 * 使用方法:
 * 1. 确保MySQL/MariaDB服务已启动
 * 2. 复制 .env.local.example 为 .env.local 并配置数据库连接信息
 * 3. 在浏览器中访问: http://your-domain/setup_database.php
 * 4. 或在命令行运行: php setup_database.php
 *
 * 安全提示: 初始化完成后请删除此文件！
 */

// 允许命令行和Web访问
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>数据库初始化</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}
    .success{color:#28a745;}.error{color:#dc3545;}.info{color:#007bff;}
    pre{background:#fff;padding:15px;border-radius:5px;border:1px solid #ddd;}</style></head><body>";
    echo "<h1>MRS系统数据库初始化</h1>";
}

// 读取环境配置
function loadEnvConfig() {
    $envFile = __DIR__ . '/.env.local';
    if (!file_exists($envFile)) {
        return [
            'host' => 'localhost',
            'name' => 'mrs_local',
            'user' => 'mrs_user',
            'pass' => 'mrs_password_local_2024',
            'charset' => 'utf8mb4'
        ];
    }

    $config = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === 'MRS_DB_HOST') $config['host'] = $value;
            if ($key === 'MRS_DB_NAME') $config['name'] = $value;
            if ($key === 'MRS_DB_USER') $config['user'] = $value;
            if ($key === 'MRS_DB_PASS') $config['pass'] = $value;
            if ($key === 'MRS_DB_CHARSET') $config['charset'] = $value;
        }
    }

    return $config;
}

function logMessage($message, $type = 'info') {
    $colors = [
        'info' => "\033[0;34m",
        'success' => "\033[0;32m",
        'error' => "\033[0;31m",
        'warning' => "\033[1;33m",
        'reset' => "\033[0m"
    ];

    if (PHP_SAPI === 'cli') {
        echo $colors[$type] . $message . $colors['reset'] . PHP_EOL;
    } else {
        echo "<div class='$type'>" . htmlspecialchars($message) . "</div>";
        flush();
    }
}

try {
    logMessage("===================================", 'info');
    logMessage("MRS系统数据库初始化开始", 'info');
    logMessage("===================================", 'info');

    // 加载配置
    $config = loadEnvConfig();
    logMessage("数据库配置加载完成", 'success');
    logMessage("主机: " . $config['host'], 'info');
    logMessage("数据库: " . $config['name'], 'info');
    logMessage("用户: " . $config['user'], 'info');

    // 连接MySQL（不指定数据库）
    logMessage("\n连接MySQL服务器...", 'info');
    $dsn = sprintf('mysql:host=%s;charset=%s', $config['host'], $config['charset']);
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    logMessage("MySQL连接成功", 'success');

    // 创建数据库
    logMessage("\n创建数据库 {$config['name']}...", 'info');
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['name']}`
                CHARACTER SET {$config['charset']}
                COLLATE {$config['charset']}_unicode_ci");
    logMessage("数据库创建成功", 'success');

    // 选择数据库
    $pdo->exec("USE `{$config['name']}`");

    // 读取并执行SQL文件
    $sqlFile = __DIR__ . '/docs/mrsexp_db_schema_structure_only.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL文件不存在: $sqlFile");
    }

    logMessage("\n读取SQL文件: $sqlFile", 'info');
    $sql = file_get_contents($sqlFile);

    // 分割SQL语句并执行
    logMessage("开始执行SQL语句...", 'info');
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) &&
                   strpos($stmt, '--') !== 0 &&
                   strpos($stmt, '/*') !== 0;
        }
    );

    $successCount = 0;
    $skipCount = 0;

    foreach ($statements as $statement) {
        // 跳过注释和空语句
        if (empty($statement) ||
            preg_match('/^(--|\/\*|SET |START |COMMIT|USE )/i', $statement)) {
            $skipCount++;
            continue;
        }

        try {
            $pdo->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            // 忽略"已存在"的错误
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate') !== false) {
                $skipCount++;
            } else {
                logMessage("警告: " . $e->getMessage(), 'warning');
            }
        }
    }

    logMessage("\nSQL执行完成", 'success');
    logMessage("成功执行: $successCount 条语句", 'success');
    logMessage("跳过: $skipCount 条语句", 'info');

    // 验证表创建
    logMessage("\n验证表结构...", 'info');
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);

    logMessage("已创建 " . count($tables) . " 个表:", 'success');
    foreach ($tables as $table) {
        logMessage("  ✓ $table", 'success');
    }

    // 可选：插入测试数据
    $testDataFile = __DIR__ . '/INSERT_TEST_DATA.sql';
    if (file_exists($testDataFile)) {
        logMessage("\n发现测试数据文件，是否导入？", 'info');
        logMessage("(如需导入，请在浏览器中手动执行)", 'warning');
    }

    logMessage("\n===================================", 'info');
    logMessage("数据库初始化完成！", 'success');
    logMessage("===================================", 'info');
    logMessage("\n重要提示：", 'warning');
    logMessage("1. 数据库初始化成功", 'success');
    logMessage("2. 请立即删除此setup_database.php文件以确保安全", 'error');
    logMessage("3. 请访问系统登录页面开始使用", 'info');

} catch (PDOException $e) {
    logMessage("\n数据库错误: " . $e->getMessage(), 'error');
    logMessage("错误代码: " . $e->getCode(), 'error');

    if ($e->getCode() == 1045) {
        logMessage("\n可能的原因：", 'warning');
        logMessage("1. 数据库用户名或密码错误", 'info');
        logMessage("2. 请检查 .env.local 文件中的配置", 'info');
        logMessage("3. 确保MySQL用户有足够的权限", 'info');
    } elseif ($e->getCode() == 2002) {
        logMessage("\n可能的原因：", 'warning');
        logMessage("1. MySQL服务未启动", 'info');
        logMessage("2. 主机地址错误", 'info');
        logMessage("3. 防火墙阻止连接", 'info');
    }

    exit(1);
} catch (Exception $e) {
    logMessage("\n错误: " . $e->getMessage(), 'error');
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    echo "</body></html>";
}
?>
