<?php
/**
 * Action: product_name_autocomplete
 * 文件路径: app/mrs/actions/product_name_autocomplete.php
 * 功能: 为前端“修改包裹信息”模态框提供产品名称自动完成数据
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

// 获取并处理关键词
$keyword = trim($_GET['keyword'] ?? '');

// 关键词为空时直接返回空结果
if ($keyword === '') {
    json_response(true, [], 'Empty keyword');
    exit;
}

try {
    // 查询逻辑：
    // 1. 从 mrs_package_ledger (包裹台账表) 中查找 content_note (内容描述/产品名)
    // 2. 使用 DISTINCT 去重
    // 3. 排除空值
    // 4. 按名称排序并限制返回 10 条
    
    $sql = "SELECT DISTINCT content_note 
            FROM mrs_package_ledger 
            WHERE content_note LIKE :keyword 
            AND content_note IS NOT NULL 
            AND content_note != ''
            ORDER BY content_note ASC 
            LIMIT 10";

    // 假设 $pdo 在 index.php/bootstrap.php 中已初始化
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['keyword' => '%' . $keyword . '%']);
    
    // FETCH_COLUMN 模式只返回一维数组，例如: ['产品A', '产品B']
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 返回标准 JSON 响应
    json_response(true, $results, 'Success');

} catch (PDOException $e) {
    // 记录错误日志
    if (function_exists('mrs_log')) {
        mrs_log('Autocomplete API Error: ' . $e->getMessage(), 'ERROR');
    }
    //即使出错也返回合法的JSON，避免前端报错
    json_response(false, [], 'Database error');
}