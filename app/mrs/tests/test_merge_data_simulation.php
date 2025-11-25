<?php
/**
 * Test Script: Backend Merge Data Simulation (Verification)
 * Location: app/mrs/tests/test_merge_data_simulation.php
 *
 * Usage: php app/mrs/tests/test_merge_data_simulation.php
 *
 * Goal: Verify that backend_merge_data.php returns confirmed items correctly and in a stable manner.
 * Note: Since I cannot run the API, I will verify the logic by "dry running" it in my head against the file content.
 *
 * Logic Review:
 * 1. It fetches expected items.
 * 2. It fetches raw records.
 * 3. It combines them into $skuMap.
 * 4. It calculates suggested quantities based on RAW records.
 *
 * ISSUE IDENTIFIED:
 * The `backend_merge_data.php` script DOES NOT fetch `mrs_batch_confirmed_item`.
 * It only fetches `mrs_batch_expected_item` and `mrs_batch_raw_record`.
 *
 * If a batch item is already confirmed (partially or fully), and the user goes back to the Merge page,
 * the script recalculates "suggested" values from RAW records.
 * It DOES NOT populate `confirmed_case` and `confirmed_single` from `mrs_batch_confirmed_item`.
 *
 * It sets:
 * 'confirmed_case' => $caseQty (calculated from raw),
 * 'confirmed_single' => $singleQty (calculated from raw).
 *
 * This means:
 * 1. If I confirm an item (saved to DB), and reload the page.
 * 2. The page shows the item based on RAW records again.
 * 3. If I haven't added new raw records, it shows the same values.
 * 4. `appState.mergeItems` is populated with these values.
 *
 * So, where does the "Synchronization Error" come from?
 * The user says "Adjusting CONFIRMED items".
 * If the user confirmed it, maybe they expect to see their CONFIRMED values, not the raw-calculated ones?
 * But `backend_merge_data.php` ignores the confirmed table!
 *
 * Wait, if the user confirms, the batch status might change?
 * I fixed `backend_confirm_merge.php` to NOT change batch status if `close_batch` is false.
 *
 * However, the error "Data synchronization error" is checking `!appState.mergeItems[index]`.
 * This implies the array length or index is wrong.
 *
 * If `backend_merge_data.php` returns the list of items based on Expected + Raw.
 * Does the list change after confirmation?
 * No, confirming doesn't delete raw records.
 *
 * So the list should be stable.
 *
 * Hypotheses for "Data sync error":
 * A. The user refreshed the page? No, error says "Please refresh".
 * B. The user clicked a row that exists in DOM but not in `appState`.
 *    This happens if `renderMergePage` wasn't called or failed.
 * C. The user filtered the list? The HTML has no filter for merge page.
 *
 * Let's look at `backend.js` `confirmItem`.
 * It uses `index`.
 *
 * Is it possible that `appState.mergeItems` is being overwritten or cleared?
 *
 * ONE BIG ISSUE:
 * `backend_merge_data.php` completely ignores `mrs_batch_confirmed_item`.
 * If I confirm an item with DIFFERENT quantities than the suggested (raw) ones (e.g. I manually edited the input fields),
 * and then I click "Confirm", it saves to DB.
 * Then `showMergePage` calls `getMergeData`.
 * `getMergeData` returns the RAW calculated values again, IGNORING my manual confirmation!
 * So the UI reverts to the raw values.
 * This is a functional bug (User loses their manual adjustment visualization), but not the "Sync Error".
 *
 * The "Sync Error" is technical.
 * `!appState.mergeItems[index]`
 *
 * Maybe `appState` is not preserved?
 *
 * Wait, I see `renderMergePage` logic:
 * `appState.mergeItems = data.items;`
 *
 * If `data.items` is null?
 * `json_response(true, ['items' => $items])`
 * If `$items` is empty array `[]`.
 * `appState.mergeItems` is `[]`.
 * Table renders empty. User cannot click.
 *
 * What if `data.items` is NOT an array but an object?
 * PHP `$items = []`. `json_encode` makes it `[]`.
 * If keys are not sequential? `$items` is pushed `[]` so it is sequential.
 *
 * Let's fix the Functional Bug first: `backend_merge_data.php` SHOULD return confirmed values if they exist.
 * This will also help stability.
 *
 * PROPOSED FIX for `backend_merge_data.php`:
 * 1. Fetch `mrs_batch_confirmed_item` entries.
 * 2. Merge them into the list.
 *    If an item is confirmed, use `confirmed_case_qty` and `confirmed_single_qty` for the 'confirmed_case/single' fields.
 *    And maybe mark it as 'Confirmed' in UI?
 *
 * This will ensure that when the page reloads, the user sees what they confirmed.
 *
 * PROPOSED FIX for "Sync Error":
 * I suspect the issue is `appState.mergeItems` scope or persistence.
 * Or maybe the DOM has rows that shouldn't be there?
 *
 * I will modify `backend_merge_data.php` to include confirmed data.
 * I will also modify `backend.js` to be more robust: use `sku_id` to find item instead of index.
 *
 * Using Index is brittle if the list changes.
 * Pass `sku_id` to `confirmItem`.
 * `confirmItem(sku_id)`.
 * find item in `appState.mergeItems.find(i => i.sku_id == sku_id)`.
 *
 * This is much safer.
 */
?>
