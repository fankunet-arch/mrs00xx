<?php
// MRS Central Router (v3 - Corrected)

define('MRS_ENTRY', true);

require_once __DIR__ . '/bootstrap.php';

// Routing Logic
$action = $_GET['action'] ?? null;

if ($action === null) {
    // Determine default action based on the entry script path
    $script_path = $_SERVER['SCRIPT_NAME'];
    // Backend path contains '/be/', frontend does not
    if (strpos($script_path, '/be/') !== false) {
        $action = 'dashboard'; // Backend default
    } else {
        $action = 'quick_receipt'; // Frontend default
    }
}

$action = basename($action);

// Whitelist of all allowed actions
$allowed_actions = [
    // Backend Auth
    'login', 'process_login', 'logout',
    // Backend Pages
    'dashboard',
    'sku_list', 'sku_edit', 'sku_save',
    'batch_list', 'batch_detail', 'batch_create', 'batch_create_save', 'batch_edit', 'batch_save',
    'outbound_list', 'outbound_create', 'outbound_save', 'outbound_detail',
    'category_list', 'category_edit', 'category_save',
    'inventory_list',
    'reports',
    // Frontend Page
    'quick_receipt',
    // API-like actions for Quick Receipt
    'get_batch_list_api', 'sku_search_api', 'save_receipt_api', 'get_batch_records_api',
];

// Dynamic routing for backend_ API calls
if (strpos($action, 'backend_') === 0) {
    $api_file = MRS_API_PATH . '/' . $action . '.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        header("HTTP/1.0 404 Not Found");
        mrs_log("Backend API file not found: {$api_file}", 'ERROR');
        die("Error 404: API '{$action}' not found.");
    }
}

// Check whitelist for regular actions
if (!in_array($action, $allowed_actions)) {
    mrs_log("Disallowed action requested: {$action}", 'WARNING');
    $action = 'dashboard'; // Default to a safe page
}

$action_file = MRS_ACTION_PATH . '/' . $action . '.php';

if (file_exists($action_file)) {
    require_once $action_file;
} else {
    header("HTTP/1.0 404 Not Found");
    mrs_log("Action file not found: {$action_file}", 'ERROR');
    die("Error 404: Action '{$action}' not found.");
}
