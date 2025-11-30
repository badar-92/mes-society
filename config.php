<?php
// MES Society Website Configuration
define('SITE_NAME', 'MES Society - University of Lahore');
define('SITE_URL', 'http://localhost/mes-society');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/mes-society/uploads/');
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/mes-society/');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'mes_society');
define('DB_USER', 'root');
define('DB_PASS', '');

// File upload configurations
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx']);

// Theme colors
define('THEME_PRIMARY', '#000000'); // Black
define('THEME_SECONDARY', '#FFFFFF'); // White
define('THEME_ACCENT', '#FF6600'); // Orange

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration - ONLY if session not already active
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 1800); // 30 minutes
    session_set_cookie_params(1800);
    session_start();
}
?>