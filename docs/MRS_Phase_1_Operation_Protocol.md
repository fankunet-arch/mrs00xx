# MRS Phase 1 Operation Protocol

## 1. System Access Points
*   **Frontend (Quick Entry):** `https://[Domain]/mrs/`
*   **Backend (Merge/Admin):** `https://[Domain]/mrs/backend.php`

## 2. Operational Rules

### Frontline Staff
*   **Rule:** "Record exactly what you see. Don't do mental math."
*   **Example:** If you count 65 bottles, enter 65 bottles. Do not try to convert it to cases yourself.

### Backend Staff
*   **Rule:** "Check the Case/Unit split in the Merge page before confirming. If it looks wrong, edit it manually."
*   **Action:** Verify that the automated calculations make sense before finalizing any batch.

## 3. Key Feature Note: Auto-Normalization
The system now includes an **Auto-Normalization** feature.
*   **What it does:** Automatically converts decimal entries into standard formats upon confirmation.
*   **Example:** Entering "6.5 cases" will be automatically converted and stored as "6 cases + 5 units" (assuming a case size of 10).
*   **Benefit:** This ensures inventory data is clean and consistent without requiring manual calculation errors.
