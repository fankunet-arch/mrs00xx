<?php
/**
 * Test script to verify Graceful Degradation fix
 * Run this on the target environment to verify the fix works even without DB migration.
 */

define('MRS_ENTRY', true);
require_once __DIR__ . '/../../config_mrs/env_mrs.php';
require_once __DIR__ . '/../mrs_lib.php';

echo "=== MRS Graceful Degradation Test ===\n";

try {
    $pdo = get_db_connection();

    // 1. Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM mrs_batch_raw_record LIKE 'input_sku_name'");
    $hasColumn = $stmt->rowCount() > 0;
    echo "Column 'input_sku_name' exists? " . ($hasColumn ? "YES" : "NO") . "\n";

    if ($hasColumn) {
        echo "NOTE: Column exists, so degradation logic will NOT be triggered.\n";
        echo "To test degradation, you would need to drop the column temporarily.\n";
    } else {
        echo "Column missing. Testing degradation logic...\n";
    }

    // 2. Try to insert a record
    $batch_id = 1; // Assuming batch 1 exists, or we need to find one
    $checkBatch = $pdo->query("SELECT batch_id FROM mrs_batch LIMIT 1");
    $batch = $checkBatch->fetch();

    if (!$batch) {
        echo "No batches found. Creating a test batch...\n";
        // Create dummy batch logic here if needed, but for now just fail
        die("Error: No batches found to test with.\n");
    }
    $batch_id = $batch['batch_id'];

    $data = [
        'batch_id' => $batch_id,
        'sku_id' => null,
        'input_sku_name' => 'Test Item ' . time(),
        'qty' => 10,
        'unit_name' => '个',
        'operator_name' => 'Tester'
    ];

    echo "Attempting insert...\n";
    $id = save_raw_record($data);

    if ($id) {
        echo "SUCCESS: Record inserted with ID: $id\n";
        if (!$hasColumn) {
            echo "Graceful degradation worked!\n";
        }
    } else {
        echo "FAILURE: Record insert failed.\n";
    }

    // 3. Try to fetch records
    echo "Attempting fetch...\n";
    $records = get_batch_raw_records($batch_id);
    if (count($records) > 0) {
        echo "SUCCESS: Fetched " . count($records) . " records.\n";
    } else {
        echo "WARNING: Fetched 0 records (might be empty, or failed).\n";
    }

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
