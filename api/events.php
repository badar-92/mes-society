<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$auth = new Auth();
$functions = new Functions();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch($action) {
        case 'get_upcoming':
            $limit = intval($_GET['limit'] ?? 10);
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT * FROM events WHERE start_date >= NOW() AND status = 'published' ORDER BY start_date LIMIT ?");
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
                $stmt->execute();
                $events = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'events' => $events]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
            
        case 'get_event':
            $event_id = intval($_GET['id'] ?? 0);
            if ($event_id > 0) {
                try {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
                    $stmt->execute([$event_id]);
                    $event = $stmt->fetch();
                    
                    if ($event) {
                        echo json_encode(['success' => true, 'event' => $event]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Event not found']);
                    }
                } catch(PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>