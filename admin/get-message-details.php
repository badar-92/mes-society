<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head']);

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Message ID required']);
    exit();
}

$messageId = (int)$_GET['id'];
$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($message) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
    }
} catch (PDOException $e) {
    error_log("Message details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>