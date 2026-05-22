<?php
require_once __DIR__ . '/../vendor-push/autoload.php';
require_once __DIR__ . '/../config/vapid.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function sendPushNotification($user_id, $title, $body, $url = '/mes-society/public/') {
    $db = Database::getInstance()->getConnection();
    
    // Check if user has push enabled
    $stmt = $db->prepare("SELECT push_enabled FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetchColumn()) {
        return false; // Push disabled
    }
    
    // Get subscription
    $stmt = $db->prepare("SELECT subscription_json FROM push_subscriptions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        return false; // No subscription
    }
    
    $webPush = new WebPush([
        'VAPID' => [
            'subject' => VAPID_SUBJECT,
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ]
    ]);
    
    $sub = Subscription::create(json_decode($row['subscription_json'], true));
    $webPush->queueNotification($sub, json_encode([
        'title' => $title,
        'body' => $body,
        'url' => $url
    ]));
    
    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            error_log("Push failed for user $user_id: " . $report->getReason());
            return false;
        }
    }
    return true;
}