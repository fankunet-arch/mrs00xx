<?php
/**
 * MRS 物料收发管理系统 - 后台API: 获取批次合并数据
 * 文件路径: app/mrs/api/backend_merge_data.php
 * 说明: 获取批次的原始记录并生成合并建议
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取批次ID
    $batchId = intval($_GET['batch_id'] ?? 0);

    if (!$batchId) {
        json_response(false, null, '缺少批次ID');
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    // 获取批次信息
    $batchSql = "SELECT * FROM mrs_batch WHERE batch_id = :batch_id";
    $batchStmt = $pdo->prepare($batchSql);
    $batchStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
    $batchStmt->execute();
    $batch = $batchStmt->fetch();

    if (!$batch) {
        json_response(false, null, '批次不存在');
    }

    // 获取预计清单
    $expectedSql = "SELECT
                        e.*,
                        s.sku_name,
                        s.brand_name,
                        s.standard_unit,
                        s.case_unit_name,
                        s.case_to_standard_qty,
                        s.is_precise_item,
                        c.category_name
                    FROM mrs_batch_expected_item e
                    LEFT JOIN mrs_sku s ON e.sku_id = s.sku_id
                    LEFT JOIN mrs_category c ON s.category_id = c.category_id
                    WHERE e.batch_id = :batch_id";
    $expectedStmt = $pdo->prepare($expectedSql);
    $expectedStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
    $expectedStmt->execute();
    $expectedItems = $expectedStmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取原始记录并按SKU分组汇总
    $rawSql = "SELECT
                    r.sku_id,
                    s.sku_name,
                    s.brand_name,
                    s.standard_unit,
                    s.case_unit_name,
                    s.case_to_standard_qty,
                    s.is_precise_item,
                    c.category_name,
                    r.unit_name,
                    SUM(r.qty) as total_qty,
                    COUNT(*) as record_count
                FROM mrs_batch_raw_record r
                LEFT JOIN mrs_sku s ON r.sku_id = s.sku_id
                LEFT JOIN mrs_category c ON s.category_id = c.category_id
                WHERE r.batch_id = :batch_id
                GROUP BY r.sku_id, r.unit_name";
    $rawStmt = $pdo->prepare($rawSql);
    $rawStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
    $rawStmt->execute();
    $rawRecords = $rawStmt->fetchAll(PDO::FETCH_ASSOC);

    // [FIX] 获取已确认的记录 (用于回显已保存的调整值)
    $confirmedSql = "SELECT * FROM mrs_batch_confirmed_item WHERE batch_id = :batch_id";
    $confirmedStmt = $pdo->prepare($confirmedSql);
    $confirmedStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
    $confirmedStmt->execute();
    $confirmedItems = $confirmedStmt->fetchAll(PDO::FETCH_ASSOC);

    // 转为 sku_id 索引的 map
    $confirmedMap = [];
    foreach ($confirmedItems as $c) {
        $confirmedMap[$c['sku_id']] = $c;
    }

    // 按SKU聚合数据
    $skuMap = [];

    // 处理预计清单
    foreach ($expectedItems as $item) {
        $skuId = $item['sku_id'];
        if (!isset($skuMap[$skuId])) {
            $skuMap[$skuId] = [
                'sku_id' => $skuId,
                'sku_name' => $item['sku_name'] ?? '未知物料',
                'brand_name' => $item['brand_name'] ?? '',
                'category_name' => $item['category_name'] ?? '',
                'is_precise_item' => $item['is_precise_item'] ?? 1,
                'standard_unit' => $item['standard_unit'] ?? '',
                'case_unit_name' => $item['case_unit_name'] ?? '',
                'case_to_standard_qty' => $item['case_to_standard_qty'] ?? 0,
                'expected_qty' => floatval($item['expected_qty'] ?? 0),
                'expected_unit' => $item['expected_unit'] ?? '',
                'raw_records' => [],
                'raw_total_standard' => 0
            ];
        }
    }

    // 处理原始记录
    foreach ($rawRecords as $record) {
        $skuId = $record['sku_id'];

        if (!isset($skuMap[$skuId])) {
            $skuMap[$skuId] = [
                'sku_id' => $skuId,
                'sku_name' => $record['sku_name'] ?? '未知物料',
                'brand_name' => $record['brand_name'] ?? '',
                'category_name' => $record['category_name'] ?? '',
                'is_precise_item' => $record['is_precise_item'] ?? 1,
                'standard_unit' => $record['standard_unit'] ?? '',
                'case_unit_name' => $record['case_unit_name'] ?? '',
                'case_to_standard_qty' => $record['case_to_standard_qty'] ?? 0,
                'expected_qty' => 0,
                'expected_unit' => '',
                'raw_records' => [],
                'raw_total_standard' => 0
            ];
        }

        $qty = floatval($record['total_qty']);
        $unit = $record['unit_name'];

        // 转换为标准单位
        $standardQty = $qty;
        if ($unit === $skuMap[$skuId]['case_unit_name'] && $skuMap[$skuId]['case_to_standard_qty'] > 0) {
            $standardQty = $qty * floatval($skuMap[$skuId]['case_to_standard_qty']);
        }

        $skuMap[$skuId]['raw_records'][] = [
            'qty' => $qty,
            'unit' => $unit,
            'count' => $record['record_count']
        ];
        $skuMap[$skuId]['raw_total_standard'] += $standardQty;
    }

    // 生成合并建议
    $items = [];
    foreach ($skuMap as $skuId => $data) {
        // [FIX] 始终使用当前原始记录计算的总数
        $currentRawTotal = $data['raw_total_standard'];
        $expectedQty = $data['expected_qty'];

        // 根据当前原始记录计算箱数和散件数
        $caseQty = 0;
        $singleQty = 0;

        if ($data['case_to_standard_qty'] > 0) {
            $caseQty = floor($currentRawTotal / $data['case_to_standard_qty']);
            $singleQty = $currentRawTotal % $data['case_to_standard_qty'];
        } else {
            $singleQty = $currentRawTotal;
        }

        // [FIX] 检查是否存在已确认记录，并对比数据是否变更
        $isConfirmed = false;
        $confirmedTotal = 0;
        $confirmedCase = 0;
        $confirmedSingle = 0;
        $hasDataChanged = false; // 新增字段：标记数据是否已变更

        if (isset($confirmedMap[$skuId])) {
            $c = $confirmedMap[$skuId];
            $confirmedCase = floatval($c['confirmed_case_qty']);
            $confirmedSingle = floatval($c['confirmed_single_qty']);
            $confirmedTotal = floatval($c['total_standard_qty']);
            $isConfirmed = true;

            // [FIX CRITICAL] 对比已确认数量和当前原始记录总数
            // 如果不一致，说明有新的原始记录被添加，需要重新确认
            if (abs($confirmedTotal - $currentRawTotal) > 0.001) { // 使用浮点数比较容差
                $hasDataChanged = true;
                // 当数据变更时，使用当前原始记录的数量作为建议值
                // 保留已确认的值用于前端对比显示
            } else {
                // 数据未变更，使用已确认的值
                $caseQty = $confirmedCase;
                $singleQty = $confirmedSingle;
            }
        }

        // 判断状态（始终基于当前原始记录总数）
        $status = 'normal';
        $statusText = '正常';
        if ($expectedQty > 0) {
            if ($currentRawTotal > $expectedQty) {
                $status = 'over';
                $statusText = '超收';
            } elseif ($currentRawTotal < $expectedQty) {
                $status = 'under';
                $statusText = '少收';
            }
        }

        // [FIX] 如果数据已变更，标记为需要重新确认
        if ($hasDataChanged) {
            $status = 'changed';
            $statusText = '数据已变更，需重新确认';
        }

        // 生成原始记录摘要
        $rawSummary = [];
        foreach ($data['raw_records'] as $r) {
            $rawSummary[] = $r['qty'] . ' ' . $r['unit'];
        }

        $items[] = [
            'sku_id' => $skuId,
            'sku_name' => $data['sku_name'],
            'brand_name' => $data['brand_name'],
            'category_name' => $data['category_name'],
            'is_precise_item' => $data['is_precise_item'],
            'standard_unit' => $data['standard_unit'],
            'case_unit_name' => $data['case_unit_name'],
            'case_to_standard_qty' => $data['case_to_standard_qty'],
            'expected_qty' => $expectedQty,
            'expected_unit' => $data['expected_unit'],
            'raw_summary' => implode(' + ', $rawSummary),
            'raw_total_standard' => $currentRawTotal, // [FIX] 始终返回当前原始记录总数
            'suggested_qty' => $caseQty . ' ' . ($data['case_unit_name'] ?: '箱') . ' + ' . $singleQty . ' ' . $data['standard_unit'],
            'confirmed_case' => $caseQty,
            'confirmed_single' => $singleQty,
            'is_confirmed' => $isConfirmed,
            'has_data_changed' => $hasDataChanged, // [FIX] 新增字段
            'confirmed_total' => $confirmedTotal, // [FIX] 返回已确认的总数用于对比
            'status' => $status,
            'status_text' => $statusText
        ];
    }

    // 返回数据
    json_response(true, [
        'batch' => $batch,
        'items' => $items
    ]);

} catch (PDOException $e) {
    mrs_log('获取合并数据失败: ' . $e->getMessage(), 'ERROR', ['batch_id' => $batchId ?? null]);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('获取合并数据异常: ' . $e->getMessage(), 'ERROR', ['batch_id' => $batchId ?? null]);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}
