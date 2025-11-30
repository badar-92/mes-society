<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple admin check
$allowed_roles = ['super_admin', 'department_head'];
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    die('Not authorized.');
}

// Now include required files
require_once '../includes/config.php';
require_once '../includes/database.php';

if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = "../uploads/confirmation-letters/" . $filename;
    
    if (file_exists($filepath) && is_file($filepath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        http_response_code(404);
        die('File not found.');
    }
} else {
    http_response_code(400);
    die('Invalid request.');
}
?>