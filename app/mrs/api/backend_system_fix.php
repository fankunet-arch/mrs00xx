<?php
/**
 * MRS System Fix API
 * Route: api.php?route=backend_system_fix
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST allowed');
    }

    $pdo = get_db_connection();
    $messages = [];

    // Check 001_add_input_sku_name_to_raw_record
    $checkSql = "SHOW COLUMNS FROM mrs_batch_raw_record LIKE 'input_sku_name'";
    $stmt = $pdo->query($checkSql);

    if ($stmt->rowCount() === 0) {
        $migrationFile = MRS_APP_PATH . '/../docs/migrations/001_add_input_sku_name_to_raw_record.sql';
        if (file_exists($migrationFile)) {
            $sqlContent = file_get_contents($migrationFile);
            $statements = explode(';', $sqlContent);

            foreach ($statements as $sql) {
                $sql = trim($sql);
                if (empty($sql) || strpos($sql, '--') === 0) continue;
                try {
                    $pdo->exec($sql);
                } catch (Exception $e) {
                    // Ignore errors if it's just "column exists" or similar safe errors
                    // But here we rely on the initial check
                    mrs_log("Migration partial error: " . $e->getMessage(), 'WARNING');
                }
            }
            $messages[] = "Applied migration: 001_add_input_sku_name_to_raw_record";
        } else {
            throw new Exception("Migration file 001 missing");
        }
    } else {
        $messages[] = "Migration 001 already applied";
    }

    json_response(true, ['messages' => $messages], 'System fix applied successfully');

} catch (Exception $e) {
    mrs_log('System fix failed: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, $e->getMessage());
}
