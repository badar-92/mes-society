<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head']);

$db = Database::getInstance()->getConnection();

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        // Join with users table to get linked user data
        $stmt = $db->prepare("SELECT tm.*, 
                                      u.name AS user_name, 
                                      u.email AS user_email, 
                                      u.phone AS user_phone, 
                                      u.profile_picture AS user_profile_picture
                               FROM team_members_all tm
                               LEFT JOIN users u ON tm.user_id = u.id
                               WHERE tm.id = ?");
        $stmt->execute([$id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($member) {
            // Decode contact_info JSON
            $member['contact_info'] = json_decode($member['contact_info'], true);
            
            // Add display-ready fields (prioritize user data over manual data)
            $member['display_name'] = $member['user_name'] ?? $member['name'];
            $member['display_email'] = $member['user_email'] ?? ($member['contact_info']['email'] ?? '');
            $member['display_phone'] = $member['user_phone'] ?? ($member['contact_info']['phone'] ?? '');
            $member['display_picture'] = $member['user_profile_picture'] ?? $member['profile_picture'];
            
            echo json_encode($member);
        } else {
            echo json_encode(['error' => 'Team member not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No ID provided']);
}
?>