<?php
// Action: batch_detail.php - 批次详情/确认入库页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$batch_id = $_GET['id'] ?? null;

if (!$batch_id) {
    $_SESSION['error_message'] = '批次ID缺失';
    header('Location: /mrs/be/index.php?action=batch_list');
    exit;
}

try {
    $pdo = get_db_connection();

    // 获取批次信息
    $stmt = $pdo->prepare("SELECT * FROM mrs_batch WHERE batch_id = ?");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        $_SESSION['error_message'] = '批次不存在';
        header('Location: /mrs/be/index.php?action=batch_list');
        exit;
    }

    // 获取批次的原始记录
    $stmt = $pdo->prepare("
        SELECT r.*, s.sku_name, s.brand_name, s.standard_unit, c.category_name
        FROM mrs_batch_raw_record r
        LEFT JOIN mrs_sku s ON r.sku_id = s.sku_id
        LEFT JOIN mrs_category c ON s.category_id = c.category_id
        WHERE r.batch_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$batch_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取合并数据（按SKU汇总）- 包括所有状态和未知物料
    $merge_data = [];
    $aggregated_data = [];
    if (in_array($batch['batch_status'], ['receiving', 'pending_merge', 'draft', 'confirmed'])) {
        // Query ALL raw records grouped by SKU and processing_status
        // IMPORTANT: Use LEFT JOIN to include records with NULL sku_id (unknown items)
        $stmt = $pdo->prepare("
            SELECT
                r.sku_id,
                r.input_sku_name,
                r.processing_status,
                COALESCE(s.sku_name, r.input_sku_name, '未知物料') as sku_name,
                COALESCE(s.brand_name, '未知品牌') as brand_name,
                COALESCE(c.category_name, '未分类') as category_name,
                COALESCE(s.is_precise_item, 1) as is_precise_item,
                COALESCE(s.standard_unit, r.unit_name) as standard_unit,
                s.case_unit_name,
                s.case_to_standard_qty,
                SUM(
                    CASE
                        WHEN r.unit_name = s.case_unit_name AND s.case_to_standard_qty > 0
                        THEN r.qty * s.case_to_standard_qty
                        ELSE r.qty
                    END
                ) as total_quantity,
                COUNT(*) as record_count
            FROM mrs_batch_raw_record r
            LEFT JOIN mrs_sku s ON r.sku_id = s.sku_id
            LEFT JOIN mrs_category c ON s.category_id = c.category_id
            WHERE r.batch_id = ?
            GROUP BY r.sku_id, r.input_sku_name, r.processing_status, s.sku_name, s.brand_name, c.category_name, s.is_precise_item, s.standard_unit, s.case_unit_name, s.case_to_standard_qty, r.unit_name
        ");
        $stmt->execute([$batch_id]);
        $merge_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Transform merge_data to aggregated_data format expected by view
        // Group by SKU and show all records with their processing status
        foreach ($merge_data as $item) {
            $sku_id = $item['sku_id'];
            $processing_status = $item['processing_status'];
            $case_to_standard = floatval($item['case_to_standard_qty'] ?? 0);
            $total_qty = floatval($item['total_quantity']);

            // Create unique key for SKU + status combination
            // For unknown items (sku_id IS NULL), use input_sku_name
            if ($sku_id) {
                $unique_key = $sku_id . '_' . $processing_status;
            } else {
                $unique_key = 'unknown_' . md5($item['input_sku_name'] ?? '') . '_' . $processing_status;
            }

            // Calculate case and single quantities
            $calculated_case_qty = 0;
            $calculated_single_qty = 0;

            if ($case_to_standard > 0 && fmod($case_to_standard, 1.0) == 0.0) {
                // If case conversion is a whole number, calculate breakdown
                $case_size = (int)$case_to_standard;
                $calculated_case_qty = intdiv((int)$total_qty, $case_size);
                $calculated_single_qty = (int)$total_qty % $case_size;
            } else {
                // Otherwise, all goes to single quantity
                $calculated_single_qty = (int)$total_qty;
            }

            // Build sku_spec string
            $sku_spec = '';
            if ($case_to_standard > 0 && !empty($item['case_unit_name'])) {
                $sku_spec = format_number($case_to_standard, 4) . ' ' . $item['standard_unit'] . '/' . $item['case_unit_name'];
            } else {
                $sku_spec = $item['standard_unit'];
            }

            $aggregated_data[$unique_key] = [
                'sku_id' => $sku_id,
                'processing_status' => $processing_status,
                'sku_name' => $item['sku_name'],
                'brand_name' => $item['brand_name'],
                'category_name' => $item['category_name'],
                'sku_spec' => $sku_spec,
                'case_to_standard_qty' => $case_to_standard,
                'standard_unit' => $item['standard_unit'],
                'calculated_case_qty' => $calculated_case_qty,
                'calculated_single_qty' => $calculated_single_qty,
                'calculated_total' => (int)$total_qty,
                'raw_total' => (int)$total_qty,
                'record_count' => $item['record_count']
            ];
        }
    }

} catch (PDOException $e) {
    mrs_log("Failed to load batch detail: " . $e->getMessage(), 'ERROR');
    $_SESSION['error_message'] = '加载批次详情失败';
    header('Location: /mrs/be/index.php?action=batch_list');
    exit;
}

$is_spa = false;
$page_title = "批次详情 - " . $batch['batch_code'];
$action = 'batch_detail';

require_once MRS_VIEW_PATH . '/batch_detail.php';
