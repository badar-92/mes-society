<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/pdf-generator/PDFGenerator.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'hiring_head']);

$auth = new Auth();
$pdfGenerator = new PDFGenerator();
$db = Database::getInstance()->getConnection();

$response = ['success' => false, 'message' => ''];

try {
    $type = $_GET['type'] ?? '';
    $id = $_GET['id'] ?? 0;
    
    if (empty($type) || empty($id)) {
        throw new Exception('Invalid request parameters');
    }
    
    $userData = [];
    $tempPassword = '';
    $userId = null;
    
    if ($type === 'application') {
        // Generate for application approval
        $stmt = $db->prepare("
            SELECT a.*, u.name, u.email, u.phone, u.sap_id, u.department, u.semester, u.profile_picture 
            FROM applications a 
            LEFT JOIN users u ON a.user_id = u.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $application = $stmt->fetch();
        
        if (!$application) {
            throw new Exception('Application not found');
        }
        
        // Get user posts from application if available
        $posts = [];
        if (!empty($application['applied_posts'])) {
            $posts = explode(',', $application['applied_posts']);
        }
        $postStr = implode(', ', $posts);
        
        // Generate temporary password
        $tempPassword = $pdfGenerator->generatePassword();
        
        // Check if user already exists
        $userCheckStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $userCheckStmt->execute([$application['email']]);
        $existingUser = $userCheckStmt->fetch();
        
        if ($existingUser) {
            $userId = $existingUser['id'];
            // Update existing user with new password
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE users SET password = ?, temp_password = ?, status = 'active' WHERE id = ?");
            $updateStmt->execute([$hashedPassword, $tempPassword, $userId]);
        } else {
            // Create new user account
            $personalInfo = json_decode($application['personal_info'], true);
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            $createStmt = $db->prepare("
                INSERT INTO users (name, email, sap_id, phone, department, semester, password, temp_password, role, status, profile_picture, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'member', 'active', 'default-avatar.png', NOW())
            ");
            $createStmt->execute([
                $application['name'],
                $application['email'],
                $application['sap_id'],
                $application['phone'],
                $application['department'],
                $application['semester'],
                $hashedPassword,
                $tempPassword
            ]);
            
            $userId = $db->lastInsertId();
        }
        
        $userData = [
            'name' => $application['name'],
            'email' => $application['email'],
            'phone' => $application['phone'],
            'sap_id' => $application['sap_id'],
            'department' => $application['department'],
            'semester' => $application['semester'],
            'posts' => $postStr,
            'profile_picture' => $application['profile_picture'],
            'temp_password' => $tempPassword
        ];
        
        // Update application status
        $appStmt = $db->prepare("UPDATE applications SET status = 'approved', user_id = ? WHERE id = ?");
        $appStmt->execute([$userId, $id]);
        
    } elseif ($type === 'user') {
        // Generate for existing user
        $user = $auth->getUserById($id);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Get user posts
        $postStmt = $db->prepare("SELECT post_name FROM user_posts WHERE user_id = ?");
        $postStmt->execute([$id]);
        $posts = $postStmt->fetchAll(PDO::FETCH_COLUMN);
        $postStr = implode(', ', $posts);
        
        // Generate new temporary password and update user
        $tempPassword = $pdfGenerator->generatePassword();
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE users SET password = ?, temp_password = ? WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $tempPassword, $id]);
        
        $userData = [
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'sap_id' => $user['sap_id'],
            'department' => $user['department'],
            'semester' => $user['semester'],
            'posts' => $postStr,
            'profile_picture' => $user['profile_picture'],
            'temp_password' => $tempPassword
        ];
        
        $userId = $id;
    } else {
        throw new Exception('Invalid type specified');
    }
    
    // Generate PDF - This should return the PDF content directly
    $pdfContent = $pdfGenerator->generateWelcomeLetterPDF($userData);
    
    if ($pdfContent) {
        $filename = "confirmation_letter_" . ($userData['sap_id'] ? $userData['sap_id'] : 'user_' . $userId) . ".pdf";
        
        // Save PDF to file for future downloads
        $directory = "../uploads/confirmation-letters/";
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $filepath = $directory . $filename;
        file_put_contents($filepath, $pdfContent);
        
        $response['success'] = true;
        $response['message'] = 'Confirmation letter generated successfully';
        $response['filepath'] = "uploads/confirmation-letters/" . $filename;
        $response['filename'] = $filename;
        $response['temp_password'] = $tempPassword;
        $response['download_url'] = 'download-confirmation-letter.php?user_id=' . $userId;
        
    } else {
        throw new Exception('Failed to generate PDF');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>