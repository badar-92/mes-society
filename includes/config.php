<?php
// MES Society Website Configuration for InfinityFree
define('SITE_NAME', 'MES UOL');

// Base URL - Updated with your actual domain
define('SITE_URL', 'https://mesuol.xo.je/mes-society');

// File paths - Updated for InfinityFree
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/mes-society/uploads/');
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/mes-society/');

define('UPLOAD_PATH_CERTIFICATES', UPLOAD_PATH . 'certificates/');
define('UPLOAD_PATH_CERTIFICATES_THUMBS', UPLOAD_PATH . 'certificates/thumbs/');

// Database configuration for InfinityFree - FIXED CONNECTION
define('DB_HOST', 'sql100.infinityfree.com'); // Use the actual hostname, not localhost
define('DB_NAME', 'if0_40553977_mes');
define('DB_USER', 'if0_40553977');
define('DB_PASS', 'fwbnUkVq4BheQUw');
define('DB_PORT', '3306');

// File upload configurations
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx']);

// Theme colors
define('THEME_PRIMARY', '#000000'); // Black
define('THEME_SECONDARY', '#FFFFFF'); // White
define('THEME_ACCENT', '#FF6600'); // Orange

// Security key for encryption
define('SECURITY_KEY', 'mes_society_infinityfree_2024');

// Error reporting (disable in production by setting to 0)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 1800); // 30 minutes
    session_set_cookie_params(1800);
    session_start();
}

// Timezone setting
date_default_timezone_set('Asia/Karachi');

// Include database connection
require_once 'database.php';
?>