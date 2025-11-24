<?php
/**
 * MRS API Gateway
 * Unified entry point for all frontend API requests.
 */

// Define entry constant to allow inclusion of protected files
if (!defined('MRS_ENTRY')) {
    define('MRS_ENTRY', true);
}

// Load environment configuration
require_once __DIR__ . '/../../app/mrs/config_mrs/env_mrs.php';

// Get the route parameter
$route = $_GET['route'] ?? '';

// Security filter: Allow only alphanumeric characters and underscores
// This prevents directory traversal and inclusion of arbitrary files
if (empty($route) || !preg_match('/^[a-zA-Z0-9_]+$/', $route)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing route parameter'
    ]);
    exit;
}

// Construct the target file path
// basename() is used as an extra layer of security
$target_file = __DIR__ . '/../../app/mrs/api/' . basename($route) . '.php';

// Check if the file exists
if (file_exists($target_file)) {
    // Execute the backend logic
    require $target_file;
} else {
    // Return 404 if the endpoint doesn't exist
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'API endpoint not found: ' . htmlspecialchars($route)
    ]);
    exit;
}
