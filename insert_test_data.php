<?php
/**
 * 插入测试数据脚本
 * 用途：向数据库插入测试数据以验证系统完整功能
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "======================================\n";
echo "MRS系统测试数据插入\n";
echo "======================================\n\n";

try {
    // 连接数据库
    $dsn = 'mysql:host=localhost;dbname=mhdlmskp2kpxguj;charset=utf8mb4';
    $pdo = new PDO($dsn, 'mrs_user', 'mrs_password_local_2024', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✓ 数据库连接成功\n\n";

    $pdo->beginTransaction();

    // 1. 插入测试用户
    echo "[步骤1] 插入测试用户\n";
    echo "----------------------------------------\n";

    $password_hash = password_hash('test123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO sys_users (user_login, user_secret_hash, user_email, user_display_name, user_status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', $password_hash, 'admin@test.com', '测试管理员', 'active']);
    $user_id = $pdo->lastInsertId();
    echo "✓ 创建用户: admin (ID: $user_id)\n";

    $stmt->execute(['testuser', $password_hash, 'test@test.com', '测试用户', 'active']);
    $user_id2 = $pdo->lastInsertId();
    echo "✓ 创建用户: testuser (ID: $user_id2)\n\n";

    // 2. 插入分类
    echo "[步骤2] 插入商品分类\n";
    echo "----------------------------------------\n";

    $categories = [
        ['食品', '食品类商品'],
        ['日用品', '日常生活用品'],
        ['电子产品', '电子类商品'],
    ];

    $category_ids = [];
    foreach ($categories as $cat) {
        $stmt = $pdo->prepare("INSERT INTO mrs_category (category_name, description) VALUES (?, ?)");
        $stmt->execute($cat);
        $cat_id = $pdo->lastInsertId();
        $category_ids[] = $cat_id;
        echo "✓ 创建分类: {$cat[0]} (ID: $cat_id)\n";
    }
    echo "\n";

    // 3. 插入SKU
    echo "[步骤3] 插入SKU商品\n";
    echo "----------------------------------------\n";

    $skus = [
        ['SKU001', '矿泉水', '农夫山泉', '550ml', $category_ids[0]],
        ['SKU002', '方便面', '康师傅', '桶装', $category_ids[0]],
        ['SKU003', '洗衣液', '蓝月亮', '2kg', $category_ids[1]],
        ['SKU004', '毛巾', '洁丽雅', '纯棉', $category_ids[1]],
        ['SKU005', '充电宝', '小米', '10000mAh', $category_ids[2]],
    ];

    $sku_ids = [];
    foreach ($skus as $sku) {
        $stmt = $pdo->prepare("INSERT INTO mrs_sku (sku_code, sku_name, brand_name, spec_info, category_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute($sku);
        $sku_id = $pdo->lastInsertId();
        $sku_ids[] = $sku_id;
        echo "✓ 创建SKU: {$sku[0]} - {$sku[1]} (ID: $sku_id)\n";
    }
    echo "\n";

    // 4. 插入MRS批次
    echo "[步骤4] 插入MRS收货批次\n";
    echo "----------------------------------------\n";

    $batch_code = 'MRS' . date('Ymd') . '001';
    $stmt = $pdo->prepare("INSERT INTO mrs_batch (batch_code, batch_name, batch_date, batch_status, location_name, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$batch_code, '测试收货批次001', date('Y-m-d'), 'receiving', '仓库A', 'admin']);
    $mrs_batch_id = $pdo->lastInsertId();
    echo "✓ 创建MRS批次: $batch_code (ID: $mrs_batch_id)\n\n";

    // 5. 插入MRS批次确认项
    echo "[步骤5] 插入MRS批次确认项\n";
    echo "----------------------------------------\n";

    foreach ([$sku_ids[0], $sku_ids[1], $sku_ids[2]] as $index => $sku_id) {
        $case_qty = rand(5, 20);
        $single_qty = rand(0, 10);
        $total_qty = $case_qty * 24 + $single_qty;

        $stmt = $pdo->prepare("INSERT INTO mrs_batch_confirmed_item (batch_id, sku_id, confirmed_case_qty, confirmed_single_qty, total_standard_qty) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$mrs_batch_id, $sku_id, $case_qty, $single_qty, $total_qty]);
        echo "✓ SKU ID $sku_id: 箱数 $case_qty, 散装 $single_qty, 总计 $total_qty\n";
    }
    echo "\n";

    // 6. 插入库存
    echo "[步骤6] 初始化库存数据\n";
    echo "----------------------------------------\n";

    foreach ($sku_ids as $index => $sku_id) {
        $qty = rand(100, 500);
        $stmt = $pdo->prepare("INSERT INTO mrs_inventory (sku_id, current_qty, unit) VALUES (?, ?, '个')");
        $stmt->execute([$sku_id, $qty]);
        echo "✓ SKU ID $sku_id: 库存 $qty 个\n";
    }
    echo "\n";

    // 7. 插入Express批次
    echo "[步骤7] 插入Express快递批次\n";
    echo "----------------------------------------\n";

    $stmt = $pdo->prepare("INSERT INTO express_batch (batch_name, created_by, status) VALUES (?, ?, ?)");
    $stmt->execute(['快递收货批次_' . date('Ymd'), 'admin', 'active']);
    $exp_batch_id = $pdo->lastInsertId();
    echo "✓ 创建Express批次 (ID: $exp_batch_id)\n\n";

    // 8. 插入Express包裹
    echo "[步骤8] 插入Express包裹\n";
    echo "----------------------------------------\n";

    $tracking_numbers = ['SF1234567890', 'YTO9876543210', 'ZTO5555555555'];
    $package_ids = [];

    foreach ($tracking_numbers as $tracking) {
        $stmt = $pdo->prepare("INSERT INTO express_package (batch_id, tracking_number) VALUES (?, ?)");
        $stmt->execute([$exp_batch_id, $tracking]);
        $package_id = $pdo->lastInsertId();
        $package_ids[] = $package_id;
        echo "✓ 包裹: $tracking (ID: $package_id)\n";
    }
    echo "\n";

    // 9. 插入Express包裹项目
    echo "[步骤9] 插入Express包裹项目\n";
    echo "----------------------------------------\n";

    $products = ['奶粉', '纸尿裤', '保健品', '零食', '化妆品'];

    foreach ($package_ids as $index => $package_id) {
        $item_count = rand(2, 4);
        for ($i = 0; $i < $item_count; $i++) {
            $product = $products[rand(0, count($products) - 1)];
            $qty = rand(1, 5);
            $expiry = date('Y-m-d', strtotime('+' . rand(180, 730) . ' days'));

            $stmt = $pdo->prepare("INSERT INTO express_package_items (package_id, product_name, quantity, expiry_date, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$package_id, $product, $qty, $expiry, $i + 1]);
            echo "✓ 包裹 #$package_id 项目: $product x $qty (效期: $expiry)\n";
        }
    }
    echo "\n";

    // 10. 提交事务
    $pdo->commit();

    echo "======================================\n";
    echo "测试数据插入完成！\n";
    echo "======================================\n";
    echo "数据统计:\n";
    echo "- 用户: 2 个\n";
    echo "- 分类: 3 个\n";
    echo "- SKU: 5 个\n";
    echo "- MRS批次: 1 个\n";
    echo "- MRS确认项: 3 个\n";
    echo "- 库存记录: 5 个\n";
    echo "- Express批次: 1 个\n";
    echo "- Express包裹: " . count($package_ids) . " 个\n";
    echo "- Express包裹项目: 多个\n";
    echo "\n所有测试数据插入成功！\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ 错误: " . $e->getMessage() . "\n";
    exit(1);
}
?>
