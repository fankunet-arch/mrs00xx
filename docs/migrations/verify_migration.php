<?php
/**
 * 验证迁移结果
 * 文件路径: docs/migrations/verify_migration.php
 *
 * 使用方法：
 * php docs/migrations/verify_migration.php
 */

// 设置项目根目录
define('PROJECT_ROOT', dirname(dirname(__DIR__)));
define('MRS_ENTRY', true);

// 加载配置
require_once PROJECT_ROOT . '/app/mrs/config_mrs/env_mrs.php';

echo "========================================\n";
echo "验证迁移结果\n";
echo "========================================\n\n";

try {
    $pdo = get_mrs_db_connection();

    // 1. 检查表结构是否已扩展
    echo "1. 检查 mrs_sku 表结构...\n";
    $columns_sql = "SHOW COLUMNS FROM mrs_sku";
    $columns = $pdo->query($columns_sql)->fetchAll(PDO::FETCH_ASSOC);

    $required_fields = [
        'sku_name_cn' => false,
        'sku_name_es' => false,
        'product_category' => false,
        'barcode' => false,
        'shelf_life_months' => false,
        'supplier_country' => false
    ];

    foreach ($columns as $col) {
        if (isset($required_fields[$col['Field']])) {
            $required_fields[$col['Field']] = true;
        }
    }

    $all_fields_exist = true;
    foreach ($required_fields as $field => $exists) {
        if ($exists) {
            echo "  ✓ 字段 '$field' 存在\n";
        } else {
            echo "  ✗ 字段 '$field' 不存在\n";
            $all_fields_exist = false;
        }
    }

    if (!$all_fields_exist) {
        echo "\n⚠️ 表结构尚未扩展，请先执行：\n";
        echo "   php docs/migrations/run_migration.php\n\n";
        exit(1);
    }

    echo "  ✓ 表结构检查通过\n\n";

    // 2. 统计SKU数据
    echo "2. SKU表数据统计...\n";
    $stats_sql = "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count,
        SUM(CASE WHEN product_category IS NOT NULL THEN 1 ELSE 0 END) as with_category,
        SUM(CASE WHEN barcode IS NOT NULL AND barcode != '' THEN 1 ELSE 0 END) as with_barcode,
        SUM(CASE WHEN shelf_life_months IS NOT NULL THEN 1 ELSE 0 END) as with_shelf_life,
        SUM(CASE WHEN sku_name_es IS NOT NULL AND sku_name_es != '' THEN 1 ELSE 0 END) as with_spanish,
        SUM(CASE WHEN supplier_country IS NOT NULL THEN 1 ELSE 0 END) as with_supplier
    FROM mrs_sku";
    $stats = $pdo->query($stats_sql)->fetch();

    echo "  总SKU数量: " . $stats['total'] . "\n";
    echo "  使用中: " . $stats['active_count'] . "\n";
    echo "  已停用: " . $stats['inactive_count'] . "\n";
    echo "  已设置产品类别: " . $stats['with_category'] . " (" .
         ($stats['total'] > 0 ? round($stats['with_category'] / $stats['total'] * 100, 1) : 0) . "%)\n";
    echo "  已设置条码: " . $stats['with_barcode'] . " (" .
         ($stats['total'] > 0 ? round($stats['with_barcode'] / $stats['total'] * 100, 1) : 0) . "%)\n";
    echo "  已设置保质期: " . $stats['with_shelf_life'] . " (" .
         ($stats['total'] > 0 ? round($stats['with_shelf_life'] / $stats['total'] * 100, 1) : 0) . "%)\n";
    echo "  已设置西班牙语名称: " . $stats['with_spanish'] . " (" .
         ($stats['total'] > 0 ? round($stats['with_spanish'] / $stats['total'] * 100, 1) : 0) . "%)\n";
    echo "  已设置供货商国家: " . $stats['with_supplier'] . " (" .
         ($stats['total'] > 0 ? round($stats['with_supplier'] / $stats['total'] * 100, 1) : 0) . "%)\n\n";

    // 3. 检查库存商品是否都在SKU表中
    echo "3. 检查库存商品与SKU表的匹配情况...\n";

    // 从package_items获取商品
    $items_sql = "SELECT DISTINCT product_name
                  FROM mrs_package_items
                  WHERE product_name IS NOT NULL
                    AND TRIM(product_name) != ''";
    $items = $pdo->query($items_sql)->fetchAll(PDO::FETCH_COLUMN);

    echo "  库存中的商品种类: " . count($items) . "\n";

    if (count($items) > 0) {
        // 检查有多少商品已在SKU表中
        $placeholders = str_repeat('?,', count($items) - 1) . '?';
        $match_sql = "SELECT COUNT(*) as matched
                      FROM mrs_sku
                      WHERE COALESCE(sku_name_cn, sku_name) IN ($placeholders)";
        $stmt = $pdo->prepare($match_sql);
        $stmt->execute($items);
        $matched = $stmt->fetchColumn();

        echo "  已在SKU表中: " . $matched . " (" .
             round($matched / count($items) * 100, 1) . "%)\n";

        if ($matched < count($items)) {
            echo "  ⚠️ 还有 " . (count($items) - $matched) . " 个商品未在SKU表中\n\n";

            // 显示未匹配的商品
            $unmatch_sql = "SELECT DISTINCT pi.product_name
                           FROM mrs_package_items pi
                           WHERE pi.product_name IS NOT NULL
                             AND TRIM(pi.product_name) != ''
                             AND NOT EXISTS (
                                 SELECT 1
                                 FROM mrs_sku s
                                 WHERE COALESCE(s.sku_name_cn, s.sku_name) = pi.product_name
                             )
                           LIMIT 10";
            $unmatched = $pdo->query($unmatch_sql)->fetchAll(PDO::FETCH_COLUMN);

            echo "  未匹配的商品（前10个）：\n";
            foreach ($unmatched as $idx => $name) {
                echo "    " . ($idx + 1) . ". " . $name . "\n";
            }

            if (count($items) - $matched > 10) {
                echo "    ... 还有 " . (count($items) - $matched - 10) . " 个\n";
            }

            echo "\n  建议执行数据迁移：\n";
            echo "   php docs/migrations/migrate_all_products_to_sku.php\n\n";
        } else {
            echo "  ✓ 所有库存商品都已在SKU表中\n\n";
        }
    }

    // 4. 产品类别分布
    if ($stats['with_category'] > 0) {
        echo "4. 产品类别分布...\n";
        $category_sql = "SELECT
            product_category,
            COUNT(*) as count
        FROM mrs_sku
        WHERE product_category IS NOT NULL
        GROUP BY product_category
        ORDER BY count DESC";
        $categories = $pdo->query($category_sql)->fetchAll();

        $category_names = [
            'packaging' => '包材',
            'raw_material' => '原物料',
            'semi_finished' => '半成品',
            'finished_product' => '成品'
        ];

        foreach ($categories as $cat) {
            $name = $category_names[$cat['product_category']] ?? $cat['product_category'];
            echo "  " . $name . ": " . $cat['count'] . "\n";
        }
        echo "\n";
    }

    // 5. 最近添加的SKU
    echo "5. 最近添加的SKU（前5个）...\n";
    $recent_sql = "SELECT
        sku_code,
        COALESCE(sku_name_cn, sku_name) as name,
        created_at
    FROM mrs_sku
    ORDER BY created_at DESC
    LIMIT 5";
    $recent = $pdo->query($recent_sql)->fetchAll();

    if (!empty($recent)) {
        foreach ($recent as $sku) {
            echo "  " . $sku['sku_code'] . " - " . $sku['name'] .
                 " (" . date('Y-m-d H:i', strtotime($sku['created_at'])) . ")\n";
        }
    } else {
        echo "  暂无SKU数据\n";
    }

    echo "\n========================================\n";
    echo "验证完成\n";
    echo "========================================\n\n";

    // 总结和建议
    if ($stats['total'] == 0) {
        echo "⚠️ SKU表中暂无数据\n";
        echo "\n建议执行数据迁移：\n";
        echo "  php docs/migrations/migrate_all_products_to_sku.php\n\n";
    } elseif ($stats['total'] > 0 && $matched < count($items)) {
        echo "⚠️ 部分库存商品尚未在SKU表中\n";
        echo "\n建议执行增量迁移：\n";
        echo "  php docs/migrations/migrate_all_products_to_sku.php\n\n";
    } else {
        echo "✓ 迁移验证通过！\n\n";

        // 提供后续建议
        if ($stats['with_category'] / $stats['total'] < 0.5) {
            echo "💡 建议：在SKU管理页面中为商品设置产品类别\n";
        }
        if ($stats['with_barcode'] / $stats['total'] < 0.5) {
            echo "💡 建议：为常用商品录入条码，方便扫码管理\n";
        }
        if ($stats['with_shelf_life'] / $stats['total'] < 0.5) {
            echo "💡 建议：设置商品保质期，启用有效期提醒功能\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "\n✗ 验证失败: " . $e->getMessage() . "\n";
    exit(1);
}
