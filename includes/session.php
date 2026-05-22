<?php
require_once 'config.php';
require_once 'auth.php';

class SessionManager {
    private $auth;
    
    public function __construct() {
        $this->auth = new Auth();
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    // FIXED: Now accepts both single role and array of roles
    public function requireRole($role) {
        if (!$this->auth->isLoggedIn()) {
            header('Location: ' . SITE_URL . '/public/login.php');
            exit();
        }
        
        // If role is array, check if user has any of the roles
        if (is_array($role)) {
            $hasRole = false;
            foreach ($role as $r) {
                if ($this->auth->hasRole($r)) {
                    $hasRole = true;
                    break;
                }
            }
            if (!$hasRole) {
                header('Location: ' . SITE_URL . '/public/access-denied.php');
                exit();
            }
        } 
        // If role is string, check single role
        else {
            if (!$this->auth->hasRole($role)) {
                header('Location: ' . SITE_URL . '/public/access-denied.php');
                exit();
            }
        }
    }
    
    // ADD THIS MISSING METHOD for sidebar compatibility
    public function userHasRole($allowedRoles) {
        if (!$this->auth->isLoggedIn()) {
            return false;
        }
        
        if (is_string($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        
        foreach ($allowedRoles as $role) {
            if ($this->auth->hasRole($role)) {
                return true;
            }
        }
        
        return false;
    }
    
    // Keep other methods the same
    public function requireLogin() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: ' . SITE_URL . '/public/login.php');
            exit();
        }
    }
    
    public function isLoggedIn() {
        return $this->auth->isLoggedIn();
    }
    
    public function getCurrentUser() {
        return $this->auth->getCurrentUser();
    }
    
    public function logout() {
        return $this->auth->logout();
    }
    
    public function isFirstLogin() {
        return $this->auth->isFirstLogin();
    }
}
?>