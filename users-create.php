<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole('super_admin');

$auth = new Auth();
$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $sap_id = $_POST['sap_id'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $department = $_POST['department'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $posts = $_POST['posts'] ?? [];
    $role = $_POST['role'] ?? 'member';
    
    try {
        // Check if email already exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $_SESSION['error'] = "Email already exists!";
            header("Location: users.php");
            exit();
        }
        
        // Check if SAP ID already exists
        if (!empty($sap_id)) {
            $checkStmt = $db->prepare("SELECT id FROM users WHERE sap_id = ?");
            $checkStmt->execute([$sap_id]);
            if ($checkStmt->fetch()) {
                $_SESSION['error'] = "SAP ID already exists!";
                header("Location: users.php");
                exit();
            }
        }
        
        // Generate temporary password
        $tempPassword = generateRandomPassword(12);
        
        // Handle profile picture upload
        $profilePicture = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profile-pictures/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExtension, $allowedTypes)) {
                $fileName = 'user_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $uploadFile = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadFile)) {
                    $profilePicture = $fileName;
                }
            }
        }
        
        // Create user with BOTH hashed password and temporary plain password
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        if ($profilePicture) {
            $stmt = $db->prepare("
                INSERT INTO users (name, email, sap_id, phone, department, semester, password, temp_password, role, status, profile_picture, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ");
            $stmt->execute([$name, $email, $sap_id, $phone, $department, $semester, $hashedPassword, $tempPassword, $role, $profilePicture]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO users (name, email, sap_id, phone, department, semester, password, temp_password, role, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$name, $email, $sap_id, $phone, $department, $semester, $hashedPassword, $tempPassword, $role]);
        }
        
        $userId = $db->lastInsertId();
        
        // Assign posts
        if (!empty($posts)) {
            $postStmt = $db->prepare("INSERT INTO user_posts (user_id, post_name) VALUES (?, ?)");
            foreach ($posts as $post) {
                if (!empty(trim($post))) {
                    $postStmt->execute([$userId, trim($post)]);
                }
            }
        }
        
        // Generate download URL for immediate download
        $downloadUrl = 'download-confirmation-letter.php?user_id=' . $userId;
        
        $_SESSION['success'] = "User created successfully!<br>
        <strong>Temporary Password:</strong> " . $tempPassword . "<br>
        <strong>Important:</strong> Save this password as it won't be shown again.<br>
        <a href='" . $downloadUrl . "' class='btn btn-success btn-sm mt-2'>
            <i class='fas fa-download me-1'></i>Download Welcome Letter
        </a>";
        
        header("Location: users.php");
        exit();
        
    } catch(PDOException $e) {
        error_log("User creation error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to create user: " . $e->getMessage();
        header("Location: users.php");
        exit();
    }
} else {
    header("Location: users.php");
    exit();
}

function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
?>