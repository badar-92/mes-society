<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = file_get_contents('php://input');
$subscription = json_decode($input);

if (!$subscription) {
    echo json_encode(['error' => 'Invalid subscription data']);
    exit;
}

$db = Database::getInstance()->getConnection();

// Check if user has push enabled
$stmt = $db->prepare("SELECT push_enabled FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$push_enabled = $stmt->fetchColumn();

if (!$push_enabled) {
    echo json_encode(['error' => 'Push notifications are disabled for this account.']);
    exit;
}

$stmt = $db->prepare("INSERT INTO push_subscriptions (user_id, subscription_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE subscription_json = VALUES(subscription_json)");
$stmt->execute([$user_id, json_encode($subscription)]);

echo json_encode(['success' => true]);