<?php
// Action: inventory_detail.php - 库存明细页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$page_title = "库存明细";
$action = 'inventory_detail';

require_once MRS_VIEW_PATH . '/inventory_detail.php';
