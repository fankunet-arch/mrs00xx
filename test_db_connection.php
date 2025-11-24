<?php
/**
 * MRS 数据库连接测试脚本
 * 用途：验证数据库连接和数据状态
 */

define('MRS_ENTRY', true);
require_once './app/mrs/config_mrs/env_mrs.php';

echo "=== MRS 数据库连接测试 ===\n\n";

// 测试1: 数据库连接
echo "测试1: 数据库连接\n";
echo "--------------------\n";
echo "Host: " . DB_HOST . "\n";
echo "Database: " . DB_NAME . "\n";
echo "User: " . DB_USER . "\n\n";

try {
    $pdo = get_db_connection();
    echo "✅ 数据库连接成功！\n\n";

    // 测试2: 检查表是否存在
    echo "测试2: 检查表结构\n";
    echo "--------------------\n";

    $tables = ['mrs_sku', 'mrs_category', 'mrs_batch', 'mrs_batch_raw_record'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ 表 $table 存在\n";
        } else {
            echo "❌ 表 $table 不存在\n";
        }
    }
    echo "\n";

    // 测试3: 检查数据量
    echo "测试3: 数据统计\n";
    echo "--------------------\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM mrs_sku");
    $skuCount = $stmt->fetchColumn();
    echo "SKU数量: $skuCount\n";

    if ($skuCount == 0) {
        echo "⚠️ 警告: 数据库中没有SKU数据！\n";
        echo "   搜索功能将返回空结果。\n";
        echo "   请先添加SKU数据或导入测试数据。\n\n";
    } else {
        echo "✅ SKU数据正常\n\n";

        // 显示前5个SKU
        echo "示例SKU数据（前5条）:\n";
        $stmt = $pdo->query("SELECT sku_id, sku_name, brand_name, standard_unit FROM mrs_sku LIMIT 5");
        $skus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($skus as $sku) {
            echo "  - [{$sku['sku_id']}] {$sku['sku_name']} ({$sku['brand_name']}) - {$sku['standard_unit']}\n";
        }
        echo "\n";
    }

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM mrs_category");
    $catCount = $stmt->fetchColumn();
    echo "品类数量: $catCount\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM mrs_batch");
    $batchCount = $stmt->fetchColumn();
    echo "批次数量: $batchCount\n\n";

    // 测试4: 测试搜索功能
    if ($skuCount > 0) {
        echo "测试4: 测试搜索SQL\n";
        echo "--------------------\n";

        // 获取第一个SKU的部分名称进行搜索测试
        $stmt = $pdo->query("SELECT sku_name FROM mrs_sku LIMIT 1");
        $firstSku = $stmt->fetchColumn();

        if ($firstSku) {
            $searchKeyword = substr($firstSku, 0, 2); // 取前两个字符
            echo "搜索关键词: '$searchKeyword'\n";

            $sql = "SELECT COUNT(*) FROM mrs_sku WHERE sku_name LIKE :keyword OR brand_name LIKE :keyword";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':keyword', '%' . $searchKeyword . '%');
            $stmt->execute();
            $searchCount = $stmt->fetchColumn();

            echo "搜索结果数量: $searchCount\n";

            if ($searchCount > 0) {
                echo "✅ 搜索功能SQL正常\n\n";
            } else {
                echo "⚠️ 搜索没有结果（可能关键词不匹配）\n\n";
            }
        }
    }

    // 测试5: 测试input_sku_name字段
    echo "测试5: 检查input_sku_name字段\n";
    echo "--------------------\n";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM mrs_batch_raw_record LIKE 'input_sku_name'");
        if ($stmt->rowCount() > 0) {
            echo "✅ input_sku_name 字段存在\n";
            echo "   手动输入物料名称功能已启用\n\n";
        } else {
            echo "❌ input_sku_name 字段不存在\n";
            echo "   需要执行迁移脚本: docs/migrations/001_add_input_sku_name_to_raw_record.sql\n\n";
        }
    } catch (Exception $e) {
        echo "⚠️ 无法检查字段: " . $e->getMessage() . "\n\n";
    }

    echo "=== 测试完成 ===\n";
    echo "如果所有测试通过，搜索功能应该可以正常工作。\n";

} catch (PDOException $e) {
    echo "❌ 数据库连接失败！\n";
    echo "错误信息: " . $e->getMessage() . "\n\n";
    echo "可能的原因:\n";
    echo "1. 数据库服务器未启动\n";
    echo "2. 数据库连接信息不正确\n";
    echo "3. 网络连接问题\n";
    echo "4. 数据库用户权限不足\n\n";
    echo "解决方法:\n";
    echo "1. 检查数据库服务器状态\n";
    echo "2. 验证 app/mrs/config_mrs/env_mrs.php 中的配置\n";
    echo "3. 或设置环境变量:\n";
    echo "   export MRS_DB_HOST=localhost\n";
    echo "   export MRS_DB_NAME=mrs_local\n";
    echo "   export MRS_DB_USER=root\n";
    echo "   export MRS_DB_PASS=your_password\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ 测试过程中发生错误:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
