<?php
if (!defined('SPS_ENTRY')) die('Access denied');
session_unset();
session_destroy();
header('Location: /sps/index.php?action=login');
exit;
