<?php
/**
 * 数据库连接测试脚本
 * 用于测试数据库连接和API功能
 */

// 定义入口常量
define('MRS_ENTRY', true);

// 加载配置文件
require_once __DIR__ . '/../app/mrs/config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

echo "<html><head><meta charset='UTF-8'><title>数据库连接测试</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f4f4f4;padding:10px;border-radius:5px;}</style>";
echo "</head><body>";
echo "<h1>数据库连接测试</h1>";

// 1. 测试数据库连接
echo "<h2>1. 测试数据库连接</h2>";
try {
    $pdo = get_db_connection();
    echo "<p class='success'>✓ 数据库连接成功!</p>";

    // 显示数据库配置信息(隐藏密码)
    echo "<p class='info'>数据库主机: " . DB_HOST . "</p>";
    echo "<p class='info'>数据库名称: " . DB_NAME . "</p>";
    echo "<p class='info'>数据库用户: " . DB_USER . "</p>";

} catch (PDOException $e) {
    echo "<p class='error'>✗ 数据库连接失败: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='info'>请检查数据库配置信息:</p>";
    echo "<ul>";
    echo "<li>数据库主机: " . htmlspecialchars(DB_HOST) . "</li>";
    echo "<li>数据库名称: " . htmlspecialchars(DB_NAME) . "</li>";
    echo "<li>数据库用户: " . htmlspecialchars(DB_USER) . "</li>";
    echo "</ul>";
    echo "</body></html>";
    exit;
}

// 2. 测试品类表
echo "<h2>2. 测试品类表 (mrs_category)</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM mrs_category");
    $result = $stmt->fetch();
    echo "<p class='success'>✓ 品类表可访问,共有 {$result['count']} 条记录</p>";

    // 显示前5条品类
    $stmt = $pdo->query("SELECT * FROM mrs_category LIMIT 5");
    $categories = $stmt->fetchAll();
    if (count($categories) > 0) {
        echo "<p class='info'>前5条品类数据:</p>";
        echo "<pre>" . print_r($categories, true) . "</pre>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>✗ 品类表访问失败: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. 测试SKU表
echo "<h2>3. 测试SKU表 (mrs_sku)</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM mrs_sku");
    $result = $stmt->fetch();
    echo "<p class='success'>✓ SKU表可访问,共有 {$result['count']} 条记录</p>";

    // 显示前5条SKU
    $stmt = $pdo->query("SELECT s.*, c.category_name FROM mrs_sku s LEFT JOIN mrs_category c ON s.category_id = c.category_id LIMIT 5");
    $skus = $stmt->fetchAll();
    if (count($skus) > 0) {
        echo "<p class='info'>前5条SKU数据:</p>";
        echo "<pre>" . print_r($skus, true) . "</pre>";
    } else {
        echo "<p class='info'>SKU表为空,可以添加测试数据</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>✗ SKU表访问失败: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 4. 测试get_sku_by_id函数
echo "<h2>4. 测试get_sku_by_id函数</h2>";
try {
    $stmt = $pdo->query("SELECT sku_id FROM mrs_sku LIMIT 1");
    $firstSku = $stmt->fetch();
    if ($firstSku) {
        $skuDetail = get_sku_by_id($firstSku['sku_id']);
        if ($skuDetail) {
            echo "<p class='success'>✓ get_sku_by_id函数工作正常</p>";
            echo "<p class='info'>SKU详情 (ID: {$firstSku['sku_id']}):</p>";
            echo "<pre>" . print_r($skuDetail, true) . "</pre>";

            // 检查是否包含category_id
            if (isset($skuDetail['category_id'])) {
                echo "<p class='success'>✓ category_id字段存在</p>";
            } else {
                echo "<p class='error'>✗ category_id字段缺失</p>";
            }

            // 检查是否包含note字段
            if (array_key_exists('note', $skuDetail)) {
                echo "<p class='success'>✓ note字段存在</p>";
            } else {
                echo "<p class='error'>✗ note字段缺失</p>";
            }
        } else {
            echo "<p class='error'>✗ get_sku_by_id返回null</p>";
        }
    } else {
        echo "<p class='info'>SKU表为空,无法测试get_sku_by_id函数</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ 测试失败: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 5. 测试API路径
echo "<h2>5. API路径信息</h2>";
echo "<p class='info'>API基础路径: " . htmlspecialchars(MRS_API_PATH) . "</p>";
echo "<p class='info'>API网关: /dc_html/mrs/api.php</p>";
echo "<p class='info'>SKU管理页面: /backend/sku_management.html</p>";

echo "<h2>测试完成!</h2>";
echo "<p>如果所有测试都通过,可以访问 <a href='sku_management.html'>SKU管理页面</a> 进行编辑操作测试。</p>";

echo "</body></html>";
?>
