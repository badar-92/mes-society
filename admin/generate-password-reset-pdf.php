<?php
// Enable error reporting for debugging - but only to logs, not output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header immediately
header('Content-Type: application/json');

// Simple admin check
$allowed_roles = ['super_admin', 'department_head'];
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Not authorized. Please log in again.']);
    exit;
}

// Now include required files
try {
    require_once '../includes/config.php';
    require_once '../includes/database.php';
    require_once '../includes/auth.php';
    require_once '../includes/functions.php';
    require_once '../includes/pdf-generator/PDFGenerator.php';
} catch (Exception $e) {
    error_log("Required file include error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    try {
        $auth = new Auth();
        $db = Database::getInstance()->getConnection();
        
        // Generate temporary password
        $new_password = $auth->generatePassword(12);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update user password in database - REMOVED force_password_change column
        $stmt = $db->prepare("UPDATE users SET password = ?, temp_password = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$hashed_password, $new_password, $user_id]);
        
        if (!$result) {
            throw new Exception("Database update failed");
        }
        
        // Get user details
        $user_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception("User not found with ID: " . $user_id);
        }
        
        // Generate PDF
        $pdf = new PDFGenerator();
        $pdf_data = $pdf->generatePasswordResetPDF($user, $new_password);
        
        if (!$pdf_data) {
            throw new Exception("PDF generation failed");
        }
        
        // Ensure uploads directory exists
        $upload_dir = '../uploads/confirmation-letters/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception("Could not create upload directory");
            }
        }
        
        // Save PDF file
        $filename = "password_reset_{$user['sap_id']}_" . date('Ymd_His') . ".pdf";
        $filepath = $upload_dir . $filename;
        
        $bytes_written = file_put_contents($filepath, $pdf_data);
        if ($bytes_written === false) {
            throw new Exception("Failed to write PDF file. Check directory permissions.");
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully. PDF generated.',
            'download_url' => 'download-password-reset.php?file=' . $filename,
            'user_name' => $user['name'],
            'user_email' => $user['email'],
            'new_password' => $new_password
        ]);
        
    } catch(Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request. Required: POST method and user_id parameter.'
    ]);
}
?>