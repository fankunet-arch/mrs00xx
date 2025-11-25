<?php
/**
 * MRS System Status API
 * Route: api.php?route=backend_system_status
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// Require Admin Login
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_db_connection();
    $issues = [];
    $migration_needed = false;

    // Check 001_add_input_sku_name_to_raw_record
    $checkSql = "SHOW COLUMNS FROM mrs_batch_raw_record LIKE 'input_sku_name'";
    $stmt = $pdo->query($checkSql);
    if ($stmt->rowCount() === 0) {
        $issues[] = "Database schema outdated: Missing 'input_sku_name' column.";
        $migration_needed = true;
    }

    json_response(true, [
        'healthy' => empty($issues),
        'migration_needed' => $migration_needed,
        'issues' => $issues
    ], 'Status checked');

} catch (Exception $e) {
    json_response(false, null, $e->getMessage());
}
