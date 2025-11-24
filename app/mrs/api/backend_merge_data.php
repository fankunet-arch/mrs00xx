<?php
/**
 * MRS 物料收发管理系统 - 后台API: 获取批次合并数据
 * 文件路径: app/mrs/api/backend_merge_data.php
 * 说明: 获取批次的原始记录并生成合并建议
 */

// 定义API入口
define('MRS_ENTRY', true);

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
        $totalStandard = $data['raw_total_standard'];
        $expectedQty = $data['expected_qty'];

        // 计算箱数和散件数
        $caseQty = 0;
        $singleQty = 0;

        if ($data['case_to_standard_qty'] > 0) {
            $caseQty = floor($totalStandard / $data['case_to_standard_qty']);
            $singleQty = $totalStandard % $data['case_to_standard_qty'];
        } else {
            $singleQty = $totalStandard;
        }

        // 判断状态
        $status = 'normal';
        $statusText = '正常';
        if ($expectedQty > 0) {
            if ($totalStandard > $expectedQty) {
                $status = 'over';
                $statusText = '超收';
            } elseif ($totalStandard < $expectedQty) {
                $status = 'under';
                $statusText = '少收';
            }
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
            'raw_total_standard' => $totalStandard,
            'suggested_qty' => $caseQty . ' ' . ($data['case_unit_name'] ?: '箱') . ' + ' . $singleQty . ' ' . $data['standard_unit'],
            'confirmed_case' => $caseQty,
            'confirmed_single' => $singleQty,
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
