<?php
/**
 * IPManager Pro Configuration
 */

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'ipmanage');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('OFFLINE_TTL_MINUTES', (int)(getenv('OFFLINE_TTL_MINUTES') ?: 30));

// Application Configuration
define('APP_NAME', 'IPManager Pro');
define('APP_URL', 'http://localhost/ipmanage');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Standard success/error responses
function json_response($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Auth Helpers
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_viewer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'viewer';
}
