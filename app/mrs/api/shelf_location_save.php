<?php
/**
 * MRS API - 保存/更新货架位置
 * 文件路径: app/mrs/api/shelf_location_save.php
 * 功能: 新增或编辑货架位置配置
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, '非法请求方式');
    exit;
}

// 获取POST数据
$input = mrs_get_json_input();
if (!$input) {
    $input = $_POST;
}

$location_id = intval($input['location_id'] ?? 0);
$shelf_code = trim($input['shelf_code'] ?? '');
$shelf_name = trim($input['shelf_name'] ?? '');
$level_number = isset($input['level_number']) && $input['level_number'] !== '' ? intval($input['level_number']) : null;
$capacity = isset($input['capacity']) && $input['capacity'] !== '' ? intval($input['capacity']) : null;
$zone = trim($input['zone'] ?? '');
$is_active = isset($input['is_active']) ? intval($input['is_active']) : 1;
$sort_order = isset($input['sort_order']) ? intval($input['sort_order']) : 0;
$remark = trim($input['remark'] ?? '');

// 验证必填字段
if (empty($shelf_code)) {
    json_response(false, null, '货架编号不能为空');
    exit;
}

if (empty($shelf_name)) {
    json_response(false, null, '货架名称不能为空');
    exit;
}

// 构建完整位置名称
if ($level_number !== null && $level_number > 0) {
    $location_full_name = $shelf_name . $level_number . '层';
} else {
    $location_full_name = $shelf_name;
}

try {
    $pdo->beginTransaction();

    if ($location_id > 0) {
        // 更新现有位置

        // 检查是否存在
        $check_sql = "SELECT location_id FROM mrs_shelf_locations WHERE location_id = :location_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute(['location_id' => $location_id]);

        if (!$check_stmt->fetch()) {
            $pdo->rollBack();
            json_response(false, null, '货架位置不存在');
            exit;
        }

        // 检查完整名称是否与其他记录重复
        $dup_sql = "SELECT location_id FROM mrs_shelf_locations
                    WHERE location_full_name = :location_full_name
                    AND location_id != :location_id";
        $dup_stmt = $pdo->prepare($dup_sql);
        $dup_stmt->execute([
            'location_full_name' => $location_full_name,
            'location_id' => $location_id
        ]);

        if ($dup_stmt->fetch()) {
            $pdo->rollBack();
            json_response(false, null, '位置名称已存在: ' . $location_full_name);
            exit;
        }

        // 执行更新
        $update_sql = "UPDATE mrs_shelf_locations SET
                        shelf_code = :shelf_code,
                        shelf_name = :shelf_name,
                        level_number = :level_number,
                        location_full_name = :location_full_name,
                        capacity = :capacity,
                        zone = :zone,
                        is_active = :is_active,
                        sort_order = :sort_order,
                        remark = :remark
                      WHERE location_id = :location_id";

        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            'shelf_code' => $shelf_code,
            'shelf_name' => $shelf_name,
            'level_number' => $level_number,
            'location_full_name' => $location_full_name,
            'capacity' => $capacity,
            'zone' => $zone,
            'is_active' => $is_active,
            'sort_order' => $sort_order,
            'remark' => $remark,
            'location_id' => $location_id
        ]);

        $pdo->commit();
        json_response(true, ['location_id' => $location_id], '货架位置更新成功');

    } else {
        // 新增位置

        // 检查完整名称是否已存在
        $dup_sql = "SELECT location_id FROM mrs_shelf_locations
                    WHERE location_full_name = :location_full_name";
        $dup_stmt = $pdo->prepare($dup_sql);
        $dup_stmt->execute(['location_full_name' => $location_full_name]);

        if ($dup_stmt->fetch()) {
            $pdo->rollBack();
            json_response(false, null, '位置名称已存在: ' . $location_full_name);
            exit;
        }

        // 执行插入
        $insert_sql = "INSERT INTO mrs_shelf_locations
                      (shelf_code, shelf_name, level_number, location_full_name,
                       capacity, zone, is_active, sort_order, remark)
                      VALUES
                      (:shelf_code, :shelf_name, :level_number, :location_full_name,
                       :capacity, :zone, :is_active, :sort_order, :remark)";

        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([
            'shelf_code' => $shelf_code,
            'shelf_name' => $shelf_name,
            'level_number' => $level_number,
            'location_full_name' => $location_full_name,
            'capacity' => $capacity,
            'zone' => $zone,
            'is_active' => $is_active,
            'sort_order' => $sort_order,
            'remark' => $remark
        ]);

        $new_location_id = $pdo->lastInsertId();

        $pdo->commit();
        json_response(true, ['location_id' => $new_location_id], '货架位置创建成功');
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    if (function_exists('mrs_log')) {
        mrs_log('Shelf location save error: ' . $e->getMessage(), 'ERROR');
    }
    json_response(false, null, '保存失败: ' . $e->getMessage());
}
