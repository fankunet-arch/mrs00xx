<?php
/**
 * Test Script: Backend Confirm Simulation
 * Simulates the frontend calling backend_confirm_merge.php via the API gateway.
 */

// Define entry constant
define('MRS_ENTRY', true);

// Load environment configuration
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

echo "Database Connection: OK\n";

// --- Helpers ---

function create_test_category() {
    $pdo = get_db_connection();
    $name = 'TestCat_' . time();
    $sql = "INSERT INTO mrs_category (category_name, created_at, updated_at) VALUES (:name, NOW(6), NOW(6))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':name' => $name]);
    return $pdo->lastInsertId();
}

function create_test_sku($catId, $spec = 10) {
    $pdo = get_db_connection();
    $code = 'TEST-' . time() . '-' . rand(100,999);
    $sql = "INSERT INTO mrs_sku (
        category_id, sku_name, sku_code, brand_name,
        standard_unit, case_unit_name, case_to_standard_qty,
        created_at, updated_at
    ) VALUES (
        :cat, :name, :code, 'TestBrand',
        'pcs', 'case', :spec,
        NOW(6), NOW(6)
    )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':cat' => $catId,
        ':name' => 'TestSKU ' . $code,
        ':code' => $code,
        ':spec' => $spec
    ]);
    return $pdo->lastInsertId();
}

function create_test_batch() {
    $pdo = get_db_connection();
    $code = 'BATCH-' . time() . '-' . rand(100,999);
    $sql = "INSERT INTO mrs_batch (
        batch_code, batch_date, batch_status, created_at, updated_at, location_name
    ) VALUES (
        :code, CURDATE(), 'receiving', NOW(6), NOW(6), 'Warehouse A'
    )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':code' => $code]);
    return $pdo->lastInsertId();
}

function create_test_user() {
    $pdo = get_db_connection();
    $login = 'testuser_' . time();
    $hash = password_hash('password', PASSWORD_DEFAULT);
    $email = $login . '@example.com';
    $displayName = 'Test User';

    $sql = "INSERT INTO sys_users (
        user_login, user_secret_hash, user_status, user_registered_at, user_email, user_display_name
    ) VALUES (
        :login, :hash, 'active', NOW(6), :email, :displayName
    )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':login' => $login,
        ':hash' => $hash,
        ':email' => $email,
        ':displayName' => $displayName
    ]);
    return $pdo->lastInsertId();
}

function get_confirmed_item($batchId, $skuId) {
    $pdo = get_db_connection();
    $sql = "SELECT * FROM mrs_batch_confirmed_item WHERE batch_id = :bid AND sku_id = :sid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':bid' => $batchId, ':sid' => $skuId]);
    return $stmt->fetch();
}

function get_batch_status($batchId) {
    $pdo = get_db_connection();
    $sql = "SELECT batch_status FROM mrs_batch WHERE batch_id = :bid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':bid' => $batchId]);
    return $stmt->fetchColumn();
}

// --- Main Test Logic ---

try {
    echo "Creating Test Data...\n";
    // 1. Setup Data
    $catId = create_test_category();
    echo "Category created: $catId\n";
    $skuId = create_test_sku($catId, 10); // 1 case = 10 units
    echo "SKU created: $skuId\n";
    $batchId = create_test_batch();
    echo "Batch created: $batchId\n";
    $userId = create_test_user();
    echo "User created: $userId\n";

    // 2. Prepare Payload
    $payload = [
        'batch_id' => $batchId,
        'items' => [
            [
                'sku_id' => $skuId,
                'case_qty' => 6.5,
                'single_qty' => 0,
                'expected_qty' => 0
            ]
        ]
    ];

    echo "Payload prepared. Launching runner...\n";

    $runnerScript = __DIR__ . '/runner_confirm.php';

    $envPath = realpath(__DIR__ . '/../config_mrs/env_mrs.php');
    $libPath = realpath(MRS_LIB_PATH . '/mrs_lib.php');
    $apiPath = realpath(__DIR__ . '/../api/backend_confirm_merge.php');

    // Dynamically read the target file
    $apiCode = file_get_contents($apiPath);

    // Inject Mock Input Logic
    $apiCode = preg_replace('/^<\?php/', '', $apiCode);
    $apiCode = str_replace(
        '$input = get_json_input();',
        '$input = $inputData;',
        $apiCode
    );

    // If specific DB credentials are required for the sandbox,
    // they should be set via export MRS_DB_USER=... before running the script
    // or rely on env_mrs.php defaults.
    // Here we omit the hardcoded putenv calls to be secure.
    // The runner inherits the environment of the parent process.

    $runnerCode = <<<PHP
<?php
define('MRS_ENTRY', true);

require_once '{$envPath}';
require_once '{$libPath}';

// Decode STDIN for input
\$inputData = json_decode(file_get_contents('php://stdin'), true);

// --- INJECTED CODE START ---
{$apiCode}
// --- INJECTED CODE END ---
PHP;

    file_put_contents($runnerScript, $runnerCode);

    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];

    $process = proc_open('php ' . escapeshellarg($runnerScript), $descriptorspec, $pipes);

    if (is_resource($process)) {
        fwrite($pipes[0], json_encode($payload));
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnValue = proc_close($process);

        // echo "Runner Return Value: $returnValue\n";
        // echo "Runner Output: $output\n";
        if ($returnValue != 0 || !empty($error)) {
             echo "Runner Error: $error\n";
        }
    } else {
        echo "Failed to open process.\n";
    }

    // 4. Verification
    echo "Verifying results...\n";

    $item = get_confirmed_item($batchId, $skuId);
    $status = get_batch_status($batchId);

    if ($item) {
        $c = $item['confirmed_case_qty'];
        $s = $item['confirmed_single_qty'];

        // Logic: 6.5 cases * 10 = 65 units.
        // Normalized: 6 cases (60 units) + 5 units.

        if ($c == 6 && $s == 5) {
            echo "Normalization Check: PASS (Expected: 6 cases, 5 units; Actual: {$c} cases, {$s} units)\n";
        } else {
            echo "Normalization Check: FAIL (Expected: 6 cases, 5 units; Actual: {$c} cases, {$s} units)\n";
        }
    } else {
        echo "Normalization Check: FAIL (Record not found)\n";
    }

    if ($status === 'confirmed') {
        echo "Status Update Check: PASS\n";
    } else {
        echo "Status Update Check: FAIL (Status: $status)\n";
    }

    if ($item && $c == 6 && $s == 5 && $status === 'confirmed') {
        echo "ALL TESTS PASSED\n";
    } else {
        echo "TESTS FAILED\n";
    }

    // Cleanup
    echo "Cleaning up...\n";
    @unlink($runnerScript);
    $pdo = get_db_connection();
    $pdo->query("DELETE FROM mrs_batch_confirmed_item WHERE batch_id = $batchId");
    $pdo->query("DELETE FROM mrs_batch WHERE batch_id = $batchId");
    $pdo->query("DELETE FROM mrs_sku WHERE sku_id = $skuId");
    $pdo->query("DELETE FROM mrs_category WHERE category_id = $catId");
    $pdo->query("DELETE FROM sys_users WHERE user_id = $userId");

} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
