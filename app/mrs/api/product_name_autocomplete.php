<?php
/**
 * 产品名称自动完成API
 * 文件路径: app/mrs/api/product_name_autocomplete.php
 * 说明: 从库存中搜索产品名称,支持模糊搜索,自动去重
 */

if (!defined('MRS_ENTRY')) {
    define('MRS_ENTRY', true);
    require_once dirname(__DIR__) . '/bootstrap.php';
}

header('Content-Type: application/json; charset=utf-8');

try {
    $keyword = trim($_GET['keyword'] ?? '');

    if (empty($keyword)) {
        echo json_encode([
            'success' => true,
            'data' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = get_db_connection();

    // 从mrs_package_items表中搜索产品名称,去重
    $sql = "
        SELECT DISTINCT product_name
        FROM mrs_package_items
        WHERE product_name IS NOT NULL
          AND product_name != ''
          AND product_name LIKE :keyword
        ORDER BY product_name
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['keyword' => '%' . $keyword . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'data' => $results
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Product name autocomplete error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '搜索失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
