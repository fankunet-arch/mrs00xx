<?php
/**
 * SKU Save API
 * 文件路径: app/mrs/api/sku_save_api.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

try {
    $input = mrs_get_json_input();

    if (!$input) {
        mrs_json_response(false, null, '无效的输入数据');
    }

    // 验证必填字段
    if (empty($input['sku_name_cn'])) {
        mrs_json_response(false, null, '中文名称不能为空');
    }

    $sku_id = $input['sku_id'] ?? null;
    $is_edit = !empty($sku_id);

    // 准备数据
    $fields = [
        'sku_name_cn' => $input['sku_name_cn'],
        'sku_name_es' => $input['sku_name_es'] ?? null,
        'sku_code' => $input['sku_code'] ?? null,
        'barcode' => $input['barcode'] ?? null,
        'product_category' => !empty($input['product_category']) ? $input['product_category'] : null,
        'brand_name' => $input['brand_name'] ?? null,
        'spec_info' => $input['spec_info'] ?? null,
        'shelf_life_months' => !empty($input['shelf_life_months']) ? (int)$input['shelf_life_months'] : null,
        'production_time_days' => !empty($input['production_time_days']) ? (int)$input['production_time_days'] : null,
        'standard_unit' => $input['standard_unit'] ?? '件',
        'case_unit_name' => $input['case_unit_name'] ?? '箱',
        'case_to_standard_qty' => !empty($input['case_to_standard_qty']) ? (float)$input['case_to_standard_qty'] : null,
        'default_shelf_location' => $input['default_shelf_location'] ?? null,
        'supplier_country' => !empty($input['supplier_country']) ? $input['supplier_country'] : null,
        'status' => $input['status'] ?? 'active',
        'remark' => $input['remark'] ?? null
    ];

    if ($is_edit) {
        // 更新SKU
        $sql = "UPDATE mrs_sku SET ";
        $updates = [];
        $params = [];

        foreach ($fields as $field => $value) {
            // 尝试使用新字段名
            $column = $field;

            // 检查字段是否存在
            $check_sql = "SHOW COLUMNS FROM mrs_sku LIKE :column";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':column' => $column]);

            if ($check_stmt->rowCount() > 0) {
                $updates[] = "$column = :$field";
                $params[":$field"] = $value;
            }
        }

        $updates[] = "updated_at = NOW()";
        $sql .= implode(', ', $updates) . " WHERE sku_id = :sku_id";
        $params[':sku_id'] = $sku_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        mrs_json_response(true, ['sku_id' => $sku_id], 'SKU更新成功');
    } else {
        // 创建新SKU
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($fields as $field => $value) {
            // 检查字段是否存在
            $check_sql = "SHOW COLUMNS FROM mrs_sku LIKE :column";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([':column' => $field]);

            if ($check_stmt->rowCount() > 0) {
                $columns[] = $field;
                $placeholders[] = ":$field";
                $params[":$field"] = $value;
            }
        }

        $sql = "INSERT INTO mrs_sku (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $new_sku_id = $pdo->lastInsertId();

        mrs_json_response(true, ['sku_id' => $new_sku_id], 'SKU创建成功');
    }
} catch (Exception $e) {
    mrs_log('SKU保存失败: ' . $e->getMessage(), 'ERROR');
    mrs_json_response(false, null, '保存失败: ' . $e->getMessage());
}
