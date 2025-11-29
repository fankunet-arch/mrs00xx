<?php
// app/mrs/actions/batch_list.php

// Whitelist sortable columns to prevent SQL injection
$allowed_sort_columns = ['batch_code', 'batch_status', 'created_at', 'updated_at', 'raw_record_count'];
$sort_column = $_GET['sort'] ?? 'created_at';
if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = 'created_at';
}

$sort_order = strtoupper($_GET['order'] ?? 'DESC');
if ($sort_order !== 'ASC' && $sort_order !== 'DESC') {
    $sort_order = 'DESC';
}


// 获取批次列表, a left join to count raw records and confirmed items
$pdo = get_db_connection();
$sql = "
    SELECT
        b.*,
        COUNT(DISTINCT r.raw_record_id) AS raw_record_count,
        COUNT(DISTINCT c.confirmed_item_id) AS confirmed_item_count
    FROM
        mrs_batch b
    LEFT JOIN
        mrs_batch_raw_record r ON b.batch_id = r.batch_id
    LEFT JOIN
        mrs_batch_confirmed_item c ON b.batch_id = c.batch_id
    GROUP BY
        b.batch_id
    ORDER BY {$sort_column} {$sort_order}
";
$stmt = $pdo->query($sql);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 为SPA和传统视图设置变量
$is_spa = false;
$page_title = "批次列表";
$action = 'batch_list';

require_once MRS_VIEW_PATH . '/batch_list.php';
