<?php
require_once 'database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Register new user (for membership applications) - FIXED FOR YOUR EXACT DATABASE STRUCTURE
    public function registerApplication($applicationData) {
        try {
            error_log("=== registerApplication CALLED ===");
            error_log("Application Data: " . print_r($applicationData, true));
            
            // Use the EXACT column names from your database
            $sql = "INSERT INTO applications (user_id, applied_for, personal_info, academic_info, skills_experience, portfolio_links, motivation_statement, resume_path, status) 
                    VALUES (:user_id, 'membership', :personal_info, :academic_info, :skills_experience, :portfolio_links, :motivation_statement, :resume_path, 'pending')";
            
            error_log("SQL: " . $sql);
            
            $stmt = $this->db->prepare($sql);
            
            // Map to your exact database columns - NO profile_picture or desired_position
            $params = [
                'user_id' => $applicationData['user_id'] ?? 0,
                'personal_info' => $applicationData['personal_info'] ?? '',
                'academic_info' => $applicationData['academic_info'] ?? '',
                'skills_experience' => $applicationData['skills_experience'] ?? '',
                'portfolio_links' => $applicationData['portfolio_links'] ?? '',
                'motivation_statement' => $applicationData['motivation_statement'] ?? '',
                'resume_path' => $applicationData['resume_path'] ?? null
            ];
            
            error_log("Params: " . print_r($params, true));
            
            $result = $stmt->execute($params);
            
            if ($result) {
                $lastId = $this->db->lastInsertId();
                error_log("✓ Application inserted successfully. ID: " . $lastId);
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("✗ SQL Error: " . print_r($errorInfo, true));
                throw new PDOException("SQL Error: " . $errorInfo[2], $errorInfo[1]);
            }
            
        } catch(PDOException $e) {
            error_log("✗ Registration PDOException: " . $e->getMessage());
            error_log("✗ SQL State: " . $e->getCode());
            return false;
        } catch(Exception $e) {
            error_log("✗ Registration Exception: " . $e->getMessage());
            return false;
        }
    }
    
    // Login user with comprehensive error handling
    public function login($email, $password) {
        try {
            error_log("Login attempt for email: " . $email);
            
            $sql = "SELECT * FROM users WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                error_log("Login failed: Email not found - " . $email);
                return false;
            }
            
            if ($user['status'] !== 'active') {
                error_log("Login failed: Account not active - " . $email . " Status: " . $user['status']);
                return false;
            }
            
            if (empty($user['password']) || $user['password'] === 'first_login') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['first_login'] = true;
                $_SESSION['last_activity'] = time();
                
                error_log("First login detected for: " . $email);
                return true;
            }
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['last_activity'] = time();
                
                $this->updateLastLogin($user['id']);
                
                error_log("Login successful: " . $email . " Role: " . $user['role']);
                return true;
            } else {
                error_log("Login failed: Invalid password for - " . $email);
                return false;
            }
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    // Add this function to your auth.php file, anywhere in the Auth class:

public function isAdmin() {
    if (!$this->isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'];
    $adminRoles = ['super_admin', 'department_head', 'hiring_head'];
    
    return in_array($userRole, $adminRoles);
}
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function isFirstLogin() {
        return isset($_SESSION['first_login']) && $_SESSION['first_login'] === true;
    }
    
    public function hasRole($role) {
        if (!$this->isLoggedIn()) return false;
        
        $userRole = $_SESSION['user_role'];
        $rolesHierarchy = [
            'public' => 0,
            'member' => 1,
            'media_head' => 2,
            'event_planner' => 2,
            'competition_head' => 2,
            'hiring_head' => 2,
            'department_head' => 3,
            'super_admin' => 4
        ];
        
        if (!isset($rolesHierarchy[$userRole]) || !isset($rolesHierarchy[$role])) {
            return false;
        }
        
        return $rolesHierarchy[$userRole] >= $rolesHierarchy[$role];
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $sql = "SELECT * FROM users WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $_SESSION['user_id']]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
    
    public function logout() {
        $_SESSION = array();
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(), 
                    '', 
                    time() - 42000,
                    $params["path"], 
                    $params["domain"],
                    $params["secure"], 
                    $params["httponly"]
                );
            }
            
            session_destroy();
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return true;
    }
    
    private function updateLastLogin($userId) {
        try {
            $sql = "UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $userId]);
        } catch(PDOException $e) {
            error_log("Last login update error: " . $e->getMessage());
        }
    }
    
    public function createMemberAccount($applicationId) {
        try {
            $sql = "SELECT * FROM applications WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $applicationId]);
            $application = $stmt->fetch();
            
            if (!$application) return false;
            
            $personalInfo = json_decode($application['personal_info'], true);
            
            $sql = "INSERT INTO users (sap_id, name, email, password, department, semester, phone, profile_picture, role, status) 
                    VALUES (:sap_id, :name, :email, :password, :department, :semester, :phone, :profile_picture, 'member', 'active')";
            
            $stmt = $this->db->prepare($sql);
            
            $result = $stmt->execute([
                'sap_id' => $personalInfo['sap_id'],
                'name' => $personalInfo['name'],
                'email' => $personalInfo['email'],
                'password' => 'first_login',
                'department' => $personalInfo['department'],
                'semester' => $personalInfo['semester'],
                'phone' => $personalInfo['phone'],
                'profile_picture' => 'default-avatar.png'
            ]);
            
            if ($result) {
                $this->updateApplicationStatus($applicationId, 'selected');
                return true;
            }
            
            return false;
        } catch(PDOException $e) {
            error_log("Member creation error: " . $e->getMessage());
            return false;
        }
    }
    
public function createMemberAccountWithPassword($applicationId, $tempPassword) {
    try {
        $sql = "SELECT * FROM applications WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $applicationId]);
        $application = $stmt->fetch();
        
        if (!$application) return false;
        
        $personalInfo = json_decode($application['personal_info'], true);
        
        // FIXED: Include temp_password in the INSERT
        $sql = "INSERT INTO users (sap_id, name, email, password, temp_password, department, semester, phone, profile_picture, role, status) 
                VALUES (:sap_id, :name, :email, :password, :temp_password, :department, :semester, :phone, :profile_picture, 'member', 'active')";
        
        $stmt = $this->db->prepare($sql);
        
        $result = $stmt->execute([
            'sap_id' => $personalInfo['sap_id'],
            'name' => $personalInfo['name'],
            'email' => $personalInfo['email'],
            'password' => password_hash($tempPassword, PASSWORD_DEFAULT),
            'temp_password' => $tempPassword, // FIXED: Store plain temp password
            'department' => $personalInfo['department'],
            'semester' => $personalInfo['semester'],
            'phone' => $personalInfo['phone'],
            'profile_picture' => $personalInfo['profile_picture'] ?? 'default-avatar.png'
        ]);
        
        if ($result) {
            $userId = $this->db->lastInsertId();
            
            // Update application with user_id
            $updateAppSql = "UPDATE applications SET user_id = ?, status = 'selected' WHERE id = ?";
            $updateStmt = $this->db->prepare($updateAppSql);
            $updateStmt->execute([$userId, $applicationId]);
            
            return true;
        }
        
        return false;
    } catch(PDOException $e) {
        error_log("Member creation with password error: " . $e->getMessage());
        return false;
    }
}
    
    public function setInitialPassword($userId, $newPassword) {
        try {
            $sql = "UPDATE users SET password = :password WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $result = $stmt->execute([
                'password' => $hashedPassword,
                'id' => $userId
            ]);
            
            if ($result) {
                unset($_SESSION['first_login']);
                return true;
            }
            
            return false;
        } catch(PDOException $e) {
            error_log("Set initial password error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateApplicationStatus($applicationId, $status) {
        try {
            $sql = "UPDATE applications SET status = :status WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'status' => $status,
                'id' => $applicationId
            ]);
        } catch(PDOException $e) {
            error_log("Application status update error: " . $e->getMessage());
            return false;
        }
    }
    
    public function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $sql = "SELECT password FROM users WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return false;
            }
            
            if ($user['password'] !== 'first_login' && !password_verify($currentPassword, $user['password'])) {
                return false;
            }
            
            $sql = "UPDATE users SET password = :password WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            return $stmt->execute([
                'password' => $hashedPassword,
                'id' => $userId
            ]);
        } catch(PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            return false;
        }
    }
    
    public function resetPassword($userId, $newPassword) {
        try {
            $sql = "UPDATE users SET password = :password WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            return $stmt->execute([
                'password' => $hashedPassword,
                'id' => $userId
            ]);
        } catch(PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            return false;
        }
    }
    
    public function emailExists($email) {
        try {
            $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['email' => $email]);
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("Email exists check error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sapIdExists($sapId) {
        try {
            $sql = "SELECT COUNT(*) FROM users WHERE sap_id = :sap_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['sap_id' => $sapId]);
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            error_log("SAP ID exists check error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserByEmail($email) {
        try {
            $sql = "SELECT * FROM users WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['email' => $email]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Get user by email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserById($userId) {
        try {
            $sql = "SELECT * FROM users WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $userId]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Get user by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateProfile($userId, $data) {
        try {
            $sql = "UPDATE users SET name = :name, phone = :phone, department = :department, semester = :semester, bio = :bio, skills = :skills WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'department' => $data['department'],
                'semester' => $data['semester'],
                'bio' => $data['bio'],
                'skills' => $data['skills'],
                'id' => $userId
            ]);
        } catch(PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateProfilePicture($userId, $profilePicture) {
        try {
            $sql = "UPDATE users SET profile_picture = :profile_picture WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'profile_picture' => $profilePicture,
                'id' => $userId
            ]);
        } catch(PDOException $e) {
            error_log("Profile picture update error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUsersByRole($role) {
        try {
            $sql = "SELECT * FROM users WHERE role = :role AND status = 'active' ORDER BY name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['role' => $role]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Get users by role error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getPendingApplications() {
        try {
            $sql = "SELECT a.*, u.name as applicant_name 
                    FROM applications a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    WHERE a.status = 'pending' 
                    ORDER BY a.applied_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Get pending applications error: " . $e->getMessage());
            return false;
        }
    }
}
?>