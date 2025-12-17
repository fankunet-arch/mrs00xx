<?php
/**
 * API: Search Product Names (Global)
 * 文件路径: app/express/actions/search_product_name_api.php
 * 说明: 搜索所有批次中的产品名称，用于自动完成和保证名称一致性
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

// 捕获所有警告/通知，避免破坏JSON输出
ob_start();
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$success = false;
$payload = null;
$msg = '';

try {
    $keyword = $_GET['keyword'] ?? '';
    $keyword = trim($keyword);

    if (empty($keyword)) {
        throw new InvalidArgumentException('搜索关键词不能为空');
    }

    // 搜索产品名称（全局，不限批次）
    // 使用DISTINCT去重，按使用次数排序
    $sql = "
        SELECT
            product_name,
            COUNT(*) as usage_count,
            MAX(created_at) as last_used
        FROM express_package_items
        WHERE product_name LIKE :keyword
        GROUP BY product_name
        ORDER BY usage_count DESC, last_used DESC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':keyword', '%' . $keyword . '%', PDO::PARAM_STR);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $payload = array_map(function($row) {
        return [
            'product_name' => $row['product_name'],
            'usage_count' => (int)$row['usage_count'],
            'last_used' => $row['last_used']
        ];
    }, $results);

    $success = true;
} catch (Throwable $e) {
    $msg = $e instanceof InvalidArgumentException ? $e->getMessage() : '搜索产品名称失败';
    express_log('Search product name API failed: ' . $e->getMessage(), 'ERROR');
}

// 清理缓冲并记录意外输出
$buffer = ob_get_clean();
if (!empty($buffer)) {
    express_log('Search product name API extra output: ' . trim($buffer), $success ? 'WARNING' : 'ERROR');
}
restore_error_handler();

express_json_response($success, $payload, $msg);
