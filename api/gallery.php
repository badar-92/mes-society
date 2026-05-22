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
        case 'get_albums':
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->query("SELECT * FROM gallery_albums WHERE status = 'active' ORDER BY created_at DESC");
                $albums = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'albums' => $albums]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
            
        case 'get_album_photos':
            $album_id = intval($_GET['album_id'] ?? 0);
            if ($album_id > 0) {
                try {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT * FROM gallery_photos WHERE album_id = ? ORDER BY uploaded_at DESC");
                    $stmt->execute([$album_id]);
                    $photos = $stmt->fetchAll();
                    
                    echo json_encode(['success' => true, 'photos' => $photos]);
                } catch(PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid album ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>