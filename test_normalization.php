<?php

/**
 * Normalization Logic Verification Script
 * Simulates the normalization patch applied in app/mrs/api/backend_confirm_merge.php
 */

function normalize($caseQty, $singleQty, $caseToStandard) {
    // Exact same logic as the patch

    // 1. Calculate theoretical float value
    $rawTotal = ($caseQty * $caseToStandard) + $singleQty;

    // 2. Round to integer to prevent float precision issues
    $totalStandard = round($rawTotal, 0);

    // [PATCH] Normalization Logic
    if ($caseToStandard > 0 && fmod($caseToStandard, 1.0) == 0.0) {
        $caseSize = (int)$caseToStandard;
        $total    = (int)$totalStandard;

        $normalizedCaseQty   = intdiv($total, $caseSize);
        $normalizedSingleQty = $total % $caseSize;

        $caseQty   = $normalizedCaseQty;
        $singleQty = $normalizedSingleQty;
    }

    return [
        'case_qty' => $caseQty,
        'single_qty' => $singleQty
    ];
}

function runTest($scenarioName, $input, $expected) {
    echo "Running Scenario: $scenarioName\n";
    echo "Input: CaseQty={$input['case']}, SingleQty={$input['single']}, Rule={$input['rule']}\n";

    $result = normalize($input['case'], $input['single'], $input['rule']);

    echo "Output: CaseQty={$result['case_qty']}, SingleQty={$result['single_qty']}\n";
    echo "Expected: CaseQty={$expected['case']}, SingleQty={$expected['single']}\n";

    if ($result['case_qty'] == $expected['case'] && $result['single_qty'] == $expected['single']) {
        echo "Result: PASS\n";
    } else {
        echo "Result: FAIL\n";
    }
    echo "------------------------------------------------\n";
}

// Scenario A (Decimal Split)
// Input: 6.5 Cases, 0 Singles, Rule: 1 Case = 10 Units.
// Expected: 6 Cases, 5 Singles.
runTest('A (Decimal Split)',
    ['case' => 6.5, 'single' => 0, 'rule' => 10],
    ['case' => 6, 'single' => 5]
);

// Scenario B (Auto-Combine)
// Input: 0 Cases, 65 Singles, Rule: 1 Case = 10 Units.
// Expected: 6 Cases, 5 Singles.
runTest('B (Auto-Combine)',
    ['case' => 0, 'single' => 65, 'rule' => 10],
    ['case' => 6, 'single' => 5]
);

// Scenario C (Non-Integer Rule)
// Input: 2 Cases, 0 Singles, Rule: 1 Case = 12.5 Units.
// Expected: 2 Cases, 0 Singles (Normalization skipped).
runTest('C (Non-Integer Rule)',
    ['case' => 2, 'single' => 0, 'rule' => 12.5],
    ['case' => 2, 'single' => 0]
);
