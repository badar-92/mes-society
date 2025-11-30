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

if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    
    try {
        // Get user details with password - FIXED QUERY
        $stmt = $db->prepare("SELECT *, COALESCE(temp_password, '') as display_password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("User not found");
        }
        
        // Get user posts
        $postStmt = $db->prepare("SELECT post_name FROM user_posts WHERE user_id = ?");
        $postStmt->execute([$user_id]);
        $posts = $postStmt->fetchAll(PDO::FETCH_COLUMN);
        $postStr = implode(', ', $posts);
        
        // Prepare user data for PDF - Ensure password is properly passed
        $userData = [
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'sap_id' => $user['sap_id'],
            'department' => $user['department'],
            'semester' => $user['semester'],
            'posts' => $postStr,
            'profile_picture' => $user['profile_picture'],
            'temp_password' => $user['display_password'] ?: 'Please contact administrator'
        ];
        
        // Generate PDF directly
        $pdfContent = $pdfGenerator->generateWelcomeLetterPDF($userData);
        
        if ($pdfContent) {
            $filename = "confirmation_letter_" . ($user['sap_id'] ? $user['sap_id'] : $user['id']) . ".pdf";
            
            // Set headers for direct download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . strlen($pdfContent));
            
            // Output the PDF directly
            echo $pdfContent;
            exit();
        } else {
            throw new Exception('Failed to generate PDF content');
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error downloading letter: " . $e->getMessage();
        header("Location: users.php");
        exit();
    }
} else {
    $_SESSION['error'] = "No user specified";
    header("Location: users.php");
    exit();
}
?>