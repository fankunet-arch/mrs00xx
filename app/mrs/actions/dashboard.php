<?php
// Action: dashboard.php

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$page_title = "仪表盘"; // Used for the template
$action = 'dashboard'; // For highlighting the active menu item

$metrics = [
    'sku_count' => 0,
    'category_count' => 0,
    'open_batches' => 0,
    'outbound_week' => 0,
    'low_stock' => 0,
];

$recent_batches = [];
$recent_outbounds = [];
$inventory_alerts = [];
$recent_movements = [
    'inbound_7d' => 0,
    'outbound_7d' => 0,
    'adjustment_7d' => 0,
];

try {
    $pdo = get_db_connection();

    // 预加载数据表信息，避免重复查询
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $hasTable = function ($name) use ($tables) {
        return in_array($name, $tables, true);
    };

    if ($hasTable('mrs_sku')) {
        $metrics['sku_count'] = (int)$pdo->query("SELECT COUNT(*) FROM mrs_sku")->fetchColumn();
    }

    if ($hasTable('mrs_category')) {
        $metrics['category_count'] = (int)$pdo->query("SELECT COUNT(*) FROM mrs_category")->fetchColumn();
    }

    if ($hasTable('mrs_batch')) {
        $metrics['open_batches'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM mrs_batch WHERE batch_status NOT IN ('posted')"
        )->fetchColumn();

        $recent_batches = $pdo->query(
            "SELECT batch_id, batch_code, batch_date, location_name, batch_status, remark
             FROM mrs_batch
             ORDER BY batch_date DESC, batch_id DESC
             LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($hasTable('mrs_outbound_order')) {
        $metrics['outbound_week'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM mrs_outbound_order WHERE outbound_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
        )->fetchColumn();

        $recent_outbounds = $pdo->query(
            "SELECT outbound_order_id, outbound_code, outbound_date, status, location_name, remark
             FROM mrs_outbound_order
             ORDER BY outbound_date DESC, outbound_order_id DESC
             LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($hasTable('mrs_inventory')) {
        $metrics['low_stock'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM mrs_inventory WHERE current_qty < 5"
        )->fetchColumn();

        $inventory_alerts = $pdo->query(
            "SELECT s.sku_name, c.category_name, COALESCE(inv.current_qty, 0) AS current_qty, s.standard_unit
             FROM mrs_inventory inv
             JOIN mrs_sku s ON inv.sku_id = s.sku_id
             LEFT JOIN mrs_category c ON s.category_id = c.category_id
             ORDER BY inv.current_qty ASC
             LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($hasTable('mrs_inventory_transaction')) {
        $movementStmt = $pdo->query(
            "SELECT
                SUM(CASE WHEN transaction_type = 'inbound' THEN quantity_change ELSE 0 END) AS inbound_7d,
                SUM(CASE WHEN transaction_type = 'outbound' THEN quantity_change ELSE 0 END) AS outbound_7d,
                SUM(CASE WHEN transaction_type = 'adjustment' THEN quantity_change ELSE 0 END) AS adjustment_7d
             FROM mrs_inventory_transaction
             WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
        );

        $movement = $movementStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $recent_movements['inbound_7d'] = floatval($movement['inbound_7d'] ?? 0);
        $recent_movements['outbound_7d'] = floatval($movement['outbound_7d'] ?? 0);
        $recent_movements['adjustment_7d'] = floatval($movement['adjustment_7d'] ?? 0);
    }
} catch (PDOException $e) {
    mrs_log('仪表盘数据加载失败: ' . $e->getMessage(), 'ERROR');
}

require_once MRS_VIEW_PATH . '/dashboard.php';
