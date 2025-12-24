<?php
/**
 * MRS Count Library
 * 文件路径: app/mrs/lib/count_lib.php
 * 说明: 清点功能业务逻辑库
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

/**
 * 创建清点任务
 * @param PDO $pdo
 * @param string $session_name 任务名称
 * @param string $created_by 创建人
 * @param string|null $remark 备注
 * @return array 成功返回['success'=>true, 'session_id'=>int], 失败返回['success'=>false, 'error'=>string]
 */
function mrs_count_create_session($pdo, $session_name, $created_by = null, $remark = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mrs_count_session (session_name, status, created_by, remark, start_time)
            VALUES (:session_name, 'counting', :created_by, :remark, NOW(6))
        ");

        $stmt->execute([
            ':session_name' => $session_name,
            ':created_by' => $created_by,
            ':remark' => $remark
        ]);

        return ['success' => true, 'session_id' => (int)$pdo->lastInsertId()];
    } catch (PDOException $e) {
        error_log("MRS Count: Create session failed - " . $e->getMessage());
        return ['success' => false, 'error' => '创建清点任务失败'];
    }
}

/**
 * 获取清点任务列表
 * @param PDO $pdo
 * @param string|null $status 状态筛选 (counting, completed, cancelled)
 * @param int $limit 限制数量
 * @return array
 */
function mrs_count_get_sessions($pdo, $status = null, $limit = 20) {
    try {
        $sql = "SELECT * FROM mrs_count_session";
        $params = [];

        if ($status) {
            $sql .= " WHERE status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("MRS Count: Get sessions failed - " . $e->getMessage());
        return [];
    }
}

/**
 * 搜索箱号（精确查找，用于点击下拉建议后）
 * @param PDO $pdo
 * @param string $box_number 箱号（精确匹配）或快递单号
 * @return array|null 找到返回台账信息，否则返回null
 */
function mrs_count_search_box($pdo, $box_number) {
    try {
        // [FIX] Added tracking_number support.
        // [FIX] Added l.tracking_number to SELECT.
        // [FIX] Used unique named parameters to avoid HY093.
        $stmt = $pdo->prepare("
            SELECT
                l.ledger_id,
                l.box_number,
                l.tracking_number,
                l.content_note,
                l.quantity,
                l.warehouse_location,
                l.status,
                l.inbound_time,
                l.batch_name,
                NULL as sku_id,
                l.content_note as sku_name,
                '件' as standard_unit
            FROM mrs_package_ledger l
            WHERE (l.box_number = :bn1 OR l.tracking_number = :bn2)
            AND l.status = 'in_stock'
            ORDER BY l.inbound_time DESC
            LIMIT 1
        ");

        $stmt->execute([':bn1' => $box_number, ':bn2' => $box_number]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    } catch (PDOException $e) {
        error_log("MRS Count: Search box failed - " . $e->getMessage());
        return [];
    }
}

/**
 * 自动完成搜索箱号（支持模糊搜索）
 * @param PDO $pdo
 * @param string $keyword 关键词（搜索箱号、快递单号、内容备注、SKU名称）
 * @return array 返回建议列表
 */
function mrs_count_autocomplete_box($pdo, $keyword) {
    try {
        // [FIX] Added tracking_number support.
        // [FIX] Added l.tracking_number to SELECT.
        $stmt = $pdo->prepare("
            SELECT
                l.ledger_id,
                l.box_number,
                l.tracking_number,
                l.content_note,
                l.quantity,
                l.batch_name,
                l.inbound_time,
                l.content_note AS sku_name,
                '件' AS standard_unit
            FROM mrs_package_ledger l
            WHERE l.status = 'in_stock'
            AND (
                l.box_number LIKE :kw1
                OR l.content_note LIKE :kw2
                OR l.tracking_number LIKE :kw3
            )
            ORDER BY
                CASE
                    WHEN l.box_number LIKE :kw_start THEN 1
                    WHEN l.tracking_number LIKE :kw_start_track THEN 1
                    WHEN l.box_number LIKE :kw4 THEN 2
                    ELSE 3
                END,
                l.inbound_time DESC
            LIMIT 10
        ");

        $keyword_param = '%' . $keyword . '%';
        $keyword_start = $keyword . '%';

        $stmt->execute([
            ':kw1' => $keyword_param,
            ':kw2' => $keyword_param,
            ':kw3' => $keyword_param,
            ':kw_start' => $keyword_start,
            ':kw_start_track' => $keyword_start,
            ':kw4' => $keyword_param
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("MRS Count: Autocomplete box failed - " . $e->getMessage());
        return [];
    }
}

/**
 * 保存清点记录（仅箱级，不含明细）
 * @param PDO $pdo
 * @param array $data 清点数据
 * @return array
 */
function mrs_count_save_record($pdo, $data) {
    try {
        // [FIX] Removed transaction control to avoid nesting errors (caller handles transaction)

        // 插入清点记录
        $stmt = $pdo->prepare("
            INSERT INTO mrs_count_record (
                session_id, box_number, ledger_id, system_content, system_total_qty,
                check_mode, has_multiple_items, match_status, is_new_box, remark, counted_by
            ) VALUES (
                :session_id, :box_number, :ledger_id, :system_content, :system_total_qty,
                :check_mode, :has_multiple_items, :match_status, :is_new_box, :remark, :counted_by
            )
        ");

        $stmt->execute([
            ':session_id' => $data['session_id'],
            ':box_number' => $data['box_number'],
            ':ledger_id' => $data['ledger_id'] ?? null,
            ':system_content' => $data['system_content'] ?? null,
            ':system_total_qty' => $data['system_total_qty'] ?? null,
            ':check_mode' => $data['check_mode'],
            ':has_multiple_items' => $data['has_multiple_items'] ?? 0,
            ':match_status' => $data['match_status'],
            ':is_new_box' => $data['is_new_box'] ?? 0,
            ':remark' => $data['remark'] ?? null,
            ':counted_by' => $data['counted_by'] ?? null
        ]);

        $record_id = (int)$pdo->lastInsertId();

        // 更新任务的已清点数
        $stmt = $pdo->prepare("
            UPDATE mrs_count_session
            SET total_counted = total_counted + 1
            WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $data['session_id']]);

        return ['success' => true, 'record_id' => $record_id];
    } catch (PDOException $e) {
        error_log("MRS Count: Save record failed - " . $e->getMessage());
        return ['success' => false, 'error' => '保存清点记录失败'];
    }
}

/**
 * 保存清点记录明细（箱内多件物品）
 * @param PDO $pdo
 * @param int $record_id 清点记录ID
 * @param array $items 物品列表 [['sku_id'=>, 'sku_name'=>, 'system_qty'=>, 'actual_qty'=>, ...], ...]
 * @return array
 */
function mrs_count_save_record_items($pdo, $record_id, $items) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mrs_count_record_item (
                record_id, sku_id, sku_name, system_qty, actual_qty, diff_qty, unit, remark
            ) VALUES (
                :record_id, :sku_id, :sku_name, :system_qty, :actual_qty, :diff_qty, :unit, :remark
            )
        ");

        foreach ($items as $item) {
            $diff_qty = ($item['actual_qty'] ?? 0) - ($item['system_qty'] ?? 0);

            $stmt->execute([
                ':record_id' => $record_id,
                ':sku_id' => $item['sku_id'] ?? null,
                ':sku_name' => $item['sku_name'],
                ':system_qty' => $item['system_qty'] ?? null,
                ':actual_qty' => $item['actual_qty'],
                ':diff_qty' => $diff_qty,
                ':unit' => $item['unit'] ?? '件',
                ':remark' => $item['remark'] ?? null
            ]);
        }

        return ['success' => true];
    } catch (PDOException $e) {
        error_log("MRS Count: Save record items failed - " . $e->getMessage());
        return ['success' => false, 'error' => '保存物品明细失败'];
    }
}

/**
 * 快速录入新箱到台账
 * @param PDO $pdo
 * @param array $data 箱子数据
 * @return array
 */
function mrs_count_quick_add_box($pdo, $data) {
    try {
        // [FIX] Removed sku_id from insert as column does not exist.
        $stmt = $pdo->prepare("
            INSERT INTO mrs_package_ledger (
                content_note, box_number, quantity, status,
                inbound_time, created_by, created_at
            ) VALUES (
                :content_note, :box_number, :quantity, 'in_stock',
                NOW(6), :created_by, NOW(6)
            )
        ");

        $stmt->execute([
            ':content_note' => $data['content_note'] ?? null,
            ':box_number' => $data['box_number'],
            ':quantity' => $data['quantity'] ?? null,
            ':created_by' => $data['created_by'] ?? null
        ]);

        return ['success' => true, 'ledger_id' => (int)$pdo->lastInsertId()];
    } catch (PDOException $e) {
        error_log("MRS Count: Quick add box failed - " . $e->getMessage());
        return ['success' => false, 'error' => '录入新箱失败'];
    }
}

/**
 * 获取最近清点记录
 * @param PDO $pdo
 * @param int $session_id 清点任务ID
 * @param int $limit 限制数量
 * @return array
 */
function mrs_count_get_recent_records($pdo, $session_id, $limit = 20) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                r.*,
                (SELECT COUNT(*) FROM mrs_count_record_item WHERE record_id = r.record_id) as item_count
            FROM mrs_count_record r
            WHERE r.session_id = :session_id
            ORDER BY r.counted_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':session_id', $session_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("MRS Count: Get recent records failed - " . $e->getMessage());
        return [];
    }
}

/**
 * 完成清点任务并生成报告
 * @param PDO $pdo
 * @param int $session_id 清点任务ID
 * @return array
 */
function mrs_count_finish_session($pdo, $session_id) {
    try {
        $pdo->beginTransaction();

        // 1. 统计已清点的箱号
        $stmt = $pdo->prepare("
            SELECT DISTINCT box_number FROM mrs_count_record WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $session_id]);
        $counted_boxes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. 获取系统中所有在库箱号
        $stmt = $pdo->query("
            SELECT box_number FROM mrs_package_ledger WHERE status = 'in_stock'
        ");
        $all_boxes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 3. 计算未清点的箱号（差集）
        $missing_boxes = array_diff($all_boxes, $counted_boxes);

        // 4. 统计清点数据
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_count,
                SUM(CASE WHEN check_mode = 'box_only' THEN 1 ELSE 0 END) as box_only_count,
                SUM(CASE WHEN check_mode = 'with_qty' THEN 1 ELSE 0 END) as with_qty_count,
                SUM(CASE WHEN match_status = 'matched' THEN 1 ELSE 0 END) as matched_count,
                SUM(CASE WHEN match_status = 'diff' THEN 1 ELSE 0 END) as diff_count,
                SUM(CASE WHEN match_status = 'not_found' THEN 1 ELSE 0 END) as not_found_count,
                SUM(CASE WHEN is_new_box = 1 THEN 1 ELSE 0 END) as new_box_count
            FROM mrs_count_record
            WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $session_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // 5. 获取有差异的记录
        $stmt = $pdo->prepare("
            SELECT r.*,
                   (SELECT GROUP_CONCAT(CONCAT(sku_name, '(系统:', system_qty, ' 实际:', actual_qty, ' 差异:', diff_qty, ')') SEPARATOR '; ')
                    FROM mrs_count_record_item WHERE record_id = r.record_id) as items_detail
            FROM mrs_count_record r
            WHERE session_id = :session_id AND match_status = 'diff'
            ORDER BY counted_at DESC
        ");
        $stmt->execute([':session_id' => $session_id]);
        $diff_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. 获取"仓库有但系统无"的记录
        $stmt = $pdo->prepare("
            SELECT * FROM mrs_count_record
            WHERE session_id = :session_id AND match_status = 'not_found'
            ORDER BY counted_at DESC
        ");
        $stmt->execute([':session_id' => $session_id]);
        $not_found_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 7. 更新任务状态
        $stmt = $pdo->prepare("
            UPDATE mrs_count_session
            SET status = 'completed', end_time = NOW(6)
            WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $session_id]);

        $pdo->commit();

        // 8. 返回报告
        return [
            'success' => true,
            'report' => [
                'stats' => $stats,
                'missing_boxes' => array_values($missing_boxes),
                'missing_count' => count($missing_boxes),
                'diff_records' => $diff_records,
                'not_found_records' => $not_found_records
            ]
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("MRS Count: Finish session failed - " . $e->getMessage());
        return ['success' => false, 'error' => '完成清点任务失败'];
    }
}

/**
 * 搜索SKU
 * @param PDO $pdo
 * @param string $keyword 关键词
 * @return array
 */
function mrs_count_search_sku($pdo, $keyword) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                sku_id,
                sku_name,
                sku_code,
                brand_name,
                spec_info,
                standard_unit,
                case_unit_name,
                case_to_standard_qty
            FROM mrs_sku
            WHERE status = 'active'
            AND (sku_name LIKE :keyword OR sku_code LIKE :keyword OR brand_name LIKE :keyword)
            ORDER BY sku_name
            LIMIT 20
        ");

        $stmt->execute([':keyword' => '%' . $keyword . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("MRS Count: Search SKU failed - " . $e->getMessage());
        return [];
    }
}
