<?php
// Mobile inventory check page
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

mrs_require_login();

require_once MRS_VIEW_PATH . '/inventory_check.php';
