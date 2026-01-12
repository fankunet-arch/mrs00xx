<?php
/**
 * 综合迁移：将所有来源的商品名称迁移到SKU表
 * 文件路径: docs/migrations/migrate_all_products_to_sku.php
 *
 * 数据来源：
 * 1. mrs_package_items (包裹产品明细表)
 * 2. mrs_package_ledger (包裹台账表的content_note字段)
 *
 * 使用方法：
 * php docs/migrations/migrate_all_products_to_sku.php
 */

// 设置项目根目录
define('PROJECT_ROOT', dirname(dirname(__DIR__)));
define('MRS_ENTRY', true);

// 加载配置
require_once PROJECT_ROOT . '/app/mrs/config_mrs/env_mrs.php';

echo "========================================\n";
echo "综合迁移：商品名称 → SKU表\n";
echo "========================================\n\n";

try {
    $pdo = get_mrs_db_connection();

    // 开启事务
    $pdo->beginTransaction();

    // 1. 从多个来源收集所有唯一的产品名称
    echo "步骤 1: 收集所有商品名称...\n";

    $all_products = [];

    // 1.1 从 mrs_package_items 获取
    echo "  - 从 mrs_package_items 提取...\n";
    $sql1 = "SELECT DISTINCT product_name
             FROM mrs_package_items
             WHERE product_name IS NOT NULL
               AND TRIM(product_name) != ''";
    $stmt1 = $pdo->query($sql1);
    $products_from_items = $stmt1->fetchAll(PDO::FETCH_COLUMN);
    echo "    找到 " . count($products_from_items) . " 个\n";
    $all_products = array_merge($all_products, $products_from_items);

    // 1.2 从 mrs_package_ledger 获取
    echo "  - 从 mrs_package_ledger 提取...\n";
    $sql2 = "SELECT DISTINCT content_note
             FROM mrs_package_ledger
             WHERE content_note IS NOT NULL
               AND TRIM(content_note) != ''
               AND content_note NOT LIKE '%,%'";  // 排除包含逗号的（可能是多产品）
    $stmt2 = $pdo->query($sql2);
    $products_from_ledger = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    echo "    找到 " . count($products_from_ledger) . " 个\n";
    $all_products = array_merge($all_products, $products_from_ledger);

    // 去重并排序
    $all_products = array_unique($all_products);
    sort($all_products);

    echo "\n合计找到 " . count($all_products) . " 个唯一商品名称\n\n";

    if (empty($all_products)) {
        echo "没有需要迁移的商品，退出。\n";
        $pdo->rollBack();
        exit(0);
    }

    // 2. 检查哪些商品已经存在于SKU表中
    echo "步骤 2: 检查已存在的SKU...\n";
    $placeholders = str_repeat('?,', count($all_products) - 1) . '?';
    $existing_sql = "SELECT COALESCE(sku_name_cn, sku_name) as name
                     FROM mrs_sku
                     WHERE COALESCE(sku_name_cn, sku_name) IN ($placeholders)";
    $stmt = $pdo->prepare($existing_sql);
    $stmt->execute($all_products);
    $existing_products = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "已存在 " . count($existing_products) . " 个SKU\n";

    // 3. 过滤出需要新增的商品
    $new_products = array_diff($all_products, $existing_products);

    if (empty($new_products)) {
        echo "\n所有商品都已存在于SKU表中，无需迁移。\n";
        $pdo->rollBack();
        exit(0);
    }

    echo "需要新增 " . count($new_products) . " 个SKU\n\n";

    // 4. 显示待新增的商品列表（前10个）
    echo "待新增的商品列表（前10个）：\n";
    $preview = array_slice($new_products, 0, 10);
    foreach ($preview as $idx => $product) {
        echo "  " . ($idx + 1) . ". " . $product . "\n";
    }
    if (count($new_products) > 10) {
        echo "  ... 还有 " . (count($new_products) - 10) . " 个\n";
    }
    echo "\n";

    // 5. 确认是否继续
    echo "是否继续执行迁移？[Y/n]: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if (!empty($line) && strtolower($line) !== 'y' && strtolower($line) !== 'yes') {
        echo "已取消迁移。\n";
        $pdo->rollBack();
        exit(0);
    }

    // 6. 批量插入新商品到SKU表
    echo "\n步骤 3: 开始批量插入新商品...\n";

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
    $errors = [];

    foreach ($new_products as $product_name) {
        try {
            // 生成自动SKU编码（带时间戳避免冲突）
            $sku_code = 'AUTO-' . date('ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // 插入数据
            $stmt->execute([
                $sku_code,
                $product_name,
                $product_name  // 兼容旧字段
            ]);

            $success_count++;
            echo "  ✓ [" . $success_count . "/" . count($new_products) . "] " . $product_name . " (SKU: $sku_code)\n";

        } catch (PDOException $e) {
            $error_count++;
            $error_msg = "  ✗ " . $product_name . " - " . $e->getMessage();
            echo $error_msg . "\n";
            $errors[] = $error_msg;
        }
    }

    // 提交事务
    $pdo->commit();

    echo "\n========================================\n";
    echo "迁移完成！\n";
    echo "========================================\n";
    echo "✓ 成功: $success_count 个\n";
    if ($error_count > 0) {
        echo "✗ 失败: $error_count 个\n\n";
        echo "失败详情：\n";
        foreach ($errors as $error) {
            echo $error . "\n";
        }
    }

    // 7. 显示最终统计
    echo "\n当前SKU表统计：\n";
    $stats_sql = "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count,
        SUM(CASE WHEN product_category IS NOT NULL THEN 1 ELSE 0 END) as with_category,
        SUM(CASE WHEN barcode IS NOT NULL THEN 1 ELSE 0 END) as with_barcode,
        SUM(CASE WHEN shelf_life_months IS NOT NULL THEN 1 ELSE 0 END) as with_shelf_life
    FROM mrs_sku";
    $stats = $pdo->query($stats_sql)->fetch();

    echo "  总计: " . $stats['total'] . " 个SKU\n";
    echo "  使用中: " . $stats['active_count'] . " 个\n";
    echo "  已停用: " . $stats['inactive_count'] . " 个\n";
    echo "  已设置产品类别: " . $stats['with_category'] . " 个\n";
    echo "  已设置条码: " . $stats['with_barcode'] . " 个\n";
    echo "  已设置保质期: " . $stats['with_shelf_life'] . " 个\n";

    echo "\n✓ 迁移成功！接下来可以在SKU管理页面中完善各个SKU的详细信息。\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ 迁移失败: " . $e->getMessage() . "\n";
    echo "\n详细错误信息:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
