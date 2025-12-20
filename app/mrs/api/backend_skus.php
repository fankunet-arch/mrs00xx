<?php
/**
 * MRS 物料收发管理系统 - 后台API: 获取SKU列表
 * 文件路径: app/mrs/api/backend_skus.php
 * 说明: 获取品牌SKU列表,支持筛选和分页
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取数据库连接
    $pdo = get_db_connection();

    // 获取并验证筛选参数
    $search = mrs_sanitize_input($_GET['search'] ?? '', MRS_MAX_SEARCH_LENGTH);
    $categoryId = mrs_sanitize_int($_GET['category_id'] ?? '', 0, PHP_INT_MAX, 0);
    $isPrecise = mrs_sanitize_input($_GET['is_precise_item'] ?? '', 10);

    // 使用通用分页函数（SKU列表使用50条/页）
    $pagination = mrs_get_pagination_params(50, MRS_MAX_PAGE_SIZE, 'page_size');
    $page = $pagination['page'];
    $pageSize = $pagination['limit'];
    $offset = $pagination['offset'];

    // 构建SQL查询
    $sql = "SELECT
                s.*,
                c.category_name
            FROM mrs_sku s
            LEFT JOIN mrs_category c ON s.category_id = c.category_id
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        // [FIX] 使用单个参数，MySQL支持在多个地方使用同一个命名参数
        $sql .= " AND (s.sku_name LIKE :search OR s.brand_name LIKE :search OR s.sku_code LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }

    if ($categoryId > 0) {
        $sql .= " AND s.category_id = :category_id";
        $params['category_id'] = $categoryId;
    }

    if (!empty($isPrecise)) {
        $sql .= " AND s.is_precise_item = :is_precise";
        $params['is_precise'] = $isPrecise;
    }

    // 排序
    $sql .= " ORDER BY s.created_at DESC";

    // 分页
    $sql .= " LIMIT :limit OFFSET :offset";

    // 准备和执行查询
    $stmt = $pdo->prepare($sql);

    // 绑定所有参数
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $skus = $stmt->fetchAll();

    json_response(true, ['skus' => $skus]);

} catch (PDOException $e) {
    mrs_log('获取SKU列表失败: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('获取SKU列表异常: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误: ' . $e->getMessage());
}
