<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
$session = new SessionManager();
$session->requireLogin();

$db = Database::getInstance()->getConnection();
$id = $_GET['id'] ?? 0;
if ($id) {
    $stmt = $db->prepare("SELECT id, name, email, phone, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($user);
} else {
    echo json_encode(null);
}
?>