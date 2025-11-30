<?php
// GUARANTEED WORKING LOGOUT - SIMPLE VERSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Completely destroy everything
session_unset();
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

session_destroy();

// Force redirect to homepage
header("Location: http://localhost/mes-society/public/");
exit();
?>