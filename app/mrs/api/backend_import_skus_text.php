<?php
if (!defined('MRS_ENTRY')) die('Access denied');

/**
 * Backend API: Import SKUs from text
 * Implements parsing logic for Batch Import feature.
 * Update: Switched to explicit '#END#' separator parsing to handle AI output more reliably.
 */

// Get raw text
$input = get_json_input();
$text = $input['text'] ?? '';

if (empty($text)) {
    json_response(false, null, 'No text provided');
}

$pdo = get_db_connection();

// Split by Explicit Separator #END#
$rawRows = explode('#END#', $text);
$results = ['created' => 0, 'skipped' => 0, 'errors' => []];

// Pre-fetch categories to minimize DB hits
$categories = [];
try {
    $stmt = $pdo->query("SELECT category_id, category_name FROM mrs_category");
    while ($row = $stmt->fetch()) {
        $categories[$row['category_name']] = $row['category_id'];
    }
} catch (PDOException $e) {
    // Ignore, just won't optimize
}

$pdo->beginTransaction();

try {
    foreach ($rawRows as $index => $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Split by pipe
        $parts = array_map('trim', explode('|', $line));

        // Validation: At least Name, Spec, CaseUnit (3 parts)
        if (count($parts) < 3) {
            // Might be trailing whitespace or noise after #END#
            continue;
        }

        $raw_name = $parts[0];
        $spec_str = $parts[1];
        $case_unit_str = $parts[2];
        $category_name = $parts[3] ?? '';

        // --- Spec Parser (Retained) ---
        $case_to_std_qty = 0;
        $std_unit = '个'; // Default
        $extra_name = '';

        // Case 1: Pure number (e.g. 500)
        if (preg_match('/^(\d+)$/', $spec_str, $matches)) {
            $case_to_std_qty = (float)$matches[1];
            $std_unit = '个';
        }
        // Case 3: Multiplication (e.g. 1L*12瓶)
        elseif (preg_match('/^(.+)\s*[*xX×]\s*(\d+)(.+)$/u', $spec_str, $matches)) {
            $case_to_std_qty = (float)$matches[2];
            $std_unit = trim($matches[3]);
        }
        // Case 2: Standard (e.g. 500g/30包)
        elseif (preg_match('/^(.+)\s*[/／]\s*(\d+)(.+)$/u', $spec_str, $matches)) {
            $extra_name = trim($matches[1]);
            $case_to_std_qty = (float)$matches[2];
            $std_unit = trim($matches[3]);
        }
        // Fallback
        else {
            if (preg_match('/(\d+)/', $spec_str, $matches)) {
                 $case_to_std_qty = (float)$matches[1];
            }
        }

        if ($case_to_std_qty <= 0) {
             $results['errors'][] = "Line " . ($index + 1) . ": Could not parse quantity from spec '$spec_str'";
             continue;
        }

        // Final Name
        $sku_name = $raw_name;
        if (!empty($extra_name)) {
            $sku_name .= ' ' . $extra_name;
        }

        // --- Check Existence ---
        $stmt = $pdo->prepare("SELECT sku_id FROM mrs_sku WHERE sku_name = :name");
        $stmt->execute([':name' => $sku_name]);
        if ($stmt->fetch()) {
            $results['skipped']++;
            continue;
        }

        // --- Category ---
        $category_id = null;
        if (!empty($category_name)) {
            if (isset($categories[$category_name])) {
                $category_id = $categories[$category_name];
            } else {
                // Auto create category
                $cat_stmt = $pdo->prepare("INSERT INTO mrs_category (category_name, created_at, updated_at) VALUES (:name, NOW(), NOW())");
                try {
                    $cat_stmt->execute([':name' => $category_name]);
                    $category_id = $pdo->lastInsertId();
                    $categories[$category_name] = $category_id; // Update cache
                } catch (PDOException $e) {
                    // Might failed due to race condition or something, try fetch again
                    $stmt = $pdo->prepare("SELECT category_id FROM mrs_category WHERE category_name = :name");
                    $stmt->execute([':name' => $category_name]);
                    $category_id = $stmt->fetchColumn();
                }
            }
        }

        // --- Create SKU ---
        // Generate Code: AUTO-YYYYMMDDHHMMSS-RAND
        $sku_code = sprintf('AUTO-%s-%03d', date('YmdHis'), rand(0, 999));

        $sql = "INSERT INTO mrs_sku (
            sku_name, sku_code, brand_name,
            standard_unit, case_unit_name, case_to_standard_qty,
            is_precise_item, category_id,
            created_at, updated_at
        ) VALUES (
            :name, :code, :brand,
            :std_unit, :case_unit, :case_qty,
            1, :cat_id,
            NOW(), NOW()
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $sku_name,
            ':code' => $sku_code,
            ':brand' => '默认', // Default brand
            ':std_unit' => $std_unit,
            ':case_unit' => $case_unit_str,
            ':case_qty' => $case_to_std_qty,
            ':cat_id' => $category_id
        ]);

        $results['created']++;
    }

    $pdo->commit();

    $msg = "成功创建 {$results['created']} 个，跳过 {$results['skipped']} 个（已存在）。";
    if (!empty($results['errors'])) {
        $msg .= " 发现 " . count($results['errors']) . " 个错误。";
    }

    json_response(true, $results, $msg);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mrs_log('Bulk Import Failed: ' . $e->getMessage(), 'ERROR', ['lines' => $rawRows]);
    json_response(false, null, 'Import failed: ' . $e->getMessage());
}
