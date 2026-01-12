<?php
/**
 * 将现有库存商品名称迁移到SKU表
 * 文件路径: docs/migrations/migrate_products_to_sku.php
 *
 * 使用方法：
 * php docs/migrations/migrate_products_to_sku.php
 */

// 设置项目根目录
define('PROJECT_ROOT', dirname(dirname(__DIR__)));
define('MRS_ENTRY', true);

// 加载配置
require_once PROJECT_ROOT . '/app/mrs/config_mrs/env_mrs.php';

echo "========================================\n";
echo "商品名称迁移到SKU表\n";
echo "========================================\n\n";

try {
    $pdo = get_mrs_db_connection();

    // 开启事务
    $pdo->beginTransaction();

    // 1. 从 mrs_package_items 获取所有唯一的产品名称
    echo "步骤 1: 获取现有库存中的所有唯一商品名称...\n";
    $sql = "SELECT DISTINCT product_name
            FROM mrs_package_items
            WHERE product_name IS NOT NULL
              AND TRIM(product_name) != ''
            ORDER BY product_name";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "找到 " . count($products) . " 个唯一商品名称\n\n";

    if (empty($products)) {
        echo "没有需要迁移的商品，退出。\n";
        exit(0);
    }

    // 2. 检查哪些商品已经存在于SKU表中
    echo "步骤 2: 检查已存在的SKU...\n";
    $existing_sql = "SELECT COALESCE(sku_name_cn, sku_name) as name
                     FROM mrs_sku
                     WHERE COALESCE(sku_name_cn, sku_name) IN (" .
                     str_repeat('?,', count($products) - 1) . "?)";
    $stmt = $pdo->prepare($existing_sql);
    $stmt->execute($products);
    $existing_products = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "已存在 " . count($existing_products) . " 个SKU\n";

    // 3. 过滤出需要新增的商品
    $new_products = array_diff($products, $existing_products);

    if (empty($new_products)) {
        echo "\n所有商品都已存在于SKU表中，无需迁移。\n";
        $pdo->rollBack();
        exit(0);
    }

    echo "需要新增 " . count($new_products) . " 个SKU\n\n";

    // 4. 批量插入新商品到SKU表
    echo "步骤 3: 开始批量插入新商品...\n";
    echo "注意：所有商品名称都作为中文名称（sku_name_cn），西班牙语名称留空\n\n";

    $insert_sql = "INSERT INTO mrs_sku (
        sku_code,
        sku_name_cn,
        sku_name,
        status,
        standard_unit,
        case_unit_name,
        case_to_standard_qty,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, 'active', '件', '箱', 1.00, NOW(), NOW())";

    $stmt = $pdo->prepare($insert_sql);

    $success_count = 0;
    $error_count = 0;

    foreach ($new_products as $product_name) {
        try {
            // 生成自动SKU编码
            $sku_code = 'AUTO-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

            // 插入数据
            $stmt->execute([
                $sku_code,
                $product_name,
                $product_name  // 兼容旧字段
            ]);

            $success_count++;
            echo "  ✓ " . $product_name . " (SKU: $sku_code)\n";

        } catch (PDOException $e) {
            $error_count++;
            echo "  ✗ " . $product_name . " - 错误: " . $e->getMessage() . "\n";
        }
    }

    // 提交事务
    $pdo->commit();

    echo "\n========================================\n";
    echo "迁移完成！\n";
    echo "========================================\n";
    echo "成功: $success_count 个\n";
    echo "失败: $error_count 个\n";

    // 5. 显示最终统计
    echo "\n当前SKU表统计：\n";
    $stats_sql = "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
    FROM mrs_sku";
    $stats = $pdo->query($stats_sql)->fetch();

    echo "  总计: " . $stats['total'] . " 个SKU\n";
    echo "  使用中: " . $stats['active_count'] . " 个\n";
    echo "  已停用: " . $stats['inactive_count'] . " 个\n";

    echo "\n迁移成功！\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n迁移失败: " . $e->getMessage() . "\n";
    echo "详细错误信息: " . $e->getTraceAsString() . "\n";
    exit(1);
}
