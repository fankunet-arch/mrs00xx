<?php
/**
 * API: Get Package Items (Product Details)
 * 文件路径: app/express/api/get_package_items.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$package_id = (int)($_GET['package_id'] ?? 0);

if ($package_id <= 0) {
    express_json_response(false, null, '包裹ID无效');
}

try {
    // 获取包裹信息（包括 skip_inbound 字段）
    $stmt = $pdo->prepare("
        SELECT skip_inbound FROM express_package
        WHERE package_id = :package_id
    ");
    $stmt->execute(['package_id' => $package_id]);
    $package = $stmt->fetch();

    if (!$package) {
        express_json_response(false, null, '包裹不存在');
    }

    // 获取产品明细
    $stmt = $pdo->prepare("
        SELECT * FROM express_package_items
        WHERE package_id = :package_id
        ORDER BY sort_order ASC
    ");
    $stmt->execute(['package_id' => $package_id]);
    $items = $stmt->fetchAll();

    // 返回产品明细和 skip_inbound 状态
    express_json_response(true, [
        'items' => $items,
        'skip_inbound' => (int)$package['skip_inbound']
    ]);
} catch (PDOException $e) {
    express_log('Failed to get package items: ' . $e->getMessage(), 'ERROR');
    express_json_response(false, null, '获取产品明细失败');
}
