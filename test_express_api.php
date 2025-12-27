<?php
/**
 * Express API测试脚本
 * 用途：测试Express快递收货系统API是否能正常工作
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义入口点，防止 env_express.php 报错
define('EXPRESS_ENTRY', true);
// 定义 PROJECT_ROOT，防止 env_mrs.php 报错
define('PROJECT_ROOT', dirname(__FILE__));

// 加载 Express 库
require_once 'app/express/config_express/env_express.php';
require_once 'app/express/lib/express_lib.php';

echo "======================================\n";
echo "Express API功能测试\n";
echo "======================================\n\n";

// 设置本地数据库连接
function get_test_db_connection() {
    return get_express_db_connection();
}

// 测试1：数据库连接测试
echo "[测试1] 数据库连接测试\n";
echo "----------------------------------------\n";

try {
    $pdo = get_test_db_connection();
    echo "✓ 本地数据库连接成功\n";
} catch (Exception $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// 测试2：验证批次编号生成 (Test the newly upgraded feature)
echo "[测试2] 验证批次编号生成\n";
echo "----------------------------------------\n";

try {
    // 1. 获取下一个编号
    $next = express_generate_next_batch_number($pdo);
    echo "预期第一个编号: 000 (Cycle 1)\n";
    echo "实际生成编号: " . $next['batch_name'] . " (Cycle " . $next['batch_cycle'] . ")\n";

    if ($next['batch_name'] === '000' && $next['batch_cycle'] == 1) {
        echo "✓ 编号生成逻辑正确\n";
    } else {
        echo "✗ 编号生成逻辑错误\n";
    }

    // 2. 创建一个批次
    echo "\n正在创建批次 '000'...\n";
    $batch_id = express_create_batch($pdo, 'TestUser', 'Auto Test Batch');

    if ($batch_id) {
        echo "✓ 批次创建成功，ID: $batch_id\n";

        // 验证数据库中的值
        $stmt = $pdo->prepare("SELECT batch_name, batch_cycle FROM express_batch WHERE batch_id = ?");
        $stmt->execute([$batch_id]);
        $row = $stmt->fetch();
        echo "  DB验证: Name={$row['batch_name']}, Cycle={$row['batch_cycle']}\n";
    } else {
        echo "✗ 批次创建失败\n";
    }

    // 3. 获取下一个编号 (应该是 001)
    echo "\n获取下一个编号...\n";
    $next2 = express_generate_next_batch_number($pdo);
    echo "预期下一个编号: 001 (Cycle 1)\n";
    echo "实际生成编号: " . $next2['batch_name'] . " (Cycle " . $next2['batch_cycle'] . ")\n";

    if ($next2['batch_name'] === '001' && $next2['batch_cycle'] == 1) {
        echo "✓ 递增逻辑正确\n";
    } else {
        echo "✗ 递增逻辑错误\n";
    }

} catch (Exception $e) {
    echo "✗ 测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试3：批量导入
echo "[测试3] 批量导入测试\n";
echo "----------------------------------------\n";

if (isset($batch_id) && $batch_id) {
    $import_data = [
        "SF123456",
        "YT654321|2025-12-31|5",
        "JD999999"
    ];

    echo "正在导入 " . count($import_data) . " 条数据...\n";
    $result = express_bulk_import($pdo, $batch_id, $import_data);

    if ($result['success']) {
        echo "✓ 导入成功: " . $result['imported'] . " 条\n";

        // 验证数据
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM express_package WHERE batch_id = ?");
        $stmt->execute([$batch_id]);
        $count = $stmt->fetchColumn();
        echo "  DB验证: 包裹数=$count (预期3)\n";

        // 验证特殊字段
        $stmt = $pdo->prepare("SELECT quantity, expiry_date FROM express_package WHERE tracking_number = 'YT654321'");
        $stmt->execute();
        $pkg = $stmt->fetch();
        echo "  特殊字段验证 (YT654321): Qty={$pkg['quantity']}, Expiry={$pkg['expiry_date']}\n";

        if ($pkg['quantity'] == 5 && $pkg['expiry_date'] == '2025-12-31') {
            echo "✓ 字段解析正确\n";
        } else {
            echo "✗ 字段解析错误\n";
        }

    } else {
        echo "✗ 导入失败: " . $result['message'] . "\n";
    }
} else {
    echo "跳过导入测试（批次未创建）\n";
}

echo "\n";

// 总结
echo "======================================\n";
echo "Express 升级验证完成！\n";
echo "======================================\n";
?>
