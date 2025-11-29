<?php
// Action: dashboard.php

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$page_title = "仪表盘"; // Used for the template
$action = 'dashboard'; // For highlighting the active menu item

require_once MRS_VIEW_PATH . '/dashboard.php';
